<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\LeaveSetting;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LeaveTypeController extends Controller
{
    public function index(): View
    {
        $leaveTypes = LeaveType::with('settings')
            ->withCount(['requests as pending_requests_count' => function ($query) {
                $query->where('status', \App\Models\LeaveRequest::STATUS_PENDING);
            }])
            ->orderBy('name')
            ->get();

        return view('super-admin.leave-types.index', compact('leaveTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'max_days_per_year' => ['required', 'integer', 'min:0'],
            'default_entitled' => ['required', 'integer', 'min:0'],
            'allow_carry_forward' => ['boolean'],
            'max_carry_forward_days' => ['integer', 'min:0'],
        ]);

        $slug = Str::slug($validated['name']);
        if (LeaveType::where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'A leave type with a similar name already exists.'])->withInput();
        }

        $type = LeaveType::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'max_days_per_year' => $validated['max_days_per_year'],
            'allow_carry_forward' => $request->boolean('allow_carry_forward'),
            'max_carry_forward_days' => $validated['max_carry_forward_days'] ?? 0,
        ]);

        LeaveSetting::create([
            'leave_type_id' => $type->id,
            'default_entitled' => $validated['default_entitled'],
        ]);

        return back()->with('success', 'Leave type created.');
    }

    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'max_days_per_year' => ['required', 'integer', 'min:0'],
            'default_entitled' => ['required', 'integer', 'min:0'],
            'allow_carry_forward' => ['boolean'],
            'max_carry_forward_days' => ['integer', 'min:0'],
        ]);

        $leaveType->update([
            'name' => $validated['name'],
            'max_days_per_year' => $validated['max_days_per_year'],
            'allow_carry_forward' => $request->boolean('allow_carry_forward'),
            'max_carry_forward_days' => $validated['max_carry_forward_days'] ?? 0,
        ]);

        LeaveSetting::updateOrCreate(
            ['leave_type_id' => $leaveType->id],
            ['default_entitled' => $validated['default_entitled']]
        );

        return back()->with('success', 'Leave type updated.');
    }
    
    public function toggleStatus(LeaveType $leaveType, \App\Services\LeaveBalanceService $balanceService): RedirectResponse
    {
        $newStatus = !$leaveType->is_active;

        if (!$newStatus) {
            $pendingRequests = $leaveType->requests()->where('status', \App\Models\LeaveRequest::STATUS_PENDING)->get();
            
            \Illuminate\Support\Facades\DB::transaction(function () use ($pendingRequests, $balanceService) {
                foreach ($pendingRequests as $request) {
                    $year = (int) $request->start_date->year;
                    $balance = $balanceService->ensureBalanceRow($request->user, $request->leaveType, $year);
                    
                    $balance->decrement('pending', $request->days_count);
                    
                    $request->update([
                        'status' => \App\Models\LeaveRequest::STATUS_REJECTED,
                        'admin_comment' => 'Leave type disabled by system admin.',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                    ]);
                    
                    $request->user->notify(new \App\Notifications\LeaveRequestReviewed($request));
                }
            });
        }

        $leaveType->update(['is_active' => $newStatus]);

        return back()->with('success', 'Leave type status updated successfully.');
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        if ($leaveType->requests()->where('status', \App\Models\LeaveRequest::STATUS_PENDING)->exists()) {
            return back()->withErrors(['delete' => 'Cannot delete this leave type because there are pending leave requests. You can only disable it.']);
        }
        
        $leaveType->delete();
        return back()->with('success', 'Leave type deleted successfully.');
    }
}
