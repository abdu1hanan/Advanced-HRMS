<?php

namespace App\Services;

use App\Models\LeaveApprovalChain;
use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;

/**
 * Manages the approval chain logic for leave requests.
 */
class LeaveApprovalChainService
{
    public function __construct(protected HierarchyService $hierarchyService) {}

    /**
     * Initialise chain columns when a new leave request is submitted.
     * Returns the target level count (snapshotted from system settings).
     */
    public function initialise(LeaveRequest $request): int
    {
        $target = SystemSetting::approvalLevels();

        $request->update([
            'approval_chain_target'   => $target,
            'approval_chain_progress' => 0,
        ]);

        return $target;
    }

    /**
     * Reset the approval chain for an in-flight request when modified by employee.
     * Clears all recorded step actions and resets progress to 0.
     */
    public function resetChain(LeaveRequest $request): void
    {
        LeaveApprovalChain::where('leave_request_id', $request->id)->delete();

        $request->update([
            'approval_chain_progress' => 0,
            'status'                  => LeaveRequest::STATUS_PENDING,
            'reviewed_by'             => null,
            'reviewed_at'             => null,
        ]);
    }

    /**
     * Return the User who should receive the next approval notification.
     * Level 1 = immediate boss of the employee.
     * Level N = the Nth boss up the chain.
     * Returns null if the chain is exhausted (employee has no boss at that level).
     */
    public function getNextApprover(LeaveRequest $request): ?User
    {
        if ($request->chainComplete() || ($request->approval_chain_target > 0 && $request->approval_chain_progress >= $request->approval_chain_target)) {
            return null;
        }

        $level = $request->nextChainLevel(); // 1-indexed
        if ($request->approval_chain_target > 0 && $level > $request->approval_chain_target) {
            return null;
        }

        $bossChain = $this->hierarchyService->getBossChain($request->user);

        // $bossChain[0] = immediate boss, $bossChain[1] = their boss, etc.
        return $bossChain[$level - 1] ?? null;
    }

    /**
     * Get the User who is the current expected approver
     * (the next in line after current chain progress).
     */
    public function getCurrentExpectedApprover(LeaveRequest $request): ?User
    {
        return $this->getNextApprover($request);
    }

    /**
     * Determine which hierarchy level (1-indexed) this manager is
     * in the boss chain for the given leave request employee.
     * Returns null if the manager is not in the chain.
     */
    public function getManagerLevel(LeaveRequest $request, int $managerId): ?int
    {
        $bossChain = $this->hierarchyService->getBossChain($request->user);
        foreach ($bossChain as $idx => $boss) {
            if ((int) $boss->id === $managerId) {
                return $idx + 1; // 1-indexed
            }
        }
        return null;
    }

    /**
     * Record an approval action and advance the chain.
     * Returns whether the chain is now complete after this approval.
     */
    public function recordApproval(LeaveRequest $request, User $manager): bool
    {
        $level = $this->getManagerLevel($request, $manager->id);

        // Upsert: allow reversal from a previous rejection at this level
        LeaveApprovalChain::updateOrCreate(
            ['leave_request_id' => $request->id, 'approver_id' => $manager->id],
            ['level' => $level, 'action' => 'approved', 'acted_at' => Carbon::now()]
        );

        $request->refresh();

        // Advance progress to this level (in case of out-of-order edge cases)
        $newProgress = max($request->approval_chain_progress, $level);

        // Check if boss chain is exhausted at this point (no more bosses above)
        $bossChain = $this->hierarchyService->getBossChain($request->user);
        $chainExhausted = !isset($bossChain[$newProgress]); // $bossChain is 0-indexed, $newProgress is next level index

        $complete = $newProgress >= $request->approval_chain_target || $chainExhausted;

        $request->update([
            'approval_chain_progress' => $newProgress,
            'status'                  => $complete ? 'approved_by_manager' : LeaveRequest::STATUS_PENDING,
        ]);

        return $complete;
    }

    /**
     * Record a rejection action. Chain stops here.
     */
    public function recordRejection(LeaveRequest $request, User $manager): void
    {
        $level = $this->getManagerLevel($request, $manager->id);

        LeaveApprovalChain::updateOrCreate(
            ['leave_request_id' => $request->id, 'approver_id' => $manager->id],
            ['level' => $level, 'action' => 'rejected', 'acted_at' => Carbon::now()]
        );

        if ($level !== null) {
            $request->update(['approval_chain_progress' => max($request->approval_chain_progress, $level)]);
        }
    }

