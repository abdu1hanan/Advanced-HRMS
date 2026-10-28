<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\CompanyHoliday;
use App\Models\Department;
use App\Models\User;
use App\Models\LeaveType;
use App\Models\LeaveRequest;
use App\Models\RemoteWorkLog;
use App\Models\Role;
use App\Notifications\AbsentMarkedNotification;
use App\Notifications\HolidayDeclaredNotification;
use App\Notifications\LeaveGrantedNotification;
use App\Notifications\RemoteMarkedNotification;
use App\Notifications\LeaveTypeChangedNotification;
use App\Notifications\AbsentDeletedNotification;
use App\Notifications\RemoteDeletedNotification;
use App\Notifications\LeaveDeletedNotification;
use App\Notifications\HolidayDeletedNotification;
use App\Services\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class AttendanceHolidayController extends Controller
{
    public function absents(Request $request): View
    {
        $users = User::whereHas('role', function($q) {
            $q->where('name', '!=', \App\Models\Role::SUPER_ADMIN);
        })->orderBy('name')->get();

        $leaveTypes = LeaveType::where('is_active', true)->orderBy('name')->get();

        // Read break settings
        $breakSettingsFile = storage_path('app/break_settings.json');
        $breakSettings = [
            'break_balance_minutes' => 60,
            'break_grace_period_minutes' => 15,
        ];
        if (file_exists($breakSettingsFile)) {
            $breakSettings = array_merge($breakSettings, json_decode(file_get_contents($breakSettingsFile), true) ?: []);
        }

        return view('super-admin.attendance-holidays.absents', compact('users', 'leaveTypes', 'breakSettings'));
    }

    public function absentLogs(Request $request): View
    {
        $users = User::whereHas('role', function($q) {
            $q->where('name', '!=', \App\Models\Role::SUPER_ADMIN);
        })->orderBy('name')->get();

        $leaveTypes = LeaveType::where('is_active', true)->orderBy('name')->get();
        $type = $request->get('type', 'absent');

        $tz = 'Asia/Karachi';
        $now = Carbon::now($tz);
        $selectedMonth = (int) $request->input('month', $now->month);
        $selectedYear = (int) $request->input('year', $now->year);

        if ($selectedMonth < 1 || $selectedMonth > 12) {
            $selectedMonth = $now->month;
        }
        if ($selectedYear < 2020 || $selectedYear > 2035) {
            $selectedYear = $now->year;
        }

        $startDate = Carbon::createFromDate($selectedYear, $selectedMonth, 1, $tz)->startOfMonth()->format('Y-m-d');
        $endDate = Carbon::createFromDate($selectedYear, $selectedMonth, 1, $tz)->endOfMonth()->format('Y-m-d');

        $grouped = [];
        if ($type === 'leave') {
            $query = LeaveRequest::with(['user', 'reviewer', 'leaveType'])
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->where(function($q) {
                    $q->where('is_admin_marked', true)
                      ->orWhere('admin_comment', 'like', 'Directly approved by%')
                      ->orWhere('admin_comment', 'like', 'Automatically converted from Absent%');
                })
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate);

            if ($request->filled('user_id')) {
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
                    ? Carbon::parse($record->start_date)->format('M d, Y')
                    : Carbon::parse($record->start_date)->format('M d, Y') . ' – ' . Carbon::parse($record->end_date)->format('M d, Y');
                
                $record->notes = $record->reason ?? '—';
                $record->marker = $record->reviewer ?? (object)['name' => 'System'];
                $record->record_ids = [$record->id];
                $record->days_count = (double) $record->days_count;

                $grouped[] = $record;
            }
        } else {
            $query = ($type === 'remote')
                ? RemoteWorkLog::with(['user', 'marker'])
                : AttendanceRecord::with(['user', 'marker']);

            $query->whereBetween('date', [$startDate, $endDate]);

            if ($request->filled('user_id')) {
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
                    Carbon::parse($last->end_date)->addDay()->format('Y-m-d') == $record->date->format('Y-m-d') &&
                    $last->notes == $record->notes &&
                    $last->marked_by == $record->marked_by) {
                    
                    $last->end_date = $record->date->format('Y-m-d');
                    $last->date_range_display = Carbon::parse($last->start_date)->format('M d, Y') . ' – ' . Carbon::parse($last->end_date)->format('M d, Y');
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
        $currentPage = LengthAwarePaginator::resolveCurrentPage() ?: 1;
        $perPage = 15;
        $currentItems = array_slice($grouped, ($currentPage - 1) * $perPage, $perPage);
        
        $absents = new LengthAwarePaginator($currentItems, count($grouped), $perPage, $currentPage, [
            'path' => LengthAwarePaginator::resolveCurrentPath()
        ]);

        return view('super-admin.attendance-holidays.logs', compact('users', 'leaveTypes', 'absents', 'type', 'selectedMonth', 'selectedYear', 'totalDaysCount', 'totalRecordsCount'));
    }

    public function holidays(Request $request): View
    {
        $query = CompanyHoliday::with(['creator', 'departments', 'users']);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }

        // Fetch all matching records sorted by name and date so we can merge consecutive days
        $records = $query->orderBy('name')
            ->orderBy('date', 'asc')
            ->get();

        // Perform grouping of consecutive days
        $grouped = [];
        foreach ($records as $record) {
            $last = end($grouped);
            if ($last && $last->name == $record->name && $last->created_by == $record->created_by &&
                $last->target_type == $record->target_type && $last->target_action == $record->target_action &&
                Carbon::parse($last->end_date)->addDay()->format('Y-m-d') == $record->date->format('Y-m-d')) {
                
                $last->end_date = $record->date->format('Y-m-d');
                $last->date_range_display = Carbon::parse($last->start_date)->format('M d, Y') . ' – ' . Carbon::parse($last->end_date)->format('M d, Y');
                $last->days_count++;
                
                $tempIds = $last->record_ids;
                $tempIds[] = $record->id;
                $last->record_ids = $tempIds;
            } else {
                $record->start_date = $record->date->format('Y-m-d');
                $record->end_date = $record->date->format('Y-m-d');
                $record->date_range_display = $record->date->format('M d, Y');
                $record->days_count = 1;
                $record->record_ids = [$record->id];
                $record->target_badge = $record->getTargetBadgeData();
                $record->applicable_users_count = $record->getApplicableUsers()->count();
                $grouped[] = $record;
            }
        }

        // Sort grouped array by start_date desc to show latest records first
        usort($grouped, function($a, $b) {
            return strcmp($b->start_date, $a->start_date);
        });

        // Paginate manually
        $currentPage = LengthAwarePaginator::resolveCurrentPage() ?: 1;
        $perPage = 15;
        $currentItems = array_slice($grouped, ($currentPage - 1) * $perPage, $perPage);
        
        $holidays = new LengthAwarePaginator($currentItems, count($grouped), $perPage, $currentPage, [
            'path' => LengthAwarePaginator::resolveCurrentPath()
        ]);

        $departments = Department::orderBy('name')->get();
        $users = User::whereHas('role', fn($q) => $q->where('name', '!=', Role::SUPER_ADMIN))
            ->with('department')
            ->orderBy('name')
            ->get();

        return view('super-admin.attendance-holidays.holidays', compact('holidays', 'departments', 'users'));
    }

    public function updateDeductionRule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rule' => 'required|in:working_days,calendar_days,fixed_30',
            'recalculate_all' => 'required|boolean',
        ]);

        $settingsFile = storage_path('app/salary_deduction_settings.json');
        $current = [];
        if (file_exists($settingsFile)) {
            $current = json_decode(file_get_contents($settingsFile), true) ?: [];
        }

        $oldRule = $current['rule'] ?? 'calendar_days';

        $recalculateAll = (bool) $validated['recalculate_all'];

        $newSettings = [
            'rule' => $validated['rule'],
            'recalculate_all' => $recalculateAll,
            'effective_month' => $recalculateAll ? null : now()->format('Y-m'),
            'previous_rule' => $recalculateAll ? null : $oldRule,
        ];

        file_put_contents($settingsFile, json_encode($newSettings, JSON_PRETTY_PRINT));

        $ruleText = $validated['rule'] === 'working_days' 
            ? 'Working Days in Month' 
            : ($validated['rule'] === 'fixed_30' ? 'Fixed 30 Days' : 'Total Days in Month');
        $scopeText = $recalculateAll ? 'applied retroactively to all records.' : 'applied from the current month (' . now()->format('F Y') . ') onwards.';

        return back()->with('success', "Salary deduction rule updated to {$ruleText} and {$scopeText}");
    }

    public function updateBreakSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'break_balance_minutes'      => 'required|integer|min:1|max:300',
            'break_grace_period_minutes' => 'required|integer|min:0|max:120',
        ]);

        $settingsFile = storage_path('app/break_settings.json');
        $newSettings = [
            'break_balance_minutes'      => (int) $validated['break_balance_minutes'],
            'break_grace_period_minutes' => (int) $validated['break_grace_period_minutes'],
            'updated_at'                 => now()->toIso8601String(),
        ];

        file_put_contents($settingsFile, json_encode($newSettings, JSON_PRETTY_PRINT));

        // Update Cache in real time so queue workers and webhooks pick it up with 0 restarts needed
        \Illuminate\Support\Facades\Cache::put('hrms_break_balance_seconds', ((int)$validated['break_balance_minutes']) * 60);
        \Illuminate\Support\Facades\Cache::put('hrms_break_grace_period_seconds', ((int)$validated['break_grace_period_minutes']) * 60);

        return back()->with('success', "Daily break settings updated: {$validated['break_balance_minutes']} min balance + {$validated['break_grace_period_minutes']} min grace buffer.");
    }

    public function storeAbsent(Request $request): JsonResponse
    {
        $request->validate([
            'date'                 => 'required|date|before_or_equal:today',
            'user_id'              => 'required|exists:users,id',
            'notes'                => 'nullable|string|max:500',
            'force_reject_pending' => 'nullable|boolean',
        ]);

        $userId    = $request->user_id;
        $dateStr   = Carbon::parse($request->date)->format('Y-m-d');
        $user      = User::findOrFail($userId);
        $force     = (bool) $request->input('force_reject_pending', false);

        // --- Conflict checks ---

        // 1. Already marked absent
        if (AttendanceRecord::where('user_id', $userId)->where('date', $dateStr)->exists()) {
            return response()->json([
                'success'       => false,
                'conflict_type' => 'already_absent',
                'message'       => "{$user->name} is already marked absent on " . Carbon::parse($dateStr)->format('M d, Y') . '.',
            ], 422);
        }

        // 2. Day is a company holiday for this user
        $holiday = CompanyHoliday::isHolidayForUser($user, $dateStr);
        if ($holiday) {
            return response()->json([
                'success'       => false,
                'conflict_type' => 'is_holiday',
                'message'       => Carbon::parse($dateStr)->format('M d, Y') . " is a holiday ({$holiday->name}) for {$user->name}. Cannot mark absent on a holiday.",
            ], 422);
        }

        // 3. Employee has an approved leave on that day — hard block
        $approvedLeave = LeaveRequest::with('leaveType')
            ->where('user_id', $userId)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->first();

        if ($approvedLeave) {
            $leaveName = $approvedLeave->leaveType?->name ?? 'Leave';
            return response()->json([
                'success'       => false,
                'conflict_type' => 'has_approved_leave',
                'message'       => "{$user->name} has an approved {$leaveName} on " . Carbon::parse($dateStr)->format('M d, Y') . ". Please cancel the leave before marking absent.",
            ], 422);
        }

        // 4. [NEW] Employee is already marked remote — hard block with link
        $remoteLog = RemoteWorkLog::where('user_id', $userId)->where('date', $dateStr)->first();
        if ($remoteLog) {
            return response()->json([
                'success'       => false,
                'conflict_type' => 'has_remote_log',
                'message'       => "{$user->name} is already marked as working remotely on " . Carbon::parse($dateStr)->format('M d, Y') . ". Please delete the remote work log first before marking absent.",
                'logs_url'      => route('super-admin.attendance-holidays.absents.logs', ['type' => 'remote', 'user_id' => $userId]),
            ], 422);
        }

        // 5. Employee has a pending leave on that day — hard block
        $pendingLeave = LeaveRequest::with('leaveType')
            ->where('user_id', $userId)
            ->where(function($q) {
                $q->whereIn('status', [LeaveRequest::STATUS_PENDING, 'approved_by_manager', 'pending_cancellation'])
                  ->orWhere('change_request_status', 'pending');
            })
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->first();

        if ($pendingLeave) {
            $leaveName = $pendingLeave->leaveType?->name ?? 'Leave';
            $leaveFrom = Carbon::parse($pendingLeave->start_date)->format('M d');
            $leaveTo   = Carbon::parse($pendingLeave->end_date)->format('M d, Y');
            return response()->json([
                'success'       => false,
                'conflict_type' => 'has_pending_leave',
                'message'       => "{$user->name} has a pending {$leaveName} request ({$leaveFrom} – {$leaveTo}) awaiting approval for this date. Please approve or reject the leave request before marking absent.",
                'logs_url'      => route('super-admin.leave-requests.index', ['user_id' => $userId, 'status' => 'pending']),
            ], 422);
        }

        // --- No conflicts — save ---
        AttendanceRecord::create([
            'user_id'   => $userId,
            'date'      => $dateStr,
            'notes'     => $request->notes,
            'marked_by' => auth()->id(),
        ]);

        // --- Notify employee and their line manager ---
        $formattedDate = Carbon::parse($dateStr)->format('M d, Y');

        $user->notify(new AbsentMarkedNotification(
            employeeName:  $user->name,
            dateFormatted: $formattedDate,
            notes:         $request->notes,
            isSelf:        true,
            employeeId:    $user->id,
        ));

        $bossChain = app(\App\Services\HierarchyService::class)->getBossChain($user);
        foreach ($bossChain as $boss) {
            $boss->notify(new AbsentMarkedNotification(
                employeeName:  $user->name,
                dateFormatted: $formattedDate,
                notes:         $request->notes,
                isSelf:        false,
                employeeId:    $user->id,
            ));
        }

        return response()->json([
            'success' => true,
            'message' => "{$user->name} marked absent on " . Carbon::parse($dateStr)->format('M d, Y') . '.',
        ]);
    }

    public function convertAbsent(Request $request, AttendanceRecord $record): RedirectResponse
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
        ]);

        $leaveType = LeaveType::findOrFail($validated['leave_type_id']);
        
        $recordIds = $request->filled('record_ids') 
            ? explode(',', $request->record_ids) 
            : [$record->id];
            
        $records = AttendanceRecord::whereIn('id', $recordIds)->orderBy('date', 'asc')->get();
        if ($records->isEmpty()) {
            return back()->with('error', 'No absence records found to convert.');
        }

        $user = $records->first()->user;
        $year = (int) $records->first()->date->year;
        
        $balanceService = app(LeaveBalanceService::class);
        $totalDays = $leaveType->is_half_day ? (count($records) * 0.5) : count($records);

        try {
            DB::transaction(function() use ($records, $leaveType, $user, $year, $balanceService, $totalDays) {
                // Verify leave balance (except for unpaid leave)
                $balance = $balanceService->ensureBalanceRow($user, $leaveType, $year);
                if ($leaveType->slug !== 'unpaid' && $leaveType->slug !== 'emergency' && $balanceService->availableDays($balance) < $totalDays) {
                    throw new \Exception("Insufficient leave balance for {$leaveType->name}. You have " . $balanceService->availableDays($balance) . " days left but need {$totalDays} days.");
                }

                // Verify overlap for each date
                foreach ($records as $rec) {
                    $dateStr = $rec->date->format('Y-m-d');
                    $overlap = LeaveRequest::where('user_id', $user->id)
                        ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
                        ->where('start_date', '<=', $dateStr)
                        ->where('end_date', '>=', $dateStr)
                        ->exists();

                    if ($overlap) {
                        throw new \Exception("An approved or pending leave request already exists for {$dateStr}.");
                    }
                }

                // Create approved LeaveRequest spanning the start and end of this block
                $startDate = $records->first()->date;
                $endDate = $records->last()->date;
                
                LeaveRequest::create([
                    'user_id'         => $user->id,
                    'leave_type_id'   => $leaveType->id,
                    'start_date'      => $startDate,
                    'end_date'        => $endDate,
                    'days_count'      => (double) $totalDays,
                    'reason'          => 'Converted from Absents (' . $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d') . ')',
                    'status'          => LeaveRequest::STATUS_APPROVED,
                    'reviewed_by'     => auth()->id(),
                    'reviewed_at'     => now(),
                    'admin_comment'   => 'Automatically converted from Absent records block',
                    'is_admin_marked' => true,
                ]);

                // Increment used balance
                $balance->increment('used', (double) $totalDays);

                // Delete all consolidated records
                AttendanceRecord::whereIn('id', $records->pluck('id'))->delete();
            });
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to convert absent to leave: ' . $e->getMessage());
        }

        // --- Notify employee and their line manager ---
        $startDate = $records->first()->date;
        $endDate = $records->last()->date;
        $startFormatted = $startDate->format('M d, Y');
        $endFormatted   = $endDate->format('M d, Y');

        $user->notify(new LeaveGrantedNotification(
            employeeName:   $user->name,
            leaveTypeName:  $leaveType->name,
            startFormatted: $startFormatted,
            endFormatted:   $endFormatted,
            daysCount:      $totalDays,
            isSelf:         true,
            employeeId:     $user->id,
            isConverted:    true,
        ));

        $bossChain = app(\App\Services\HierarchyService::class)->getBossChain($user);
        foreach ($bossChain as $boss) {
            $boss->notify(new LeaveGrantedNotification(
                employeeName:   $user->name,
                leaveTypeName:  $leaveType->name,
                startFormatted: $startFormatted,
                endFormatted:   $endFormatted,
                daysCount:      $totalDays,
                isSelf:         false,
                employeeId:     $user->id,
                isConverted:    true,
            ));
        }

        $dateText = $totalDays === 1 
            ? "on " . $startDate->format('Y-m-d')
            : "from " . $startDate->format('Y-m-d') . " to " . $endDate->format('Y-m-d');

        return back()->with('success', "Successfully converted absent to {$leaveType->name} for {$user->name} {$dateText} (total {$totalDays} days).");
    }

    public function destroyAbsent(Request $request, AttendanceRecord $record): RedirectResponse
    {
        $recordIds = $request->filled('record_ids') 
            ? explode(',', $request->record_ids) 
            : [$record->id];
            
        $records = AttendanceRecord::whereIn('id', $recordIds)->orderBy('date', 'asc')->get();
        if ($records->isEmpty()) {
            return back()->with('error', 'No absence records found to delete.');
        }
        
        $user = $records->first()->user;
        $totalDays = count($records);
        
        AttendanceRecord::whereIn('id', $records->pluck('id'))->delete();

        $startFormatted = $records->first()->date->format('M d, Y');
        $endFormatted   = $records->last()->date->format('M d, Y');
        $notifRange     = $startFormatted === $endFormatted
            ? $startFormatted
            : $records->first()->date->format('M d') . ' – ' . $endFormatted;

        // Notify employee
        $user->notify(new AbsentDeletedNotification(
            employeeName:  $user->name,
            dateFormatted: $notifRange,
            isSelf:        true,
            employeeId:    $user->id,
        ));

        // Notify manager
        $bossChain = app(\App\Services\HierarchyService::class)->getBossChain($user);
        foreach ($bossChain as $boss) {
            $boss->notify(new AbsentDeletedNotification(
                employeeName:  $user->name,
                dateFormatted: $notifRange,
                isSelf:        false,
                employeeId:    $user->id,
            ));
        }

        $dateText = $totalDays === 1 
            ? "on " . $records->first()->date->format('Y-m-d')
            : "from " . $records->first()->date->format('Y-m-d') . " to " . $records->last()->date->format('Y-m-d');

        return back()->with('success', "Removed absent records for {$user->name} {$dateText} (total {$totalDays} days).");
    }

    public function storeHoliday(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'start_date'          => 'required|date',
            'end_date'            => 'required|date|after_or_equal:start_date',
            'target_type'         => 'nullable|string|in:all,department,employee',
            'target_action'       => 'nullable|string|in:include,exclude',
            'department_ids'      => 'nullable|array',
            'department_ids.*'    => 'exists:departments,id',
            'user_ids'            => 'nullable|array',
            'user_ids.*'          => 'exists:users,id',
            'editing_record_ids'  => 'nullable|string',
            'force'               => 'nullable|boolean',
        ]);

        $start            = Carbon::parse($validated['start_date']);
        $end              = Carbon::parse($validated['end_date']);
        $force            = !empty($validated['force']);
        $targetType       = $validated['target_type'] ?? CompanyHoliday::TARGET_ALL;
        $targetAction     = $validated['target_action'] ?? CompanyHoliday::ACTION_INCLUDE;
        $deptIds          = $validated['department_ids'] ?? [];
        $userIds          = $validated['user_ids'] ?? [];
        $editingRecordIds = [];

        if (!empty($validated['editing_record_ids'])) {
            $editingRecordIds = array_filter(array_map('intval', explode(',', $validated['editing_record_ids'])));
        }

        // Track previous state when editing to avoid repeating notifications to unchanged users
        $oldApplicableUserIds = [];
        $oldDates             = [];
        $oldName              = '';
        if (!empty($editingRecordIds)) {
            $oldRecords = CompanyHoliday::with(['departments', 'users'])->whereIn('id', $editingRecordIds)->get();
            if ($oldRecords->isNotEmpty()) {
                $oldName              = $oldRecords->first()->name;
                $oldDates             = $oldRecords->pluck('date')->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))->sort()->values()->toArray();
                foreach ($oldRecords as $oldRec) {
                    $oldApplicableUserIds = array_merge($oldApplicableUserIds, $oldRec->getApplicableUsers()->pluck('id')->toArray());
                }
                $oldApplicableUserIds = array_unique($oldApplicableUserIds);
            }
        }

        if ($targetType === CompanyHoliday::TARGET_DEPARTMENT && empty($deptIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Please select at least one department.',
            ], 422);
        }

        if ($targetType === CompanyHoliday::TARGET_EMPLOYEE && empty($userIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Please select at least one employee.',
            ], 422);
        }

        // Collect all dates in range
        $dates = [];
        $cur = $start->copy();
        while ($cur->lte($end)) {
            $dates[] = $cur->format('Y-m-d');
            $cur->addDay();
        }

        // Determine applicable non-super-admin users
        $baseUsers = User::whereHas('role', fn($q) => $q->where('name', '!=', Role::SUPER_ADMIN));
        if ($targetType === CompanyHoliday::TARGET_DEPARTMENT) {
            $applicableUsers = ($targetAction === CompanyHoliday::ACTION_EXCLUDE)
                ? $baseUsers->where(function($q) use ($deptIds) {
                    $q->whereNotIn('department_id', $deptIds)->orWhereNull('department_id');
                })->get()
                : $baseUsers->whereIn('department_id', $deptIds)->get();
        } elseif ($targetType === CompanyHoliday::TARGET_EMPLOYEE) {
            $applicableUsers = ($targetAction === CompanyHoliday::ACTION_EXCLUDE)
                ? $baseUsers->whereNotIn('id', $userIds)->get()
                : $baseUsers->whereIn('id', $userIds)->get();
        } else {
            $applicableUsers = $baseUsers->get();
        }

        $applicableUserIds = $applicableUsers->pluck('id')->toArray();

        if (empty($applicableUserIds)) {
            return response()->json([
                'success' => false,
                'message' => 'A holiday cannot be marked. Someone has to be selected.',
            ], 422);
        }

        // --- 1. Hard block: duplicate holidays with overlapping audience (excluding current editing records) ---
        $existingHolidays = CompanyHoliday::with(['departments', 'users'])
            ->whereIn('date', $dates)
            ->when(!empty($editingRecordIds), fn($q) => $q->whereNotIn('id', $editingRecordIds))
            ->get();

        $conflictDates = [];
        foreach ($existingHolidays as $ex) {
            $exApplicableIds = $ex->getApplicableUsers()->pluck('id')->toArray();
            $intersection = array_intersect($applicableUserIds, $exApplicableIds);
            if (!empty($intersection)) {
                $conflictDates[] = Carbon::parse($ex->date)->format('M d, Y') . ' (' . $ex->name . ')';
            }
        }

        if (!empty($conflictDates)) {
            $conflictList = implode(', ', array_unique($conflictDates));
            return response()->json([
                'success'       => false,
                'conflict_type' => 'duplicate_holiday',
                'message'       => "The following dates already have an active holiday for the selected audience: {$conflictList}. Please adjust dates or selected scope.",
            ], 422);
        }

        // --- 2. Detect overlapping leaves and absent records only for applicable users ---
        $overlappingLeaves = LeaveRequest::select(['id', 'user_id', 'leave_type_id', 'start_date', 'end_date', 'status', 'days_count'])
            ->with([
                'user:id,name',
                'leaveType:id,name'
            ])
            ->whereIn('user_id', $applicableUserIds)
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_PENDING])
            ->where('start_date', '<=', $end->format('Y-m-d'))
            ->where('end_date', '>=', $start->format('Y-m-d'))
            ->get();

        $overlappingAbsents = AttendanceRecord::select(['id', 'user_id', 'date'])
            ->whereIn('user_id', $applicableUserIds)
            ->whereIn('date', $dates)
            ->get();

        if (($overlappingLeaves->isNotEmpty() || $overlappingAbsents->isNotEmpty()) && !$force) {
            $affectedLeaves = $overlappingLeaves->map(fn($l) => [
                'employee'   => $l->user?->name ?? 'Unknown',
                'leave_type' => $l->leaveType?->name ?? 'Leave',
                'from'       => Carbon::parse($l->start_date)->format('M d, Y'),
                'to'         => Carbon::parse($l->end_date)->format('M d, Y'),
                'status'     => $l->status === LeaveRequest::STATUS_APPROVED ? 'Approved' : 'Pending',
                'days'       => $l->days_count,
            ]);

            return response()->json([
                'success'         => false,
                'conflict_type'   => 'needs_confirmation',
                'affected_leaves' => $affectedLeaves,
                'absent_count'    => $overlappingAbsents->count(),
                'message'         => 'Some affected employees have existing leave requests or absent records on these dates.',
            ], 409);
        }

        // --- 3. force=true OR no conflicts: execute in transaction ---
        DB::transaction(function () use ($overlappingLeaves, $overlappingAbsents, $dates, $validated, $targetType, $targetAction, $deptIds, $userIds, $editingRecordIds) {
            $balanceService = app(LeaveBalanceService::class);

            // If updating, delete the previous holiday records being replaced
            if (!empty($editingRecordIds)) {
                CompanyHoliday::whereIn('id', $editingRecordIds)->delete();
            }

            // Cancel overlapping leaves and restore balances
            foreach ($overlappingLeaves as $leave) {
                $year    = (int) $leave->start_date->year;
                $balance = $balanceService->ensureBalanceRow($leave->user, $leave->leaveType, $year);

                if ($leave->status === LeaveRequest::STATUS_APPROVED) {
                    $balance->update(['used' => max(0.0, $balance->used - $leave->days_count)]);
                } else {
                    $balance->update(['pending' => max(0.0, $balance->pending - $leave->days_count)]);
                }

                $leave->update([
                    'status'                => LeaveRequest::STATUS_CANCELLED,
                    'admin_comment'         => 'Automatically cancelled: holiday declared for these dates.',
                    'change_request'        => null,
                    'change_request_status' => null,
                ]);
            }

            // Remove absent records for applicable employees on these dates
            if ($overlappingAbsents->isNotEmpty()) {
                AttendanceRecord::whereIn('id', $overlappingAbsents->pluck('id'))->delete();
            }

            // Save holidays and attach pivot relationships
            $userId = auth()->id();
            foreach ($dates as $dateStr) {
                $holiday = CompanyHoliday::create([
                    'date'          => $dateStr,
                    'name'          => $validated['name'],
                    'target_type'   => $targetType,
                    'target_action' => $targetAction,
                    'created_by'    => $userId,
                ]);

                if ($targetType === CompanyHoliday::TARGET_DEPARTMENT && !empty($deptIds)) {
                    $holiday->departments()->sync($deptIds);
                } elseif ($targetType === CompanyHoliday::TARGET_EMPLOYEE && !empty($userIds)) {
                    $holiday->users()->sync($userIds);
                }
            }
        });

        $count    = count($dates);
        $dateText = $validated['start_date'] === $validated['end_date']
            ? 'on ' . Carbon::parse($validated['start_date'])->format('M d, Y')
            : 'from ' . Carbon::parse($validated['start_date'])->format('M d, Y') . ' to ' . Carbon::parse($validated['end_date'])->format('M d, Y');

        $notifRange = $validated['start_date'] === $validated['end_date']
            ? Carbon::parse($validated['start_date'])->format('M d, Y')
            : Carbon::parse($validated['start_date'])->format('M d') . ' – ' . Carbon::parse($validated['end_date'])->format('M d, Y');

        // --- Smart Notification Dispatch: Only notify affected parties on edit ---
        $isEditing             = !empty($editingRecordIds);
        $newDates              = collect($dates)->sort()->values()->toArray();
        $scheduleOrNameChanged = ($oldDates !== $newDates) || ($oldName !== $validated['name']);

        $declaredNotification = new HolidayDeclaredNotification(
            holidayName: $validated['name'],
            dateRange:   $notifRange,
            daysCount:   $count,
        );

        if (!$isEditing || $scheduleOrNameChanged) {
            // New holiday declaration OR changed schedule/name: notify all currently applicable users
            foreach ($applicableUsers as $recipient) {
                $recipient->notify($declaredNotification);
            }
        } else {
            // Scope-only edit: notify ONLY newly added users who were not included previously
            $newlyAddedUserIds = array_diff($applicableUserIds, $oldApplicableUserIds);
            if (!empty($newlyAddedUserIds)) {
                $newlyAddedUsers = User::whereIn('id', $newlyAddedUserIds)->get();
                foreach ($newlyAddedUsers as $newRecipient) {
                    $newRecipient->notify($declaredNotification);
                }
            }
        }

        // Notify previously included users who have now been excluded from this holiday
        $removedUserIds = array_diff($oldApplicableUserIds, $applicableUserIds);
        if (!empty($removedUserIds)) {
            $removedUsers = User::whereIn('id', $removedUserIds)->get();
            $revokedMessage = "[Holiday Excluded] System: \"You have been excluded from the company holiday '{$validated['name']}' for {$notifRange}. It no longer applies to your account.\"";
            $revokedNotification = new HolidayDeletedNotification(
                holidayName:   $validated['name'],
                dateRange:     $notifRange,
                daysCount:     $count,
                customMessage: $revokedMessage,
            );
            foreach ($removedUsers as $removedUser) {
                $removedUser->notify($revokedNotification);
            }
        }

        $verb = !empty($editingRecordIds) ? 'updated' : 'saved';
        return response()->json([
            'success' => true,
            'message' => "Holiday '{$validated['name']}' {$verb} {$dateText} for {$applicableUsers->count()} employee(s) (total {$count} day" . ($count > 1 ? 's' : '') . ').',
        ]);
    }

    public function destroyHoliday(Request $request, CompanyHoliday $holiday): RedirectResponse
    {
        $recordIds = $request->filled('record_ids') 
            ? explode(',', $request->record_ids) 
            : [$holiday->id];
            
        $holidays = CompanyHoliday::whereIn('id', $recordIds)->orderBy('date', 'asc')->get();
        if ($holidays->isEmpty()) {
            return back()->with('error', 'No company holidays found to delete.');
        }
        
        $name = $holidays->first()->name;
        $totalDays = count($holidays);
        
        CompanyHoliday::whereIn('id', $holidays->pluck('id'))->delete();

        $startFormatted = $holidays->first()->date->format('M d, Y');
        $endFormatted   = $holidays->last()->date->format('M d, Y');
        $notifRange     = $startFormatted === $endFormatted
            ? $startFormatted
            : $holidays->first()->date->format('M d') . ' – ' . $endFormatted;

        // Notify recipients who were eligible for this holiday
        $recipients = $holidays->first()->getApplicableUsers();

        $notification = new HolidayDeletedNotification(
            holidayName: $name,
            dateRange:   $notifRange,
            daysCount:   $totalDays,
        );
        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
        }

        $dateText = $totalDays === 1 
            ? "on " . $holidays->first()->date->format('Y-m-d')
            : "from " . $holidays->first()->date->format('Y-m-d') . " to " . $holidays->last()->date->format('Y-m-d');

        return back()->with('success', "Deleted company holiday '{$name}' {$dateText} (total {$totalDays} days).");
    }

    public function storeLeave(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'       => 'required|exists:users,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'nullable|string|max:500',
        ]);

        $userId      = $request->user_id;
        $leaveTypeId = $request->leave_type_id;
        $startDate   = Carbon::parse($request->start_date);
        $endDate     = Carbon::parse($request->end_date);
        $user        = User::findOrFail($userId);
        $leaveType   = LeaveType::findOrFail($leaveTypeId);
        $leaveReason = $request->get('reason') ?: 'Marked by Super Admin via Attendance Management';

        // Calculate business days (weekdays only) in the range
        $totalDays = 0;
        $cur = $startDate->copy();
        while ($cur->lte($endDate)) {
            if (!$cur->isWeekend()) {
                $totalDays++;
            }
            $cur->addDay();
        }

        if ($totalDays === 0) {
            return response()->json([
                'success' => false,
                'message' => 'The selected date range contains no working days (weekdays).',
            ], 422);
        }

        if ($leaveType->is_half_day) {
            $totalDays = $totalDays * 0.5;
        }

        $year           = (int) $startDate->year;
        $balanceService = app(LeaveBalanceService::class);
        $balance        = $balanceService->ensureBalanceRow($user, $leaveType, $year);

        // Check leave balance (skip for unpaid leave)
        if ($leaveType->slug !== 'unpaid' && $leaveType->slug !== 'emergency') {
            $available = $balanceService->availableDays($balance);
            if ($available < $totalDays) {
                return response()->json([
                    'success' => false,
                    'message' => "{$user->name} has insufficient {$leaveType->name} balance. Available: {$available} day(s), Needed: {$totalDays} day(s).",
                ], 422);
            }
        }

        // 1. Check for company holidays in the date range applicable to this user
        $overlapHolidays = CompanyHoliday::with(['departments', 'users'])
            ->where('date', '>=', $startDate->format('Y-m-d'))
            ->where('date', '<=', $endDate->format('Y-m-d'))
            ->get();
        foreach ($overlapHolidays as $h) {
            if ($h->appliesToUser($user)) {
                return response()->json([
                    'success'       => false,
                    'conflict_type' => 'is_holiday',
                    'message'       => "The selected date range contains a company holiday: {$h->name} (" . Carbon::parse($h->date)->format('M d, Y') . "). Cannot mark leave on a holiday.",
                ], 422);
            }
        }

        // 2. Check for absent overlap in the date range — hard block with link
        $overlapAbsent = AttendanceRecord::where('user_id', $userId)
            ->where('date', '>=', $startDate->format('Y-m-d'))
            ->where('date', '<=', $endDate->format('Y-m-d'))
            ->first();
        if ($overlapAbsent) {
            return response()->json([
                'success'       => false,
                'conflict_type' => 'already_absent',
                'message'       => "{$user->name} is already marked absent on " . Carbon::parse($overlapAbsent->date)->format('M d, Y') . ". Please delete the absent record first before marking leave.",
                'logs_url'      => route('super-admin.attendance-holidays.absents.logs', ['type' => 'absent', 'user_id' => $userId]),
            ], 422);
        }

        // 3. Check for remote log overlap in the date range — hard block with link
        $overlapRemote = RemoteWorkLog::where('user_id', $userId)
            ->where('date', '>=', $startDate->format('Y-m-d'))
            ->where('date', '<=', $endDate->format('Y-m-d'))
            ->first();
        if ($overlapRemote) {
            return response()->json([
                'success'       => false,
                'conflict_type' => 'has_remote_log',
                'message'       => "{$user->name} is already marked working remotely on " . Carbon::parse($overlapRemote->date)->format('M d, Y') . ". Please delete the remote work log first before marking leave.",
                'logs_url'      => route('super-admin.attendance-holidays.absents.logs', ['type' => 'remote', 'user_id' => $userId]),
            ], 422);
        }

        // Check for overlapping pending/approved leave requests
        $overlap = LeaveRequest::where('user_id', $userId)
            ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
            ->where('start_date', '<=', $endDate->format('Y-m-d'))
            ->where('end_date',   '>=', $startDate->format('Y-m-d'))
            ->first();

        if ($overlap) {
            $overlapType   = $overlap->leaveType?->name ?? 'Leave';
            $overlapStatus = $overlap->status === LeaveRequest::STATUS_APPROVED ? 'already approved' : 'pending';
            $overlapFrom   = Carbon::parse($overlap->start_date)->format('M d');
            $overlapTo     = Carbon::parse($overlap->end_date)->format('M d, Y');
            return response()->json([
                'success' => false,
                'message' => "{$user->name} has an {$overlapStatus} {$overlapType} request covering these dates ({$overlapFrom} – {$overlapTo}). Please cancel it first if you need to replace it.",
            ], 422);
        }

        // Create fully-approved leave request
        DB::transaction(function () use ($user, $leaveType, $startDate, $endDate, $totalDays, $balance, $leaveReason) {
            LeaveRequest::create([
                'user_id'          => $user->id,
                'leave_type_id'    => $leaveType->id,
                'start_date'       => $startDate->format('Y-m-d'),
                'end_date'         => $endDate->format('Y-m-d'),
                'days_count'       => (double) $totalDays,
                'reason'           => $leaveReason,
                'status'           => LeaveRequest::STATUS_APPROVED,
                'reviewed_by'      => auth()->id(),
                'reviewed_at'      => now(),
                'admin_comment'    => 'Directly approved by ' . (auth()->user()->name ?? 'Admin') . ' — bypasses standard approval workflow.',
                'is_admin_marked'  => true,
            ]);

            // Increment used balance
            $balance->increment('used', (double) $totalDays);
        });

        // --- Notify employee and their line manager ---
        $startFormatted = $startDate->format('M d, Y');
        $endFormatted   = $endDate->format('M d, Y');

        $user->notify(new LeaveGrantedNotification(
            employeeName:   $user->name,
            leaveTypeName:  $leaveType->name,
            startFormatted: $startFormatted,
            endFormatted:   $endFormatted,
            daysCount:      $totalDays,
            isSelf:         true,
            employeeId:     $user->id,
        ));

        $bossChain = app(\App\Services\HierarchyService::class)->getBossChain($user);
        foreach ($bossChain as $boss) {
            $boss->notify(new LeaveGrantedNotification(
                employeeName:   $user->name,
                leaveTypeName:  $leaveType->name,
                startFormatted: $startFormatted,
                endFormatted:   $endFormatted,
                daysCount:      $totalDays,
                isSelf:         false,
                employeeId:     $user->id,
            ));
        }

        return response()->json([
            'success' => true,
            'message' => "{$user->name} granted {$totalDays} day(s) of {$leaveType->name} from "
                . $startDate->format('M d') . ' to ' . $endDate->format('M d, Y') . " (fully approved).",
        ]);
    }

    public function storeRemote(Request $request): JsonResponse
    {
        $request->validate([
            'date'                 => 'required|date',
            'user_id'              => 'required|exists:users,id',
            'start_time'           => 'nullable|string',
            'end_time'             => 'nullable|string',
            'notes'                => 'nullable|string|max:500',
            'force_reject_pending' => 'nullable|boolean',
        ]);

        $userId    = $request->user_id;
        $dateStr   = Carbon::parse($request->date)->format('Y-m-d');
        $user      = User::findOrFail($userId);
        $force     = (bool) $request->input('force_reject_pending', false);

        $startTime   = $request->input('start_time');
        $endTime     = $request->input('end_time');
        $customNotes = $request->filled('notes') ? trim($request->notes) : null;

        if ($startTime && $endTime) {
            try {
                $startCarbon = Carbon::parse($startTime);
                $endCarbon = Carbon::parse($endTime);
                if ($endCarbon->lte($startCarbon)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Start time must be earlier than end time.',
                    ], 422);
                }
                $timeFormatted = "Remote Work: {$startCarbon->format('h:i A')} to {$endCarbon->format('h:i A')}";
                $notes = $customNotes ? "{$timeFormatted} ({$customNotes})" : $timeFormatted;
            } catch (\Throwable $e) {
                $notes = $customNotes ?: "Remote Work: Full Day";
            }
        } elseif ($startTime || $endTime) {
            return response()->json([
                'success' => false,
                'message' => 'Both start time and end time must be provided for custom hours, or leave both empty for full day.',
            ], 422);
        } else {
            $notes = $customNotes ?: "Remote Work: Full Day";
        }

        // --- Conflict checks ---

        // 1. Already marked remote
        if (RemoteWorkLog::where('user_id', $userId)->where('date', $dateStr)->exists()) {
            return response()->json([
                'success'       => false,
                'conflict_type' => 'already_remote',
                'message'       => "{$user->name} is already marked as working remotely on " . Carbon::parse($dateStr)->format('M d, Y') . '.',
            ], 422);
        }

        // 2. Already marked absent — hard block with link
        if (AttendanceRecord::where('user_id', $userId)->where('date', $dateStr)->exists()) {
            return response()->json([
                'success'       => false,
                'conflict_type' => 'already_absent',
                'message'       => "{$user->name} is marked absent on " . Carbon::parse($dateStr)->format('M d, Y') . ". Please delete the absent record first before marking remote.",
                'logs_url'      => route('super-admin.attendance-holidays.absents.logs', ['type' => 'absent', 'user_id' => $userId]),
            ], 422);
        }

        // 3. Day is a company holiday for this user
        $holiday = CompanyHoliday::isHolidayForUser($user, $dateStr);
        if ($holiday) {
            return response()->json([
                'success'       => false,
                'conflict_type' => 'is_holiday',
                'message'       => Carbon::parse($dateStr)->format('M d, Y') . " is a company holiday ({$holiday->name}) for {$user->name}. Cannot mark as remote.",
            ], 422);
        }

        // 4. Employee has approved leave on that day — hard block
        $approvedLeave = LeaveRequest::with('leaveType')
            ->where('user_id', $userId)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->first();

        if ($approvedLeave) {
            $leaveName = $approvedLeave->leaveType?->name ?? 'Leave';
            return response()->json([
                'success'       => false,
                'conflict_type' => 'has_approved_leave',
                'message'       => "{$user->name} has an approved {$leaveName} on " . Carbon::parse($dateStr)->format('M d, Y') . ". Cannot mark as remote.",
            ], 422);
        }

        // 5. Employee has a pending leave on that day — hard block
        $pendingLeave = LeaveRequest::with('leaveType')
            ->where('user_id', $userId)
            ->where(function($q) {
                $q->whereIn('status', [LeaveRequest::STATUS_PENDING, 'approved_by_manager', 'pending_cancellation'])
                  ->orWhere('change_request_status', 'pending');
            })
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->first();

        if ($pendingLeave) {
            $leaveName = $pendingLeave->leaveType?->name ?? 'Leave';
            $leaveFrom = Carbon::parse($pendingLeave->start_date)->format('M d');
            $leaveTo   = Carbon::parse($pendingLeave->end_date)->format('M d, Y');
            return response()->json([
                'success'       => false,
                'conflict_type' => 'has_pending_leave',
                'message'       => "{$user->name} has a pending {$leaveName} request ({$leaveFrom} – {$leaveTo}) awaiting approval for this date. Please approve or reject the leave request before marking remote.",
                'logs_url'      => route('super-admin.leave-requests.index', ['user_id' => $userId, 'status' => 'pending']),
            ], 422);
        }

        // --- No conflicts — save ---
        RemoteWorkLog::create([
            'user_id'   => $userId,
            'date'      => $dateStr,
            'notes'     => $notes,
            'marked_by' => auth()->id(),
        ]);

        // --- Notify employee and their line manager ---
        $formattedDate = Carbon::parse($dateStr)->format('M d, Y');

        $user->notify(new RemoteMarkedNotification(
            employeeName:  $user->name,
            dateFormatted: $formattedDate,
            notes:         $notes,
            isSelf:        true,
            employeeId:    $user->id,
        ));

        $bossChain = app(\App\Services\HierarchyService::class)->getBossChain($user);
        foreach ($bossChain as $boss) {
            $boss->notify(new RemoteMarkedNotification(
                employeeName:  $user->name,
                dateFormatted: $formattedDate,
                notes:         $notes,
                isSelf:        false,
                employeeId:    $user->id,
            ));
        }

        return response()->json([
            'success' => true,
            'message' => "{$user->name} marked working remotely on " . Carbon::parse($dateStr)->format('M d, Y') . '.',
        ]);
    }

    public function destroyRemote(Request $request, RemoteWorkLog $log)
    {
        $recordIds = $request->filled('record_ids') 
            ? explode(',', $request->record_ids) 
            : [$log->id];
            
        $records = RemoteWorkLog::whereIn('id', $recordIds)->orderBy('date', 'asc')->get();
        if ($records->isEmpty()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'No remote work records found to delete.'], 404);
            }
            return back()->with('error', 'No remote work records found to delete.');
        }

        $user = $records->first()->user;
        $totalDays = count($records);

        RemoteWorkLog::whereIn('id', $records->pluck('id'))->delete();

        $startFormatted = $records->first()->date->format('M d, Y');
        $endFormatted   = $records->last()->date->format('M d, Y');
        $notifRange     = $startFormatted === $endFormatted
            ? $startFormatted
            : $records->first()->date->format('M d') . ' – ' . $endFormatted;

        // Notify employee
        $user->notify(new RemoteDeletedNotification(
            employeeName:  $user->name,
            dateFormatted: $notifRange,
            isSelf:        true,
            employeeId:    $user->id,
        ));

        // Notify manager
        $bossChain = app(\App\Services\HierarchyService::class)->getBossChain($user);
        foreach ($bossChain as $boss) {
            $boss->notify(new RemoteDeletedNotification(
                employeeName:  $user->name,
                dateFormatted: $notifRange,
                isSelf:        false,
                employeeId:    $user->id,
            ));
        }

        $dateText = $totalDays === 1 
            ? "on " . $records->first()->date->format('Y-m-d')
            : "from " . $records->first()->date->format('Y-m-d') . " to " . $records->last()->date->format('Y-m-d');

        $message = "Removed remote work records for {$user->name} {$dateText} (total {$totalDays} days).";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    public function destroyLeave(Request $request, LeaveRequest $leaveRequest)
    {
        $user = $leaveRequest->user;
        $totalDays = $leaveRequest->days_count;
        $leaveType = $leaveRequest->leaveType;
        $startFormatted = $leaveRequest->start_date->format('M d, Y');
        $endFormatted   = $leaveRequest->end_date->format('M d, Y');
        $notifRange     = $startFormatted === $endFormatted
            ? $startFormatted
            : $leaveRequest->start_date->format('M d') . ' – ' . $endFormatted;

        $dateText = ($leaveRequest->start_date->format('Y-m-d') === $leaveRequest->end_date->format('Y-m-d'))
            ? "on " . $leaveRequest->start_date->format('Y-m-d')
            : "from " . $leaveRequest->start_date->format('Y-m-d') . " to " . $leaveRequest->end_date->format('Y-m-d');

        DB::transaction(function() use ($leaveRequest) {
            $year = (int) $leaveRequest->start_date->year;
            $balance = app(LeaveBalanceService::class)->ensureBalanceRow($leaveRequest->user, $leaveRequest->leaveType, $year);

            if ($leaveRequest->status === LeaveRequest::STATUS_APPROVED) {
                $balance->update(['used' => max(0.0, $balance->used - $leaveRequest->days_count)]);
            } else if (in_array($leaveRequest->status, [LeaveRequest::STATUS_PENDING, 'approved_by_manager'])) {
                $balance->update(['pending' => max(0.0, $balance->pending - $leaveRequest->days_count)]);
            }

            $leaveRequest->delete();
        });

        // Notify employee
        $user->notify(new LeaveDeletedNotification(
            employeeName:       $user->name,
            leaveTypeName:      $leaveType->name,
            dateRangeFormatted: $notifRange,
            isSelf:             true,
            employeeId:         $user->id,
        ));

        // Notify manager
        $bossChain = app(\App\Services\HierarchyService::class)->getBossChain($user);
        foreach ($bossChain as $boss) {
            $boss->notify(new LeaveDeletedNotification(
                employeeName:       $user->name,
                leaveTypeName:      $leaveType->name,
                dateRangeFormatted: $notifRange,
                isSelf:             false,
                employeeId:         $user->id,
            ));
        }

        $message = "Removed marked leave record for {$user->name} {$dateText} (total {$totalDays} days).";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Update a Super Admin-marked leave (date, reason, leave type).
     * Replaces the old changeLeaveType() — now supports full-field edits.
     * If a pending employee change request exists, it is cleared since HR has directly resolved it.
     */
    public function updateAdminMarkedLeave(Request $request, LeaveRequest $leaveRequest)
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'nullable|string|max:500',
        ]);

        $newType     = LeaveType::findOrFail($validated['leave_type_id']);
        $oldType     = $leaveRequest->leaveType;
        $user        = $leaveRequest->user;
        $oldStart    = $leaveRequest->start_date->format('Y-m-d');
        $oldEnd      = $leaveRequest->end_date->format('Y-m-d');
        $oldDays     = (float) $leaveRequest->days_count;
        $oldYear     = (int) $leaveRequest->start_date->year;

        $newStart    = $validated['start_date'];
        $newEnd      = $validated['end_date'];
        $newReason   = $validated['reason'] ?? $leaveRequest->reason;

        // Calculate new business days
        $newDays = 0;
        $cur = Carbon::parse($newStart)->copy();
        $endCarbon = Carbon::parse($newEnd);
        while ($cur->lte($endCarbon)) {
            if (!$cur->isWeekend()) {
                $newDays++;
            }
            $cur->addDay();
        }

        if ($newDays === 0) {
            return back()->with('error', 'The selected date range contains no working days.');
        }

        $noChange = ($newType->id === $oldType->id)
            && ($newStart === $oldStart)
            && ($newEnd === $oldEnd)
            && (trim($newReason) === trim($leaveRequest->reason ?? ''));

        if ($noChange) {
            return back()->with('error', 'No changes were made — all values are identical.');
        }

        $balanceService = app(LeaveBalanceService::class);
        $newYear = (int) Carbon::parse($newStart)->year;

        try {
            DB::transaction(function () use (
                $leaveRequest, $user, $newType, $oldType,
                $newStart, $newEnd, $newDays, $newReason, $newYear,
                $oldDays, $oldYear, $balanceService
            ) {
                // 1. Verify balance for new type (skip unpaid/emergency)
                $newBalance = $balanceService->ensureBalanceRow($user, $newType, $newYear);
                if ($newType->slug !== 'unpaid' && $newType->slug !== 'emergency') {
                    $available = $balanceService->availableDays($newBalance);
                    // We'll be restoring oldDays from old type, so effective available = current + oldDays (if same type)
                    $effectiveAvailable = ($newType->id === $oldType->id && $newYear === $oldYear)
                        ? $available + $oldDays
                        : $available;
                    if ($effectiveAvailable < $newDays) {
                        throw new \Exception("Insufficient {$newType->name} balance. Available: {$effectiveAvailable} day(s), Needed: {$newDays} day(s).");
                    }
                }

                // 2. Restore old balance
                $oldBalance = $balanceService->ensureBalanceRow($user, $oldType, $oldYear);
                $oldBalance->update(['used' => max(0.0, $oldBalance->used - $oldDays)]);

                // 3. Deduct new balance
                $newBalance->fresh()->increment('used', (float) $newDays);

                // 4. Update leave request
                $updateData = [
                    'leave_type_id' => $newType->id,
                    'start_date'    => $newStart,
                    'end_date'      => $newEnd,
                    'days_count'    => (float) $newDays,
                    'reason'        => $newReason,
                    'admin_comment' => 'Directly approved by ' . (auth()->user()->name ?? 'Admin') . ' — bypasses standard approval workflow.',
                ];

                // 5. Clear any orphan pending change request (employee had pending change, HR fixed it directly)
                if ($leaveRequest->change_request_status === 'pending'
                    && empty($leaveRequest->change_request['request_cancellation'])) {
                    $updateData['change_request']        = null;
                    $updateData['change_request_status'] = null;
                }

                $leaveRequest->update($updateData);
            });
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update leave: ' . $e->getMessage());
        }

        // --- Notify employee and their line managers ---
        $startFormatted = Carbon::parse($newStart)->format('M d, Y');
        $endFormatted   = Carbon::parse($newEnd)->format('M d, Y');

        $user->notify(new LeaveTypeChangedNotification(
            employeeName:     $user->name,
            oldLeaveTypeName: $oldType->name,
            newLeaveTypeName: $newType->name,
            startFormatted:   $startFormatted,
            endFormatted:     $endFormatted,
            daysCount:        $newDays,
            isSelf:           true,
            employeeId:       $user->id,
        ));

        $bossChain = app(\App\Services\HierarchyService::class)->getBossChain($user);
        foreach ($bossChain as $boss) {
            $boss->notify(new LeaveTypeChangedNotification(
                employeeName:     $user->name,
                oldLeaveTypeName: $oldType->name,
                newLeaveTypeName: $newType->name,
                startFormatted:   $startFormatted,
                endFormatted:     $endFormatted,
                daysCount:        $newDays,
                isSelf:           false,
                employeeId:       $user->id,
            ));
        }

        return back()->with('success', "Successfully updated leave for {$user->name}.");
    }
}
