<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => Auth::user()->unreadNotifications()->count(),
        ]);
    }

    public function index(): JsonResponse
    {
        $notifications = Auth::user()
            ->notifications()
            ->where(function ($query) {
                $query->whereNull('read_at')
                      ->orWhere('is_marked', true)
                      ->orWhere('created_at', '>=', now()->startOfMonth());
            })
            ->latest()
            ->limit(50)
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'message' => $n->data['message'] ?? '',
                    'url' => $this->mapNotificationUrl($n),
                    'read_at' => $n->read_at,
                    'is_marked' => (bool)($n->is_marked ?? false),
                    'created_at' => $n->created_at->isAfter(now()->subHours(24)) ? $n->created_at->diffForHumans() : $n->created_at->format('M d, Y'),
                ];
            });

        return response()->json(['notifications' => $notifications]);
    }

    private function mapNotificationUrl($n): string
    {
        $url        = $n->data['url'] ?? '#';
        $notifClass = $n->type;
        $notifType  = $n->data['type'] ?? '';
        $message    = $n->data['message'] ?? '';
        $empId      = $n->data['employee_id'] ?? null;

        // Resolve empId from message bracket format: [Type] EmployeeName: "..."
        if (!$empId && !empty($message)) {
            if (preg_match('/^\[[^\]]+\]\s*([^:]+):/', $message, $matches)) {
                $name  = trim($matches[1]);
                $empId = \App\Models\User::where('name', $name)->value('id');
            }
        }

        // Fallback: resolve from employee_name field
        if (!$empId) {
            $employeeName = $n->data['employee_name'] ?? null;
            if ($employeeName) {
                $empId = \App\Models\User::where('name', $employeeName)->value('id');
            }
        }

        // Fallback: resolve from slack_message_id
        if (!$empId) {
            $slackMessageId = $n->data['slack_message_id'] ?? null;
            if ($slackMessageId) {
                $empId = \App\Models\SlackMessage::find($slackMessageId)?->mapping?->user_id;
            }
        }

        $user = Auth::user();

        // ── Account Approval & Activation Notifications ─────────────────────────────────────
        if (in_array($notifType, ['account_approval_required', 'account_activation_requested'])) {
            return route('super-admin.users.index', ['show' => 'pending'], false);
        }

        if (in_array($notifType, ['account_approved', 'account_rejected'])) {
            return route('admin.dashboard', [], false);
        }

        // ── Session / Track Record ──────────────────────────────────────────────────────────
        if (in_array($notifType, [
                'session_override_decision',
                'track_record_fixes',
                'break_exceeded',
                'break_warning',
                'break_grace_period',
                'auto_append',
                'forgot_to_sign_out',
                'session_anomaly',
                'forgot_to_type_back'
            ])
            || in_array($notifClass, [
                'App\Notifications\SessionOverrideDecisionNotification',
                'App\Notifications\TrackRecordFixesNotification',
                'App\Notifications\BreakExceededNotification',
                'App\Notifications\BreakWarningNotification',
                'App\Notifications\BreakGracePeriodNotification',
                'App\Notifications\AutoAppendNotification',
                'App\Notifications\ForgotToSignOutNotification',
                'App\Notifications\SessionAnomalyNotification',
                'App\Notifications\ForgotToTypeBackNotification',
            ])) {
            $params = $empId ? ['user_id' => $empId] : [];
            if ($user->isSuperAdmin()) {
                return route('super-admin.session-management.track-record', $params, false);
            }
            if ($user->isStaff()) {
                if (($n->data['is_self'] ?? false) || ($empId && (int)$user->id === (int)$empId)) {
                    return route('admin.track-record', [], false);
                }
                return route('admin.team.track-record', $params, false);
            }
            return route('admin.track-record', [], false);
        }

        // ── Determine if self-directed notification ──────────────────────────────────────────
        $isSelf = $n->data['is_self'] ?? false;
        if (!$isSelf && !empty($message)) {
            if (str_contains($message, 'You were marked')
                || str_contains($message, 'You were granted')
                || str_contains($message, 'Your leave')
                || str_contains($message, 'your leave')
                || str_contains($message, 'Your break')
                || str_contains($message, 'your break')
                || str_contains($message, 'Your daily break')
                || str_contains($message, 'Your leave request')) {
                $isSelf = true;
            }
        }

        // ── For leave_manager_approved: determine whether chain is mid-way or complete
        // Level and total_levels are stored in the notification data.
        // If level < total_levels and the leave is NOT yet fully approved → still pending
        // (next level approval needed). If level == total_levels OR is_complete is true
        // → approved_by_manager (CEO pending).
        $isChainComplete = false;
        if ($notifType === 'leave_manager_approved') {
            $level       = (int) ($n->data['level'] ?? 0);
            $totalLevels = (int) ($n->data['total_levels'] ?? 0);
            // is_complete flag may be stored in older notifications
            $storedComplete = $n->data['is_complete'] ?? null;

            if ($storedComplete !== null) {
                $isChainComplete = (bool) $storedComplete;
            } elseif ($level > 0 && $totalLevels > 0) {
                $isChainComplete = ($level >= $totalLevels);
            } else {
                // No level info stored — default to complete (approved_by_manager)
                $isChainComplete = true;
            }
        }

        // ── Super Admin routes ────────────────────────────────────────────────────
        if ($user->isSuperAdmin()) {

            $params = $empId ? ['user_id' => $empId] : [];

            // Absent / Remote (deleted or marked)
            if (in_array($notifType, ['absent_marked', 'remote_marked', 'absent_deleted', 'remote_deleted'])
                || in_array($notifClass, ['App\Notifications\AbsentMarkedNotification', 'App\Notifications\RemoteMarkedNotification'])) {
                $type = (str_contains($notifType, 'remote') || str_contains($notifClass, 'Remote')) ? 'remote' : 'absent';
                $params['type'] = $type;
                return route('super-admin.attendance-holidays.absents.logs', $params, false);
            }

            // Finalized / history events
            if (in_array($notifType, [
                    'leave_cancelled',
                    'leave_granted',
                    'leave_deleted',
                    'leave_type_changed',
                    'leave_admin_approved_to_manager',
                    'leave_overridden',
                    'leave_cancellation_rejected',
                    'leave_change_processed',
                ])) {
                return route('super-admin.leave-requests.history', $params, false);
            }

            // Active-queue leave events
            if (in_array($notifType, [
                    'leave_submitted',
                    'leave_no_manager',
                    'leave_manager_approved',
                    'leave_manager_rejected',
                    'leave_change_requested',
                    'leave_cancellation_requested',
                    'chain_approval_required',
                ])) {
                if ($notifType === 'leave_change_requested') {
                    $params['status'] = 'changes_requested';
                } elseif ($notifType === 'leave_manager_approved') {
                    // Mid-chain approval → leave is still `pending` (next level needed)
                    // Final chain approval → `approved_by_manager` (CEO pending)
                    $params['status'] = $isChainComplete ? 'approved_by_manager' : 'pending';
                } elseif ($notifType === 'leave_manager_rejected') {
                    $params['status'] = 'rejected_by_manager';
                } elseif ($notifType === 'leave_cancellation_requested') {
                    $params['status'] = 'pending_cancellation';
                } elseif ($notifType === 'chain_approval_required') {
                    // The next manager needs to act — leave is still pending
                    $params['status'] = 'pending';
                }
                // leave_submitted, leave_no_manager → no status filter (show all active)
                return route('super-admin.leave-requests.index', $params, false);
            }
        }

        // ── Staff / Admin routes ──────────────────────────────────────────────────
        elseif ($user->isStaff()) {

            $params = $empId ? ['user_id' => $empId] : [];

            if ($isSelf) {
                // Self-directed notifications (employee reviewing their own leaves)
                if (in_array($notifType, ['absent_marked', 'absent_deleted'])
                    || $notifClass === 'App\Notifications\AbsentMarkedNotification') {
                    return route('admin.leaves.record', ['type' => 'absent'], false);
                }
                if (in_array($notifType, ['remote_marked', 'remote_deleted'])
                    || $notifClass === 'App\Notifications\RemoteMarkedNotification') {
                    return route('admin.leaves.record', ['type' => 'remote'], false);
                }
                return route('admin.leaves.record', ['type' => 'leaves'], false);
            }

            // Team-directed notifications
            if (in_array($notifType, ['absent_marked', 'absent_deleted'])
                || $notifClass === 'App\Notifications\AbsentMarkedNotification') {
                $params['type'] = 'absent';
                return route('admin.team.attendance-logs', $params, false);
            }
            if (in_array($notifType, ['remote_marked', 'remote_deleted'])
                || $notifClass === 'App\Notifications\RemoteMarkedNotification') {
                $params['type'] = 'remote';
                return route('admin.team.attendance-logs', $params, false);
            }

            // Finalized / history events for team
            if (in_array($notifType, [
                    'leave_granted',
                    'leave_cancelled',
                    'leave_deleted',
                    'leave_type_changed',
                    'leave_admin_approved_to_manager',
                    'leave_overridden',
                    'leave_cancellation_rejected',
                    'leave_change_processed',
                ])) {
                return route('admin.team.leaves.history', $params, false);
            }

            // Active-queue leave events for team
            if (in_array($notifType, [
                    'leave_submitted',
                    'leave_change_requested',
                    'leave_commented',
                    'leave_reviewed',
                    'leave_cancellation_requested',
                    'leave_manager_approved',
                    'leave_manager_rejected',
                    'chain_approval_required',
                ])) {
                if ($notifType === 'leave_change_requested') {
                    $params['status'] = 'changes_requested';
                } elseif ($notifType === 'leave_manager_approved') {
                    $params['status'] = $isChainComplete ? 'approved_by_manager' : 'pending';
                } elseif ($notifType === 'leave_manager_rejected') {
                    $params['status'] = 'rejected_by_manager';
                } elseif ($notifType === 'leave_cancellation_requested') {
                    $params['status'] = 'pending_cancellation';
                } elseif ($notifType === 'chain_approval_required') {
                    $params['status'] = 'pending';
                }
                return route('admin.team.leaves', $params, false);
            }
        }

        // ── Fallback: append empId to raw stored URL if applicable ─────────────
        if ($empId) {
            if (str_contains($url, 'session-management/overrides')) {
                $url .= (str_contains($url, '?') ? '&' : '?') . 'employee_id=' . $empId;
            } elseif (str_contains($url, 'session-management/track-record')) {
                $url .= (str_contains($url, '?') ? '&' : '?') . 'user_id=' . $empId;
            }
        }

        return $url;
    }

    public function markRead(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function toggleRead(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->where('id', $id)->firstOrFail();
        if ($notification->read_at) {
            $notification->read_at = null;
        } else {
            $notification->read_at = now();
        }
        $notification->save();

        return response()->json([
            'success' => true,
            'is_read' => $notification->read_at !== null
        ]);
    }

    public function toggleMark(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->where('id', $id)->firstOrFail();
        $notification->is_marked = !($notification->is_marked ?? false);
        $notification->save();

        return response()->json([
            'success' => true,
            'is_marked' => (bool)$notification->is_marked
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->where('id', $id)->firstOrFail();
        $notification->delete();

        return response()->json(['success' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    public function bulkAction(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'action' => 'required|string|in:read,unread,mark,unmark,delete'
        ]);

        $ids = $request->ids;
        $action = $request->action;
        $user = Auth::user();

        if ($action === 'read') {
            $user->notifications()->whereIn('id', $ids)->update(['read_at' => now()]);
        } elseif ($action === 'unread') {
            $user->notifications()->whereIn('id', $ids)->update(['read_at' => null]);
        } elseif ($action === 'mark') {
            $user->notifications()->whereIn('id', $ids)->update(['is_marked' => true]);
        } elseif ($action === 'unmark') {
            $user->notifications()->whereIn('id', $ids)->update(['is_marked' => false]);
        } elseif ($action === 'delete') {
            $user->notifications()->whereIn('id', $ids)->delete();
        }

        return response()->json(['success' => true]);
    }

    public function activityLog(Request $request)
    {
        $query = Auth::user()->notifications();

        // Build unique keywords list for filter dropdown
        $defaultKeywords = [
            'Absent',
            'Absent Cancelled',
            'Alert',
            'Break Exceeded',
            'Break Warning',
            'Department Updated',
            'Forgot Back',
            'Forgot Break End',
            'Forgot Break Start',
            'Forgot Sign In',
            'Forgot Sign Out',
            'Holiday',
            'Holiday Cancelled',
            'Leave',
            'Leave Cancelled',
            'Leave Converted',
            'Leave Details Updated',
            'Leave Type Changed',
            'Remote',
            'Remote Cancelled',
            'Salary Slip Email',
            'Salary Slip Issued',
            'Session Anomaly',
            'Session Override',
        ];

        $dbMessages = Auth::user()->notifications()
            ->select('data')
            ->limit(500)
            ->get()
            ->pluck('data.message')
            ->filter();

        $extractedKeywords = [];
        foreach ($dbMessages as $msg) {
            if (preg_match('/^\[([^\]]+)\]/', $msg, $m)) {
                $extractedKeywords[] = trim($m[1]);
            }
        }

        $keywords = collect($defaultKeywords)
            ->merge($extractedKeywords)
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // Build unique employee filter list for filter dropdown based on hierarchy
        $employeesList = collect();
        $authUser      = Auth::user();
        $isSuperAdmin  = $authUser->isSuperAdmin();

        if ($isSuperAdmin) {
            // Super Admin can view/filter all non-superadmin employees in the organization
            $users = \App\Models\User::whereHas('role', fn($q) => $q->where('name', '!=', \App\Models\Role::SUPER_ADMIN))
                ->orderBy('name')
                ->get(['id', 'name']);
        } else {
            // Staff / Line Managers should only see themselves and employees under them in the hierarchy
            $subordinateIds = $authUser->subordinate_ids ?? [];
            $allowedUserIds = array_unique(array_merge([$authUser->id], $subordinateIds));

            $users = \App\Models\User::whereIn('id', $allowedUserIds)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        foreach ($users as $u) {
            $employeesList->push([
                'value' => $u->name,
                'label' => $u->name,
            ]);
        }

        $notifEmployeeNames = $authUser->notifications()
            ->select('data')
            ->limit(500)
            ->get()
            ->map(function ($n) {
                if (!empty($n->data['employee_name'])) {
                    return $n->data['employee_name'];
                }
                $msg = $n->data['message'] ?? '';
                if (preg_match('/^\[([^\]]+)\]\s*([^:]+):/', $msg, $m)) {
                    return trim($m[2]);
                }
                return null;
            })
            ->filter()
            ->unique();

        foreach ($notifEmployeeNames as $name) {
            if ($name !== 'System' && !preg_match('/^U_TEST_/i', $name) && !preg_match('/^U[0-9A-Z]{8,}$/i', $name) && !$employeesList->contains('value', $name)) {
                if ($isSuperAdmin || $users->contains('name', $name)) {
                    $employeesList->push([
                        'value' => $name,
                        'label' => $name,
                    ]);
                }
            }
        }

        $employees = $employeesList->sortBy('label')->values()->toArray();

        // Filter by Read/Unread/Marked
        if ($request->has('filter')) {
            if ($request->filter === 'unread') {
                $query->whereNull('read_at');
            } elseif ($request->filter === 'marked') {
                $query->where('is_marked', true);
            }
        }

        // Keyword Filter
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('data->message', 'like', "%[{$kw}]%")
                  ->orWhere('data->message', 'like', "%{$kw}%");
            });
        }

        // Employee Filter
        if ($request->filled('employee')) {
            $emp = $request->employee;
            $query->where(function ($q) use ($emp) {
                $q->where('data->employee_name', 'like', "%{$emp}%")
                  ->orWhere('data->message', 'like', "%{$emp}%");
            });
        }

        // Legacy Search in message text
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('data->message', 'like', "%{$search}%")
                  ->orWhere('data->employee_name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 15);
        $notifications = $query->latest()->paginate($perPage)->withQueryString();

        $notifications->through(function ($n) {
            $n->mapped_url = $this->mapNotificationUrl($n);
            return $n;
        });

        return view('super-admin.notifications.activity', compact('notifications', 'keywords', 'employees'));
    }
}
