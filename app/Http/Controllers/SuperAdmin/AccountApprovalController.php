<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AccountApprovalRequest;
use App\Models\User;
use App\Notifications\AccountApprovedNotification;
use App\Notifications\AccountRejectedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountApprovalController extends Controller
{
    public function index(Request $request): View
    {
        // Self-heal: ensure any pending users have an approval request record
        $untrackedPendingUsers = User::where('approval_status', User::STATUS_PENDING)
            ->whereDoesntHave('accountApprovalRequests', function ($q) {
                $q->where('status', AccountApprovalRequest::STATUS_PENDING);
            })
            ->get();

        foreach ($untrackedPendingUsers as $u) {
            AccountApprovalRequest::create([
                'user_id'    => $u->id,
                'status'     => AccountApprovalRequest::STATUS_PENDING,
                'created_at' => $u->activation_requested_at ?? $u->created_at ?? now(),
            ]);
        }

        $status = $request->query('status', 'unresolved');
        $isResolved = $status === 'resolved';
        $search = $request->query('search');

        $query = AccountApprovalRequest::with(['user.role', 'user.department', 'actionBy']);

        if ($isResolved) {
            $query->whereIn('status', [AccountApprovalRequest::STATUS_APPROVED, AccountApprovalRequest::STATUS_REJECTED])
                  ->orderByDesc('action_at');
        } else {
            $query->where('status', AccountApprovalRequest::STATUS_PENDING)
                  ->orderByDesc('created_at');
        }

        if (! empty($search)) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $requests = $query->paginate(20)->withQueryString();

        $unresolvedCount = AccountApprovalRequest::where('status', AccountApprovalRequest::STATUS_PENDING)->count();
        $resolvedCount   = AccountApprovalRequest::whereIn('status', [AccountApprovalRequest::STATUS_APPROVED, AccountApprovalRequest::STATUS_REJECTED])->count();

        return view('super-admin.users.approvals', compact(
            'requests',
            'isResolved',
            'unresolvedCount',
            'resolvedCount',
            'search'
        ));
    }

    public function approve(AccountApprovalRequest $accountApprovalRequest): RedirectResponse
    {
        $accountApprovalRequest->update([
            'status'    => AccountApprovalRequest::STATUS_APPROVED,
            'action_by' => auth()->id(),
            'action_at' => now(),
        ]);

        $user = $accountApprovalRequest->user;
        if ($user) {
            $user->update([
                'approval_status' => User::STATUS_APPROVED,
                'approved_at'     => now(),
                'rejected_at'     => null,
            ]);

            $user->notify(new AccountApprovedNotification());
        }

        return back()->with('success', "Account request for '{$user?->name}' has been approved and activated.");
    }

    public function reject(AccountApprovalRequest $accountApprovalRequest): RedirectResponse
    {
        $user = $accountApprovalRequest->user;
        if ($user && $user->isSuperAdmin()) {
            return back()->withErrors(['reject' => 'Super Admin accounts cannot be rejected.']);
        }

        $accountApprovalRequest->update([
            'status'    => AccountApprovalRequest::STATUS_REJECTED,
            'action_by' => auth()->id(),
            'action_at' => now(),
        ]);

        if ($user) {
            $user->update([
                'approval_status' => User::STATUS_REJECTED,
                'rejected_at'     => now(),
            ]);

            $user->notify(new AccountRejectedNotification());
        }

        return back()->with('success', "Account request for '{$user?->name}' has been rejected.");
    }
}
