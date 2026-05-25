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
                'remaining' => $this->balanceService->availableDays($b),
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
                'remaining' => $this->balanceService->availableDays($b),
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
}
