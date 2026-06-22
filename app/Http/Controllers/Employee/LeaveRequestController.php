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

    public function store(Request $request): JsonResponse
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
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        $user = Auth::user();
        $year = (int) now()->year;
        $balances = $user->leaveBalances()
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

        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted successfully.',
            'request_id' => $leaveRequest->id,
            'balances' => $balances,
        ]);
    }

    public function cancel(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('cancel', $leaveRequest);

        try {
            $this->leaveRequestService->cancel($leaveRequest, Auth::user());
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        $user = Auth::user();
        $year = (int) now()->year;
        $balances = $user->leaveBalances()
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

        return response()->json([
            'success' => true,
            'message' => 'Leave request cancelled.',
            'balances' => $balances,
        ]);
    }

    public function comment(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate(['comment' => 'required|string']);

        app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, Auth::user(), $request->comment);

        return back()->with('success', 'Comment added.');
    }

    public function recordView(Request $request): \Illuminate\View\View
    {
        $user = Auth::user();
        $year = (int) now()->year;

        $validated = $request->validate([
            'leave_type_id' => ['nullable', 'exists:leave_types,id'],
            'status' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $query = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->with(['leaveType', 'comments.user']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['leave_type_id'])) {
            $query->where('leave_type_id', $validated['leave_type_id']);
        }

        if (! empty($validated['start_date'])) {
            $query->where('start_date', '>=', $validated['start_date']);
        }

        if (! empty($validated['end_date'])) {
            $query->where('end_date', '<=', $validated['end_date']);
        }

        $recentRequests = $query->latest()->paginate(15)->withQueryString();
        $leaveTypes = \App\Models\LeaveType::where('is_active', true)->orderBy('name')->get();

        return view('employee.record', compact('user', 'recentRequests', 'leaveTypes', 'year'));
    }

    public function changeRequest(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        if ($leaveRequest->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'leave_type_id' => ['nullable', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'reason' => ['nullable', 'string', 'max:2000'],
            'request_cancellation' => ['nullable', 'boolean'],
        ]);

        try {
            $this->leaveRequestService->requestChange($leaveRequest, $validated, Auth::user());
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave change request submitted successfully.',
        ]);
    }
}
