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
            ->get()
            ->sortBy(function ($type) {
                if ($type->slug === 'unpaid') return '0_unpaid';
                if ($type->slug === 'emergency') return '1_emergency';
                if ($type->slug === 'absent') return '2_absent';
                return '3_' . strtolower($type->name);
            })
            ->values();

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
            'is_half_day' => ['boolean'],
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
            'is_half_day' => $request->boolean('is_half_day'),
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
            'is_half_day' => ['boolean'],
        ]);

        $isSystemType = ($leaveType->slug === 'unpaid' || $leaveType->slug === 'emergency' || $leaveType->slug === 'absent');
        $name = $isSystemType ? $leaveType->name : $validated['name'];
        $maxDays = $isSystemType ? $leaveType->max_days_per_year : $validated['max_days_per_year'];
        $defaultEntitled = $isSystemType ? ($leaveType->settings->first()?->default_entitled ?? 0) : $validated['default_entitled'];
        $isHalfDay = $isSystemType ? false : $request->boolean('is_half_day');

        $leaveType->update([
            'name' => $name,
            'max_days_per_year' => $maxDays,
            'allow_carry_forward' => $request->boolean('allow_carry_forward'),
            'max_carry_forward_days' => $validated['max_carry_forward_days'] ?? 0,
            'is_half_day' => $isHalfDay,
        ]);

        LeaveSetting::updateOrCreate(
            ['leave_type_id' => $leaveType->id],
            ['default_entitled' => $defaultEntitled]
        );

        return back()->with('success', 'Leave type updated.');
    }
    
    public function toggleStatus(LeaveType $leaveType, \App\Services\LeaveBalanceService $balanceService): RedirectResponse
    {
        if ($leaveType->slug === 'unpaid' || $leaveType->slug === 'emergency' || $leaveType->slug === 'absent') {
            return back()->withErrors(['status' => 'System-required leave types (Unpaid, Emergency and Absent) cannot be disabled.']);
        }

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
        if ($leaveType->slug === 'unpaid' || $leaveType->slug === 'emergency' || $leaveType->slug === 'absent') {
            return back()->withErrors(['delete' => 'System-required leave types (Unpaid, Emergency and Absent) cannot be deleted.']);
        }

        if ($leaveType->requests()->where('status', \App\Models\LeaveRequest::STATUS_PENDING)->exists()) {
            return back()->withErrors(['delete' => 'Cannot delete this leave type because there are pending leave requests. You can only disable it.']);
        }
        
        \Illuminate\Support\Facades\DB::transaction(function () use ($leaveType) {
            \App\Models\LeaveBalance::where('leave_type_id', $leaveType->id)->delete();
            \App\Models\LeaveSetting::where('leave_type_id', $leaveType->id)->delete();
            \App\Models\LeaveBalanceOverride::where('leave_type_id', $leaveType->id)->delete();
            $leaveType->delete();
        });

        return back()->with('success', 'Leave type deleted successfully.');
    }
}
