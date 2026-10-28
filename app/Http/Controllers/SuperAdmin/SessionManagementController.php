<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SlackMessage;
use App\Models\SlackUserMapping;
use App\Models\User;
use App\Models\LeaveRequest;
use App\Models\AttendanceRecord;
use App\Models\CompanyHoliday;
use App\Models\RemoteWorkLog;
use App\Notifications\SessionOverrideDecisionNotification;
use App\Notifications\TrackRecordFixesNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SessionManagementController extends Controller
{
    public function index()
    {
        return redirect()->route('super-admin.session-management.overrides');
    }

    private function buildSlackMessagesQuery(Request $request)
    {
        $query = SlackMessage::with(['edits', 'mapping.user']);

        // Base conditions
        $query->where(function ($q) {
            $q->where('is_auto_appended', false)
              ->orWhere(function ($sub) {
                  $sub->where('is_auto_appended', true)
                      ->whereIn('message_text', ['signout', 'sign out']);
              });
        })
        ->where(function ($q) {
            $q->whereNull('approval_status')
              ->orWhere('approval_status', '!=', 'merged');
        });

        // 1. Employee Filter
        if ($request->filled('employee')) {
            $emp = $request->input('employee');
            if (is_numeric($emp)) {
                $slackUserIds = SlackUserMapping::where('user_id', $emp)->pluck('slack_user_id')->toArray();
                $query->whereIn('slack_user_id', $slackUserIds ?: ['__none__']);
            } else {
                $slackUserIds = SlackUserMapping::where('slack_user_id', $emp)
                    ->orWhere('slack_username', $emp)
                    ->orWhereHas('user', function ($u) use ($emp) {
                        $u->where('name', $emp);
                    })
                    ->pluck('slack_user_id')
                    ->toArray();

                if (!empty($slackUserIds)) {
                    $query->whereIn('slack_user_id', array_merge([$emp], $slackUserIds));
                } else {
                    $query->where('slack_user_id', $emp);
                }
            }
        }

        // 2. Approval Required Filter
        if ($request->boolean('approval_required')) {
            $query->whereIn('approval_status', ['pending', 'pending_edit']);
        }

        // 3. Date Filters
        $tz = 'Asia/Karachi';
        $period = $request->input('period', 'all');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if ($startDate || $endDate) {
            if ($startDate) {
                $startUtc = Carbon::parse($startDate, $tz)->startOfDay()->timezone('UTC');
                $query->where('message_at', '>=', $startUtc);
            }
            if ($endDate) {
                $endUtc = Carbon::parse($endDate, $tz)->endOfDay()->timezone('UTC');
                $query->where('message_at', '<=', $endUtc);
            }
        } elseif ($period && $period !== 'all') {
            $nowLocal = Carbon::now($tz);
            if ($period === 'today') {
                $query->where('message_at', '>=', $nowLocal->copy()->startOfDay()->timezone('UTC'));
            } elseif ($period === 'yesterday') {
                $yStart = $nowLocal->copy()->subDay()->startOfDay()->timezone('UTC');
                $yEnd = $nowLocal->copy()->subDay()->endOfDay()->timezone('UTC');
                $query->whereBetween('message_at', [$yStart, $yEnd]);
            } elseif ($period === 'last_7') {
                $query->where('message_at', '>=', $nowLocal->copy()->subDays(7)->startOfDay()->timezone('UTC'));
            } elseif ($period === 'last_30') {
                $query->where('message_at', '>=', $nowLocal->copy()->subDays(30)->startOfDay()->timezone('UTC'));
            }
        } else {
            // Default window
            $query->where('message_at', '>=', now()->subDays(90));
        }

        return $query;
    }

    private function formatSlackMessage(SlackMessage $msg): array
    {
        $displayName = $msg->slack_user_id;
        if ($msg->mapping) {
            if ($msg->mapping->slack_username) {
                $displayName = $msg->mapping->slack_username;
            } elseif ($msg->mapping->user) {
                $displayName = $msg->mapping->user->name;
            }
        }

        return [
            'id' => $msg->id,
            'message_text' => $msg->approval_status === 'pending_edit' ? e($msg->pending_message_text) : e($msg->message_text),
            'slack_user_id' => e($msg->slack_user_id),
            'display_name' => e($displayName),
            'channel_id' => e($msg->channel_id),
            'formatted_time' => $msg->message_at->timezone('Asia/Karachi')->format('M d, Y h:i A'),
            'formatted_original_time' => ($msg->original_message_at ?? $msg->message_at)->timezone('Asia/Karachi')->format('M d, Y h:i A'),
            'formatted_date' => $msg->message_at->timezone('Asia/Karachi')->format('F d, Y'),
            'is_edited' => (bool) $msg->is_edited,
            'approval_status' => $msg->approval_status,
            'break_exceeded' => (bool) $msg->break_exceeded,
            'is_auto_appended' => (bool) $msg->is_auto_appended,
            'pending_message_text' => $msg->pending_message_text ? e($msg->pending_message_text) : null,
            'user_id' => $msg->mapping ? $msg->mapping->user_id : null,
            'month' => $msg->message_at->timezone('Asia/Karachi')->month,
            'year' => $msg->message_at->timezone('Asia/Karachi')->year,
            'message_at_raw' => $msg->message_at->timezone('Asia/Karachi')->toIso8601String(),
            'edits' => $msg->edits->map(function ($edit) {
                return [
                    'id' => $edit->id,
                    'previous_text' => e($edit->previous_text),
                    'new_text' => e($edit->new_text),
                    'formatted_edited_time' => $edit->edited_at->timezone('Asia/Karachi')->format('M d, Y h:i A'),
                ];
            }),
        ];
    }

    public function overrides(Request $request): View
    {
        // Build employee filter list for instant server-rendered dropdown
        $employeesList = collect();
        
        $users = User::whereHas('slackMapping')->orderBy('name')->get();
        foreach ($users as $user) {
            $employeesList->push([
                'value' => $user->name,
                'label' => $user->name,
            ]);
        }

        $mappings = SlackUserMapping::whereNull('user_id')->get();
        foreach ($mappings as $m) {
            $name = $m->slack_username ?: $m->slack_user_id;
            if (!$employeesList->contains('value', $name)) {
                $employeesList->push([
                    'value' => $name,
                    'label' => $name . ' (Slack)',
                ]);
            }
        }

        $employees = $employeesList->sortBy('label')->values();

        $limit = 30;
        $query = $this->buildSlackMessagesQuery($request);

        $rawMessages = $query->orderBy('message_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rawMessages->count() > $limit;
        if ($hasMore) {
            $rawMessages->pop();
        }

        $initialMessages = $rawMessages->map(fn($msg) => $this->formatSlackMessage($msg));
        $nextCursor = $rawMessages->isNotEmpty() ? $rawMessages->last()->id : null;
        $maxId = $rawMessages->isNotEmpty() ? $rawMessages->max('id') : 0;

        $hasMappedNames = $rawMessages->contains(function ($msg) {
            return $msg->mapping && ($msg->mapping->user || $msg->mapping->slack_username);
        });

        return view('super-admin.session-management.overrides', compact(
            'initialMessages',
            'hasMappedNames',
            'employees',
            'nextCursor',
            'hasMore',
            'maxId'
        ));
    }

    public function mappings(): View
    {
        // 1. Get the latest message details for each unique slack_user_id that has posted
        $latestMessages = SlackMessage::selectRaw('slack_user_id, message_text, message_at')
            ->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('slack_messages')
                    ->groupBy('slack_user_id');
            })
            ->get()
            ->keyBy('slack_user_id');

        // 2. Get all currently recorded mappings
        $mappings = SlackUserMapping::with('user')->get()->keyBy('slack_user_id');

        // 3. Union the unique Slack IDs (either found in message log or already mapped)
        $slackUserIds = $latestMessages->keys()->merge($mappings->keys())->unique();

        // 4. Build custom data collection for the mapping list
        $userMappings = $slackUserIds->map(function ($slackId) use ($latestMessages, $mappings) {
            $mapping = $mappings->get($slackId);
            $lastMsg = $latestMessages->get($slackId);

            return [
                'slack_user_id' => $slackId,
                'slack_username' => $mapping ? $mapping->slack_username : '',
                'user_id' => $mapping ? $mapping->user_id : null,
                'user' => $mapping ? $mapping->user : null,
                'last_message_text' => $lastMsg ? $lastMsg->message_text : null,
                'last_message_at' => $lastMsg ? $lastMsg->message_at : null,
            ];
        });

        // 5. Get all registered HRMS users to choose from
        $users = User::orderBy('name')->get();

        $autoConnectEnabled = true;
        $settingFile = storage_path('app/slack_auto_connect_setting.json');
        if (file_exists($settingFile)) {
            $settingData = json_decode(file_get_contents($settingFile), true);
            $autoConnectEnabled = isset($settingData['enabled']) ? (bool) $settingData['enabled'] : true;
        }

        return view('super-admin.session-management.mappings', compact('userMappings', 'users', 'autoConnectEnabled'));
    }

    public function saveMapping(Request $request): JsonResponse
    {
        $request->validate([
            'slack_user_id' => 'required|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'slack_username' => 'nullable|string|max:255',
        ]);

        $mapping = SlackUserMapping::updateOrCreate(
            ['slack_user_id' => $request->slack_user_id],
            [
                'user_id' => $request->user_id,
                'slack_username' => $request->slack_username,
            ]
        );

        $mapping->load('user');

        return response()->json([
            'status' => 'success',
            'message' => 'User mapping saved successfully!',
            'mapping' => [
                'slack_user_id' => $mapping->slack_user_id,
                'slack_username' => $mapping->slack_username,
                'user_id' => $mapping->user_id,
                'user_name' => $mapping->user ? $mapping->user->name : null,
                'user_email' => $mapping->user ? $mapping->user->email : null,
            ]
        ]);
    }

    public function toggleAutoConnect(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $enabled = (bool) $request->enabled;
        $settingFile = storage_path('app/slack_auto_connect_setting.json');
        
        $dir = dirname($settingFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($settingFile, json_encode(['enabled' => $enabled]));

        return response()->json([
            'status' => 'success',
            'enabled' => $enabled,
            'message' => $enabled ? 'Auto-connect function turned on.' : 'Auto-connect function turned off.'
        ]);
    }

    public function trackRecord(): View
    {
        $users = User::whereHas('slackMapping')->orderBy('name')->get();
        return view('super-admin.session-management.track-record', compact('users'));
    }

    public function getTrackRecordApi(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required',
            'period' => 'required|in:today,month,custom',
            'start_date' => 'required_if:period,custom|nullable|date',
            'end_date' => 'required_if:period,custom|nullable|date',
        ]);

        $userId = $request->user_id;
        $period = $request->period;

        if ($userId === 'all') {
            $tz = 'Asia/Karachi';
            $today = Carbon::now($tz);
            $startDate = $today->copy()->startOfDay();
            $endDate = $today->copy()->endOfDay();

            $currentUser = auth()->user();
            if ($currentUser->isSuperAdmin()) {
                $users = User::whereHas('slackMapping')->orderBy('name')->get();
            } else {
                $subordinateIds = $currentUser->subordinate_ids;
                $users = User::whereIn('id', $subordinateIds)->whereHas('slackMapping')->orderBy('name')->get();
            }

            $flatRecords = [];
            $maxSessions = 1;

            $todayDateStr = $today->format('Y-m-d');
            $todayHolidays = CompanyHoliday::with(['departments', 'users'])->where('date', $todayDateStr)->get();
            $todayAbsents = AttendanceRecord::where('date', $todayDateStr)->pluck('user_id')->toArray();
            $todayRemotes = RemoteWorkLog::where('date', $todayDateStr)->get()->keyBy('user_id');

            foreach ($users as $emp) {
                $mapping = $emp->slackMapping;
                if (!$mapping) {
                    continue;
                }

                $queryStartDate = $startDate->copy()->subDays(2)->timezone('UTC');
                $queryEndDate = $endDate->copy()->timezone('UTC');

                $empLeaves = LeaveRequest::with('leaveType')->where('user_id', $emp->id)
                    ->where('status', LeaveRequest::STATUS_APPROVED)
                    ->where('start_date', '<=', $endDate)
                    ->where('end_date', '>=', $startDate)
                    ->get();
                
                $isTodayLeave = false;
                $isTodayHalfDayLeave = false;
                foreach ($empLeaves as $leave) {
                    if ($leave->start_date->format('Y-m-d') <= $todayDateStr && $leave->end_date->format('Y-m-d') >= $todayDateStr) {
                        $isTodayLeave = true;
                        if ($leave->leaveType && $leave->leaveType->is_half_day) {
                            $isTodayHalfDayLeave = true;
                        }
                        break;
                    }
                }

                $isTodayAbsent = in_array($emp->id, $todayAbsents);
                $isTodayHoliday = $todayHolidays->contains(fn($h) => $h->appliesToUser($emp));
                $empJoinDate = $emp->join_date ? $emp->join_date->copy()->startOfDay() : null;
                $isTodayBeforeJoin = $empJoinDate && $today->copy()->startOfDay()->lt($empJoinDate);
                
                $todayRemoteLog = $todayRemotes->get($emp->id);
                $isTodayRemote = !is_null($todayRemoteLog);
                $todayRemoteNotes = $todayRemoteLog ? $todayRemoteLog->notes : null;

                $dayMessages = SlackMessage::where('slack_user_id', $mapping->slack_user_id)
                    ->whereBetween('message_at', [$queryStartDate, $queryEndDate])
                    ->orderBy('message_at', 'asc')
                    ->get();

                $sessionsByDate = $this->reconstructSessions($dayMessages, $startDate, $endDate, $tz);
                $todaySessions = $sessionsByDate[$today->format('Y-m-d')] ?? [];

                $dailySeconds = 0;
                $formattedSessions = [];
                
                foreach ($todaySessions as $session) {
                    $inStr = $session['in']->format('h:i:s A');
                    $outStr = '';
                    
                    if ($session['out']) {
                        $outStr = $session['out']->format('h:i:s A');
                        if ($session['is_active']) {
                            $outStr .= ' (Active)';
                        }
                        $dailySeconds += ($session['out']->timestamp - $session['in']->timestamp);
                    } else {
                        $outStr = 'Incomplete';
                    }

                    $formattedSessions[] = [
                        'in' => $inStr,
                        'out' => $outStr,
                        'is_virtual_in' => $session['is_virtual_in'] ?? false,
                        'virtual_in_reason' => $session['virtual_in_reason'] ?? '',
                        'is_virtual_out' => $session['is_virtual_out'] ?? false,
                        'virtual_out_reason' => $session['virtual_out_reason'] ?? '',
                    ];
                }

                $expectedHoursPerDay = $emp->expected_hour_per_day ?? 8.0;
                if ($isTodayAbsent || $isTodayHoliday || $isTodayBeforeJoin) {
                    $dailyExpectedSeconds = 0;
                } elseif ($isTodayLeave) {
                    $dailyExpectedSeconds = $isTodayHalfDayLeave ? (int)round(($expectedHoursPerDay * 0.5) * 3600) : 0;
                } else {
                    $dailyExpectedSeconds = (int)round($expectedHoursPerDay * 3600);
                }
                $pct = 0.0;
                if ($dailyExpectedSeconds > 0) {
                    $pct = $dailySeconds / $dailyExpectedSeconds;
                }

                $maxSessions = max($maxSessions, count($formattedSessions));

                $flatRecords[] = [
                    'employee_name' => $emp->name,
                    'date' => $today->format('M d, Y'),
                    'day_name' => $today->format('l'),
                    'weekly_summary_label' => '',
                    'weekly_summary_value' => '',
                    'pct' => $pct,
                    'pct_formatted' => sprintf("%.2f%%", $pct * 100),
                    'total_seconds' => $dailySeconds,
                    'total_formatted' => $dailySeconds > 0 ? $this->formatSecondsToTime($dailySeconds) : "00:00:00",
                    'expected_seconds' => $dailyExpectedSeconds,
                    'expected_formatted' => $this->formatSecondsToTime($dailyExpectedSeconds),
                    'sessions' => $formattedSessions,
                    'is_filler' => false,
                    'is_before_join' => $isTodayBeforeJoin,
                    'day_of_week' => $today->dayOfWeekIso,
                    'is_weekend' => ($today->dayOfWeekIso === 6 || $today->dayOfWeekIso === 7),
                    'weekly_summary_diff_seconds' => 0,
                    'raw_date_string' => $today->format('Y-m-d'),
                    'is_leave' => $isTodayLeave,
                    'is_half_day_leave' => $isTodayHalfDayLeave,
                    'is_absent' => $isTodayAbsent,
                    'is_holiday' => $isTodayHoliday,
                    'is_remote' => $isTodayRemote,
                    'remote_notes' => $todayRemoteNotes,
                ];
            }

            return response()->json([
                'records' => $flatRecords,
                'max_sessions' => $maxSessions,
                'summary' => [
                    'total_session_seconds' => 0,
                    'total_session_formatted' => '00:00:00',
                    'total_expected_seconds' => 0,
                    'total_expected_formatted' => '00:00:00',
                    'total_difference_seconds' => 0,
                    'total_difference_formatted' => '00:00:00',
                    'total_leaves' => 0,
                    'total_absents' => 0,
                    'total_unpaid_leaves' => 0,
                    'total_holidays' => 0,
                    'total_remotes' => 0,
                    'expected_salary' => '0',
                    'deductions' => '0',
                    'take_away' => '0',
                    'show_summary' => false
                ]
            ]);
        }

        $request->validate([
            'user_id' => 'exists:users,id',
        ]);

        $user = User::find($userId);

        // Find the Slack user mapping
        $mapping = SlackUserMapping::where('user_id', $userId)->first();
        if (!$mapping) {
            return response()->json([
                'records' => [],
                'max_sessions' => 0,
                'summary' => [
                    'total_session_seconds' => 0,
                    'total_session_formatted' => '00:00:00',
                    'total_expected_seconds' => 0,
                    'total_expected_formatted' => '00:00:00',
                    'total_difference_seconds' => 0,
                    'total_difference_formatted' => '00:00:00',
                    'total_leaves' => 0,
                    'total_absents' => 0,
                    'expected_salary' => '0',
                    'deductions' => '0',
                    'take_away' => '0',
                    'show_summary' => $period !== 'today',
                    'has_expected_salary' => !is_null($user->expected_salary_amount),
                    'has_slack_mapping' => false,
                ]
            ]);
        }

        // Hardcode local timezone for Pakistan / Islamabad
        $tz = 'Asia/Karachi';
        $today = Carbon::now($tz);

        if ($period === 'today') {
            $startDate = $today->copy()->startOfDay();
            $endDate = $today->copy()->endOfDay();
        } elseif ($period === 'month') {
            $startDate = $today->copy()->startOfMonth();
            $endDate = $today->copy()->endOfMonth();
        } else {
            $startDate = Carbon::parse($request->start_date, $tz)->startOfDay();
            $endDate = Carbon::parse($request->end_date, $tz)->endOfDay();
        }

        $leaves = LeaveRequest::with('leaveType')
            ->where('user_id', $userId)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->get();

        $absentsList = AttendanceRecord::where('user_id', $userId)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy(fn($r) => $r->date->format('Y-m-d'));

        $holidaysList = CompanyHoliday::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with(['departments', 'users'])
            ->get()
            ->filter(fn($h) => $h->appliesToUser($user))
            ->keyBy(fn($h) => $h->date->format('Y-m-d'));

        $remotesList = RemoteWorkLog::where('user_id', $userId)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy(fn($r) => $r->date->format('Y-m-d'));

        // Generate full chronological dates in the range
        $periodDates = [];
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $periodDates[$current->format('Y-m-d')] = [
                'date_formatted' => $current->format('M d, Y'),
                'day_name' => $current->format('l'),
                'raw_date' => $current->copy(),
                'messages' => [],
            ];
            $current->addDay();
        }

        $queryStartDate = $startDate->copy()->subDays(2)->timezone('UTC');
        $queryEndDate = $endDate->copy()->addDay()->timezone('UTC');

        // Lazy-heal any dates in the queried range (plus the day before the start date and day after the end date for overnight boundary crossing) that are before today and have subsequent check-ins, but are not yet cached as healed.
        $checkDate = $startDate->copy()->subDay();
        while ($checkDate->lte($endDate->copy()->addDay())) {
            $dateStr = $checkDate->format('Y-m-d');
            if ($checkDate->lt($today->copy()->startOfDay())) {
                $cacheKey = "healed_{$mapping->slack_user_id}_{$dateStr}";
                if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                    SlackMessage::ensureDayAutoAppends($mapping->slack_user_id, $dateStr, false);
                }
            }
            $checkDate->addDay();
        }

        // Query all messages for the mapped slack user ID
        $messages = SlackMessage::where('slack_user_id', $mapping->slack_user_id)
            ->whereBetween('message_at', [$queryStartDate, $queryEndDate])
            ->orderBy('message_at', 'asc')
            ->get();

        $sessionsByDate = $this->reconstructSessions($messages, $startDate, $endDate, $tz);

        $records = [];
        foreach ($periodDates as $dateString => $dayData) {
            $rawDate = $dayData['raw_date'];
            $daySessions = $sessionsByDate[$dateString] ?? [];

            $dailySeconds = 0;
            $formattedSessions = [];

            foreach ($daySessions as $session) {
                $inStr = $session['in']->format('h:i:s A');
                $outStr = '';

                if ($session['out']) {
                    $outStr = $session['out']->format('h:i:s A');
                    if ($session['is_active']) {
                        $outStr .= ' (Active)';
                    }
                    $dailySeconds += ($session['out']->timestamp - $session['in']->timestamp);
                } else {
                    $outStr = 'Incomplete';
                }

                $formattedSessions[] = [
                    'in' => $inStr,
                    'out' => $outStr,
                    'is_virtual_in' => $session['is_virtual_in'] ?? false,
                    'virtual_in_reason' => $session['virtual_in_reason'] ?? '',
                    'is_virtual_out' => $session['is_virtual_out'] ?? false,
                    'virtual_out_reason' => $session['virtual_out_reason'] ?? '',
                ];
            }

            $isLeave = false;
            $isUnpaidLeave = false;
            $isHalfDayLeave = false;
            foreach ($leaves as $leave) {
                if ($leave->start_date->format('Y-m-d') <= $dateString && $leave->end_date->format('Y-m-d') >= $dateString) {
                    if ($leave->leaveType && $leave->leaveType->slug === 'unpaid') {
                        $isUnpaidLeave = true;
                    } else {
                        $isLeave = true;
                    }
                    if ($leave->leaveType && $leave->leaveType->is_half_day) {
                        $isHalfDayLeave = true;
                    }
                    break;
                }
            }

            $isAbsent = isset($absentsList[$dateString]);
            $isHoliday = isset($holidaysList[$dateString]);
            $isBeforeJoin = $user->join_date && $dateString < $user->join_date->format('Y-m-d');
            
            $remoteLog = $remotesList[$dateString] ?? null;
            $isRemote = !is_null($remoteLog);
            $remoteNotes = $remoteLog ? $remoteLog->notes : null;

            $records[$dateString] = [
                'date' => $dayData['date_formatted'],
                'day_name' => $dayData['day_name'],
                'raw_date' => $rawDate,
                'sessions' => $formattedSessions,
                'total_seconds' => $dailySeconds,
                'is_leave' => $isLeave,
                'is_unpaid_leave' => $isUnpaidLeave,
                'is_half_day_leave' => $isHalfDayLeave,
                'is_absent' => $isAbsent,
                'is_holiday' => $isHoliday,
                'is_before_join' => $isBeforeJoin,
                'is_remote' => $isRemote,
                'remote_notes' => $remoteNotes,
            ];
        }

        if ($period === 'today') {
            $isTodayLeave = false;
            $isTodayUnpaidLeave = false;
            $isTodayHalfDayLeave = false;
            $todayDateStr = $today->format('Y-m-d');
            foreach ($leaves as $leave) {
                if ($leave->start_date->format('Y-m-d') <= $todayDateStr && $leave->end_date->format('Y-m-d') >= $todayDateStr) {
                    if ($leave->leaveType && $leave->leaveType->slug === 'unpaid') {
                        $isTodayUnpaidLeave = true;
                    } else {
                        $isTodayLeave = true;
                    }
                    if ($leave->leaveType && $leave->leaveType->is_half_day) {
                        $isTodayHalfDayLeave = true;
                    }
                    break;
                }
            }

            $isTodayAbsent = isset($absentsList[$todayDateStr]);
            $isTodayHoliday = isset($holidaysList[$todayDateStr]);
            $isTodayBeforeJoin = $user->join_date && $today->copy()->startOfDay()->lt($user->join_date->copy()->startOfDay());

            $expectedHoursPerDay = $user->expected_hour_per_day ?? 8.0;
            $dailyExpectedSeconds = 0;
            if ($today->dayOfWeekIso >= 1 && $today->dayOfWeekIso <= 5 && !$isTodayAbsent && !$isTodayHoliday && !$isTodayBeforeJoin) {
                if (!$isTodayLeave && !$isTodayUnpaidLeave) {
                    $dailyExpectedSeconds = (int)round($expectedHoursPerDay * 3600);
                } elseif ($isTodayHalfDayLeave) {
                    $dailyExpectedSeconds = (int)round(($expectedHoursPerDay * 0.5) * 3600);
                }
            }
            
            $todayKey = $today->format('Y-m-d');
            $todayRecord = $records[$todayKey] ?? null;
            
            if (!$todayRecord) {
                $todayRecord = [
                    'date' => $today->format('M d, Y'),
                    'day_name' => $today->format('l'),
                    'raw_date' => $today,
                    'sessions' => [],
                    'total_seconds' => 0,
                ];
            }

            $dailySeconds = $todayRecord['total_seconds'];
            $pct = 0.0;
            if ($dailyExpectedSeconds > 0) {
                $pct = $dailySeconds / $dailyExpectedSeconds;
            }

            $flatRecords = [
                [
                    'date' => $todayRecord['date'],
                    'day_name' => $todayRecord['day_name'],
                    'weekly_summary_label' => '',
                    'weekly_summary_value' => '',
                    'pct' => $pct,
                    'pct_formatted' => sprintf("%.2f%%", $pct * 100),
                    'total_seconds' => $dailySeconds,
                    'total_formatted' => $dailySeconds > 0 ? $this->formatSecondsToTime($dailySeconds) : "00:00:00",
                    'expected_seconds' => $dailyExpectedSeconds,
                    'expected_formatted' => $this->formatSecondsToTime($dailyExpectedSeconds),
                    'sessions' => $todayRecord['sessions'],
                    'is_filler' => false,
                    'is_before_join' => $isTodayBeforeJoin,
                    'day_of_week' => $today->dayOfWeekIso,
                    'is_weekend' => ($today->dayOfWeekIso === 6 || $today->dayOfWeekIso === 7),
                    'weekly_summary_diff_seconds' => 0,
                    'raw_date_string' => $today->format('Y-m-d'),
                    'is_leave' => $isTodayLeave,
                    'is_unpaid_leave' => $isTodayUnpaidLeave,
                    'is_half_day_leave' => $isTodayHalfDayLeave,
                    'is_absent' => $isTodayAbsent,
                    'is_holiday' => $isTodayHoliday,
                    'is_remote' => $todayRecord['is_remote'] ?? false,
                    'remote_notes' => $todayRecord['remote_notes'] ?? null,
                ]
            ];

            $canViewFinancials = auth()->user() && (auth()->user()->isSuperAdmin() || (int)auth()->id() === (int)$user->id);

            $breakBalanceData = \App\Models\SlackMessage::computeDayBreakBalance($mapping->slack_user_id, $todayKey);

            return response()->json([
                'records' => $flatRecords,
                'max_sessions' => max(1, count($todayRecord['sessions'])),
                'summary' => [
                    'total_session_seconds' => $dailySeconds,
                    'total_session_formatted' => $this->formatSecondsToTime($dailySeconds),
                    'total_expected_seconds' => $dailyExpectedSeconds,
                    'total_expected_formatted' => $this->formatSecondsToTime($dailyExpectedSeconds),
                    'total_difference_seconds' => $dailySeconds - $dailyExpectedSeconds,
                    'total_difference_formatted' => $this->formatSecondsToTime($dailySeconds - $dailyExpectedSeconds),
                    'total_leaves' => 0,
                    'total_absents' => $isTodayAbsent ? 1 : 0,
                    'total_unpaid_leaves' => 0,
                    'total_holidays' => $isTodayHoliday ? 1 : 0,
                    'total_remotes' => ($todayRecord['is_remote'] ?? false) ? self::getRemoteDayValue($todayRecord['remote_notes'] ?? null, (float)$expectedHoursPerDay) : 0,
                    'expected_salary' => $canViewFinancials ? ($user->expected_salary_amount ? number_format($user->expected_salary_amount) : '—') : 'Hidden',
                    'deductions' => $canViewFinancials ? '-' : 'Hidden',
                    'take_away' => $canViewFinancials ? '-' : 'Hidden',
                    'show_summary' => false,
                    'has_expected_salary' => $canViewFinancials && !is_null($user->expected_salary_amount),
                    'is_financial_hidden' => !$canViewFinancials,
                    'has_slack_mapping' => true,
                    'edit_url' => route('super-admin.users.edit', $user->id),
                    'break_balance_total_seconds'    => \App\Models\SlackMessage::getBreakBalanceSeconds(),
                    'break_balance_consumed_seconds' => $breakBalanceData['consumed_seconds'] ?? 0,
                    'break_balance_remaining_seconds'=> $breakBalanceData['remaining_seconds'] ?? \App\Models\SlackMessage::getBreakBalanceSeconds(),
                    'break_balance_exceeded_seconds' => $breakBalanceData['exceeded_seconds'] ?? 0,
                ]
            ]);
        }

        $weeks = [];
        foreach ($records as $dateString => $record) {
            $rawDate = $record['raw_date'];
            $weekKey = $rawDate->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
            if (!isset($weeks[$weekKey])) {
                $weeks[$weekKey] = [];
            }
            $weeks[$weekKey][] = array_merge($record, ['is_filler' => false]);
        }

        // Pad the last week up to Sunday
        $lastWeekKey = array_key_last($weeks);
        if ($lastWeekKey) {
            $lastWeek = $weeks[$lastWeekKey];
            $lastRecord = end($lastWeek);
            $lastDay = $lastRecord['raw_date'];
            $lastDayOfWeek = $lastDay->dayOfWeekIso;
            if ($lastDayOfWeek < 7) {
                for ($i = $lastDayOfWeek + 1; $i <= 7; $i++) {
                    $fillerDate = $lastDay->copy()->addDays($i - $lastDayOfWeek);
                    $weeks[$lastWeekKey][] = [
                        'date' => '',
                        'day_name' => $fillerDate->format('l'),
                        'raw_date' => $fillerDate,
                        'sessions' => [],
                        'total_seconds' => 0,
                        'is_filler' => true,
                        'is_leave' => false,
                        'is_unpaid_leave' => false,
                        'is_half_day_leave' => false,
                        'is_absent' => false,
                        'is_holiday' => false,
                        'is_remote' => false,
                    ];
                }
            }
        }

        // Post-process the first week for custom HR start-of-month label alignment
        $firstWeekKey = array_key_first($weeks);
        if ($firstWeekKey) {
            $firstWeekDays = $weeks[$firstWeekKey];
            $firstDay = $firstWeekDays[0];
            $firstDayOfWeek = $firstDay['raw_date']->dayOfWeekIso;

            if ($firstDayOfWeek >= 2 && $firstDayOfWeek <= 5) {
                // Determine if we have leaves or absents in this first week
                $firstWeekLeaves = 0;
                $firstWeekAbsents = 0;
                foreach ($firstWeekDays as $day) {
                    if (!$day['is_filler']) {
                        if ($day['is_leave'] || $day['is_unpaid_leave']) {
                            $firstWeekLeaves++;
                        }
                        if ($day['is_absent']) {
                            $firstWeekAbsents++;
                        }
                    }
                }

                $prependCount = 0;
                if ($firstWeekLeaves > 0) {
                    $prependCount = $firstDayOfWeek - 1;
                } elseif ($firstWeekAbsents > 0) {
                    $prependCount = $firstDayOfWeek - 2;
                }

                if ($prependCount > 0) {
                    $prependedDays = [];
                    for ($d = $firstDayOfWeek - $prependCount; $d < $firstDayOfWeek; $d++) {
                        $fillerDate = $firstDay['raw_date']->copy()->subDays($firstDayOfWeek - $d);
                        $prependedDays[] = [
                            'date' => '',
                            'day_name' => '',
                            'raw_date' => $fillerDate,
                            'sessions' => [],
                            'total_seconds' => 0,
                            'is_filler' => true,
                            'is_leave' => false,
                            'is_unpaid_leave' => false,
                            'is_half_day_leave' => false,
                            'is_absent' => false,
                            'is_holiday' => false,
                            'is_remote' => false,
                        ];
                    }
                    $weeks[$firstWeekKey] = array_merge($prependedDays, $firstWeekDays);
                }
            }
        }

        // Determine if month starts on weekend (Saturday/Sunday)
        $monthStartsOnWeekend = false;
        $firstRecord = reset($records);
        if ($firstRecord) {
            $fOfWeek = $firstRecord['raw_date']->dayOfWeekIso;
            $monthStartsOnWeekend = ($fOfWeek === 6 || $fOfWeek === 7);
        }

        // Find the first Monday in the records
        $firstMondayDate = null;
        foreach ($records as $dateString => $rec) {
            if ($rec['raw_date']->dayOfWeekIso === 1) {
                $firstMondayDate = $rec['raw_date'];
                break;
            }
        }

        $flatRecords = [];
        $maxSessions = 1;
        $totalExpectedSeconds = 0;
        $totalTimeGivenSeconds = 0;
        $totalLeaves = 0;
        $totalUnpaidLeaves = 0;
        $totalAbsents = 0;
        $totalHolidays = 0;
        $totalRemotes = 0.0;

        foreach ($weeks as $weekKey => $weekDays) {
            $expectedHoursPerDay = $user->expected_hour_per_day ?? 8.0;
            
            $weekExpectedSeconds = 0;
            $weekTimeGivenSeconds = 0;
            $weeklyLeaves = 0;
            $weeklyUnpaidLeaves = 0;
            $weeklyAbsents = 0;
            $weeklyHolidays = 0;
            $weeklyRemotes = 0.0;

            foreach ($weekDays as $day) {
                $dayOfWeek = $day['raw_date']->dayOfWeekIso;
                $isDayLeave = $day['is_leave'] ?? false;
                $isDayUnpaidLeave = $day['is_unpaid_leave'] ?? false;
                $isDayHalfDayLeave = $day['is_half_day_leave'] ?? false;
                $isDayAbsent = $day['is_absent'] ?? false;
                $isDayHoliday = $day['is_holiday'] ?? false;
                $isBeforeJoin = $day['is_before_join'] ?? false;

                if ($dayOfWeek >= 1 && $dayOfWeek <= 5 && !$day['is_filler'] && !$isBeforeJoin) {
                    if (!$isDayLeave && !$isDayUnpaidLeave && !$isDayAbsent && !$isDayHoliday) {
                        $weekExpectedSeconds += $expectedHoursPerDay * 3600;
                    } elseif ($isDayHalfDayLeave && !$isDayAbsent && !$isDayHoliday) {
                        $weekExpectedSeconds += ($expectedHoursPerDay * 0.5) * 3600;
                    }
                }
                if (!$day['is_filler']) {
                    $weekTimeGivenSeconds += $day['total_seconds'];
                }
                if ($isDayLeave && !$day['is_filler'] && !$isBeforeJoin) {
                    $weeklyLeaves += ($isDayHalfDayLeave ? 0.5 : 1.0);
                }
                if ($isDayUnpaidLeave && !$day['is_filler'] && !$isBeforeJoin) {
                    $weeklyUnpaidLeaves += ($isDayHalfDayLeave ? 0.5 : 1.0);
                }
                if ($isDayAbsent && !$day['is_filler'] && !$isBeforeJoin) {
                    $weeklyAbsents++;
                }
                if ($isDayHoliday && !$day['is_filler'] && !$isBeforeJoin) {
                    $weeklyHolidays++;
                }
                $isDayRemote = $day['is_remote'] ?? false;
                if ($isDayRemote && !$day['is_filler'] && !$isBeforeJoin) {
                    $weeklyRemotes += self::getRemoteDayValue($day['remote_notes'] ?? null, (float)$expectedHoursPerDay);
                }
            }

            $weekDifferenceSeconds = $weekTimeGivenSeconds - $weekExpectedSeconds;

            // Accumulate grand totals
            $totalExpectedSeconds += $weekExpectedSeconds;
            $totalTimeGivenSeconds += $weekTimeGivenSeconds;
            $totalLeaves += $weeklyLeaves;
            $totalUnpaidLeaves += $weeklyUnpaidLeaves;
            $totalAbsents += $weeklyAbsents;
            $totalHolidays += $weeklyHolidays;
            $totalRemotes += $weeklyRemotes;

            foreach ($weekDays as $day) {
                $dayOfWeek = $day['raw_date']->dayOfWeekIso;
                $isFiller = $day['is_filler'];
                $isDayLeave = $day['is_leave'] ?? false;
                $isDayUnpaidLeave = $day['is_unpaid_leave'] ?? false;
                $isDayAbsent = $day['is_absent'] ?? false;
                $isDayHoliday = $day['is_holiday'] ?? false;
                $isBeforeJoin = $day['is_before_join'] ?? false;

                $isBeforeFirstMonday = ($monthStartsOnWeekend && $firstMondayDate && $day['raw_date']->lt($firstMondayDate));

                $label = '';
                $value = '';
                $pct = 0.0;

                $isDayHalfDayLeave = $day['is_half_day_leave'] ?? false;

                if (!$isBeforeFirstMonday) {
                    $label = match ($dayOfWeek) {
                        1 => 'Leaves =',
                        2 => 'Absent =',
                        3 => 'Holiday =',
                        4 => 'Working Hrs/Day',
                        5 => 'Expected Time*',
                        6 => 'Time Given*',
                        7 => 'Difference*',
                    };

                    if ($dayOfWeek === 1) {
                        $value = (string)$weeklyLeaves;
                    } elseif ($dayOfWeek === 2) {
                        $value = (string)$weeklyAbsents;
                    } elseif ($dayOfWeek === 3) {
                        $value = (string)$weeklyHolidays;
                    } elseif ($dayOfWeek === 4) {
                        $value = $this->formatSecondsToTime($expectedHoursPerDay * 3600);
                    } elseif ($dayOfWeek === 5) {
                        $value = $this->formatSecondsToTime($weekExpectedSeconds);
                    } elseif ($dayOfWeek === 6) {
                        $value = $this->formatSecondsToTime($weekTimeGivenSeconds);
                    } elseif ($dayOfWeek === 7) {
                        $value = $this->formatSecondsToTime($weekDifferenceSeconds);
                    }

                    if ($isBeforeJoin) {
                        $pct = 0.0;
                    } elseif ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                        $dayExp = ($isDayLeave || $isDayUnpaidLeave)
                            ? ($isDayHalfDayLeave ? ($expectedHoursPerDay * 0.5 * 3600) : 0)
                            : ($expectedHoursPerDay * 3600);
                        $pct = $dayExp > 0 ? (($day['total_seconds'] ?? 0) / $dayExp) : 0.0;
                    } elseif ($dayOfWeek === 6) {
                        if ($weekExpectedSeconds > 0) {
                            $pct = $weekTimeGivenSeconds / $weekExpectedSeconds;
                        }
                    } elseif ($dayOfWeek === 7) {
                        if ($weekExpectedSeconds > 0) {
                            $pct = $weekDifferenceSeconds / $weekExpectedSeconds;
                        }
                    }
                } else {
                    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                        $dayExp = ($isDayLeave || $isDayUnpaidLeave)
                            ? ($isDayHalfDayLeave ? ($expectedHoursPerDay * 0.5 * 3600) : 0)
                            : ($expectedHoursPerDay * 3600);
                        $pct = $isBeforeJoin ? 0.0 : ($dayExp > 0 ? (($day['total_seconds'] ?? 0) / $dayExp) : 0.0);
                    }
                }

                $dailyExpectedSeconds = 0;
                if ($dayOfWeek >= 1 && $dayOfWeek <= 5 && !$isDayAbsent && !$isDayHoliday && !$isBeforeJoin) {
                    if (!$isDayLeave && !$isDayUnpaidLeave) {
                        $dailyExpectedSeconds = (int)round($expectedHoursPerDay * 3600);
                    } elseif ($isDayHalfDayLeave) {
                        $dailyExpectedSeconds = (int)round(($expectedHoursPerDay * 0.5) * 3600);
                    }
                }

                $maxSessions = max($maxSessions, count($day['sessions']));

                $flatRecords[] = [
                    'date' => $isFiller ? '' : $day['date'],
                    'day_name' => $day['day_name'],
                    'weekly_summary_label' => $label,
                    'weekly_summary_value' => $value,
                    'pct' => $pct,
                    'pct_formatted' => sprintf("%.2f%%", $pct * 100),
                    'total_seconds' => $day['total_seconds'],
                    'total_formatted' => $day['total_seconds'] > 0 ? $this->formatSecondsToTime($day['total_seconds']) : "00:00:00",
                    'expected_seconds' => $dailyExpectedSeconds,
                    'expected_formatted' => $this->formatSecondsToTime($dailyExpectedSeconds),
                    'sessions' => $day['sessions'],
                    'is_filler' => $isFiller,
                    'is_before_join' => $isBeforeJoin,
                    'day_of_week' => $dayOfWeek,
                    'is_weekend' => ($dayOfWeek === 6 || $dayOfWeek === 7),
                    'weekly_summary_diff_seconds' => ($dayOfWeek === 7) ? $weekDifferenceSeconds : 0,
                    'raw_date_string' => $isFiller ? '' : $day['raw_date']->format('Y-m-d'),
                    'is_leave' => $isDayLeave,
                    'is_unpaid_leave' => $isDayUnpaidLeave,
                    'is_half_day_leave' => $isDayHalfDayLeave,
                    'is_absent' => $isDayAbsent,
                    'is_holiday' => $isDayHoliday,
                    'is_remote' => $day['is_remote'] ?? false,
                    'remote_notes' => $day['remote_notes'] ?? null,
                ];
            }
        }

        // Grand Totals for Summary Block
        $totalDiffSeconds = $totalTimeGivenSeconds - $totalExpectedSeconds;
        $canViewFinancials = auth()->user() && (auth()->user()->isSuperAdmin() || (int)auth()->id() === (int)$user->id);
        $hasSalary = !is_null($user->expected_salary_amount);
        $expectedSalary = $user->expected_salary_amount ?? 0;

        $calculatedDeductions = 0;
        $calculatedTakeAway = 0;

        if ($canViewFinancials && $hasSalary) {
            // Read settings
            $settingsFile = storage_path('app/salary_deduction_settings.json');
            $settings = [
                'rule' => 'calendar_days',
                'recalculate_all' => true,
                'effective_month' => null,
                'previous_rule' => null
            ];
            if (file_exists($settingsFile)) {
                $settings = array_merge($settings, json_decode(file_get_contents($settingsFile), true) ?: []);
            }

            $targetYearMonth = $startDate->format('Y-m');
            $ruleToUse = $settings['rule'];
            if (!$settings['recalculate_all'] && $settings['effective_month']) {
                if ($targetYearMonth < $settings['effective_month']) {
                    $ruleToUse = $settings['previous_rule'] ?? 'calendar_days';
                }
            }

            // Calculate division factor
            if ($ruleToUse === 'working_days') {
                $workingDaysInMonth = 0;
                $calcDate = $startDate->copy()->startOfMonth();
                $monthEnd = $startDate->copy()->endOfMonth();
                while ($calcDate->lte($monthEnd)) {
                    $dayOfWeek = $calcDate->dayOfWeekIso;
                    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                        $workingDaysInMonth++;
                    }
                    $calcDate->addDay();
                }
                $divisionFactor = $workingDaysInMonth ?: 22;
            } elseif ($ruleToUse === 'fixed_30') {
                $divisionFactor = 30.0;
            } else {
                $divisionFactor = (int)$startDate->daysInMonth;
            }

            $dailyRate = $expectedSalary / $divisionFactor;
            $daysBeforeJoin = $this->calculateDaysBeforeJoin($user, $startDate, $endDate, $ruleToUse);
            $totalDeductedDays = $daysBeforeJoin + $totalAbsents + $totalUnpaidLeaves;
            $calculatedDeductions = round($dailyRate * min($divisionFactor, $totalDeductedDays), 2);
            $calculatedTakeAway = max(0, $expectedSalary - $calculatedDeductions);
        }

        return response()->json([
            'records' => $flatRecords,
            'max_sessions' => $maxSessions,
            'summary' => [
                'total_session_seconds' => $totalTimeGivenSeconds,
                'total_session_formatted' => $this->formatSecondsToTime($totalTimeGivenSeconds),
                'total_expected_seconds' => $totalExpectedSeconds,
                'total_expected_formatted' => $this->formatSecondsToTime($totalExpectedSeconds),
                'total_difference_seconds' => $totalDiffSeconds,
                'total_difference_formatted' => $this->formatSecondsToTime($totalDiffSeconds),
                'total_leaves' => $totalLeaves,
                'total_unpaid_leaves' => $totalUnpaidLeaves,
                'total_absents' => $totalAbsents,
                'total_holidays' => $totalHolidays,
                'total_remotes' => $totalRemotes,
                'total_days_before_join' => $daysBeforeJoin ?? 0,
                'expected_salary' => $canViewFinancials ? ($hasSalary ? number_format($expectedSalary) : '—') : 'Hidden',
                'deductions' => $canViewFinancials ? ($hasSalary ? number_format($calculatedDeductions) : '—') : 'Hidden',
                'take_away' => $canViewFinancials ? ($hasSalary ? number_format($calculatedTakeAway) : '—') : 'Hidden',
                'show_summary' => $period !== 'today',
                'has_expected_salary' => $canViewFinancials && $hasSalary,
                'is_financial_hidden' => !$canViewFinancials,
                'has_slack_mapping' => true,
                'edit_url' => route('super-admin.users.edit', $user->id),
            ]
        ]);
    }

    private function parseMessageTime($msg, $dateString, $tz)
    {
        // If the message has an approval status, strictly rely on the database column message_at
        if ($msg->approval_status !== null) {
            return $msg->message_at->timezone($tz);
        }

        $defaultTime = $msg->message_at->timezone($tz);

        // Check for override suffix "at 00:00 AM/PM"
        if (preg_match('/at\s+(\d{1,2})(?:[:\s]+(\d{2}))?\s*(AM|PM)/i', $msg->message_text, $matches)) {
            $hours = (int)$matches[1];
            $minutes = (isset($matches[2]) && $matches[2] !== '') ? (int)$matches[2] : 0;
            $ampm = strtoupper($matches[3]);

            try {
                $timeStr = sprintf("%s %02d:%02d %s", $dateString, $hours, $minutes, $ampm);
                return Carbon::parse($timeStr, $tz);
            } catch (\Exception $e) {
                return $defaultTime;
            }
        }

        return $defaultTime;
    }

    public function getMessagesApi(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 30);
        $query = $this->buildSlackMessagesQuery($request);

        // Polling mode for live stream new messages
        if ($request->filled('since_id')) {
            $sinceId = (int) $request->input('since_id');
            $newMessages = (clone $query)
                ->where('id', '>', $sinceId)
                ->orderBy('message_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $formattedNew = $newMessages->map(fn($msg) => $this->formatSlackMessage($msg));

            // Also check for status updates on existing pending messages
            $updatedMessages = collect();
            if ($request->filled('visible_ids')) {
                $visibleIds = array_filter(array_map('intval', explode(',', $request->input('visible_ids'))));
                if (!empty($visibleIds)) {
                    $updatedMsgs = SlackMessage::with(['edits', 'mapping.user'])
                        ->whereIn('id', $visibleIds)
                        ->get();
                    $updatedMessages = $updatedMsgs->map(fn($msg) => $this->formatSlackMessage($msg));
                }
            }

            $maxId = $newMessages->isNotEmpty() ? $newMessages->max('id') : $sinceId;
            $deletedIds = \Illuminate\Support\Facades\Cache::get('recently_deleted_slack_msg_ids', []);

            return response()->json([
                'new_messages' => $formattedNew,
                'updated_messages' => $updatedMessages,
                'deleted_ids' => array_values($deletedIds),
                'max_id' => $maxId,
            ]);
        }

        // Historical cursor pagination mode
        if ($request->filled('cursor')) {
            $query->where('id', '<', (int) $request->input('cursor'));
        }

        $rawMessages = $query->orderBy('message_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rawMessages->count() > $limit;
        if ($hasMore) {
            $rawMessages->pop();
        }

        $formattedMessages = $rawMessages->map(fn($msg) => $this->formatSlackMessage($msg));
        $nextCursor = $rawMessages->isNotEmpty() ? $rawMessages->last()->id : null;
        $maxId = $rawMessages->isNotEmpty() ? $rawMessages->max('id') : 0;

        return response()->json([
            'messages' => $formattedMessages,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
            'max_id' => $maxId,
        ]);
    }

    public function approveOverride(SlackMessage $message): JsonResponse
    {
        if (!in_array($message->approval_status, ['pending', 'pending_edit'])) {
            return response()->json(['status' => 'error', 'message' => 'This message is not awaiting approval.'], 400);
        }

        if ($message->approval_status === 'pending_edit') {
            $updateData = [
                'message_text' => $message->pending_message_text,
                'override_at' => $message->pending_override_at,
                'message_at' => $message->pending_override_at,
                'pending_message_text' => null,
                'pending_override_at' => null,
                'approval_status' => 'approved',
            ];
            if (!$message->original_message_at) {
                $updateData['original_message_at'] = $message->message_at;
            }
        } else {
            $updateData = ['approval_status' => 'approved'];
            if ($message->override_at) {
                $updateData['message_at'] = $message->override_at;
                if (!$message->original_message_at) {
                    $updateData['original_message_at'] = $message->message_at;
                }
            }
        }
        $message->update($updateData);

        // Clear any auto-appended and other obsolete override messages on that calendar day
        if ($message->override_at) {
            $tz = 'Asia/Karachi';
            $overrideDate = $message->override_at->timezone($tz)->format('Y-m-d');
            $start = \Carbon\Carbon::parse($overrideDate, $tz)->startOfDay()->timezone('UTC');
            $end = \Carbon\Carbon::parse($overrideDate, $tz)->endOfDay()->timezone('UTC');

            // 1. Delete any auto-appended sign-outs
            SlackMessage::where('slack_user_id', $message->slack_user_id)
                ->where('is_auto_appended', true)
                ->where('message_text', 'sign out')
                ->whereBetween('message_at', [$start, $end])
                ->delete();

            // 2. Delete any other duplicate checkout, check-in, or back messages for the same user on that calendar day surgically at the exact same timestamp
            $text = strtolower(trim($message->message_text));
            $isSignIn = (str_contains($text, 'signin') || str_contains($text, 'sign in'));
            $isSignOut = (str_contains($text, 'signout') || str_contains($text, 'sign out'));
            $isBack = preg_match('/^back\b/i', $text);

            if ($isSignOut) {
                SlackMessage::where('slack_user_id', $message->slack_user_id)
                    ->where('id', '!=', $message->id)
                    ->where('is_auto_appended', false)
                    ->where('message_at', $message->override_at)
                    ->where(function ($q) {
                        $q->where('message_text', 'like', '%signout%')
                          ->orWhere('message_text', 'like', '%sign out%');
                    })
                    ->update(['approval_status' => 'merged']);
            } elseif ($isSignIn) {
                SlackMessage::where('slack_user_id', $message->slack_user_id)
                    ->where('id', '!=', $message->id)
                    ->where('is_auto_appended', false)
                    ->where('message_at', $message->override_at)
                    ->where(function ($q) {
                        $q->where('message_text', 'like', '%signin%')
                          ->orWhere('message_text', 'like', '%sign in%');
                    })
                    ->update(['approval_status' => 'merged']);
            } elseif ($isBack) {
                SlackMessage::where('slack_user_id', $message->slack_user_id)
                    ->where('id', '!=', $message->id)
                    ->where('message_at', $message->override_at)
                    ->where('message_text', 'back')
                    ->update(['approval_status' => 'merged']);
            }

            $tz = 'Asia/Karachi';
            $dateStr = $message->message_at->timezone($tz)->format('Y-m-d');
            $this->clearHealedCache($message->slack_user_id, $dateStr);
            SlackMessage::ensureDayAutoAppends($message->slack_user_id, $dateStr, false);
            SlackMessage::recalculateBreakExceeded($message->slack_user_id, $dateStr);
        }

        // Mark associated notifications as read
        \Illuminate\Support\Facades\DB::table('notifications')
            ->where('data->slack_message_id', $message->id)
            ->update(['read_at' => now(), 'updated_at' => now()]);

        // Notify employee of approval
        $targetUser = $message->mapping?->user;
        if ($targetUser && $targetUser->id !== auth()->id()) {
            $tz = 'Asia/Karachi';
            $msgDateStr = $message->message_at ? $message->message_at->timezone($tz)->format('M d, Y') : '';
            $targetUser->notify(new SessionOverrideDecisionNotification(
                messageText: $message->message_text,
                dateFormatted: $msgDateStr,
                isApproved: true
            ));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Override request approved successfully!',
        ]);
    }

    public function rejectOverride(SlackMessage $message): JsonResponse
    {
        if (!in_array($message->approval_status, ['pending', 'pending_edit'])) {
            return response()->json(['status' => 'error', 'message' => 'This message is not awaiting approval.'], 400);
        }

        if ($message->approval_status === 'pending_edit') {
            $updateData = [
                'pending_message_text' => null,
                'pending_override_at' => null,
                'approval_status' => 'approved',
            ];
        } else {
            $updateData = ['approval_status' => 'rejected'];
            if ($message->original_message_at) {
                $updateData['message_at'] = $message->original_message_at;
            }
        }
        $message->update($updateData);

        $tz = 'Asia/Karachi';
        $refTime = $message->override_at ?: $message->message_at;
        if ($refTime) {
            $dateStr = $refTime->timezone($tz)->format('Y-m-d');
            $this->clearHealedCache($message->slack_user_id, $dateStr);
            SlackMessage::ensureDayAutoAppends($message->slack_user_id, $dateStr, false);
            SlackMessage::recalculateBreakExceeded($message->slack_user_id, $dateStr);
        }

        // Mark associated notifications as read
        \Illuminate\Support\Facades\DB::table('notifications')
            ->where('data->slack_message_id', $message->id)
            ->update(['read_at' => now(), 'updated_at' => now()]);

        // Notify employee of rejection
        $targetUser = $message->mapping?->user;
        if ($targetUser && $targetUser->id !== auth()->id()) {
            $tz = 'Asia/Karachi';
            $msgDateStr = $message->message_at ? $message->message_at->timezone($tz)->format('M d, Y') : '';
            $targetUser->notify(new SessionOverrideDecisionNotification(
                messageText: $message->message_text,
                dateFormatted: $msgDateStr,
                isApproved: false
            ));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Override request rejected.',
        ]);
    }

    public function resetOverride(SlackMessage $message): JsonResponse
    {
        if (!in_array($message->approval_status, ['approved', 'rejected'])) {
            return response()->json(['status' => 'error', 'message' => 'This message decision cannot be reset.'], 400);
        }

        $oldStatus = $message->approval_status;
        $tz = 'Asia/Karachi';

        // Track both the overridden day and the original day to clean up auto-appends
        $affectedDates = [];
        if ($message->override_at) {
            $affectedDates[] = $message->override_at->timezone($tz)->format('Y-m-d');
        }
        if ($message->original_message_at) {
            $affectedDates[] = $message->original_message_at->timezone($tz)->format('Y-m-d');
        } else {
            $affectedDates[] = $message->message_at->timezone($tz)->format('Y-m-d');
        }

        // Revert message_at back to original_message_at, set status to pending
        $updateData = [
            'approval_status' => 'pending',
            'pending_message_text' => null,
            'pending_override_at' => null,
        ];
        if ($message->original_message_at) {
            $updateData['message_at'] = $message->original_message_at;
        }
        $message->update($updateData);

        if ($message->override_at) {
            // Restore any previously merged messages at that exact timestamp
            SlackMessage::where('slack_user_id', $message->slack_user_id)
                ->where('approval_status', 'merged')
                ->where('message_at', $message->override_at)
                ->update(['approval_status' => null]);
        }

        // Delete existing auto-appended sign-outs on affected dates and regenerate them
        $affectedDates = array_unique($affectedDates);
        foreach ($affectedDates as $dateStr) {
            $start = \Carbon\Carbon::parse($dateStr, $tz)->startOfDay()->timezone('UTC');
            $end = \Carbon\Carbon::parse($dateStr, $tz)->endOfDay()->timezone('UTC');

            // Delete any existing auto-appended sign-outs for this day
            SlackMessage::where('slack_user_id', $message->slack_user_id)
                ->where('is_auto_appended', true)
                ->where('message_text', 'sign out')
                ->whereBetween('message_at', [$start, $end])
                ->delete();

            // Regenerate auto-appends and recalculate break exceeded flags
            $this->clearHealedCache($message->slack_user_id, $dateStr);
            SlackMessage::ensureDayAutoAppends($message->slack_user_id, $dateStr, false);
            SlackMessage::recalculateBreakExceeded($message->slack_user_id, $dateStr);
        }

        // Reset associated notifications to unread
        \Illuminate\Support\Facades\DB::table('notifications')
            ->where('data->slack_message_id', $message->id)
            ->update(['read_at' => null, 'updated_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Override decision has been reset.',
        ]);
    }

    /**
     * Calculate unworked / pre-joining days for an employee within a given period.
     */
    public function calculateDaysBeforeJoin(User $user, Carbon $startDate, Carbon $endDate, string $ruleToUse): int
    {
        if (!$user->join_date) {
            return 0;
        }

        $joinDate = $user->join_date->copy()->startOfDay();

        // Already joined before or on the start date of this period
        if ($joinDate->lte($startDate)) {
            return 0;
        }

        // Joins after this month ends (not employed yet during this month)
        if ($joinDate->gt($endDate)) {
            if ($ruleToUse === 'working_days') {
                $workingDays = 0;
                $cur = $startDate->copy();
                while ($cur->lte($endDate)) {
                    if ($cur->dayOfWeekIso >= 1 && $cur->dayOfWeekIso <= 5) {
                        $workingDays++;
                    }
                    $cur->addDay();
                }
                return $workingDays;
            } elseif ($ruleToUse === 'fixed_30') {
                return 30;
            } else {
                return (int)$startDate->daysInMonth;
            }
        }

        // Joined mid-month (startDate < joinDate <= endDate)
        if ($ruleToUse === 'working_days') {
            $workingDays = 0;
            $cur = $startDate->copy();
            while ($cur->lt($joinDate)) {
                if ($cur->dayOfWeekIso >= 1 && $cur->dayOfWeekIso <= 5) {
                    $workingDays++;
                }
                $cur->addDay();
            }
            return $workingDays;
        } elseif ($ruleToUse === 'fixed_30') {
            $daysDiff = $startDate->diffInDays($joinDate); // e.g. Aug 1 to Aug 16 = 15 days
            return (int) min(29, $daysDiff); // cap at 29 so if joined on 31st of 31-day month, employee gets at least 1 day
        } else {
            return (int) $startDate->diffInDays($joinDate);
        }
    }

    public function calculateMonthlyTrackRecordData(User $user, int $month, int $year): array
    {
        $tz = 'Asia/Karachi';
        $today = Carbon::now($tz);
        $carbonMonth = Carbon::createFromDate($year, $month, 1, $tz);
        $startDate = $carbonMonth->copy()->startOfMonth();
        $endDate = $carbonMonth->copy()->endOfMonth();

        $mapping = SlackUserMapping::where('user_id', $user->id)->first();
        $slackUserId = $mapping ? $mapping->slack_user_id : null;

        // Generate full chronological dates in the range
        $periodDates = [];
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $periodDates[$current->format('Y-m-d')] = [
                'date_formatted' => $current->format('M d, Y'),
                'day_name' => $current->format('l'),
                'raw_date' => $current->copy(),
                'messages' => [],
            ];
            $current->addDay();
        }

        $queryStartDate = $startDate->copy()->subDays(2)->timezone('UTC');
        $queryEndDate = $endDate->copy()->addDay()->timezone('UTC');

        if ($slackUserId) {
            $checkDate = $startDate->copy()->subDay();
            while ($checkDate->lte($endDate->copy()->addDay())) {
                $dateStr = $checkDate->format('Y-m-d');
                if ($checkDate->lt($today->copy()->startOfDay())) {
                    $cacheKey = "healed_{$slackUserId}_{$dateStr}";
                    if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                        SlackMessage::ensureDayAutoAppends($slackUserId, $dateStr, false);
                    }
                }
                $checkDate->addDay();
            }
        }

        $messages = $slackUserId ? SlackMessage::where('slack_user_id', $slackUserId)
            ->where(function ($q) {
                $q->where('is_auto_appended', false)
                  ->orWhere(function ($sub) {
                      $sub->where('is_auto_appended', true)
                          ->whereIn('message_text', ['signout', 'sign out']);
                  });
            })
            ->where(function ($q) {
                $q->whereNull('approval_status')
                  ->orWhere('approval_status', '!=', 'merged');
            })
            ->whereBetween('message_at', [$queryStartDate, $queryEndDate])
            ->orderBy('message_at', 'asc')
            ->get() : collect();

        $sessionsByDate = $this->reconstructSessions($messages, $startDate, $endDate, $tz);

        $leaves = LeaveRequest::with('leaveType')
            ->where('user_id', $user->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->get();

        $absentsList = AttendanceRecord::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy(fn($r) => $r->date->format('Y-m-d'));

        $holidaysList = CompanyHoliday::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with(['departments', 'users'])
            ->get()
            ->filter(fn($h) => $h->appliesToUser($user))
            ->keyBy(fn($h) => $h->date->format('Y-m-d'));

        $remotesList = RemoteWorkLog::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy(fn($r) => $r->date->format('Y-m-d'));

        $records = [];
        foreach ($periodDates as $dateString => $dayData) {
            $rawDate = $dayData['raw_date'];
            $daySessions = $sessionsByDate[$dateString] ?? [];

            $dailySeconds = 0;
            $formattedSessions = [];

            foreach ($daySessions as $session) {
                $inStr = $session['in']->format('h:i:s A');
                $outStr = '';

                if ($session['out']) {
                    $outStr = $session['out']->format('h:i:s A');
                    $dailySeconds += ($session['out']->timestamp - $session['in']->timestamp);
                } else {
                    $outStr = 'Incomplete';
                }

                $formattedSessions[] = [
                    'in' => $inStr,
                    'out' => $outStr,
                    'is_virtual_in' => $session['is_virtual_in'] ?? false,
                    'virtual_in_reason' => $session['virtual_in_reason'] ?? '',
                    'is_virtual_out' => $session['is_virtual_out'] ?? false,
                    'virtual_out_reason' => $session['virtual_out_reason'] ?? '',
                ];
            }

            $isLeave = false;
            $isUnpaidLeave = false;
            $isHalfDayLeave = false;
            foreach ($leaves as $leave) {
                if ($leave->start_date->format('Y-m-d') <= $dateString && $leave->end_date->format('Y-m-d') >= $dateString) {
                    if ($leave->leaveType && $leave->leaveType->slug === 'unpaid') {
                        $isUnpaidLeave = true;
                    } else {
                        $isLeave = true;
                    }
                    if ($leave->leaveType && $leave->leaveType->is_half_day) {
                        $isHalfDayLeave = true;
                    }
                    break;
                }
            }

            $isAbsent = isset($absentsList[$dateString]);
            $isHoliday = isset($holidaysList[$dateString]);
            $remoteLog = $remotesList[$dateString] ?? null;
            $isRemote = !is_null($remoteLog);
            $remoteNotes = $remoteLog ? $remoteLog->notes : null;
            $isBeforeJoin = $user->join_date && $dateString < $user->join_date->format('Y-m-d');

            $records[$dateString] = [
                'date' => $dayData['date_formatted'],
                'day_name' => $dayData['day_name'],
                'raw_date' => $rawDate,
                'sessions' => $formattedSessions,
                'total_seconds' => $dailySeconds,
                'is_leave' => $isLeave,
                'is_unpaid_leave' => $isUnpaidLeave,
                'is_half_day_leave' => $isHalfDayLeave,
                'is_absent' => $isAbsent,
                'is_holiday' => $isHoliday,
                'is_remote' => $isRemote,
                'remote_notes' => $remoteNotes,
                'is_before_join' => $isBeforeJoin,
            ];
        }

        $weeks = [];
        foreach ($records as $dateString => $record) {
            $rawDate = $record['raw_date'];
            $weekKey = $rawDate->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
            if (!isset($weeks[$weekKey])) {
                $weeks[$weekKey] = [];
            }
            $weeks[$weekKey][] = array_merge($record, ['is_filler' => false]);
        }

        $expectedHoursPerDay = (float)($user->expected_hour_per_day ?? 8.0);

        $totalExpectedSeconds = 0;
        $totalTimeGivenSeconds = 0;
        $totalLeaves = 0;
        $totalUnpaidLeaves = 0;
        $totalAbsents = 0;
        $totalHolidays = 0;
        $totalRemotes = 0.0;
        $totalWorkingDays = 0;

        $processedRecords = [];

        foreach ($weeks as $weekKey => $weekDays) {
            $weekExpectedSeconds = 0;
            $weekTimeGivenSeconds = 0;
            $weeklyLeaves = 0;
            $weeklyUnpaidLeaves = 0;
            $weeklyAbsents = 0;
            $weeklyHolidays = 0;
            $weeklyRemotes = 0.0;

            foreach ($weekDays as $day) {
                $dayOfWeek = $day['raw_date']->dayOfWeekIso;
                $isDayLeave = $day['is_leave'] ?? false;
                $isDayUnpaidLeave = $day['is_unpaid_leave'] ?? false;
                $isDayHalfDayLeave = $day['is_half_day_leave'] ?? false;
                $isDayAbsent = $day['is_absent'] ?? false;
                $isDayHoliday = $day['is_holiday'] ?? false;
                $isDayRemote = $day['is_remote'] ?? false;
                $isBeforeJoin = $day['is_before_join'] ?? false;
                $isWeekend = ($dayOfWeek === 6 || $dayOfWeek === 7);

                if (!$isWeekend && !$isDayAbsent && !$isDayHoliday && !$isBeforeJoin) {
                    if (!$isDayLeave && !$isDayUnpaidLeave) {
                        $weekExpectedSeconds += $expectedHoursPerDay * 3600;
                    } elseif ($isDayHalfDayLeave) {
                        $weekExpectedSeconds += ($expectedHoursPerDay * 0.5) * 3600;
                    }
                }
                $weekTimeGivenSeconds += $day['total_seconds'];

                if ($isDayLeave && !$isBeforeJoin) $weeklyLeaves += ($isDayHalfDayLeave ? 0.5 : 1.0);
                if ($isDayUnpaidLeave && !$isBeforeJoin) $weeklyUnpaidLeaves += ($isDayHalfDayLeave ? 0.5 : 1.0);
                if ($isDayAbsent && !$isBeforeJoin) $weeklyAbsents++;
                if ($isDayHoliday && !$isBeforeJoin) $weeklyHolidays++;
                if ($isDayRemote && !$isBeforeJoin) {
                    $weeklyRemotes += self::getRemoteDayValue($day['remote_notes'] ?? null, $expectedHoursPerDay);
                }
                if (!$isWeekend && !$isDayHoliday && !$isBeforeJoin) {
                    if (!$isDayLeave && !$isDayUnpaidLeave) {
                        $totalWorkingDays++;
                    } elseif ($isDayHalfDayLeave) {
                        $totalWorkingDays += 0.5;
                    }
                }
            }

            $weekDifferenceSeconds = $weekTimeGivenSeconds - $weekExpectedSeconds;

            $totalExpectedSeconds += $weekExpectedSeconds;
            $totalTimeGivenSeconds += $weekTimeGivenSeconds;
            $totalLeaves += $weeklyLeaves;
            $totalUnpaidLeaves += $weeklyUnpaidLeaves;
            $totalAbsents += $weeklyAbsents;
            $totalHolidays += $weeklyHolidays;
            $totalRemotes += $weeklyRemotes;

            foreach ($weekDays as $day) {
                $dayOfWeek = $day['raw_date']->dayOfWeekIso;
                $isBeforeJoin = $day['is_before_join'] ?? false;
                $isWeekend = ($dayOfWeek === 6 || $dayOfWeek === 7);

                $label = match ($dayOfWeek) {
                    1 => 'Leaves =',
                    2 => 'Absent =',
                    3 => 'Holiday =',
                    4 => 'Working Hrs/Day',
                    5 => 'Expected Time*',
                    6 => 'Time Given*',
                    7 => 'Difference*',
                };

                $val = match ($dayOfWeek) {
                    1 => (string)$weeklyLeaves,
                    2 => (string)$weeklyAbsents,
                    3 => (string)$weeklyHolidays,
                    4 => $this->formatSecondsToTime((int)round($expectedHoursPerDay * 3600)),
                    5 => $this->formatSecondsToTime($weekExpectedSeconds),
                    6 => $this->formatSecondsToTime($weekTimeGivenSeconds),
                    7 => ($weekDifferenceSeconds >= 0 ? '+' : '-') . $this->formatSecondsToTime(abs($weekDifferenceSeconds)),
                };

                $pct = 0.0;
                $isDayHalfDayLeave = $day['is_half_day_leave'] ?? false;
                $expDaySec = (int)round(($isDayHalfDayLeave ? ($expectedHoursPerDay * 0.5) : $expectedHoursPerDay) * 3600);
                if ($isBeforeJoin) {
                    $pct = 0.0;
                } elseif ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                    $pct = $expDaySec > 0 ? ($day['total_seconds'] / $expDaySec) : 0.0;
                } elseif ($dayOfWeek === 6) {
                    $pct = $weekExpectedSeconds > 0 ? ($weekTimeGivenSeconds / $weekExpectedSeconds) : 0.0;
                } elseif ($dayOfWeek === 7) {
                    $pct = $weekExpectedSeconds > 0 ? ($weekDifferenceSeconds / $weekExpectedSeconds) : 0.0;
                }

                $processedRecords[] = [
                    'date' => $day['date'],
                    'date_formatted' => $day['date'],
                    'day_name' => $day['day_name'],
                    'weekly_summary_label' => $label,
                    'weekly_summary_value' => $val,
                    'weekly_summary_diff_seconds' => $weekDifferenceSeconds,
                    'pct' => $pct,
                    'pct_formatted' => sprintf("%.2f%%", $pct * 100),
                    'total_time_formatted' => $this->formatSecondsToTime($day['total_seconds']),
                    'total_seconds' => $day['total_seconds'],
                    'day_of_week' => $dayOfWeek,
                    'is_weekend' => $isWeekend,
                    'is_leave' => $day['is_leave'],
                    'is_unpaid_leave' => $day['is_unpaid_leave'],
                    'is_half_day_leave' => $isDayHalfDayLeave,
                    'is_absent' => $day['is_absent'],
                    'is_holiday' => $day['is_holiday'],
                    'is_before_join' => $isBeforeJoin,
                    'is_filler' => false,
                ];
            }
        }

        $hasExpectedSalary = !is_null($user->expected_salary_amount) && $user->expected_salary_amount !== '' && (float)$user->expected_salary_amount > 0;
        $expectedSalary = $hasExpectedSalary ? (float)$user->expected_salary_amount : null;
        $settingsFile = storage_path('app/salary_deduction_settings.json');
        $settings = [
            'rule' => 'calendar_days',
            'recalculate_all' => true,
            'effective_month' => null,
            'previous_rule' => null,
        ];
        if (file_exists($settingsFile)) {
            $settings = array_merge($settings, json_decode(file_get_contents($settingsFile), true) ?: []);
        }

        $targetYearMonth = $startDate->format('Y-m');
        $ruleToUse = $settings['rule'];
        if (!$settings['recalculate_all'] && $settings['effective_month']) {
            if ($targetYearMonth < $settings['effective_month']) {
                $ruleToUse = $settings['previous_rule'] ?? 'calendar_days';
            }
        }

        if ($ruleToUse === 'working_days') {
            $workingDaysInMonth = 0;
            $calcDate = $startDate->copy()->startOfMonth();
            $monthEnd = $startDate->copy()->endOfMonth();
            while ($calcDate->lte($monthEnd)) {
                if ($calcDate->dayOfWeekIso >= 1 && $calcDate->dayOfWeekIso <= 5) {
                    $workingDaysInMonth++;
                }
                $calcDate->addDay();
            }
            $divisionFactor = $workingDaysInMonth ?: 22;
        } elseif ($ruleToUse === 'fixed_30') {
            $divisionFactor = 30.0;
        } else {
            $divisionFactor = $startDate->daysInMonth ?: 30;
        }

        $daysBeforeJoin = $this->calculateDaysBeforeJoin($user, $startDate, $endDate, $ruleToUse);

        if ($hasExpectedSalary) {
            $dailyRate = $expectedSalary / $divisionFactor;
            $totalDeductedDays = $daysBeforeJoin + $totalAbsents + $totalUnpaidLeaves;
            $calculatedDeductions = round($dailyRate * min($divisionFactor, $totalDeductedDays), 2);
            $calculatedTakeAway = max(0, $expectedSalary - $calculatedDeductions);
        } else {
            $dailyRate = 0.0;
            $calculatedDeductions = 0.0;
            $calculatedTakeAway = null;
        }
        $diffSeconds = $totalTimeGivenSeconds - $totalExpectedSeconds;

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'records' => $processedRecords,
            'totalExpectedSeconds' => $totalExpectedSeconds,
            'totalTimeGivenSeconds' => $totalTimeGivenSeconds,
            'diffSeconds' => $diffSeconds,
            'totalLeaves' => $totalLeaves,
            'totalUnpaidLeaves' => $totalUnpaidLeaves,
            'totalAbsents' => $totalAbsents,
            'totalHolidays' => $totalHolidays,
            'totalRemotes' => $totalRemotes,
            'totalDaysBeforeJoin' => $daysBeforeJoin,
            'totalWorkingDays' => $totalWorkingDays,
            'hasExpectedSalary' => $hasExpectedSalary,
            'expectedSalary' => $expectedSalary,
            'calculatedDeductions' => $calculatedDeductions,
            'calculatedTakeAway' => $calculatedTakeAway,
        ];
    }

    public function exportTrackRecord(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'period' => 'required|in:month,custom',
            'start_date' => 'required_if:period,custom|nullable|date',
            'end_date' => 'required_if:period,custom|nullable|date',
        ]);

        $userId = $request->user_id;
        $period = $request->period;

        if ($period === 'custom') {
            $start = Carbon::parse($request->start_date);
            $end = Carbon::parse($request->end_date);
            
            $isStartFirst = ($start->day === 1);
            $isEndLast = ($end->day === $end->copy()->endOfMonth()->day);
            $isSameMonth = ($start->year === $end->year && $start->month === $end->month);
            
            if (!$isStartFirst || !$isEndLast || !$isSameMonth) {
                return redirect()->back()->with('error', 'Exports are only allowed for full calendar months.');
            }
        }

        $user = User::find($userId);
        $isSuperAdmin = auth()->user() && auth()->user()->isSuperAdmin();

        if ($isSuperAdmin && $user && is_null($user->expected_salary_amount)) {
            return redirect()->route('super-admin.users.edit', $user->id)
                ->with('error', 'Expected salary is not configured for ' . $user->name . '. Please configure it first.');
        }

        $mapping = SlackUserMapping::where('user_id', $userId)->first();
        if (!$mapping) {
            return redirect()->back()->with('error', 'User does not have a Slack mapping.');
        }

        $tz = 'Asia/Karachi';
        $today = Carbon::now($tz);

        if ($period === 'month') {
            $startDate = $today->copy()->startOfMonth();
            $endDate = $today->copy()->endOfMonth();
        } else {
            $startDate = Carbon::parse($request->start_date, $tz)->startOfDay();
            $endDate = Carbon::parse($request->end_date, $tz)->endOfDay();
        }

        // Generate full chronological dates in the range
        $periodDates = [];
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $periodDates[$current->format('Y-m-d')] = [
                'date_formatted' => $current->format('M d, Y'),
                'day_name' => $current->format('l'),
                'raw_date' => $current->copy(),
                'messages' => [],
            ];
            $current->addDay();
        }

        $queryStartDate = $startDate->copy()->subDays(2)->timezone('UTC');
        $queryEndDate = $endDate->copy()->addDay()->timezone('UTC');

        // Lazy-heal any dates in the queried range (plus the day before the start date and day after the end date for overnight boundary crossing) that are before today and have subsequent check-ins, but are not yet cached as healed.
        $checkDate = $startDate->copy()->subDay();
        while ($checkDate->lte($endDate->copy()->addDay())) {
            $dateStr = $checkDate->format('Y-m-d');
            if ($checkDate->lt($today->copy()->startOfDay())) {
                $cacheKey = "healed_{$mapping->slack_user_id}_{$dateStr}";
                if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                    SlackMessage::ensureDayAutoAppends($mapping->slack_user_id, $dateStr, false);
                }
            }
            $checkDate->addDay();
        }

        // Query all messages for the mapped slack user ID
        $messages = SlackMessage::where('slack_user_id', $mapping->slack_user_id)
            ->whereBetween('message_at', [$queryStartDate, $queryEndDate])
            ->orderBy('message_at', 'asc')
            ->get();

        $sessionsByDate = $this->reconstructSessions($messages, $startDate, $endDate, $tz);

        $leaves = LeaveRequest::with('leaveType')
            ->where('user_id', $userId)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->get();

        $absentsList = AttendanceRecord::where('user_id', $userId)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy(fn($r) => $r->date->format('Y-m-d'));

        $holidaysList = CompanyHoliday::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with(['departments', 'users'])
            ->get()
            ->filter(fn($h) => $h->appliesToUser($user))
            ->keyBy(fn($h) => $h->date->format('Y-m-d'));

        $records = [];
        foreach ($periodDates as $dateString => $dayData) {
            $rawDate = $dayData['raw_date'];
            $daySessions = $sessionsByDate[$dateString] ?? [];

            $dailySeconds = 0;
            $formattedSessions = [];

            foreach ($daySessions as $session) {
                $inStr = $session['in']->format('h:i:s A');
                $outStr = '';

                if ($session['out']) {
                    $outStr = $session['out']->format('h:i:s A');
                    $dailySeconds += ($session['out']->timestamp - $session['in']->timestamp);
                } else {
                    $outStr = 'Incomplete';
                }

                $formattedSessions[] = [
                    'in' => $inStr,
                    'out' => $outStr,
                    'is_virtual_in' => $session['is_virtual_in'] ?? false,
                    'virtual_in_reason' => $session['virtual_in_reason'] ?? '',
                    'is_virtual_out' => $session['is_virtual_out'] ?? false,
                    'virtual_out_reason' => $session['virtual_out_reason'] ?? '',
                ];
            }

            $isLeave = false;
            $isUnpaidLeave = false;
            $isHalfDayLeave = false;
            foreach ($leaves as $leave) {
                if ($leave->start_date->format('Y-m-d') <= $dateString && $leave->end_date->format('Y-m-d') >= $dateString) {
                    if ($leave->leaveType && $leave->leaveType->slug === 'unpaid') {
                        $isUnpaidLeave = true;
                    } else {
                        $isLeave = true;
                    }
                    if ($leave->leaveType && $leave->leaveType->is_half_day) {
                        $isHalfDayLeave = true;
                    }
                    break;
                }
            }

            $isAbsent = isset($absentsList[$dateString]);
            $isHoliday = isset($holidaysList[$dateString]);
            $isBeforeJoin = $user->join_date && $dateString < $user->join_date->format('Y-m-d');

            $records[$dateString] = [
                'date' => $dayData['date_formatted'],
                'day_name' => $dayData['day_name'],
                'raw_date' => $rawDate,
                'sessions' => $formattedSessions,
                'total_seconds' => $dailySeconds,
                'is_leave' => $isLeave,
                'is_unpaid_leave' => $isUnpaidLeave,
                'is_half_day_leave' => $isHalfDayLeave,
                'is_absent' => $isAbsent,
                'is_holiday' => $isHoliday,
                'is_before_join' => $isBeforeJoin,
            ];
        }

        $weeks = [];
        foreach ($records as $dateString => $record) {
            $rawDate = $record['raw_date'];
            $weekKey = $rawDate->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
            if (!isset($weeks[$weekKey])) {
                $weeks[$weekKey] = [];
            }
            $weeks[$weekKey][] = array_merge($record, ['is_filler' => false]);
        }

        // Pad the last week up to Sunday
        $lastWeekKey = array_key_last($weeks);
        if ($lastWeekKey) {
            $lastWeek = $weeks[$lastWeekKey];
            $lastRecord = end($lastWeek);
            $lastDay = $lastRecord['raw_date'];
            $lastDayOfWeek = $lastDay->dayOfWeekIso;
            if ($lastDayOfWeek < 7) {
                for ($i = $lastDayOfWeek + 1; $i <= 7; $i++) {
                    $fillerDate = $lastDay->copy()->addDays($i - $lastDayOfWeek);
                    $weeks[$lastWeekKey][] = [
                        'date' => '',
                        'day_name' => $fillerDate->format('l'),
                        'raw_date' => $fillerDate,
                        'sessions' => [],
                        'total_seconds' => 0,
                        'is_filler' => true,
                        'is_leave' => false,
                        'is_unpaid_leave' => false,
                        'is_half_day_leave' => false,
                        'is_absent' => false,
                        'is_holiday' => false,
                    ];
                }
            }
        }

        // Post-process the first week for custom HR start-of-month label alignment
        $firstWeekKey = array_key_first($weeks);
        if ($firstWeekKey) {
            $firstWeekDays = $weeks[$firstWeekKey];
            $firstDay = $firstWeekDays[0];
            $firstDayOfWeek = $firstDay['raw_date']->dayOfWeekIso;

            if ($firstDayOfWeek >= 2 && $firstDayOfWeek <= 5) {
                // Determine if we have leaves or absents in this first week
                $firstWeekLeaves = 0;
                $firstWeekAbsents = 0;
                foreach ($firstWeekDays as $day) {
                    if (!$day['is_filler']) {
                        if ($day['is_leave'] || $day['is_unpaid_leave']) {
                            $firstWeekLeaves++;
                        }
                        if ($day['is_absent']) {
                            $firstWeekAbsents++;
                        }
                    }
                }

                $prependCount = 0;
                if ($firstWeekLeaves > 0) {
                    $prependCount = $firstDayOfWeek - 1;
                } elseif ($firstWeekAbsents > 0) {
                    $prependCount = $firstDayOfWeek - 2;
                }

                if ($prependCount > 0) {
                    $prependedDays = [];
                    for ($d = $firstDayOfWeek - $prependCount; $d < $firstDayOfWeek; $d++) {
                        $fillerDate = $firstDay['raw_date']->copy()->subDays($firstDayOfWeek - $d);
                        $prependedDays[] = [
                            'date' => '',
                            'day_name' => '',
                            'raw_date' => $fillerDate,
                            'sessions' => [],
                            'total_seconds' => 0,
                            'is_filler' => true,
                            'is_leave' => false,
                            'is_unpaid_leave' => false,
                            'is_half_day_leave' => false,
                            'is_absent' => false,
                            'is_holiday' => false,
                        ];
                    }
                    $weeks[$firstWeekKey] = array_merge($prependedDays, $firstWeekDays);
                }
            }
        }

        // Determine if month starts on weekend (Saturday/Sunday)
        $monthStartsOnWeekend = false;
        $firstRecord = reset($records);
        if ($firstRecord) {
            $fOfWeek = $firstRecord['raw_date']->dayOfWeekIso;
            $monthStartsOnWeekend = ($fOfWeek === 6 || $fOfWeek === 7);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Track Record');
        $sheet->setShowGridlines(true);
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(11);

        // Column widths
        foreach (range('A', 'Y') as $col) {
            if ($col === 'D') {
                $sheet->getColumnDimension($col)->setWidth(20);
            } elseif ($col >= 'H') {
                $sheet->getColumnDimension($col)->setWidth(15);
            } else {
                $sheet->getColumnDimension($col)->setWidth(12.63);
            }
        }

        // Title row (Row 1)
        $sheet->mergeCells('A1:K1'); // Keeps text centered over columns A to K
        $sheet->setCellValue('A1', $startDate->timezone($tz)->format('M Y'));
        $sheet->getRowDimension(1)->setRowHeight(35);

        // 1. First, style the text alignment inside the merged range
        $sheet->getStyle('A1:K1')->getAlignment()->applyFromArray([
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ]);

        // 2. Then, apply the font size, color, and maroon background to the ENTIRE row (A1 to Y1)
        $sheet->getStyle('A1:Y1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 16,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '5A1837'], // Your maroon color
            ],
        ]);

        // Headers row (Row 2)
        $sheet->setCellValue('A2', 'Name');
        $sheet->setCellValue('B2', 'Date');
        $sheet->setCellValue('C2', 'Day');
        $sheet->mergeCells('D2:E2');
        $sheet->setCellValue('D2', 'Weekly Summary');
        $sheet->setCellValue('F2', '%');
        $sheet->setCellValue('G2', 'Total time');

        for ($s = 1; $s <= 9; $s++) {
            $inCol = chr(72 + (($s - 1) * 2));
            $outCol = chr(73 + (($s - 1) * 2));
            $sheet->setCellValue($inCol . '2', 'Sign-in');
            $sheet->setCellValue($outCol . '2', 'Sign-out');
        }

        $sheet->getRowDimension(2)->setRowHeight(25);
        
        $headerStyle = [
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];
        $sheet->getStyle('A2:Y2')->applyFromArray($headerStyle);

        $firstMondayDate = null;
        foreach ($records as $dateString => $rec) {
            if ($rec['raw_date']->dayOfWeekIso === 1) {
                $firstMondayDate = $rec['raw_date'];
                break;
            }
        }

        $firstDataRow = 3;
        $rowNum = 3;

        $totalExpectedSeconds = 0;
        $totalTimeGivenSeconds = 0;
        $totalLeaves = 0;
        $totalUnpaidLeaves = 0;
        $totalAbsents = 0;
        $totalHolidays = 0;

        foreach ($weeks as $weekKey => $weekDays) {
            $expectedHoursPerDay = $user->expected_hour_per_day ?? 8.0;
            
            $weekExpectedSeconds = 0;
            $weekTimeGivenSeconds = 0;
            $weeklyLeaves = 0;
            $weeklyUnpaidLeaves = 0;
            $weeklyAbsents = 0;
            $weeklyHolidays = 0;

            foreach ($weekDays as $day) {
                $dayOfWeek = $day['raw_date']->dayOfWeekIso;
                $isDayLeave = $day['is_leave'] ?? false;
                $isDayUnpaidLeave = $day['is_unpaid_leave'] ?? false;
                $isDayHalfDayLeave = $day['is_half_day_leave'] ?? false;
                $isDayAbsent = $day['is_absent'] ?? false;
                $isDayHoliday = $day['is_holiday'] ?? false;
                $isBeforeJoin = $day['is_before_join'] ?? false;

                if ($dayOfWeek >= 1 && $dayOfWeek <= 5 && !$day['is_filler'] && !$isBeforeJoin) {
                    if (!$isDayLeave && !$isDayUnpaidLeave && !$isDayAbsent && !$isDayHoliday) {
                        $weekExpectedSeconds += $expectedHoursPerDay * 3600;
                    } elseif ($isDayHalfDayLeave && !$isDayAbsent && !$isDayHoliday) {
                        $weekExpectedSeconds += ($expectedHoursPerDay * 0.5) * 3600;
                    }
                }
                if (!$day['is_filler']) {
                    $weekTimeGivenSeconds += $day['total_seconds'];
                }
                if ($isDayLeave && !$day['is_filler'] && !$isBeforeJoin) {
                    $weeklyLeaves += ($isDayHalfDayLeave ? 0.5 : 1.0);
                }
                if ($isDayUnpaidLeave && !$day['is_filler'] && !$isBeforeJoin) {
                    $weeklyUnpaidLeaves += ($isDayHalfDayLeave ? 0.5 : 1.0);
                }
                if ($isDayAbsent && !$day['is_filler'] && !$isBeforeJoin) {
                    $weeklyAbsents++;
                }
                if ($isDayHoliday && !$day['is_filler'] && !$isBeforeJoin) {
                    $weeklyHolidays++;
                }
            }

            $weekDifferenceSeconds = $weekTimeGivenSeconds - $weekExpectedSeconds;

            // Accumulate grand totals
            $totalExpectedSeconds += $weekExpectedSeconds;
            $totalTimeGivenSeconds += $weekTimeGivenSeconds;
            $totalLeaves += $weeklyLeaves;
            $totalUnpaidLeaves += $weeklyUnpaidLeaves;
            $totalAbsents += $weeklyAbsents;
            $totalHolidays += $weeklyHolidays;

            foreach ($weekDays as $day) {
                $dayOfWeek = $day['raw_date']->dayOfWeekIso;
                $isFiller = $day['is_filler'];
                $isDayLeave = $day['is_leave'] ?? false;
                $isDayUnpaidLeave = $day['is_unpaid_leave'] ?? false;
                $isDayHalfDayLeave = $day['is_half_day_leave'] ?? false;
                $isDayAbsent = $day['is_absent'] ?? false;
                $isDayHoliday = $day['is_holiday'] ?? false;

                $sheet->setCellValue('B' . $rowNum, $isFiller ? '' : $day['date']);
                $sheet->setCellValue('C' . $rowNum, $day['day_name']);

                $isBeforeFirstMonday = ($monthStartsOnWeekend && $firstMondayDate && $day['raw_date']->lt($firstMondayDate));

                $label = '';
                $value = '';
                $pct = 0.0;

                if (!$isBeforeFirstMonday) {
                    $label = match ($dayOfWeek) {
                        1 => 'Leaves =',
                        2 => 'Absent =',
                        3 => 'Holiday =',
                        4 => 'Working Hrs/Day',
                        5 => 'Expected Time*',
                        6 => 'Time Given*',
                        7 => 'Difference*',
                    };

                    if ($dayOfWeek === 1) {
                        $value = $weeklyLeaves;
                    } elseif ($dayOfWeek === 2) {
                        $value = $weeklyAbsents;
                    } elseif ($dayOfWeek === 3) {
                        $value = $weeklyHolidays;
                    } elseif ($dayOfWeek === 4) {
                        $value = $this->formatSecondsToTime($expectedHoursPerDay * 3600);
                    } elseif ($dayOfWeek === 5) {
                        $value = $this->formatSecondsToTime($weekExpectedSeconds);
                    } elseif ($dayOfWeek === 6) {
                        $value = $this->formatSecondsToTime($weekTimeGivenSeconds);
                    } elseif ($dayOfWeek === 7) {
                        $value = $this->formatSecondsToTime($weekDifferenceSeconds);
                    }

                    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                        $dayExpSec = (int)round(($isDayHalfDayLeave ? ($expectedHoursPerDay * 0.5) : $expectedHoursPerDay) * 3600);
                        $pct = $dayExpSec > 0 ? (($day['total_seconds'] ?? 0) / $dayExpSec) : 0.0;
                    } elseif ($dayOfWeek === 6) {
                        if ($weekExpectedSeconds > 0) {
                            $pct = $weekTimeGivenSeconds / $weekExpectedSeconds;
                        }
                    } elseif ($dayOfWeek === 7) {
                        if ($weekExpectedSeconds > 0) {
                            $pct = $weekDifferenceSeconds / $weekExpectedSeconds;
                        }
                    }
                } else {
                    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                        $dayExpSec = (int)round(($isDayHalfDayLeave ? ($expectedHoursPerDay * 0.5) : $expectedHoursPerDay) * 3600);
                        $pct = $dayExpSec > 0 ? (($day['total_seconds'] ?? 0) / $dayExpSec) : 0.0;
                    }
                }

                $sheet->setCellValue('D' . $rowNum, $label);
                $sheet->setCellValue('E' . $rowNum, $value);
                $sheet->setCellValue('F' . $rowNum, $pct);
                $sheet->getStyle('F' . $rowNum)->getNumberFormat()->setFormatCode('0.00%');

                $sheet->setCellValue('G' . $rowNum, $this->formatSecondsToTime($day['total_seconds'] ?? 0));

                $sessions = $day['sessions'] ?? [];
                for ($s = 0; $s < 9; $s++) {
                    $signInCol = chr(72 + ($s * 2));
                    $signOutCol = chr(73 + ($s * 2));
                    
                    if (isset($sessions[$s])) {
                        $sheet->setCellValue($signInCol . $rowNum, $sessions[$s]['in']);
                        $sheet->setCellValue($signOutCol . $rowNum, $sessions[$s]['out']);
                    } else {
                        $sheet->setCellValue($signInCol . $rowNum, '');
                        $sheet->setCellValue($signOutCol . $rowNum, '');
                    }
                }

                $sheet->getRowDimension($rowNum)->setRowHeight(20);

                $sheet->getStyle('B' . $rowNum . ':Y' . $rowNum)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Date column B: always #FFD966
                $sheet->getStyle('B' . $rowNum)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFD966'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Day column C: weekdays #FFFFFF, weekends #EA4335 (excluded from status leaves/absents row colors)
                $isWeekend = ($dayOfWeek === 6 || $dayOfWeek === 7);
                $dayBgColor = $isWeekend ? 'EA4335' : 'FFFFFF';
                $dayFontColor = $isWeekend ? 'FFFFFF' : '000000';
                $sheet->getStyle('C' . $rowNum)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $dayBgColor],
                    ],
                    'font' => [
                        'color' => ['rgb' => $dayFontColor],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Weekly Summary D & E: Mon-Sat: #C9DAF8, Sun: dynamic color based on weekDifferenceSeconds
                $sumBgColor = 'C9DAF8';
                $sumFontColor = '000000';
                $sumBold = false;
                if ($dayOfWeek === 7) {
                    $diffRatio = 1.0;
                    if ($weekDifferenceSeconds <= -3600) {
                        $diffRatio = 0.0;
                    } elseif ($weekDifferenceSeconds < 0) {
                        $diffRatio = ($weekDifferenceSeconds + 3600) / 3600;
                    }
                    $diffColors = $this->getDynamicColorForRatio($diffRatio);
                    $sumBgColor = $diffColors['bg'];
                    $sumFontColor = $diffColors['text'];
                    $sumBold = true;
                } elseif ($dayOfWeek === 6) {
                    $sumBgColor = 'C9DAF8';
                    $sumFontColor = '000000';
                    $sumBold = true;
                } elseif (in_array($dayOfWeek, [2, 3, 4])) {
                    $sumBold = true;
                }
                
                $sheet->getStyle('D' . $rowNum . ':E' . $rowNum)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $sumBgColor],
                    ],
                    'font' => [
                        'bold' => $sumBold,
                        'color' => ['rgb' => $sumFontColor],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                $sheet->getStyle('E' . $rowNum)->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                if ($dayOfWeek === 7) {
                    $sheet->getStyle('D' . ($rowNum - 1) . ':G' . $rowNum)->applyFromArray([
                        'borders' => [
                            'outline' => [
                                'borderStyle' => Border::BORDER_THICK,
                                'color' => ['rgb' => '000000'],
                            ],
                        ],
                    ]);
                }

                // Percentage F: dynamic colors based on $pct (including Saturday & Sunday weekends, leaves/absents/holidays, filler rows)
                $isBeforeJoin = $day['is_before_join'] ?? false;
                $colors = $isBeforeJoin ? ['bg' => 'FFFFFF', 'text' => '000000'] : $this->getDynamicColorForRatio($pct);
                $pctColor = $colors['bg'];
                $pctFontColor = $colors['text'];

                $sheet->getStyle('F' . $rowNum)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $pctColor],
                    ],
                    'font' => [
                        'color' => ['rgb' => $pctFontColor],
                        'bold' => true,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Total time G and Session columns H-Y: Weekdays: #FFFFFF, Weekends: #EA4335, Leaves: #FFFF00, Unpaid Leaves: #4452adff, Absents: #A64D79, Holidays: #B45F06
                $rowBgColor = $isBeforeJoin ? 'FFFFFF' : ($isWeekend ? 'EA4335' : ($isDayLeave ? 'FFFF00' : ($isDayUnpaidLeave ? '4452adff' : ($isDayAbsent ? 'A64D79' : ($isDayHoliday ? 'B45F06' : 'FFFFFF')))));
                $rowFontColor = ($isBeforeJoin) ? '000000' : (($isWeekend || $isDayAbsent || $isDayHoliday) ? 'FFFFFF' : '000000');

                $sheet->getStyle('G' . $rowNum . ':Y' . $rowNum)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $rowBgColor],
                    ],
                    'font' => [
                        'color' => ['rgb' => $rowFontColor],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $rowNum++;
            }
        }
        $lastDataRow = $rowNum - 1;

        $sheet->mergeCells('A' . $firstDataRow . ':A' . $lastDataRow);
        $sheet->setCellValue('A' . $firstDataRow, $user->name);
        $sheet->getStyle('A' . $firstDataRow . ':A' . $lastDataRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'textRotation' => 90,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Summary block
        $sumRow1 = $rowNum;
        $sumRow2 = $rowNum + 1;
        $sumRow3 = $rowNum + 2;
        $sumRow4 = $rowNum + 3;
        $sumRow5 = $rowNum + 4;
        $sumRow6 = $rowNum + 5;

        for ($r = $sumRow1; $r <= $sumRow6; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(20);
        }

        $sheet->mergeCells("A{$sumRow1}:B{$sumRow6}");
        $sheet->setCellValue("A{$sumRow1}", 'SUMMARY');
        $sheet->getStyle("A{$sumRow1}:B{$sumRow6}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '999999'],
            ],
        ]);

        $labelStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'B7B7B7'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        $valueStyle = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFFFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        // Row 1: Expected Time
        $sheet->setCellValue("C{$sumRow1}", 'Expected Time*');
        $sheet->setCellValue("D{$sumRow1}", $this->formatSecondsToTime($totalExpectedSeconds));
        $sheet->getStyle("C{$sumRow1}")->applyFromArray($labelStyle);
        $sheet->getStyle("D{$sumRow1}")->applyFromArray($valueStyle);

        // Row 2: Time Given
        $sheet->setCellValue("C{$sumRow2}", 'Time Given*');
        $sheet->setCellValue("D{$sumRow2}", $this->formatSecondsToTime($totalTimeGivenSeconds));
        $sheet->getStyle("C{$sumRow2}")->applyFromArray($labelStyle);
        $sheet->getStyle("D{$sumRow2}")->applyFromArray($valueStyle);

        // Row 3: Difference
        $totalDiffSeconds = $totalTimeGivenSeconds - $totalExpectedSeconds;
        
        $totalDiffRatio = 1.0;
        if ($totalDiffSeconds <= -3600) {
            $totalDiffRatio = 0.0;
        } elseif ($totalDiffSeconds < 0) {
            $totalDiffRatio = ($totalDiffSeconds + 3600) / 3600;
        }
        $totalDiffColors = $this->getDynamicColorForRatio($totalDiffRatio);
        $diffBg = $totalDiffColors['bg'];
        $diffFontColor = $totalDiffColors['text'];
        
        $sheet->setCellValue("C{$sumRow3}", 'Difference*');
        $sheet->setCellValue("D{$sumRow3}", $this->formatSecondsToTime($totalDiffSeconds));
        
        $diffStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => $diffFontColor],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $diffBg],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle("C{$sumRow3}")->applyFromArray($diffStyle);
        
        $diffValStyle = $diffStyle;
        $diffValStyle['alignment']['horizontal'] = Alignment::HORIZONTAL_CENTER;
        $sheet->getStyle("D{$sumRow3}")->applyFromArray($diffValStyle);

        // Row 4: Leaves
        $sheet->setCellValue("C{$sumRow4}", 'Leaves (Paid)*');
        $sheet->setCellValue("D{$sumRow4}", $totalLeaves);
        $sheet->getStyle("C{$sumRow4}")->applyFromArray($labelStyle);
        $sheet->getStyle("D{$sumRow4}")->applyFromArray($valueStyle);

        // Row 5: Unpaid Leaves
        $sheet->setCellValue("C{$sumRow5}", 'Unpaid Leaves*');
        $sheet->setCellValue("D{$sumRow5}", $totalUnpaidLeaves);
        $sheet->getStyle("C{$sumRow5}")->applyFromArray($labelStyle);
        $sheet->getStyle("D{$sumRow5}")->applyFromArray($valueStyle);

        // Row 6: Absents
        $sheet->setCellValue("C{$sumRow6}", 'Absents*');
        $sheet->setCellValue("D{$sumRow6}", $totalAbsents);
        $sheet->getStyle("C{$sumRow6}")->applyFromArray($labelStyle);
        $sheet->getStyle("D{$sumRow6}")->applyFromArray($valueStyle);

        $canViewFinancials = auth()->user() && (auth()->user()->isSuperAdmin() || (int)auth()->id() === (int)$user->id);
        $hasExpectedSalary = !is_null($user->expected_salary_amount);
        $expectedSalary = $user->expected_salary_amount ?? 0;

        if ($canViewFinancials) {
            if ($hasExpectedSalary) {
                // Salary section and settings calculations
                $settingsFile = storage_path('app/salary_deduction_settings.json');
                $settings = [
                    'rule' => 'calendar_days',
                    'recalculate_all' => true,
                    'effective_month' => null,
                    'previous_rule' => null
                ];
                if (file_exists($settingsFile)) {
                    $settings = array_merge($settings, json_decode(file_get_contents($settingsFile), true) ?: []);
                }

                $targetYearMonth = $startDate->format('Y-m');
                $ruleToUse = $settings['rule'];
                if (!$settings['recalculate_all'] && $settings['effective_month']) {
                    if ($targetYearMonth < $settings['effective_month']) {
                        $ruleToUse = $settings['previous_rule'] ?? 'calendar_days';
                    }
                }

                if ($ruleToUse === 'working_days') {
                    $workingDaysInMonth = 0;
                    $calcDate = $startDate->copy()->startOfMonth();
                    $monthEnd = $startDate->copy()->endOfMonth();
                    while ($calcDate->lte($monthEnd)) {
                        $dayOfWeek = $calcDate->dayOfWeekIso;
                        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                            $workingDaysInMonth++;
                        }
                        $calcDate->addDay();
                    }
                    $divisionFactor = $workingDaysInMonth ?: 22;
                } elseif ($ruleToUse === 'fixed_30') {
                    $divisionFactor = 30.0;
                } else {
                    $divisionFactor = $startDate->daysInMonth ?: 30;
                }

                $dailyRate = $expectedSalary / $divisionFactor;
                $daysBeforeJoin = $this->calculateDaysBeforeJoin($user, $startDate, $endDate, $ruleToUse);
                $totalDeductedDays = $daysBeforeJoin + $totalAbsents + $totalUnpaidLeaves;
                $calculatedDeductions = round($dailyRate * min($divisionFactor, $totalDeductedDays), 2);
                $calculatedTakeAway = max(0, $expectedSalary - $calculatedDeductions);

                // Actual Salary
                $sheet->setCellValue("E{$sumRow1}", 'Actual Salary');
                $sheet->setCellValue("F{$sumRow1}", number_format($expectedSalary));
                $sheet->getStyle("E{$sumRow1}")->applyFromArray($labelStyle);
                $sheet->getStyle("F{$sumRow1}")->applyFromArray($valueStyle);

                // Deductions
                $sheet->setCellValue("E{$sumRow2}", 'Deductions*');
                $sheet->setCellValue("F{$sumRow2}", number_format($calculatedDeductions));
                $sheet->getStyle("E{$sumRow2}")->applyFromArray($labelStyle);
                $sheet->getStyle("F{$sumRow2}")->applyFromArray($valueStyle);

                // Take Away
                $sheet->setCellValue("E{$sumRow3}", 'Take Away*');
                $sheet->setCellValue("F{$sumRow3}", number_format($calculatedTakeAway));
                $sheet->getStyle("E{$sumRow3}")->applyFromArray($labelStyle);
                $sheet->getStyle("F{$sumRow3}")->applyFromArray($valueStyle);
            } else {
                // Actual Salary
                $sheet->setCellValue("E{$sumRow1}", 'Actual Salary');
                $sheet->setCellValue("F{$sumRow1}", '—');
                $sheet->getStyle("E{$sumRow1}")->applyFromArray($labelStyle);
                $sheet->getStyle("F{$sumRow1}")->applyFromArray($valueStyle);

                // Deductions
                $sheet->setCellValue("E{$sumRow2}", 'Deductions*');
                $sheet->setCellValue("F{$sumRow2}", '—');
                $sheet->getStyle("E{$sumRow2}")->applyFromArray($labelStyle);
                $sheet->getStyle("F{$sumRow2}")->applyFromArray($valueStyle);

                // Take Away
                $sheet->setCellValue("E{$sumRow3}", 'Take Away*');
                $sheet->setCellValue("F{$sumRow3}", '—');
                $sheet->getStyle("E{$sumRow3}")->applyFromArray($labelStyle);
                $sheet->getStyle("F{$sumRow3}")->applyFromArray($valueStyle);
            }
        } else {
            // Confidential to Super Admin - Show italicized "Hidden"
            $hiddenValueStyle = array_merge($valueStyle, [
                'font' => ['italic' => true, 'color' => ['rgb' => '777777']],
            ]);

            // Actual Salary
            $sheet->setCellValue("E{$sumRow1}", 'Actual Salary');
            $sheet->setCellValue("F{$sumRow1}", 'Hidden');
            $sheet->getStyle("E{$sumRow1}")->applyFromArray($labelStyle);
            $sheet->getStyle("F{$sumRow1}")->applyFromArray($hiddenValueStyle);

            // Deductions
            $sheet->setCellValue("E{$sumRow2}", 'Deductions*');
            $sheet->setCellValue("F{$sumRow2}", 'Hidden');
            $sheet->getStyle("E{$sumRow2}")->applyFromArray($labelStyle);
            $sheet->getStyle("F{$sumRow2}")->applyFromArray($hiddenValueStyle);

            // Take Away
            $sheet->setCellValue("E{$sumRow3}", 'Take Away*');
            $sheet->setCellValue("F{$sumRow3}", 'Hidden');
            $sheet->getStyle("E{$sumRow3}")->applyFromArray($labelStyle);
            $sheet->getStyle("F{$sumRow3}")->applyFromArray($hiddenValueStyle);
        }

        // Empty cells in E4/F4, E5/F5, E6/F6
        $sheet->getStyle("E{$sumRow4}:F{$sumRow6}")->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFFFF'],
            ],
        ]);

        // Comments
        $sheet->mergeCells("G{$sumRow1}:S{$sumRow1}");
        $sheet->setCellValue("G{$sumRow1}", 'COMMENTS:');
        $sheet->getStyle("G{$sumRow1}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'B7B7B7'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->mergeCells("G{$sumRow2}:S{$sumRow6}");
        $sheet->setCellValue("G{$sumRow2}", $request->input('comment', ''));
        $sheet->getStyle("G{$sumRow2}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("G{$sumRow2}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("G{$sumRow2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("G{$sumRow2}:S{$sumRow6}")->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFFFF'],
            ],
        ]);

        // Colour Codes Block
        $sheet->mergeCells("T{$sumRow1}:T{$sumRow6}");
        $sheet->setCellValue("T{$sumRow1}", "COLOUR\nCODES");
        $sheet->getStyle("T{$sumRow1}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '000000'],
            ],
        ]);

        $colorCodes = [
            ['label' => 'Absent', 'bg' => 'A64D79', 'fg' => 'FFFFFF'],
            ['label' => 'Leave', 'bg' => 'FFFF00', 'fg' => '000000'],
            ['label' => 'Unpaid Leave', 'bg' => '4452adff', 'fg' => '000000'],
            ['label' => 'Holiday', 'bg' => 'B45F06', 'fg' => 'FFFFFF'],
            ['label' => 'Positive Diff', 'bg' => '6AA84F', 'fg' => 'FFFFFF'],
            ['label' => 'Negative Diff', 'bg' => 'FF0000', 'fg' => 'FFFFFF'],
        ];

        foreach ($colorCodes as $index => $code) {
            $r = $sumRow1 + $index;
            $sheet->mergeCells("U{$r}:Y{$r}");
            $sheet->setCellValue("U{$r}", $code['label']);
            $sheet->getStyle("U{$r}:Y{$r}")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => $code['fg']],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $code['bg']],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        }

        // Borders for Summary Block (A-F)
        $sheet->getStyle("A{$sumRow1}:F{$sumRow6}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Borders for Comments Block (G-S)
        $sheet->getStyle("G{$sumRow1}:S{$sumRow6}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Borders for Colour Codes Block (T-Y)
        $sheet->getStyle("T{$sumRow1}:Y{$sumRow6}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        $fileName = str_replace(' ', '_', $user->name) . '_Track_Record_' . $startDate->timezone($tz)->format('M_Y') . '.xlsx';

        return response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function formatSecondsToTime($seconds)
    {
        $isNegative = $seconds < 0;
        $seconds = abs($seconds);
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return ($isNegative ? '-' : '') . sprintf('%01d:%02d:%02d', $hours, $minutes, $secs);
    }

    public function reconstructSessions($messages, $startDate, $endDate, $tz = 'Asia/Karachi')
    {
        $sessionsByDate = [];
        
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $sessionsByDate[$current->format('Y-m-d')] = [];
            $current->addDay();
        }

        $activeCheckIn = null;
        $activeCheckInIsVirtual = false;
        $activeCheckInReason = '';
        $lastCheckoutTime = null;
        $now = Carbon::now($tz);

        // 1. Filter out all pending or rejected messages (except pending auto-appended ones)
        $filtered = [];
        foreach ($messages as $msg) {
            if (($msg->approval_status === 'rejected') || ($msg->approval_status === 'pending' && !$msg->is_auto_appended) || ($msg->approval_status === 'merged')) {
                continue;
            }
            $filtered[] = $msg;
        }

        // 1b. Pre-process $filtered to find forgotten "back" messages.
        // If a break/brb is followed directly by a signout/sign out within 6 hours,
        // we inject a virtual "back" 1 minute after the break to heal the session.
        $tempFiltered = [];
        $count = count($filtered);
        for ($i = 0; $i < $count; $i++) {
            $msg = $filtered[$i];
            $tempFiltered[] = $msg;
            
            if ($i < $count - 1) {
                $nextMsg = $filtered[$i + 1];
                $text = strtolower(trim($msg->message_text));
                $nextText = strtolower(trim($nextMsg->message_text));
                
                $isBreak = preg_match('/^(break|brb)/i', $text);
                $isSignOut = (str_contains($nextText, 'signout') || str_contains($nextText, 'sign out'));
                
                if ($isBreak && $isSignOut) {
                    $diffInHours = $nextMsg->message_at->diffInHours($msg->message_at);
                    if ($diffInHours < 6) {
                        // Inject a virtual "back" 1 minute after the break
                        $virtualBackTime = $msg->message_at->copy()->addMinute();
                        $virtualBackMsg = (object)[
                            'id' => 'virtual_back_forgotten_' . ($msg->id ?? rand(1000, 9999)) . '_' . rand(1000, 9999),
                            'approval_status' => 'approved',
                            'message_text' => 'back',
                            'message_at' => $virtualBackTime,
                            'is_auto_appended' => true,
                            'is_virtual_in' => true,
                            'slack_user_id' => $msg->slack_user_id ?? null,
                        ];
                        $tempFiltered[] = $virtualBackMsg;
                    }
                }
            }
        }
        $filtered = $tempFiltered;

        // 2. Group the remaining messages by their local date (using timezone $tz)
        $groupedByDay = [];
        foreach ($filtered as $msg) {
            $msgLocalDateStr = $msg->message_at->timezone($tz)->format('Y-m-d');
            $groupedByDay[$msgLocalDateStr][] = $msg;
        }

        // 3. For each day, sort and check for start-of-day sequence healing
        $processedMessages = [];
        $sortedDates = array_keys($groupedByDay);
        sort($sortedDates);

        foreach ($sortedDates as $dateStr) {
            $dayMsgs = $groupedByDay[$dateStr];
            
            // Sort day messages chronologically by their parsed time
            usort($dayMsgs, function($a, $b) use ($tz, $dateStr) {
                $timeA = $this->parseMessageTime($a, $dateStr, $tz);
                $timeB = $this->parseMessageTime($b, $dateStr, $tz);
                if ($timeA->timestamp === $timeB->timestamp) {
                    $idA = isset($a->id) ? (int)$a->id : 0;
                    $idB = isset($b->id) ? (int)$b->id : 0;
                    if ($idA !== $idB) {
                        return $idA <=> $idB;
                    }
                    $tsA = isset($a->slack_ts) ? (float)$a->slack_ts : 0;
                    $tsB = isset($b->slack_ts) ? (float)$b->slack_ts : 0;
                    return $tsA <=> $tsB;
                }
                return $timeA->timestamp <=> $timeB->timestamp;
            });

            if (!empty($dayMsgs)) {
                $firstMsg = $dayMsgs[0];
                $firstParsedTime = $this->parseMessageTime($firstMsg, $dateStr, $tz);
                $firstText = strtolower(trim($firstMsg->message_text));

                $isOvernightContinuation = false;
                $isOvernightBreakContinuation = false;
                $slackUserId = $firstMsg->slack_user_id;
                
                $prevMsg = \App\Models\SlackMessage::where('slack_user_id', $slackUserId)
                    ->where('message_at', '<', $firstParsedTime->copy()->timezone('UTC'))
                    ->where(function ($q) {
                        $q->whereNull('approval_status')
                          ->orWhere('approval_status', '!=', 'rejected');
                    })
                    ->latest('message_at')
                    ->first();

                if ($prevMsg) {
                    $prevText = strtolower(trim($prevMsg->message_text));
                    $isPrevCheckIn = (str_contains($prevText, 'signin') || str_contains($prevText, 'sign in') || preg_match('/^back\b/i', $prevText));
                    if ($isPrevCheckIn) {
                        $prevTime = $prevMsg->message_at->timezone($tz);
                        if ($prevTime->diffInHours($firstParsedTime) <= 14) {
                            $isOvernightContinuation = true;
                        }
                    }
                    
                    $isPrevBreak = preg_match('/^(break|brb)/i', $prevText);
                    if ($isPrevBreak) {
                        $prevTime = $prevMsg->message_at->timezone($tz);
                        if ($prevTime->diffInHours($firstParsedTime) < 6) {
                            $isOvernightBreakContinuation = true;
                        }
                    }
                }

                $isFirstCheckOut = (str_contains($firstText, 'signout') || str_contains($firstText, 'sign out') || preg_match('/^(break|brb)/i', $firstText));
                $isFirstBack = preg_match('/^back\b/i', $firstText);

                if ($isFirstCheckOut) {
                    if (!$isOvernightContinuation) {
                        // Inject virtual Sign In 1 minute before this checkout
                        $virtualTime = $firstParsedTime->copy()->subMinute();
                        
                        $virtualMsg = (object)[
                            'id' => 'virtual_in_' . $dateStr . '_' . ($firstMsg->id ?? rand(1000, 9999)),
                            'approval_status' => 'approved',
                            'message_text' => 'signin',
                            'message_at' => $virtualTime->copy()->timezone('UTC'),
                            'is_auto_appended' => true,
                            'is_virtual_in' => true,
                        ];
                        array_unshift($dayMsgs, $virtualMsg);
                    }
                } elseif ($isFirstBack) {
                    if ($isOvernightBreakContinuation) {
                        // Continuous break crossing midnight. No virtual messages needed!
                    } elseif ($isOvernightContinuation) {
                        // Inject virtual Break 1 minute before
                        $virtualBreakTime = $firstParsedTime->copy()->subMinute();

                        $virtualBreakMsg = (object)[
                            'id' => 'virtual_break_' . $dateStr . '_' . ($firstMsg->id ?? rand(1000, 9999)),
                            'approval_status' => 'approved',
                            'message_text' => 'break',
                            'message_at' => $virtualBreakTime->copy()->timezone('UTC'),
                            'is_auto_appended' => true,
                            'is_virtual_out' => true,
                        ];
                        array_unshift($dayMsgs, $virtualBreakMsg);
                    } else {
                        // Inject virtual Sign In (2 mins before) and virtual Break (1 min before)
                        $virtualInTime = $firstParsedTime->copy()->subMinutes(2);
                        $virtualBreakTime = $firstParsedTime->copy()->subMinute();

                        $virtualInMsg = (object)[
                            'id' => 'virtual_in_' . $dateStr . '_' . ($firstMsg->id ?? rand(1000, 9999)),
                            'approval_status' => 'approved',
                            'message_text' => 'signin',
                            'message_at' => $virtualInTime->copy()->timezone('UTC'),
                            'is_auto_appended' => true,
                            'is_virtual_in' => true,
                        ];

                        $virtualBreakMsg = (object)[
                            'id' => 'virtual_break_' . $dateStr . '_' . ($firstMsg->id ?? rand(1000, 9999)),
                            'approval_status' => 'approved',
                            'message_text' => 'break',
                            'message_at' => $virtualBreakTime->copy()->timezone('UTC'),
                            'is_auto_appended' => true,
                            'is_virtual_out' => true,
                        ];
                        array_unshift($dayMsgs, $virtualInMsg, $virtualBreakMsg);
                    }
                }
            }

            foreach ($dayMsgs as $m) {
                $processedMessages[] = $m;
            }
        }

        // -----------------------------------------------------------------------
        // Pre-process messages using the new 60-min daily break balance system.
        //
        // We build two lookup maps per user-day:
        //   $hiddenBreakIds[id]  = true  → both break and back are hidden (treated as continuous work)
        //   $splitBreakMap[break_msg_id] = Carbon split_time → session ends at split_time, back = sign-in
        // -----------------------------------------------------------------------
        $hiddenBreakIds        = [];
        $splitBreakMap         = [];
        $carryoverSplitMap     = [];
        $carryoverCoveredDates = [];

        // Collect (slackUserId, dateStr) combos from processed messages
        $balanceKeysSeen = [];
        foreach ($processedMessages as $msg) {
            $uid  = $msg->slack_user_id ?? null;
            $date = $msg->message_at->timezone($tz)->format('Y-m-d');
            if ($uid && !isset($balanceKeysSeen["{$uid}_{$date}"])) {
                $balanceKeysSeen["{$uid}_{$date}"] = [$uid, $date];
            }
        }

        foreach ($balanceKeysSeen as [$uid, $date]) {
            // Try cache first; fall back to fresh computation
            $balanceData = \Illuminate\Support\Facades\Cache::get("break_balance_{$uid}_{$date}")
                ?? \App\Models\SlackMessage::computeDayBreakBalance($uid, $date);

            foreach ($balanceData['break_pairs'] as $pair) {
                if ($pair['is_carryover'] ?? false) {
                    if (!$pair['break_exceeded']) {
                        // Case A carryover: back on Day 2 is within Day 2 balance -> hide back so session continues through
                        if ($pair['back_msg_id'] !== null) {
                            $hiddenBreakIds[$pair['back_msg_id']] = true;
                        }
                        $carryoverCoveredDates[$date] = true;
                    } else {
                        // Case B/C carryover: split occurs on Day 2
                        if ($pair['back_msg_id'] !== null) {
                            $carryoverSplitMap[$pair['back_msg_id']] = $pair;
                        }
                    }
                } else {
                    if (!$pair['break_exceeded'] && $pair['back_msg_id'] !== null) {
                        // Auto-appended breaks (e.g. 1-min virtual break for double-back) must remain
                        // visible on the timesheet with their warning tag even though they are within
                        // balance. Do NOT add them to hiddenBreakIds.
                        if (($pair['is_auto_appended_break'] ?? false) || ($pair['is_auto_appended_back'] ?? false)) {
                            // Still do NOT hide — but no split needed either since balance covered it
                            // reconstructSessions will render it as a normal visible break/back pair
                        } else {
                            // Fully within balance — hide break on today
                            $hiddenBreakIds[$pair['break_msg_id']] = true;
                            // Only hide back if it occurred on the same calendar day (Day 2 decides for carryovers)
                            if (!empty($pair['back_time'])) {
                                $backLocalDate = Carbon::parse($pair['back_time'])->timezone($tz)->format('Y-m-d');
                                if ($backLocalDate === $date) {
                                    $hiddenBreakIds[$pair['back_msg_id']] = true;
                                }
                            }
                        }

                    } elseif ($pair['break_exceeded'] && $pair['split_time'] !== null) {
                        if (!$pair['is_fully_session_out']) {
                            // Case B (Partial split): session ends at split_time (mid-break when balance ran out).
                            // We record split_time here so checkout occurs at split_time and is highlighted purple.
                            $splitBreakMap[$pair['break_msg_id']] = $pair['split_time'];
                        }
                        // Case C (Balance was already 0): break behaves as a normal checkout at its actual time (normal white).
                    }
                }
            }

        }

        $currentProcessingDate = null;
        foreach ($processedMessages as $msg) {
            $msgLocalDateStr = $msg->message_at->timezone($tz)->format('Y-m-d');

            // If entering a new date that has a carryover break covered by Day 2 balance,
            // and we have no active check-in (e.g. Day 1 checked out before midnight),
            // start active check-in at midnight (12:00 AM) of this date!
            if ($currentProcessingDate !== $msgLocalDateStr) {
                $currentProcessingDate = $msgLocalDateStr;
                if (!$activeCheckIn && (isset($carryoverCoveredDates[$msgLocalDateStr]) || isset($carryoverSplitMap[$msg->id]))) {
                    $activeCheckIn = Carbon::parse($msgLocalDateStr, $tz)->startOfDay();
                    $activeCheckInIsVirtual = false;
                    $activeCheckInReason = '';
                }
            }

            // Skip balance-within-break messages (hidden breaks & their backs)
            if (isset($hiddenBreakIds[$msg->id])) {
                continue;
            }

            $msgLocalDateStr = $msg->message_at->timezone($tz)->format('Y-m-d');
            $parsedTime = $this->parseMessageTime($msg, $msgLocalDateStr, $tz);
            $text = strtolower(trim($msg->message_text));

            $isCheckIn = (str_contains($text, 'signin') || str_contains($text, 'sign in') || preg_match('/^back\b/i', $text));
            $isCheckOut = (str_contains($text, 'signout') || str_contains($text, 'sign out') || preg_match('/^(break|brb)/i', $text));

            if ($isCheckIn) {
                // If this check-in is the 'back' of an overnight carryover break that exceeded Day 2 balance
                if ($msg->id && isset($carryoverSplitMap[$msg->id])) {
                    $cPair = $carryoverSplitMap[$msg->id];
                    $splitTime = Carbon::parse($cPair['split_time'])->timezone($tz);

                    if ($activeCheckIn && $splitTime->gt($activeCheckIn)) {
                        $this->addSessionAndSplit(
                            $sessionsByDate,
                            $activeCheckIn,
                            $splitTime,
                            false,
                            $startDate,
                            $endDate,
                            $tz,
                            $activeCheckInIsVirtual,
                            $activeCheckInReason,
                            true,
                            'Break Balance Exhausted'
                        );
                    }

                    $activeCheckIn = $parsedTime;
                    $activeCheckInIsVirtual = (bool)($msg->is_auto_appended ?? false);
                    $activeCheckInReason = $activeCheckInIsVirtual ? (preg_match('/^back/i', $text) ? 'Auto-Appended Back' : 'Auto-Appended Sign In') : '';
                    continue;
                }

                if ($activeCheckIn) {
                    // Violation: Checked in twice without checking out (missing break)
                    // We assume a virtual break occurred 1 minute before this check-in
                    $virtualBreak = $parsedTime->copy()->subMinute();
                    // Safety guard: ensure virtual break is after activeCheckIn
                    if ($virtualBreak->lte($activeCheckIn)) {
                        $virtualBreak = $activeCheckIn->copy()->addSecond();
                    }

                    $this->addSessionAndSplit(
                        $sessionsByDate,
                        $activeCheckIn,
                        $virtualBreak,
                        false,
                        $startDate,
                        $endDate,
                        $tz,
                        $activeCheckInIsVirtual,
                        $activeCheckInReason,
                        true,
                        'Auto-Appended Break'
                    );

                    // Transition to new check-in
                    $activeCheckIn = $parsedTime;
                    $activeCheckInIsVirtual = (bool)($msg->is_auto_appended ?? false);
                    $activeCheckInReason = $activeCheckInIsVirtual ? (preg_match('/^back/i', $text) ? 'Auto-Appended Back' : 'Auto-Appended Sign In') : '';
                } else {
                    // Valid check-in
                    $activeCheckIn = $parsedTime;
                    $activeCheckInIsVirtual = (bool)($msg->is_auto_appended ?? false);
                    $activeCheckInReason = $activeCheckInIsVirtual ? (preg_match('/^back/i', $text) ? 'Auto-Appended Back' : 'Auto-Appended Sign In') : '';
                }
            } elseif ($isCheckOut) {
                $outIsVirtual = (bool) $msg->is_auto_appended;
                $outVirtualReason = $outIsVirtual ? (preg_match('/^(break|brb)/i', $text) ? 'Auto-Appended Break' : 'Auto-Appended Sign Out') : '';

                // Balance partial-split: if this break message has a split_time, the session ends
                // at split_time (when balance ran out) rather than at the break start.
                // The portion from break_start to split_time is hidden (balance consumed).
                $msgId = $msg->id ?? null;
                if ($msgId && isset($splitBreakMap[$msgId])) {
                    $effectiveCheckoutTime = Carbon::parse($splitBreakMap[$msgId])->timezone($tz);
                    $outIsVirtual = true;
                    $outVirtualReason = 'Break Balance Exhausted';
                } else {
                    $effectiveCheckoutTime = $parsedTime;
                }

                if ($activeCheckIn) {
                    // Valid checkout
                    if ($effectiveCheckoutTime->gt($activeCheckIn)) {
                        $this->addSessionAndSplit(
                            $sessionsByDate,
                            $activeCheckIn,
                            $effectiveCheckoutTime,
                            false,
                            $startDate,
                            $endDate,
                            $tz,
                            $activeCheckInIsVirtual,
                            $activeCheckInReason,
                            $outIsVirtual,
                            $outVirtualReason
                        );
                        $activeCheckIn = null;
                        $activeCheckInIsVirtual = false;
                        $activeCheckInReason = '';
                        $lastCheckoutTime = $effectiveCheckoutTime;
                    }
                } else {
                    // Violation: Checked out twice without checking in (missing back/signin)
                    if (!$lastCheckoutTime || $lastCheckoutTime->timezone($tz)->format('Y-m-d') !== $msgLocalDateStr) {
                        // First checkout of the day/period: assume signin occurred 1 minute before
                        $virtualSignIn = $parsedTime->copy()->subMinute();
                        $this->addSessionAndSplit(
                            $sessionsByDate,
                            $virtualSignIn,
                            $parsedTime,
                            false,
                            $startDate,
                            $endDate,
                            $tz,
                            true,
                            'Auto-Appended Sign In',
                            $outIsVirtual,
                            $outVirtualReason
                        );
                        $lastCheckoutTime = $parsedTime;
                    } else {
                        // Subsequent checkout: assume back occurred 1 minute after previous checkout
                        $virtualBack = $lastCheckoutTime->copy()->addMinute();
                        // Safety guard: ensure virtual back is before this checkout
                        if ($virtualBack->gte($parsedTime)) {
                            $virtualBack = $parsedTime->copy()->subSecond();
                        }
                        $this->addSessionAndSplit(
                            $sessionsByDate,
                            $virtualBack,
                            $parsedTime,
                            false,
                            $startDate,
                            $endDate,
                            $tz,
                            true,
                            'Auto-Appended Back',
                            $outIsVirtual,
                            $outVirtualReason
                        );
                        $lastCheckoutTime = $parsedTime;
                    }
                }
            }
        }

        if ($activeCheckIn) {
            $checkInDateStr = $activeCheckIn->format('Y-m-d');
            $todayDateStr = $now->format('Y-m-d');

            if ($checkInDateStr === $todayDateStr) {
                if ($now->gt($activeCheckIn)) {
                    $this->addSessionAndSplit(
                        $sessionsByDate,
                        $activeCheckIn,
                        $now,
                        true,
                        $startDate,
                        $endDate,
                        $tz,
                        $activeCheckInIsVirtual,
                        $activeCheckInReason,
                        false,
                        ''
                    );
                }
            } else {
                if (isset($sessionsByDate[$checkInDateStr])) {
                    $sessionsByDate[$checkInDateStr][] = [
                        'in' => $activeCheckIn,
                        'out' => null,
                        'is_active' => false,
                        'is_virtual_in' => $activeCheckInIsVirtual,
                        'virtual_in_reason' => $activeCheckInReason,
                        'is_virtual_out' => false,
                        'virtual_out_reason' => '',
                    ];
                }
            }
        }

        return $sessionsByDate;
    }

    private function addSessionAndSplit(&$sessionsByDate, $in, $out, $isActive, $startDate, $endDate, $tz, $inIsVirtual = false, $inVirtualReason = '', $outIsVirtual = false, $outVirtualReason = '')
    {
        $inLocalDate = $in->copy()->timezone($tz);
        $outLocalDate = $out->copy()->timezone($tz);

        if ($inLocalDate->format('Y-m-d') === $outLocalDate->format('Y-m-d')) {
            $dateStr = $inLocalDate->format('Y-m-d');
            if (isset($sessionsByDate[$dateStr])) {
                $sessionsByDate[$dateStr][] = [
                    'in' => $in,
                    'out' => $out,
                    'is_active' => $isActive,
                    'is_virtual_in' => $inIsVirtual,
                    'virtual_in_reason' => $inVirtualReason,
                    'is_virtual_out' => $outIsVirtual,
                    'virtual_out_reason' => $outVirtualReason,
                ];
            }
            return;
        }

        $currentIn = $inLocalDate->copy();
        $isFirst = true;
        while ($currentIn->lt($outLocalDate)) {
            $currentInDateStr = $currentIn->format('Y-m-d');
            $nextMidnight = $currentIn->copy()->addDay()->startOfDay();
            
            if ($nextMidnight->gte($outLocalDate)) {
                if (isset($sessionsByDate[$currentInDateStr])) {
                    $sessionsByDate[$currentInDateStr][] = [
                        'in' => $currentIn,
                        'out' => $outLocalDate,
                        'is_active' => $isActive,
                        'is_virtual_in' => $isFirst ? $inIsVirtual : false,
                        'virtual_in_reason' => $isFirst ? $inVirtualReason : '',
                        'is_virtual_out' => $outIsVirtual,
                        'virtual_out_reason' => $outVirtualReason,
                    ];
                }
                break;
            } else {
                if (isset($sessionsByDate[$currentInDateStr])) {
                    $sessionsByDate[$currentInDateStr][] = [
                        'in' => $currentIn,
                        'out' => $nextMidnight->copy()->subSecond(),
                        'is_active' => false,
                        'is_virtual_in' => $isFirst ? $inIsVirtual : false,
                        'virtual_in_reason' => $isFirst ? $inVirtualReason : '',
                        'is_virtual_out' => false,
                        'virtual_out_reason' => '',
                    ];
                }
                $currentIn = $nextMidnight->copy();
                $isFirst = false;
            }
        }
    }

    /**
     * Return every raw Slack event for a single calendar day.
     * Called by the frontend when HR expands a row in Edit Mode.
     */
    public function getDayRawEvents(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date'    => 'required|date_format:Y-m-d',
        ]);

        $user        = User::findOrFail($request->user_id);
        $slackUserId = $user->slackMapping?->slack_user_id;

        if (!$slackUserId) {
            return response()->json(['status' => 'error', 'message' => 'User has no Slack mapping.'], 422);
        }

        $tz         = 'Asia/Karachi';
        $dateStr    = $request->date;
        $carbonDate = Carbon::parse($dateStr, $tz);
        $dayStart   = $carbonDate->copy()->startOfDay()->timezone('UTC');
        $dayEnd     = $carbonDate->copy()->endOfDay()->timezone('UTC');

        // Fetch all real + approved/pending-auto messages for this day
        $msgs = SlackMessage::where('slack_user_id', $slackUserId)
            ->whereBetween('message_at', [$dayStart, $dayEnd])
            ->where(function ($q) {
                $q->where(function ($sub) {
                    // Real (non-auto) messages: null/approved/pending_edit
                    $sub->where('is_auto_appended', false)
                        ->where(function ($s) {
                            $s->whereNull('approval_status')
                              ->orWhere('approval_status', 'approved')
                              ->orWhere('approval_status', 'pending_edit');
                        });
                })
                ->orWhere(function ($sub) {
                    // Auto-appended messages: approved or pending (gap-fillers)
                    $sub->where('is_auto_appended', true)
                        ->whereIn('approval_status', ['approved', 'pending_edit', 'pending']);
                });
            })
            ->orderBy('message_at', 'asc')
            ->get();

        $events = $msgs->map(function ($msg) use ($tz) {
            // Normalise message_text to a clean type token
            $raw  = strtolower(trim($msg->message_text));
            if (str_starts_with($raw, 'signin') || str_starts_with($raw, 'sign in')) {
                $type = 'signin';
            } elseif (str_starts_with($raw, 'signout') || str_starts_with($raw, 'sign out')) {
                $type = 'signout';
            } elseif (str_starts_with($raw, 'break') || str_starts_with($raw, 'brb')) {
                $type = 'break';
            } elseif (str_starts_with($raw, 'back')) {
                $type = 'back';
            } else {
                $type = $raw;
            }

            $timeFormatted = $msg->message_at->timezone($tz)->format('h:i:s A');
            $isAuto = (bool)$msg->is_auto_appended;
            $autoReason = '';
            if ($isAuto) {
                if ($type === 'signin') {
                    $autoReason = 'Auto-Appended Sign In';
                } elseif ($type === 'back') {
                    $autoReason = 'Auto-Appended Back';
                } elseif ($type === 'break') {
                    $autoReason = 'Auto-Appended Break';
                } elseif ($type === 'signout') {
                    $autoReason = 'Auto-Appended Sign Out';
                } else {
                    $autoReason = 'Auto-Appended ' . ucfirst($type);
                }
            }

            return [
                'id'               => $msg->id,
                'type'             => $type,
                'time'             => $timeFormatted,
                'time_utc'         => $msg->message_at->toIso8601String(),
                'is_auto_appended' => $isAuto,
                'virtual_reason'   => $autoReason,
                'approval_status'  => $msg->approval_status,
                'break_exceeded'   => (bool)$msg->break_exceeded,
            ];
        });

        return response()->json([
            'status' => 'success',
            'date'   => $dateStr,
            'events' => $events,
        ]);
    }

    public function saveOverrides(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'      => 'required|exists:users,id',
            'date_entries' => 'required|array',
        ]);

        $user        = User::findOrFail($request->user_id);
        $slackUserId = $user->slackMapping?->slack_user_id;

        if (!$slackUserId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'The selected user is not mapped to any Slack User ID.'
            ], 422);
        }

        $tz = 'Asia/Karachi';
        $now = Carbon::now($tz);
        $todayStr = $now->format('Y-m-d');
        $nowWithBuffer = $now->copy()->addMinute();

        // -----------------------------------------------------------------------
        // Pre-save validation pass — validate ALL dates before touching the DB
        // -----------------------------------------------------------------------
        foreach ($request->date_entries as $dateStr => $entry) {
            // Block future dates
            if ($dateStr > $todayStr) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Validation Error: Attendance cannot be logged for future date {$dateStr}."
                ], 422);
            }

            $mode = $entry['mode'] ?? 'sessions';

            if ($mode === 'raw_events') {
                // ---- Validate raw_events mode ----
                $events = $entry['events'] ?? [];

                if (empty($events)) {
                    // Empty event list is allowed — clears the day
                    continue;
                }

                // Must start with signin
                $firstType = strtolower($events[0]['type'] ?? '');
                if (!in_array($firstType, ['signin', 'sign in', 'signin'])) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => "Validation Error on {$dateStr}: The first event must be a sign-in."
                    ], 422);
                }

                $timeline   = [];
                $prevType   = null;
                foreach ($events as $idx => $ev) {
                    $type    = strtolower(trim($ev['type'] ?? ''));
                    $timeStr = trim($ev['time'] ?? '');

                    if (empty($timeStr)) {
                        return response()->json([
                            'status'  => 'error',
                            'message' => "Validation Error on {$dateStr}: Event #" . ($idx + 1) . " has an empty time."
                        ], 422);
                    }

                    // Parse time
                    try {
                        $parsed = Carbon::createFromFormat('Y-m-d h:i:s A', $dateStr . ' ' . $timeStr, $tz);
                    } catch (\Exception $e) {
                        try {
                            $parsed = Carbon::createFromFormat('Y-m-d h:i A', $dateStr . ' ' . $timeStr, $tz);
                        } catch (\Exception $e2) {
                            return response()->json([
                                'status'  => 'error',
                                'message' => "Invalid time format on {$dateStr} for event #" . ($idx + 1) . ": '{$timeStr}'."
                            ], 422);
                        }
                    }

                    // Block future times on today's date (with 1-minute buffer)
                    if ($dateStr === $todayStr && $parsed->greaterThan($nowWithBuffer)) {
                        return response()->json([
                            'status'  => 'error',
                            'message' => "Validation Error on {$dateStr}: Event time '{$timeStr}' cannot be in the future."
                        ], 422);
                    }

                    $timeline[] = ['type' => $type, 'time' => $parsed, 'str' => $timeStr, 'idx' => $idx];

                    // No consecutive duplicate types (break→break, back→back, etc.)
                    if ($prevType !== null && $prevType === $type && !in_array($type, ['signin', 'signout', 'sign out'])) {
                        return response()->json([
                            'status'  => 'error',
                            'message' => "Validation Error on {$dateStr}: Two consecutive '{$type}' events at index " . ($idx + 1) . " without an intervening pair."
                        ], 422);
                    }
                    $prevType = $type;
                }

                // Chronological check (allows same-minute transitions e.g. 05:18 AM back then 05:18 AM break)
                for ($i = 0; $i < count($timeline) - 1; $i++) {
                    if ($timeline[$i]['time']->greaterThan($timeline[$i + 1]['time'])) {
                        return response()->json([
                            'status'  => 'error',
                            'message' => "Chronological Error on {$dateStr}: Event '{$timeline[$i + 1]['str']}' must be after '{$timeline[$i]['str']}'."
                        ], 422);
                    }
                }

            } else {
                // ---- Validate sessions mode (original logic) ----
                $sessions  = $entry['sessions'] ?? $entry; // back-compat: if entry IS the sessions array
                $timeline  = [];
                foreach ($sessions as $index => $session) {
                    $inTimeStr  = $session['in'] ?? '';
                    $outTimeStr = $session['out'] ?? '';

                    if (!empty($inTimeStr)) {
                        try {
                            $inLocal    = Carbon::createFromFormat('Y-m-d h:i A', $dateStr . ' ' . $inTimeStr, $tz);
                            $timeline[] = ['label' => "Sign In " . ($index + 1), 'time' => $inLocal, 'str' => $inTimeStr];
                        } catch (\Exception $e) {
                            try {
                                $inLocal    = Carbon::createFromFormat('Y-m-d h:i:s A', $dateStr . ' ' . $inTimeStr, $tz);
                                $timeline[] = ['label' => "Sign In " . ($index + 1), 'time' => $inLocal, 'str' => $inTimeStr];
                            } catch (\Exception $e2) {
                                return response()->json(['status' => 'error', 'message' => "Invalid time format for Sign In " . ($index + 1) . " on {$dateStr}."], 422);
                            }
                        }

                        if ($dateStr === $todayStr && $inLocal->greaterThan($nowWithBuffer)) {
                            return response()->json(['status' => 'error', 'message' => "Validation Error on {$dateStr}: Sign In " . ($index + 1) . " ('{$inTimeStr}') cannot be in the future."], 422);
                        }
                    }

                    if (!empty($outTimeStr)) {
                        try {
                            $outLocal   = Carbon::createFromFormat('Y-m-d h:i A', $dateStr . ' ' . $outTimeStr, $tz);
                            $timeline[] = ['label' => "Sign Out " . ($index + 1), 'time' => $outLocal, 'str' => $outTimeStr];
                        } catch (\Exception $e) {
                            try {
                                $outLocal   = Carbon::createFromFormat('Y-m-d h:i:s A', $dateStr . ' ' . $outTimeStr, $tz);
                                $timeline[] = ['label' => "Sign Out " . ($index + 1), 'time' => $outLocal, 'str' => $outTimeStr];
                            } catch (\Exception $e2) {
                                return response()->json(['status' => 'error', 'message' => "Invalid time format for Sign Out " . ($index + 1) . " on {$dateStr}."], 422);
                            }
                        }

                        if (!empty($outTimeStr) && empty($inTimeStr)) {
                            return response()->json(['status' => 'error', 'message' => "Validation Error on {$dateStr}: Sign Out " . ($index + 1) . " is provided without a corresponding Sign In."], 422);
                        }

                        if ($dateStr === $todayStr && $outLocal->greaterThan($nowWithBuffer)) {
                            return response()->json(['status' => 'error', 'message' => "Validation Error on {$dateStr}: Sign Out " . ($index + 1) . " ('{$outTimeStr}') cannot be in the future."], 422);
                        }
                    }
                }

                for ($i = 0; $i < count($timeline) - 1; $i++) {
                    $current = $timeline[$i];
                    $next    = $timeline[$i + 1];
                    if ($current['time']->greaterThanOrEqualTo($next['time'])) {
                        return response()->json(['status' => 'error', 'message' => "Chronological Error on {$dateStr}: {$next['label']} ({$next['str']}) must be strictly after {$current['label']} ({$current['str']})."], 422);
                    }
                }
            }
        }

        // -----------------------------------------------------------------------
        // Execute saves inside a transaction
        // -----------------------------------------------------------------------
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($slackUserId, $request, $tz) {
                foreach ($request->date_entries as $dateStr => $entry) {
                    $mode = $entry['mode'] ?? 'sessions';
                    $this->clearHealedCache($slackUserId, $dateStr);

                    if ($mode === 'raw_events') {
                        $this->saveRawEventOverride($slackUserId, $dateStr, $entry['events'] ?? [], $tz);
                    } else {
                        $sessions = $entry['sessions'] ?? $entry;
                        $this->saveSessionOverride($slackUserId, $dateStr, $sessions, $tz);
                    }
                }
            });

            // Notify target employee if HR/Admin made manual fixes
            $targetUser = User::find($request->user_id);
            if ($targetUser && $targetUser->id !== auth()->id()) {
                $targetUser->notify(new TrackRecordFixesNotification($targetUser->id));
            }
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error saving overrides: ' . $e->getMessage()
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Timesheet manual overrides saved successfully!'
        ]);
    }

    /**
     * Save a day's records from a flat raw event list (expanded edit mode).
     * Source of truth is exactly what HR submitted — no hidden-break re-injection.
     */
    private function saveRawEventOverride(string $slackUserId, string $dateStr, array $events, string $tz): void
    {
        $carbonDate = Carbon::createFromFormat('Y-m-d', $dateStr, $tz)->startOfDay();
        $dayStartUtc = $carbonDate->copy()->startOfDay()->timezone('UTC');
        $dayEndUtc   = $carbonDate->copy()->endOfDay()->timezone('UTC');

        // 1. Gather all existing record IDs for this day before deleting
        $existingIds = SlackMessage::where('slack_user_id', $slackUserId)
            ->whereBetween('message_at', [$dayStartUtc, $dayEndUtc])
            ->pluck('id')
            ->toArray();

        // 2. Delete existing records for this day
        if (!empty($existingIds)) {
            SlackMessage::whereIn('id', $existingIds)->delete();

            // Track deleted IDs in cache for real-time Slack Sync polling stream
            $recentlyDeleted = \Illuminate\Support\Facades\Cache::get('recently_deleted_slack_msg_ids', []);
            $recentlyDeleted = array_unique(array_merge($recentlyDeleted, $existingIds));
            \Illuminate\Support\Facades\Cache::put('recently_deleted_slack_msg_ids', $recentlyDeleted, now()->addMinutes(2));
        }

        if (empty($events)) {
            // Day cleared — run healing to clean up any caches
            SlackMessage::ensureDayAutoAppends($slackUserId, $dateStr, false);
            SlackMessage::recalculateBreakExceeded($slackUserId, $dateStr);
            return;
        }

        // Check if preceding day has an unclosed break (overnight break continuation)
        $prevMsgBeforeDay = SlackMessage::where('slack_user_id', $slackUserId)
            ->where('message_at', '<', $dayStartUtc)
            ->where('is_auto_appended', false)
            ->where(function ($s) {
                $s->whereNull('approval_status')
                  ->orWhere('approval_status', 'approved')
                  ->orWhere('approval_status', 'pending_edit');
            })
            ->latest('message_at')
            ->first();

        $isFirstCheckInOvernightBack = false;
        if ($prevMsgBeforeDay && !empty($events)) {
            $prevText = strtolower(trim($prevMsgBeforeDay->message_text));
            $isPrevBreak = (preg_match('/\b(?:break|brb|brbr)\b/i', $prevText) > 0);
            if ($isPrevBreak) {
                $prevTime = $prevMsgBeforeDay->message_at->timezone($tz);
                $firstTimeStr = trim($events[0]['time'] ?? '');
                try {
                    $firstEventTime = Carbon::createFromFormat('Y-m-d h:i:s A', $dateStr . ' ' . $firstTimeStr, $tz);
                } catch (\Exception $e) {
                    try {
                        $firstEventTime = Carbon::createFromFormat('Y-m-d h:i A', $dateStr . ' ' . $firstTimeStr, $tz);
                    } catch (\Exception $e2) {
                        $firstEventTime = Carbon::parse($dateStr, $tz)->startOfDay();
                    }
                }
                if ($prevTime->diffInHours($firstEventTime) < 6) {
                    $isFirstCheckInOvernightBack = true;
                }
            }
        }

        // Insert each submitted event as an approved manual override
        $lastTime = null;
        $checkInCount = 0;
        foreach ($events as $i => $ev) {
            $type    = strtolower(trim($ev['type']));
            $timeStr = trim($ev['time']);

            // If this is the first check-in of a day that continues an overnight break, normalize to 'back'
            if ($checkInCount === 0 && in_array($type, ['signin', 'sign in', 'back'])) {
                $type = $isFirstCheckInOvernightBack ? 'back' : 'signin';
                $checkInCount++;
            } elseif (in_array($type, ['signin', 'sign in', 'back'])) {
                $checkInCount++;
            }

            // Normalise type to DB text values
            $dbText = match (true) {
                in_array($type, ['signin', 'sign in']) => 'signin',
                in_array($type, ['signout', 'sign out']) => 'signout',
                str_starts_with($type, 'break') => 'break',
                str_starts_with($type, 'back')  => 'back',
                default => $type,
            };

            // Parse the time (support both h:i A and h:i:s A)
            try {
                $utcTime = Carbon::createFromFormat('Y-m-d h:i:s A', $dateStr . ' ' . $timeStr, $tz)->timezone('UTC');
            } catch (\Exception $e) {
                $utcTime = Carbon::createFromFormat('Y-m-d h:i A', $dateStr . ' ' . $timeStr, $tz)->timezone('UTC');
            }

            // Ensure distinct ascending seconds if multiple events share the exact same second/minute
            if ($lastTime !== null && $utcTime->lessThanOrEqualTo($lastTime)) {
                $utcTime = $lastTime->copy()->addSecond();
            }
            $lastTime = $utcTime->copy();

            $utcFormatted = $utcTime->format('Y-m-d H:i:s');

            SlackMessage::create([
                'slack_user_id'   => $slackUserId,
                'channel_id'      => 'manual_override',
                'event_id'        => 'manual_' . $dbText . '_' . $dateStr . '_' . $i . '_' . uniqid(),
                'slack_ts'        => (string)($utcTime->timestamp . '.' . str_pad($i * 1000, 6, '0', STR_PAD_LEFT)),
                'message_text'    => $dbText,
                'message_at'      => $utcFormatted,
                'override_at'     => $utcFormatted,
                'approval_status' => 'approved',
                'is_auto_appended'=> false,
            ]);
        }

        // Re-run healing and balance recalculation
        SlackMessage::ensureDayAutoAppends($slackUserId, $dateStr, false);
        SlackMessage::recalculateBreakExceeded($slackUserId, $dateStr);

        if ($isFirstCheckInOvernightBack) {
            $prevDateStr = $carbonDate->copy()->subDay()->format('Y-m-d');
            $this->clearHealedCache($slackUserId, $prevDateStr);
            SlackMessage::ensureDayAutoAppends($slackUserId, $prevDateStr, false);
            SlackMessage::recalculateBreakExceeded($slackUserId, $prevDateStr);
        }
    }

    /**
     * Save a day's records from the legacy session-pair format (non-expanded edit mode).
     * Hidden Case-A breaks are re-injected only if they fall entirely within a single submitted session window.
     */
    private function saveSessionOverride(string $slackUserId, string $dateStr, array $sessions, string $tz): void
    {
        $carbonDate  = Carbon::createFromFormat('Y-m-d', $dateStr, $tz)->startOfDay();
        $dayStartUtc = $carbonDate->copy()->startOfDay()->timezone('UTC');
        $dayEndUtc   = $carbonDate->copy()->endOfDay()->timezone('UTC');

        // -----------------------------------------------------------------------
        // STEP 1: Compute balance BEFORE any changes
        // -----------------------------------------------------------------------
        $balanceDataBefore = SlackMessage::computeDayBreakBalance($slackUserId, $dateStr);

        // Map Case B split times to their original break start times
        $caseBSplitToStartMap = [];
        foreach ($balanceDataBefore['break_pairs'] as $pair) {
            if ($pair['break_exceeded'] && $pair['split_time'] !== null && !$pair['is_fully_session_out']) {
                $splitTimeKey = Carbon::parse($pair['split_time'])->timezone($tz)->format('h:i A');
                $startUtc     = Carbon::parse($pair['break_start'])->timezone('UTC');
                $caseBSplitToStartMap[$splitTimeKey] = $startUtc;
            }
        }

        // Identify hidden Case A break pairs (real break + real back fully within balance).
        $hiddenCaseABreakPairs = [];
        foreach ($balanceDataBefore['break_pairs'] as $pair) {
            if (!$pair['break_exceeded'] && $pair['back_msg_id'] !== null) {
                $isAutoBreak = $pair['is_auto_appended_break'] ?? false;
                $isAutoBack  = $pair['is_auto_appended_back']  ?? false;
                if (!$isAutoBreak && !$isAutoBack && $pair['break_msg_id'] && $pair['back_msg_id']) {
                    $hiddenCaseABreakPairs[] = [
                        'break_start' => Carbon::parse($pair['break_start'])->timezone('UTC'),
                        'back_time'   => Carbon::parse($pair['back_time'])->timezone('UTC'),
                    ];
                }
            }
        }

        // -----------------------------------------------------------------------
        // STEP 2: Gather all submitted events from the timesheet sessions
        // -----------------------------------------------------------------------
        $prevMsgBeforeDay = SlackMessage::where('slack_user_id', $slackUserId)
            ->where('message_at', '<', $dayStartUtc)
            ->where('is_auto_appended', false)
            ->where(function ($s) {
                $s->whereNull('approval_status')
                  ->orWhere('approval_status', 'approved')
                  ->orWhere('approval_status', 'pending_edit');
            })
            ->latest('message_at')
            ->first();

        $isFirstCheckInOvernightBack = false;
        if ($prevMsgBeforeDay && !empty($sessions)) {
            $prevText = strtolower(trim($prevMsgBeforeDay->message_text));
            $isPrevBreak = (preg_match('/\b(?:break|brb|brbr)\b/i', $prevText) > 0);
            if ($isPrevBreak) {
                $prevTime = $prevMsgBeforeDay->message_at->timezone($tz);
                $firstInStr = trim($sessions[0]['in'] ?? '');
                try {
                    $firstEventTime = Carbon::createFromFormat('Y-m-d h:i:s A', $dateStr . ' ' . $firstInStr, $tz);
                } catch (\Exception $e) {
                    try {
                        $firstEventTime = Carbon::createFromFormat('Y-m-d h:i A', $dateStr . ' ' . $firstInStr, $tz);
                    } catch (\Exception $e2) {
                        $firstEventTime = Carbon::parse($dateStr, $tz)->startOfDay();
                    }
                }
                if ($prevTime->diffInHours($firstEventTime) < 6) {
                    $isFirstCheckInOvernightBack = true;
                }
            }
        }

        $submittedEvents = [];
        $checkInCount    = 0;
        foreach ($sessions as $index => $session) {
            $inTimeStr   = $session['in'] ?? '';
            $outTimeStr  = $session['out'] ?? '';
            $inIsVirtual = filter_var($session['in_is_virtual'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $outIsVirtual= filter_var($session['out_is_virtual'] ?? false, FILTER_VALIDATE_BOOLEAN);

            // IN cell
            if (!empty($inTimeStr) && !$inIsVirtual) {
                $inUtc  = Carbon::parse("{$dateStr} {$inTimeStr}", $tz)->timezone('UTC');
                $type   = ($checkInCount === 0)
                    ? ($isFirstCheckInOvernightBack ? 'back' : 'signin')
                    : 'back';
                $submittedEvents[] = ['type' => $type, 'time_utc' => $inUtc];
                $checkInCount++;
            }

            // OUT cell
            if (!empty($outTimeStr)) {
                if (!$outIsVirtual) {
                    $outUtc  = Carbon::parse("{$dateStr} {$outTimeStr}", $tz)->timezone('UTC');
                    $isBreak = false;
                    for ($nextIdx = $index + 1; $nextIdx < count($sessions); $nextIdx++) {
                        if (isset($sessions[$nextIdx])) {
                            $nextIn = $sessions[$nextIdx]['in'] ?? '';
                            if (!empty($nextIn)) { $isBreak = true; break; }
                        }
                    }
                    $type = $isBreak ? 'break' : 'signout';
                    $submittedEvents[] = ['type' => $type, 'time_utc' => $outUtc];
                } else {
                    // Virtual out cell: if Case B split time was untouched, preserve original break start
                    $formattedOut = Carbon::parse("{$dateStr} {$outTimeStr}", $tz)->format('h:i A');
                    if (isset($caseBSplitToStartMap[$formattedOut])) {
                        $submittedEvents[] = [
                            'type'     => 'break',
                            'time_utc' => $caseBSplitToStartMap[$formattedOut],
                        ];
                    }
                }
            }
        }

        // -----------------------------------------------------------------------
        // STEP 3: Re-inject hidden Case A breaks — ONLY if they fall fully inside
        // a single submitted session window (prevents duplication when HR already
        // included the break explicitly in their edited sessions).
        // -----------------------------------------------------------------------

        // Build session windows from submitted events
        $sessionWindows = [];
        $tempIn = null;
        foreach ($submittedEvents as $ev) {
            if (in_array($ev['type'], ['signin', 'back'])) {
                $tempIn = $ev['time_utc'];
            } elseif (in_array($ev['type'], ['break', 'signout']) && $tempIn !== null) {
                $sessionWindows[] = ['start' => $tempIn, 'end' => $ev['time_utc']];
                $tempIn = null;
            }
        }

        foreach ($hiddenCaseABreakPairs as $hiddenPair) {
            $breakStart = $hiddenPair['break_start'];
            $backTime   = $hiddenPair['back_time'];

            // Only re-inject if BOTH break_start and back_time fall entirely within one session window
            $fullyInsideOneWindow = false;
            foreach ($sessionWindows as $win) {
                if ($breakStart->greaterThan($win['start']) && $backTime->lessThan($win['end'])) {
                    $fullyInsideOneWindow = true;
                    break;
                }
            }

            if ($fullyInsideOneWindow) {
                $submittedEvents[] = ['type' => 'break', 'time_utc' => $breakStart];
                $submittedEvents[] = ['type' => 'back',  'time_utc' => $backTime];
            }
            // If not fully inside a single window → HR's submitted sessions already account for it
        }

        // Sort chronologically
        usort($submittedEvents, fn($a, $b) => $a['time_utc']->timestamp <=> $b['time_utc']->timestamp);

        // -----------------------------------------------------------------------
        // STEP 4: Replace day's records with clean approved manual overrides
        // -----------------------------------------------------------------------
        // 1. Gather all existing record IDs for this day before deleting
        $existingIds = SlackMessage::where('slack_user_id', $slackUserId)
            ->whereBetween('message_at', [$dayStartUtc, $dayEndUtc])
            ->pluck('id')
            ->toArray();

        // 2. Delete existing records for this day
        if (!empty($existingIds)) {
            SlackMessage::whereIn('id', $existingIds)->delete();

            // Track deleted IDs in cache for real-time Slack Sync polling stream
            $recentlyDeleted = \Illuminate\Support\Facades\Cache::get('recently_deleted_slack_msg_ids', []);
            $recentlyDeleted = array_unique(array_merge($recentlyDeleted, $existingIds));
            \Illuminate\Support\Facades\Cache::put('recently_deleted_slack_msg_ids', $recentlyDeleted, now()->addMinutes(2));
        }

        foreach ($submittedEvents as $i => $ev) {
            $utcFormatted = $ev['time_utc']->copy()->timezone('UTC')->format('Y-m-d H:i:s');
            SlackMessage::create([
                'slack_user_id'   => $slackUserId,
                'channel_id'      => 'manual_override',
                'event_id'        => 'manual_' . $ev['type'] . '_' . $dateStr . '_' . $i . '_' . uniqid(),
                'slack_ts'        => (string)($ev['time_utc']->timestamp . '.' . ($i * 1000)),
                'message_text'    => $ev['type'],
                'message_at'      => $utcFormatted,
                'override_at'     => $utcFormatted,
                'approval_status' => 'approved',
                'is_auto_appended'=> false,
            ]);
        }

        // -----------------------------------------------------------------------
        // STEP 5: Run healing triggers for the target date (and previous day if carryover)
        // -----------------------------------------------------------------------
        SlackMessage::ensureDayAutoAppends($slackUserId, $dateStr, false);
        SlackMessage::recalculateBreakExceeded($slackUserId, $dateStr);

        if ($isFirstCheckInOvernightBack) {
            $prevDateStr = $carbonDate->copy()->subDay()->format('Y-m-d');
            $this->clearHealedCache($slackUserId, $prevDateStr);
            SlackMessage::recalculateBreakExceeded($slackUserId, $prevDateStr);
        }
    }


    private function clearHealedCache(string $slackUserId, string $dateStr): void
    {
        $tz = 'Asia/Karachi';
        $carbonDate = \Carbon\Carbon::parse($dateStr, $tz);
        
        $dates = [
            $carbonDate->copy()->subDay()->format('Y-m-d'),
            $dateStr,
            $carbonDate->copy()->addDay()->format('Y-m-d'),
        ];
        
        foreach ($dates as $d) {
            \Illuminate\Support\Facades\Cache::forget("healed_{$slackUserId}_{$d}");
            \Illuminate\Support\Facades\Cache::forget("break_balance_{$slackUserId}_{$d}");
        }
    }

    private function getDynamicColorForRatio($ratio)
    {
        $ratio = max(0.0, min(1.0, $ratio));

        if ($ratio < 0.5) {
            // Interpolate between Red (EA4335, RGB: 234, 67, 53) and Yellow (FFD966, RGB: 255, 217, 102)
            $t = $ratio / 0.5;
            $r = (int)round(234 + (255 - 234) * $t);
            $g = (int)round(67 + (217 - 67) * $t);
            $b = (int)round(53 + (102 - 53) * $t);
        } else {
            // Interpolate between Yellow (FFD966, RGB: 255, 217, 102) and Green (6AA84F, RGB: 106, 168, 79)
            $t = ($ratio - 0.5) / 0.5;
            $r = (int)round(255 + (106 - 255) * $t);
            $g = (int)round(217 + (168 - 217) * $t);
            $b = (int)round(102 + (79 - 102) * $t);
        }

        $hex = sprintf("%02X%02X%02X", $r, $g, $b);

        // Calculate luminance for text contrast
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        $textColor = ($luminance > 0.6) ? '000000' : 'FFFFFF';

        return [
            'bg' => $hex,
            'text' => $textColor
        ];
    }

    /**
     * Calculate the fractional/decimal day value for a remote work log based on its notes.
     */
    public static function getRemoteDayValue(?string $notes, float $expectedHoursPerDay = 8.0): float
    {
        if (empty($notes)) {
            return 1.0;
        }

        // Check if notes indicate a time range, e.g.:
        // "Remote Work: 02:00 PM to 06:00 PM (Declared via Slack)"
        // "Remote Work: 10:00 AM to 02:00 PM"
        // "14:00 to 18:00" or "from 2pm to 6pm"
        if (preg_match('/(\d{1,2}(?::\d{2})?\s*(?:AM|PM|am|pm)?)\s*(?:to|-)\s*(\d{1,2}(?::\d{2})?\s*(?:AM|PM|am|pm)?)/i', $notes, $matches)) {
            try {
                $t1 = Carbon::parse(trim($matches[1]));
                $t2 = Carbon::parse(trim($matches[2]));
                if ($t2->lt($t1)) {
                    $t2->addDay();
                }
                $diffHours = $t1->diffInMinutes($t2) / 60.0;
                if ($diffHours > 0) {
                    if ($diffHours <= ($expectedHoursPerDay / 2) + 0.5) {
                        return 0.5;
                    } elseif ($diffHours >= $expectedHoursPerDay - 0.5) {
                        return 1.0;
                    } else {
                        return round($diffHours / $expectedHoursPerDay, 1);
                    }
                }
            } catch (\Throwable $e) {}
        }

        return 1.0;
    }
}
