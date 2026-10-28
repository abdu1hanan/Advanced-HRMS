<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\LeaveBalanceService;
use App\Services\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class LeaveRequestController extends Controller
{
    public function __construct(
        protected LeaveRequestService $leaveRequestService,
        protected LeaveBalanceService $balanceService
    ) {}

    public function store(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $validated = $request->validate([
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $leaveRequest = $this->leaveRequestService->submit(Auth::user(), $validated);
        } catch (ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first(),
                    'errors' => $e->errors(),
                ], 422);
            }
            return back()->withInput()->with('error', collect($e->errors())->flatten()->first());
        }

        if (!($request->expectsJson() || $request->ajax())) {
            return back()->with('success', 'Leave request submitted successfully.');
        }

        $user = Auth::user();
        $year = (int) now()->year;
        $balances = $user->leaveBalances()
            ->whereHas('leaveType', fn ($q) => $q->where('is_active', true))
            ->with('leaveType')
            ->where('year', $year)
            ->get()
            ->map(fn ($b) => [
                'leave_type' => $b->leaveType->name,
                'entitled' => $b->entitled,
                'used' => $b->used,
                'pending' => $b->pending,
                'carried_forward' => $b->carried_forward,
                'remaining' => $this->balanceService->leftDays($b),
            ]);

        $leaveRequest->load(['leaveType', 'reviewer', 'user', 'approvalChain.approver.designation', 'comments' => function ($q) {
            $q->where('is_hidden_from_employee', false);
        }, 'comments.user']);
        $rowHtml = view('employee.partials.leave_row', ['req' => $leaveRequest])->render();

        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted successfully.',
            'request_id' => $leaveRequest->id,
            'balances' => $balances,
            'row_html' => $rowHtml,
        ]);
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $this->authorize('cancel', $leaveRequest);

        $request->validate([
            'reason' => ['required', 'string', 'min:2'],
        ], [
            'reason.required' => 'Please enter a reason for cancellation.',
            'reason.min' => 'Please enter a valid reason for cancellation.',
        ]);

        $reason = trim((string)$request->input('reason'));

        try {
            $result = $this->leaveRequestService->cancel($leaveRequest, Auth::user(), $reason);
        } catch (ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first(),
                ], 422);
            }
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $msg = $result['instant'] 
            ? 'Leave request cancelled successfully. Your leave balance has been refunded.'
            : 'Cancellation request submitted to Super Admin for review.';

        if (!($request->expectsJson() || $request->ajax())) {
            return back()->with('success', $msg);
        }

        $user = Auth::user();
        $year = (int) now()->year;
        $balances = $user->leaveBalances()
            ->whereHas('leaveType', fn ($q) => $q->where('is_active', true))
            ->with('leaveType')
            ->where('year', $year)
            ->get()
            ->map(fn ($b) => [
                'leave_type' => $b->leaveType->name,
                'entitled' => $b->entitled,
                'used' => $b->used,
                'pending' => $b->pending,
                'carried_forward' => $b->carried_forward,
                'remaining' => $this->balanceService->leftDays($b),
            ]);

        $requestModel = $result['request']->fresh(['leaveType', 'reviewer', 'user', 'approvalChain.approver.designation', 'comments' => function ($q) {
            $q->where('is_hidden_from_employee', false);
        }, 'comments.user']);
        $rowHtml = view('employee.partials.leave_row', ['req' => $requestModel])->render();

        return response()->json([
            'success' => true,
            'message' => $msg,
            'instant' => $result['instant'],
            'status' => $requestModel->status,
            'cancel_reason' => $reason,
            'cancel_banner_text' => $requestModel->cancellationBannerText(),
            'balances' => $balances,
            'row_html' => $rowHtml,
        ]);
    }

    public function comment(Request $request, LeaveRequest $leaveRequest)
    {
        $user = Auth::user();
        $isOwner = $leaveRequest->user_id === $user->id;
        $isSubordinate = in_array($leaveRequest->user_id, $user->subordinate_ids ?? [], true);
        $isInChain = $leaveRequest->approvalChain()->where('approver_id', $user->id)->exists();
        $isSuperAdmin = $user->isSuperAdmin();

        if (! $isOwner && ! $isSubordinate && ! $isInChain && ! $isSuperAdmin) {
            abort(403, 'Unauthorized access to this leave request.');
        }

        $request->validate(['comment' => 'required|string']);

        $isHidden = $request->boolean('is_hidden_from_employee');
        app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, $user, $request->comment, $isHidden);

        return back()->with('success', 'Comment added.');
    }

    public function recordView(Request $request)
    {
        $user = Auth::user();
        if ($user->isGuest()) {
            return redirect()->route('admin.dashboard');
        }
        $year = (int) now()->year;

        $validated = $request->validate([
            'type' => ['nullable', 'in:leaves,absent,remote'],
            'leave_type_id' => ['nullable', 'exists:leave_types,id'],
            'status' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
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

        return view('employee.record', compact('user', 'recentRequests', 'leaveTypes', 'year', 'type'));
    }

    public function changeRequest(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        if ($leaveRequest->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'start_date'    => ['nullable', 'date'],
            'end_date'      => ['nullable', 'date', 'after_or_equal:start_date'],
            'leave_type_id' => ['nullable', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'reason'        => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->leaveRequestService->requestChange($leaveRequest, $validated, Auth::user());
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors'  => $e->errors(),
            ], 422);
        }

        $leaveRequest->refresh();
        $leaveRequest->load(['leaveType', 'reviewer', 'user', 'approvalChain.approver.designation', 'comments' => function ($q) {
            $q->where('is_hidden_from_employee', false);
        }, 'comments.user']);
        $rowHtml = view('employee.partials.leave_row', ['req' => $leaveRequest])->render();

        return response()->json([
            'success' => true,
            'message' => 'Leave request updated successfully. Approval chain has been reset to Level 1.',
            'request_id' => $leaveRequest->id,
            'row_html' => $rowHtml,
        ]);
    }

    public function trackRecord(Request $request)
    {
        $user = Auth::user();
        if ($user->isGuest()) {
            return redirect()->route('admin.dashboard');
        }
        $users = collect([$user]);
        $period = $request->get('period', 'today');
        $selectedUserId = $user->id;
        $readOnly = true;
        $isEmployeeSide = true;

        return view('super-admin.session-management.track-record', compact('users', 'period', 'selectedUserId', 'readOnly', 'isEmployeeSide'));
    }

    public function getTrackRecordApi(Request $request): JsonResponse
    {
        $user = Auth::user();
        $request->merge(['user_id' => $user->id]);

        return app(\App\Http\Controllers\SuperAdmin\SessionManagementController::class)->getTrackRecordApi($request);
    }

    public function exportTrackRecord(Request $request)
    {
        $user = Auth::user();
        $request->merge(['user_id' => $user->id]);

        return app(\App\Http\Controllers\SuperAdmin\SessionManagementController::class)->exportTrackRecord($request);
    }
}