    /**
     * Return all managers who have participated so far (1..progress).
     */
    public function getNotifiedManagers(LeaveRequest $request): array
    {
        $bossChain = $this->hierarchyService->getBossChain($request->user);
        $progress  = $request->approval_chain_progress;

        // Levels 1..progress have been notified (0-indexed: 0..progress-1)
        return array_slice($bossChain, 0, $progress);
    }

    /**
     * Can this manager approve this request?
     * Rules:
     * - Must not be fully resolved or approved_by_manager
     * - Must not have reached configured target level (chain complete)
     * - Must be in the boss chain and within the configured approval_chain_target
     * - Must be the next expected level (chain_progress + 1)
     * - Must NOT have already approved (cannot re-approve after approving)
     * - Special case: If status is rejected_by_manager, ONLY the manager who rejected it at their level can change decision to approved!
     */
    public function canApprove(LeaveRequest $request, User $manager): bool
    {
        if ($request->status === LeaveRequest::STATUS_APPROVED ||
            $request->status === LeaveRequest::STATUS_REJECTED ||
            $request->status === LeaveRequest::STATUS_CANCELLED ||
            $request->status === 'approved_by_manager') {
            return false;
        }

        // Already approved at this level — locked (cannot change decision once approved)
        if ($request->hasManagerApproved($manager->id)) {
            return false;
        }

        $level = $this->getManagerLevel($request, $manager->id);
        if ($level === null) {
            return false;
        }

        // Special case: If status is rejected_by_manager, ONLY the manager who rejected it at their level can change decision to approved!
        if ($request->status === LeaveRequest::STATUS_REJECTED_BY_MANAGER) {
            $rejectedLevel = $request->rejectionLevel();
            return $level === $rejectedLevel;
        }

        // If chain is complete (and status is not rejected_by_manager), no further manager approvals
        if ($request->chainComplete()) {
            return false;
        }

        // Must be the next expected approver
        $expectedLevel = $request->nextChainLevel();
        return $level === $expectedLevel;
    }

    /**
     * Can this manager reject this request?
     * Rules:
     * - Must not be fully resolved or approved_by_manager or rejected_by_manager
     * - Must not have reached configured target level (chain complete)
     * - Must be in the boss chain and within the configured approval_chain_target
     * - Must be the next expected level (chain_progress + 1)
     * - Must NOT have already approved (cannot reject after approving)
     */
    public function canReject(LeaveRequest $request, User $manager): bool
    {
        if ($request->status === LeaveRequest::STATUS_APPROVED ||
            $request->status === LeaveRequest::STATUS_REJECTED ||
            $request->status === LeaveRequest::STATUS_CANCELLED ||
            $request->status === LeaveRequest::STATUS_REJECTED_BY_MANAGER ||
            $request->status === 'approved_by_manager' ||
            $request->chainComplete()) {
            return false;
        }

        // Cannot reject after approving
        if ($request->hasManagerApproved($manager->id)) {
            return false;
        }

        $level = $this->getManagerLevel($request, $manager->id);
        if ($level === null) {
            return false;
        }

        // If this manager is beyond the target level of the approval chain, they cannot reject
        if ($request->approval_chain_target > 0 && $level > $request->approval_chain_target) {
            return false;
        }

        $expectedLevel = $request->nextChainLevel();
        return $level === $expectedLevel;
    }

    /**
     * Check if this manager can see/act on this leave request at all.
     * Managers can see requests if they are within subordinate_ids
     * AND are the current expected approver (for pending action).
     * For approved_by_manager / rejected_by_manager they can see it for history.
     */
    public function managerCanSeeForAction(LeaveRequest $request, User $manager): bool
    {
        if (!in_array($request->user_id, $manager->subordinate_ids, true)) {
            return false;
        }

        if (in_array($request->status, [
            LeaveRequest::STATUS_APPROVED,
            LeaveRequest::STATUS_REJECTED,
            LeaveRequest::STATUS_CANCELLED,
        ])) {
            return false; // Fully resolved by Super Admin — managers see history only
        }

        $level = $this->getManagerLevel($request, $manager->id);

        if ($request->status === LeaveRequest::STATUS_REJECTED_BY_MANAGER) {
            // Any manager in the chain may see it (for context/history)
            return $level !== null;
        }

        if ($request->status === 'approved_by_manager') {
            // Chain complete awaiting CEO — any chain manager can see for context
            return $level !== null;
        }

        // status = 'pending':
        // 1. Expected next approver can see & act
        // 2. OR any manager in the chain who has ALREADY approved at their level ($level <= approval_chain_progress)
        if ($this->canApprove($request, $manager) || $this->canReject($request, $manager)) {
            return true;
        }

        if ($level !== null && $level <= $request->approval_chain_progress) {
            return true;
        }

        return false;
    }
}
