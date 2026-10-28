<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\Department;
use App\Services\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeamLeaveController extends Controller
{
    public function __construct(
        protected LeaveBalanceService $balanceService,
        protected \App\Services\LeaveApprovalChainService $chainService,
    ) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'user_id'       => ['nullable', 'exists:users,id'],
            'leave_type_id' => ['nullable', 'exists:leave_types,id'],
            'status'        => ['nullable', 'in:all,pending,approved_by_manager,rejected_by_manager,changes_requested,pending_cancellation'],
            'start_date'    => ['nullable', 'date'],
            'end_date'      => ['nullable', 'date'],
        ]);

        $manager        = Auth::user();
        $subordinateIds = $manager->subordinate_ids;

        // Base query — all subordinates' requests
        $query = LeaveRequest::with(['user.department', 'leaveType', 'reviewer', 'comments.user', 'approvalChain.approver.designation'])
            ->whereIn('user_id', $subordinateIds);

        $status = $request->query('status');
        if ($status === null && !$request->has('status')) {
            $status = 'all';
        }

        if ($status === 'all') {
            $query->where(function ($q) {
                $q->whereIn('status', [LeaveRequest::STATUS_PENDING, 'approved_by_manager', 'rejected_by_manager'])
                  ->orWhereIn('change_request_status', ['pending', 'approved_by_manager', 'rejected_by_manager']);
            });
        } elseif ($status === 'pending') {
            // All pending manager approvals (Level 1, Level 2, etc.) & admin-marked pending change requests
            $query->where(function ($q) {
                $q->where('status', LeaveRequest::STATUS_PENDING)
                  ->orWhere(function ($q2) {
                      $q2->where('is_admin_marked', true)
                         ->where('change_request_status', 'pending');
                  });
            })->where(function ($q) {
                $q->whereNull('change_request->request_cancellation')
                  ->orWhere('change_request->request_cancellation', false);
            });
        } elseif ($status === 'approved_by_manager') {
            // Pending CEO approval (either initial request or change request completed manager chain)
            $query->where(function ($q) {
                $q->where('status', 'approved_by_manager')
                  ->orWhere('change_request_status', 'approved_by_manager');
            })->where(function ($q) {
                $q->whereNull('change_request->request_cancellation')
                  ->orWhere('change_request->request_cancellation', false);
            });
        } elseif ($status === 'rejected_by_manager') {
            $query->where('status', 'rejected_by_manager');
        } elseif ($status === 'changes_requested') {
            // Outstanding change requests (shows with changes badge)
            $query->whereIn('change_request_status', ['pending', 'approved_by_manager'])
                  ->whereNotIn('status', [LeaveRequest::STATUS_REJECTED, LeaveRequest::STATUS_REJECTED_BY_MANAGER, LeaveRequest::STATUS_CANCELLED])
                  ->where(function ($q) {
                      $q->whereNull('change_request->request_cancellation')
                        ->orWhere('change_request->request_cancellation', false);
                  });
        } elseif ($status === 'pending_cancellation') {
            $query->where('change_request_status', 'pending')
                  ->where('change_request->request_cancellation', true);
        }

        if (! empty($validated['department_id'])) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $validated['department_id']));
        }

        if (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        if (! empty($validated['leave_type_id'])) {
            $query->where('leave_type_id', $validated['leave_type_id']);
        }

        if (! empty($validated['start_date'])) {
            $query->whereDate('start_date', '>=', $validated['start_date']);
        }

        if (! empty($validated['end_date'])) {
            $query->whereDate('end_date', '<=', $validated['end_date']);
        }

        // Fetch all and filter using chain service:
        // - Pending requests: only shown to the current expected approver
        // - approved_by_manager / rejected_by_manager: visible to all chain managers (for context)
        $allRequests = $query->latest()->get();
        $chainService = $this->chainService;
        $filteredRequests = $allRequests->filter(function (LeaveRequest $lr) use ($manager, $chainService) {
            // Legacy requests (no chain set up yet) — fall back to old subordinate_ids check
            if ($lr->approval_chain_target === null) {
                return true;
            }
            return $chainService->managerCanSeeForAction($lr, $manager);
        });

        // Re-paginate filtered collection manually
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage() ?: 1;
        $perPage     = 25;
        $currentItems = $filteredRequests->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $requests = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentItems,
            $filteredRequests->count(),
            $perPage,
            $currentPage,
            [
                'path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );

        // Departments that have at least one subordinate in them
        $departments = \App\Models\Department::whereHas('users', fn ($q) => $q->whereIn('id', $subordinateIds))->orderBy('name')->get();
        $allUsers    = User::whereIn('id', $subordinateIds)->orderBy('name')->get();
        $leaveTypes  = \App\Models\LeaveType::where('is_active', true)->orderBy('name')->get();

        return view('admin.team.leaves', compact('requests', 'departments', 'allUsers', 'leaveTypes'));
    }

    public function history(Request $request): View
    {
        $validated = $request->validate([
            'user_id'       => ['nullable', 'exists:users,id'],
            'leave_type_id' => ['nullable', 'exists:leave_types,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'status'        => ['nullable', 'in:approved,rejected,cancelled,all'],
            'start_date'    => ['nullable', 'date'],
            'end_date'      => ['nullable', 'date'],
        ]);

        $subordinateIds = Auth::user()->subordinate_ids;
        $year = (int) now()->year;

        $query = LeaveRequest::with(['user.department', 'leaveType', 'reviewer', 'approvalChain.approver.designation', 'comments.user'])
            ->whereIn('user_id', $subordinateIds)
            // Only show finalized CEO decided statuses: approved, rejected, cancelled
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_REJECTED, LeaveRequest::STATUS_CANCELLED])
            // BUT exclude if there is a pending/active change request (as it's still being decided in requests)
            ->where(function ($q) {
                $q->whereNull('change_request_status')
                  ->orWhereNotIn('change_request_status', ['pending', 'approved_by_manager', 'rejected_by_manager']);
            });

        $status = $request->query('status');
        if ($status === null && !$request->has('status')) {
            $status = 'all';
        }

        if ($status !== 'all' && !empty($status)) {
            $query->where('status', $status);
        }

        $user = null;
        $balances = collect();

        if (! empty($validated['user_id'])) {
            $user = User::whereIn('id', $subordinateIds)->find($validated['user_id']);
            if ($user) {
                $query->where('user_id', $user->id);
                foreach (\App\Models\LeaveType::where('is_active', true)->get() as $type) {
                    $this->balanceService->ensureBalanceRow($user, $type, $year);
                }
                $balances = $user->leaveBalances()
                    ->whereHas('leaveType', fn ($q) => $q->where('is_active', true))
                    ->with('leaveType')
                    ->where('year', $year)
                    ->get();
            }
        }

        if (! empty($validated['department_id'])) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $validated['department_id']));
        }

        if (! empty($validated['leave_type_id'])) {
            $query->where('leave_type_id', $validated['leave_type_id']);
        }

        if (! empty($validated['start_date']) && ! empty($validated['end_date'])) {
            $query->where('start_date', '<=', $validated['end_date'])
                  ->where('end_date', '>=', $validated['start_date']);
        } elseif (! empty($validated['start_date'])) {
            $query->where('start_date', '>=', $validated['start_date']);
        } elseif (! empty($validated['end_date'])) {
            $query->where('end_date', '<=', $validated['end_date']);
        }

        $history = $query->latest()->paginate(20)->withQueryString();
        $allUsers = User::whereIn('id', $subordinateIds)->orderBy('name')->get();
        $departments = \App\Models\Department::whereHas('users', fn ($q) => $q->whereIn('id', $subordinateIds))->orderBy('name')->get();
        $leaveTypes = \App\Models\LeaveType::orderBy('name')->get();

        return view('admin.team.history', compact('user', 'balances', 'history', 'year', 'allUsers', 'departments', 'leaveTypes'));
    }

    public function comment(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorizeAccess($leaveRequest);
        
        $request->validate(['comment' => 'required|string']);
        
        $isHidden = $request->boolean('is_hidden_from_employee');
        app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, auth()->user(), $request->comment, $isHidden);

        return back()->with('success', 'Comment added successfully.');
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorizeAccess($leaveRequest);
        $this->assertNotOverriddenBySuperAdmin($leaveRequest);

        $manager = auth()->user();

        // Guard: cannot reject after approving (but approval after rejection is allowed)
        if ($leaveRequest->hasManagerApproved($manager->id)) {
            return back()->with('error', 'You have already approved this request and cannot change it to rejected.');
        }

        // Guard: must be the current expected approver in the chain
        if (! $this->chainService->canApprove($leaveRequest, $manager)) {
            return back()->with('error', 'You are not the current expected approver for this request.');
        }

        if ($request->filled('comment')) {
            $isHidden = $request->boolean('is_hidden_from_employee');
            app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, $manager, $request->comment, $isHidden);
        }

        // Record approval in chain and determine if chain is now complete
        $chainComplete = $this->chainService->recordApproval($leaveRequest->fresh(), $manager);

        $leaveRequest->refresh();
        $managerDesignation = $manager->designation?->name 
            ?? ($manager->special_order === 1 ? 'CEO' : ($manager->special_order === 2 ? 'CTO' : ($manager->special_order === 3 ? 'HR Manager' : 'Employee')));
        $levelApproved      = $leaveRequest->approval_chain_progress;
        $totalTarget        = $leaveRequest->approval_chain_target;

        $hasChangeReq = !empty($leaveRequest->change_request) || !empty($leaveRequest->change_request_status);

        if ($chainComplete) {
            // All required manager levels approved — escalate to Super Admin
            $updateData = [
                'status'      => 'approved_by_manager',
                'reviewed_by' => $manager->id,
                'reviewed_at' => now(),
            ];
            if ($hasChangeReq) {
                $updateData['change_request_status'] = 'approved_by_manager';
            }
            $leaveRequest->update($updateData);

            // Notify employee: chain complete, pending CEO
            $leaveRequest->user->notify(new \App\Notifications\LeaveRequestReviewed(
                $leaveRequest,
                $manager->name,
                $managerDesignation
            ));

            // Notify all managers who were involved (downward notification)
            $notifiedManagers = $this->chainService->getNotifiedManagers($leaveRequest);
            foreach ($notifiedManagers as $boss) {
                if ($boss->id !== $manager->id) {
                    $boss->notify(new \App\Notifications\LeaveRequestManagerApproved(
                        $leaveRequest, $manager->name, $managerDesignation, $levelApproved, $totalTarget
                    ));
                }
            }

            // Notify Super Admin — chain is complete, awaiting final decision
            User::whereHas('role', fn($q) => $q->where('name', 'super_admin'))
                ->each(fn($admin) => $admin->notify(
                    new \App\Notifications\LeaveRequestManagerApproved(
                        $leaveRequest, $manager->name, $managerDesignation, $levelApproved, $totalTarget
                    )
                ));

            return back()->with('success', "Leave request approved. All {$totalTarget} manager level(s) complete — sent to CEO for final approval.");
        } else {
            // Chain not yet complete — find and notify next level boss
            if ($hasChangeReq) {
                $leaveRequest->update([
                    'change_request_status' => 'pending',
                ]);
            }

            $nextApprover = $this->chainService->getNextApprover($leaveRequest);

            // Notify employee of this level's approval
            $leaveRequest->user->notify(new \App\Notifications\LeaveRequestReviewed(
                $leaveRequest,
                $manager->name,
                $managerDesignation
            ));

            // Notify all managers so far (downward: let them know chain progressed)
            $notifiedManagers = $this->chainService->getNotifiedManagers($leaveRequest);
            foreach ($notifiedManagers as $boss) {
                if ($boss->id !== $manager->id) {
                    $boss->notify(new \App\Notifications\LeaveRequestManagerApproved(
                        $leaveRequest, $manager->name, $managerDesignation, $levelApproved, $totalTarget
                    ));
                }
            }

            if ($nextApprover) {
                // Notify next-level manager that their approval is now required
                $nextApprover->notify(new \App\Notifications\LeaveRequestChainApprovalRequired(
                    leaveRequest: $leaveRequest,
                    approvedByName: $manager->name,
                    approvedByDesignation: $managerDesignation,
                    level: $levelApproved,
                    totalLevels: $totalTarget,
                ));
            }

            // Super Admin always stays informed
            User::whereHas('role', fn($q) => $q->where('name', 'super_admin'))
                ->each(fn($admin) => $admin->notify(
                    new \App\Notifications\LeaveRequestManagerApproved(
                        $leaveRequest, $manager->name, $managerDesignation, $levelApproved, $totalTarget
                    )
                ));

            return back()->with('success', "Leave request approved (Level {$levelApproved} of {$totalTarget}). Notified next manager in chain.");
        }
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorizeAccess($leaveRequest);
        $this->assertNotOverriddenBySuperAdmin($leaveRequest);

        $manager = auth()->user();

        // Cannot reject after already approving
        if ($leaveRequest->hasManagerApproved($manager->id)) {
            return back()->with('error', 'You have already approved this request. You cannot reject it after approving.');
        }

        // Must be the current expected approver
        if (! $this->chainService->canReject($leaveRequest, $manager)) {
            return back()->with('error', 'You are not the current expected approver for this request.');
        }

        if ($request->filled('comment')) {
            $isHidden = $request->boolean('is_hidden_from_employee');
            app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, $manager, $request->comment, $isHidden);
        }

        // Record rejection — chain stops here
        $this->chainService->recordRejection($leaveRequest->fresh(), $manager);

        $hasChangeReq = !empty($leaveRequest->change_request) || !empty($leaveRequest->change_request_status);

        $updateData = [
            'status'      => LeaveRequest::STATUS_REJECTED_BY_MANAGER,
            'reviewed_by' => $manager->id,
            'reviewed_at' => now(),
        ];
        if ($hasChangeReq) {
            $updateData['change_request_status'] = 'rejected_by_manager';
        }
        $leaveRequest->update($updateData);

        $leaveRequest->refresh();
        $managerDesignation = $manager->designation?->name 
            ?? ($manager->special_order === 1 ? 'CEO' : ($manager->special_order === 2 ? 'CTO' : ($manager->special_order === 3 ? 'HR Manager' : 'Employee')));
        $levelRejected      = $this->chainService->getManagerLevel($leaveRequest, $manager->id) ?? $leaveRequest->rejectionLevel();
        $totalTargetR       = $leaveRequest->approval_chain_target;

        // Notify employee of rejection (downward)
        $leaveRequest->user->notify(new \App\Notifications\LeaveRequestReviewed(
            $leaveRequest,
            $manager->name,
            $managerDesignation
        ));

        // Notify all managers who were already notified (downward — they need to know chain stopped)
        $notifiedManagers = $this->chainService->getNotifiedManagers($leaveRequest);
        foreach ($notifiedManagers as $boss) {
            if ($boss->id !== $manager->id) {
                $boss->notify(new \App\Notifications\LeaveRequestManagerRejected(
                    $leaveRequest, $manager->name, $managerDesignation, $levelRejected, $totalTargetR
                ));
            }
        }

        // Super Admin ALWAYS notified (chain stops but CEO still sees it)
        User::whereHas('role', fn($q) => $q->where('name', 'super_admin'))
            ->each(fn($admin) => $admin->notify(
                new \App\Notifications\LeaveRequestManagerRejected(
                    $leaveRequest, $manager->name, $managerDesignation, $levelRejected, $totalTargetR
                )
            ));

        return back()->with('success', 'Leave request rejected. Chain stopped. Super Admin has been informed.');
    }

    protected function authorizeAccess(LeaveRequest $leaveRequest): void
    {
        $user = Auth::user();
        $subordinateIds = $user->subordinate_ids;
        $isSubordinate = in_array($leaveRequest->user_id, $subordinateIds, true);
        $isInChain = $leaveRequest->approvalChain()->where('approver_id', $user->id)->exists();

        if (! $isSubordinate && ! $isInChain) {
            abort(403, 'Unauthorized access to this leave request.');
        }
    }

    protected function assertNotOverriddenBySuperAdmin(LeaveRequest $leaveRequest): void
    {
        if ($leaveRequest->reviewer && $leaveRequest->reviewer->isSuperAdmin()) {
            abort(403, 'This request has been resolved by Super Admin and cannot be modified.');
        }
    }



    public function recordView(Request $request): View
    {
        $user = Auth::user();
        $year = (int) now()->year;

        $validated = $request->validate([
            'type'          => ['nullable', 'in:leaves,absent,remote'],
            'leave_type_id' => ['nullable', 'exists:leave_types,id'],
            'status'        => ['nullable', 'string'],
            'start_date'    => ['nullable', 'date'],
            'end_date'      => ['nullable', 'date'],
        ]);

        $type = $request->get('type', 'leaves');

        if ($type === 'leaves') {
            $query = LeaveRequest::query()
                ->where('user_id', $user->id)
                ->with(['leaveType', 'reviewer', 'approvalChain.approver.designation', 'comments' => function ($q) {
                    $q->where('is_hidden_from_employee', false);
                }, 'comments.user']);

            if (! empty($validated['status'])) {
                $status = $validated['status'];
                if ($status === 'pending') {
                    // All pending approvals, excluding pending cancellation
                    $query->where('status', LeaveRequest::STATUS_PENDING)
                          ->where(function ($q) {
                              $q->whereNull('change_request->request_cancellation')
                                ->orWhere('change_request->request_cancellation', false);
                          });
                } elseif ($status === 'approved_by_manager') {
                    // Pending CEO approval
                    $query->where(function ($q) {
                        $q->where('status', 'approved_by_manager')
                          ->orWhere('change_request_status', 'approved_by_manager');
                    })->where(function ($q) {
                        $q->whereNull('change_request->request_cancellation')
                          ->orWhere('change_request->request_cancellation', false);
                    });
                } elseif ($status === 'approved') {
                    // Fully Approved, excluding pending cancellation
                    $query->where('status', LeaveRequest::STATUS_APPROVED)
                          ->where(function ($q) {
                              $q->whereNull('change_request->request_cancellation')
                                ->orWhere('change_request->request_cancellation', false)
                                ->orWhere('change_request_status', '!=', 'pending');
                          });
                } elseif ($status === 'pending_cancellation') {
                    // Pending Cancellation
                    $query->where('change_request_status', 'pending')
                          ->where('change_request->request_cancellation', true);
                } elseif ($status === 'changes_requested') {
                    // Outstanding change requests (shows with changes badge)
                    $query->whereIn('change_request_status', ['pending', 'approved_by_manager'])
                          ->whereNotIn('status', [LeaveRequest::STATUS_REJECTED, LeaveRequest::STATUS_REJECTED_BY_MANAGER, LeaveRequest::STATUS_CANCELLED])
                          ->where(function ($q) {
                              $q->whereNull('change_request->request_cancellation')
                                ->orWhere('change_request->request_cancellation', false);
                          });
                } elseif ($status === 'rejected') {
                    $query->where('status', LeaveRequest::STATUS_REJECTED);
                } elseif ($status === 'rejected_by_manager') {
                    $query->where('status', LeaveRequest::STATUS_REJECTED_BY_MANAGER);
                } elseif ($status === 'cancelled') {
                    $query->where('status', LeaveRequest::STATUS_CANCELLED);
                } else {
                    $query->where('status', $status);
                }
            }

            if (! empty($validated['leave_type_id'])) {
                $query->where('leave_type_id', $validated['leave_type_id']);
            }

            if (! empty($validated['start_date']) && ! empty($validated['end_date'])) {
                $query->where('start_date', '<=', $validated['end_date'])
                      ->where('end_date', '>=', $validated['start_date']);
            } elseif (! empty($validated['start_date'])) {
                $query->where('end_date', '>=', $validated['start_date']);
            } elseif (! empty($validated['end_date'])) {
                $query->where('start_date', '<=', $validated['end_date']);
            }

            $recentRequests = $query->latest()->paginate(15)->withQueryString();
        } else {
            $query = ($type === 'remote')
                ? \App\Models\RemoteWorkLog::with(['marker'])
                : \App\Models\AttendanceRecord::with(['marker']);

            $query->where('user_id', $user->id);

            if (! empty($validated['start_date'])) {
                $query->where('date', '>=', $validated['start_date']);
            }

            if (! empty($validated['end_date'])) {
                $query->where('date', '<=', $validated['end_date']);
            }

            $records = $query->orderBy('date', 'asc')->get();

            $grouped = [];
            foreach ($records as $record) {
                $last = end($grouped);
                if ($last &&
                    \Carbon\Carbon::parse($last->end_date)->addDay()->format('Y-m-d') == $record->date->format('Y-m-d') &&
                    $last->notes == $record->notes &&
                    $last->marked_by == $record->marked_by) {

                    $last->end_date = $record->date->format('Y-m-d');
                    $last->date_range_display = \Carbon\Carbon::parse($last->start_date)->format('M d, Y') . ' – ' . \Carbon\Carbon::parse($last->end_date)->format('M d, Y');
                    $last->days_count++;
                } else {
                    $record->start_date = $record->date->format('Y-m-d');
                    $record->end_date = $record->date->format('Y-m-d');
                    $record->date_range_display = $record->date->format('M d, Y');
                    $record->days_count = 1;
                    $grouped[] = $record;
                }
            }

            usort($grouped, function($a, $b) {
                return strcmp($b->start_date, $a->start_date);
            });

            $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage() ?: 1;
            $perPage = 15;
            $currentItems = array_slice($grouped, ($currentPage - 1) * $perPage, $perPage);

            $recentRequests = new \Illuminate\Pagination\LengthAwarePaginator($currentItems, count($grouped), $perPage, $currentPage, [
                'path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()
            ]);
        }

        $leaveTypes = \App\Models\LeaveType::where('is_active', true)->orderBy('name')->get();

        return view('admin.team.record', compact('user', 'recentRequests', 'leaveTypes', 'year', 'type'));
    }

    public function directoryView(Request $request): View
    {
        $admin = Auth::user();
        $subordinateIds = $admin->subordinate_ids;
        $year = (int) now()->year;

        $query = User::with([
            'department', 
            'role', 
            'leaveBalances' => fn ($q) => $q->whereHas('leaveType', fn ($t) => $t->where('is_active', true))->with('leaveType')
        ])
            ->whereIn('id', $subordinateIds);

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('employee_code', 'like', $search);
            });
        }

        $team = $query->orderBy('name')->paginate(10)->withQueryString();

        // Query presence today to display status badges next to user name
        $today = now()->startOfDay();
        $todayStr = $today->format('Y-m-d');

        $leavesToday = LeaveRequest::whereIn('user_id', $subordinateIds)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->pluck('user_id')
            ->toArray();

        $remotesToday = \App\Models\RemoteWorkLog::whereIn('user_id', $subordinateIds)
            ->where('date', $todayStr)
            ->pluck('user_id')
            ->toArray();

        $absentsToday = \App\Models\AttendanceRecord::whereIn('user_id', $subordinateIds)
            ->where('date', $todayStr)
            ->pluck('user_id')
            ->toArray();

        $leaveTypes = \App\Models\LeaveType::where('is_active', true)->orderBy('name')->get();
        $teamUserIds = $team->pluck('id')->toArray();
        $existingBalances = \App\Models\LeaveBalance::whereIn('user_id', $teamUserIds)
            ->where('year', $year)
            ->get()
            ->groupBy('user_id');

        foreach ($team as $member) {
            $memberBalances = $existingBalances->get($member->id, collect());
            foreach ($leaveTypes as $type) {
                $hasBalance = $memberBalances->contains('leave_type_id', $type->id);
                if (!$hasBalance) {
                    app(\App\Services\LeaveBalanceService::class)->ensureBalanceRow($member, $type, $year);
                }
            }

            if (in_array($member->id, $leavesToday)) {
                $member->today_status = 'leave';
            } elseif (in_array($member->id, $remotesToday)) {
                $member->today_status = 'remote';
            } elseif (in_array($member->id, $absentsToday)) {
                $member->today_status = 'absent';
            } else {
                $member->today_status = 'active';
            }
        }

        return view('admin.team.directory', compact('team', 'year', 'leaveTypes'));
    }

    public function approveChange(Request $request, LeaveRequest $leaveRequest)
    {
        return $this->approve($request, $leaveRequest);
    }

    public function rejectChange(Request $request, LeaveRequest $leaveRequest)
    {
        return $this->reject($request, $leaveRequest);
    }

    public function subordinateLogs(User $user): \Illuminate\Http\JsonResponse
    {
        $admin = Auth::user();
        if (!in_array($user->id, $admin->subordinate_ids)) {
            return response()->json(['error' => 'Unauthorized access.'], 403);
        }

        // Fetch absent records and remote work logs for this user
        $absents = \App\Models\AttendanceRecord::where('user_id', $user->id)
            ->orderBy('date', 'asc')
            ->get();

        $remotes = \App\Models\RemoteWorkLog::where('user_id', $user->id)
            ->orderBy('date', 'asc')
            ->get();

        $logs = collect();

        foreach ($absents as $abs) {
            $logs->push([
                'id' => $abs->id,
                'date' => $abs->date->format('M d, Y'),
                'type' => 'absent',
                'notes' => $abs->notes,
                'marker' => $abs->marker->name ?? 'System',
            ]);
        }

        foreach ($remotes as $rem) {
            $logs->push([
                'id' => $rem->id,
                'date' => $rem->date->format('M d, Y'),
                'type' => 'remote',
                'notes' => $rem->notes,
                'marker' => $rem->marker->name ?? 'System',
            ]);
        }

        // Sort by raw date desc
        $sortedLogs = $logs->sortByDesc(function ($log) {
            return \Carbon\Carbon::parse($log['date']);
        })->values()->all();

        return response()->json([
            'success' => true,
            'logs' => $sortedLogs,
        ]);
    }

    public function trackRecord(Request $request): View
    {
        $admin = Auth::user();
        $subordinateIds = $admin->subordinate_ids;

        // Fetch subordinates only (admin's own record is under "My Track Record")
        $users = User::whereIn('id', $subordinateIds)->orderBy('name')->get();

        $period = $request->get('period', 'today');
        $selectedUserId = $request->get('user_id');

        // Check if selected employee is subordinate
        if ($selectedUserId && $selectedUserId !== 'all' && !in_array($selectedUserId, $subordinateIds)) {
            abort(403, 'Unauthorized access.');
        }

        $readOnly = true;

        return view('super-admin.session-management.track-record', compact('users', 'period', 'selectedUserId', 'readOnly'));
    }

    public function getTrackRecordApi(Request $request): \Illuminate\Http\JsonResponse
    {
        $admin = Auth::user();
        $subordinateIds = $admin->subordinate_ids;
        $userId = $request->get('user_id');

        if ($userId && $userId !== 'all' && $userId != $admin->id && !in_array($userId, $subordinateIds)) {
            return response()->json(['error' => 'Unauthorized access.'], 403);
        }

        // Delegate execution to SessionManagementController
        return app(\App\Http\Controllers\SuperAdmin\SessionManagementController::class)->getTrackRecordApi($request);
    }

    public function exportTrackRecord(Request $request)
    {
        $admin = Auth::user();
        $subordinateIds = $admin->subordinate_ids;
        $userId = $request->get('user_id');

        if ($userId && $userId != $admin->id && !in_array($userId, $subordinateIds)) {
            abort(403, 'Unauthorized access.');
        }

        return app(\App\Http\Controllers\SuperAdmin\SessionManagementController::class)->exportTrackRecord($request);
    }

    public function attendanceLogs(Request $request): View
    {
        $admin = Auth::user();
        $subordinateIds = $admin->subordinate_ids;

        $users = User::whereIn('id', $subordinateIds)->orderBy('name')->get();
        $leaveTypes = \App\Models\LeaveType::where('is_active', true)->orderBy('name')->get();
        $type = $request->get('type', 'absent');

        $tz = 'Asia/Karachi';
        $now = \Carbon\Carbon::now($tz);
        $selectedMonth = (int) $request->input('month', $now->month);
        $selectedYear = (int) $request->input('year', $now->year);

        if ($selectedMonth < 1 || $selectedMonth > 12) {
            $selectedMonth = $now->month;
        }
        if ($selectedYear < 2020 || $selectedYear > 2035) {
            $selectedYear = $now->year;
        }

        $startDate = \Carbon\Carbon::createFromDate($selectedYear, $selectedMonth, 1, $tz)->startOfMonth()->format('Y-m-d');
        $endDate = \Carbon\Carbon::createFromDate($selectedYear, $selectedMonth, 1, $tz)->endOfMonth()->format('Y-m-d');

        $grouped = [];
        if ($type === 'leave') {
            $query = LeaveRequest::with(['user', 'reviewer', 'leaveType'])
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->where(function($q) {
                    $q->where('admin_comment', 'like', 'Directly approved by Super Admin%')
                      ->orWhere('admin_comment', 'like', 'Automatically converted from Absent%');
                })
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate);

            $query->whereIn('user_id', $subordinateIds);

            if ($request->filled('user_id')) {
                if (!in_array($request->user_id, $subordinateIds)) {
                    abort(403, 'Unauthorized access.');
                }
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('leave_type_id')) {
                $query->where('leave_type_id', $request->leave_type_id);
            }

            $records = $query->latest('start_date')->get();

            foreach ($records as $record) {
                $record->start_date = $record->start_date->format('Y-m-d');
                $record->end_date = $record->end_date->format('Y-m-d');
                $record->date_range_display = ($record->start_date === $record->end_date)
                    ? \Carbon\Carbon::parse($record->start_date)->format('M d, Y')
                    : \Carbon\Carbon::parse($record->start_date)->format('M d, Y') . ' – ' . \Carbon\Carbon::parse($record->end_date)->format('M d, Y');
                
                $record->notes = $record->reason ?? '—';
                $record->marker = $record->reviewer ?? (object)['name' => 'System'];
                $record->record_ids = [$record->id];
                $record->days_count = (double) $record->days_count;

                $grouped[] = $record;
            }
        } else {
            $query = ($type === 'remote')
                ? \App\Models\RemoteWorkLog::with(['user', 'marker'])
                : \App\Models\AttendanceRecord::with(['user', 'marker']);

            $query->whereIn('user_id', $subordinateIds);
            $query->whereBetween('date', [$startDate, $endDate]);

            if ($request->filled('user_id')) {
                if (!in_array($request->user_id, $subordinateIds)) {
                    abort(403, 'Unauthorized access.');
                }
                $query->where('user_id', $request->user_id);
            }

            $records = $query->orderBy('user_id')
                ->orderBy('date', 'asc')
                ->get();

            foreach ($records as $record) {
                $dayVal = ($type === 'remote')
                    ? \App\Http\Controllers\SuperAdmin\SessionManagementController::getRemoteDayValue($record->notes)
                    : 1.0;

                $last = end($grouped);
                if ($last && $last->user_id == $record->user_id && 
                    \Carbon\Carbon::parse($last->end_date)->addDay()->format('Y-m-d') == $record->date->format('Y-m-d') &&
                    $last->notes == $record->notes &&
                    $last->marked_by == $record->marked_by) {
                    
                    $last->end_date = $record->date->format('Y-m-d');
                    $last->date_range_display = \Carbon\Carbon::parse($last->start_date)->format('M d, Y') . ' – ' . \Carbon\Carbon::parse($last->end_date)->format('M d, Y');
                    $last->days_count += $dayVal;
                    
                    $tempIds = $last->record_ids;
                    $tempIds[] = $record->id;
                    $last->record_ids = $tempIds;
                } else {
                    $record->start_date = $record->date->format('Y-m-d');
                    $record->end_date = $record->date->format('Y-m-d');
                    $record->date_range_display = $record->date->format('M d, Y');
                    $record->days_count = $dayVal;
                    $record->record_ids = [$record->id];
                    $grouped[] = $record;
                }
            }
        }

        // Calculate total days count and records count across all matching grouped entries
        $totalDaysCount = 0.0;
        foreach ($grouped as $g) {
            $totalDaysCount += (float)$g->days_count;
        }
        $totalRecordsCount = count($grouped);

        // Sort grouped array by start_date desc to show latest records first
        usort($grouped, function($a, $b) {
            return strcmp($b->start_date, $a->start_date);
        });

        // Paginate grouped collection manually
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage() ?: 1;
        $perPage = 15;
        $currentItems = array_slice($grouped, ($currentPage - 1) * $perPage, $perPage);
        
        $absents = new \Illuminate\Pagination\LengthAwarePaginator($currentItems, count($grouped), $perPage, $currentPage, [
            'path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()
        ]);

        $readOnly = true;

        return view('super-admin.attendance-holidays.logs', compact('users', 'leaveTypes', 'absents', 'type', 'readOnly', 'selectedMonth', 'selectedYear', 'totalDaysCount', 'totalRecordsCount'));
    }
}
