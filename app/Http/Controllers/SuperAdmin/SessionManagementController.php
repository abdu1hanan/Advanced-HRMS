<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SlackMessage;
use App\Models\SlackUserMapping;
use App\Models\User;
use App\Models\LeaveRequest;
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

    public function overrides(Request $request): View
    {
        $messages = SlackMessage::with(['edits', 'mapping.user'])
            ->where('message_at', '>=', now()->subDays(90))
            ->where(function ($query) {
                $query->where('is_auto_appended', false)
                      ->orWhere(function ($sub) {
                          $sub->where('is_auto_appended', true)
                              ->whereIn('message_text', ['signout', 'sign out']);
                      });
            })
            ->latest('message_at')
            ->get();
        
        $initialMessages = $messages->map(function ($msg) {
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
        });

        $hasMappedNames = $messages->contains(function ($msg) {
            return $msg->mapping && ($msg->mapping->user || $msg->mapping->slack_username);
        });

        return view('super-admin.session-management.overrides', compact('messages', 'initialMessages', 'hasMappedNames'));
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

            $users = User::whereHas('slackMapping')->orderBy('name')->get();

            $flatRecords = [];
            $maxSessions = 1;

            foreach ($users as $emp) {
                $mapping = $emp->slackMapping;
                if (!$mapping) {
                    continue;
                }

                $queryStartDate = $startDate->copy()->subDays(2)->timezone('UTC');
                $queryEndDate = $endDate->copy()->timezone('UTC');

                $empLeaves = LeaveRequest::where('user_id', $emp->id)
                    ->where('status', LeaveRequest::STATUS_APPROVED)
                    ->where('start_date', '<=', $endDate)
                    ->where('end_date', '>=', $startDate)
                    ->get();
                
                $isTodayLeave = false;
                $todayDateStr = $today->format('Y-m-d');
                foreach ($empLeaves as $leave) {
                    if ($leave->start_date->format('Y-m-d') <= $todayDateStr && $leave->end_date->format('Y-m-d') >= $todayDateStr) {
                        $isTodayLeave = true;
                        break;
                    }
                }

                $dayMessages = SlackMessage::where('slack_user_id', $mapping->slack_user_id)
                    ->whereBetween('message_at', [$queryStartDate, $queryEndDate])
                    ->orderBy('message_at', 'asc')
                    ->get();

                $sessionsByDate = $this->reconstructSessions($dayMessages, $startDate, $endDate, $tz);
                $todaySessions = $sessionsByDate[$today->format('Y-m-d')] ?? [];

                $dailySeconds = 0;
                $formattedSessions = [];
                
                foreach ($todaySessions as $session) {
                    $inStr = $session['in']->format('h:i:00 A');
                    $outStr = '';
                    
                    if ($session['out']) {
                        $outStr = $session['out']->format('h:i:00 A');
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
                $dailyExpectedSeconds = $isTodayLeave ? 0 : (int)round($expectedHoursPerDay * 3600);
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
                    'day_of_week' => $today->dayOfWeekIso,
                    'is_weekend' => ($today->dayOfWeekIso === 6 || $today->dayOfWeekIso === 7),
                    'weekly_summary_diff_seconds' => 0,
                    'is_leave' => $isTodayLeave,
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
                    'show_summary' => $period !== 'today'
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

        $leaves = LeaveRequest::where('user_id', $userId)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->get();

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
        $queryEndDate = $endDate->copy()->timezone('UTC');

        // Lazy-heal any dates in the queried range that are before today and have subsequent check-ins, but are not yet cached as healed.
        $checkDate = $startDate->copy();
        while ($checkDate->lte($endDate)) {
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
                $inStr = $session['in']->format('h:i:00 A');
                $outStr = '';

                if ($session['out']) {
                    $outStr = $session['out']->format('h:i:00 A');
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
            foreach ($leaves as $leave) {
                if ($leave->start_date->format('Y-m-d') <= $dateString && $leave->end_date->format('Y-m-d') >= $dateString) {
                    $isLeave = true;
                    break;
                }
            }

            $records[$dateString] = [
                'date' => $dayData['date_formatted'],
                'day_name' => $dayData['day_name'],
                'raw_date' => $rawDate,
                'sessions' => $formattedSessions,
                'total_seconds' => $dailySeconds,
                'is_leave' => $isLeave,
            ];
        }

        if ($period === 'today') {
            $isTodayLeave = false;
            $todayDateStr = $today->format('Y-m-d');
            foreach ($leaves as $leave) {
                if ($leave->start_date->format('Y-m-d') <= $todayDateStr && $leave->end_date->format('Y-m-d') >= $todayDateStr) {
                    $isTodayLeave = true;
                    break;
                }
            }

            $expectedHoursPerDay = $user->expected_hour_per_day ?? 8.0;
            $dailyExpectedSeconds = ($today->dayOfWeekIso >= 1 && $today->dayOfWeekIso <= 5 && !$isTodayLeave) ? (int)round($expectedHoursPerDay * 3600) : 0;
            
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
                    'day_of_week' => $today->dayOfWeekIso,
                    'is_weekend' => ($today->dayOfWeekIso === 6 || $today->dayOfWeekIso === 7),
                    'weekly_summary_diff_seconds' => 0,
                    'raw_date_string' => $today->format('Y-m-d'),
                    'is_leave' => $isTodayLeave,
                ]
            ];

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
                    'total_absents' => 0,
                    'expected_salary' => $user->expected_salary_amount ?? 30000,
                    'deductions' => '0',
                    'take_away' => $user->expected_salary_amount ?? 30000,
                    'show_summary' => false
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
                    ];
                }
            }
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
        $totalAbsents = 0;

        foreach ($weeks as $weekKey => $weekDays) {
            $expectedHoursPerDay = $user->expected_hour_per_day ?? 8.0;
            
            $weekExpectedSeconds = 0;
            $weekTimeGivenSeconds = 0;
            $weeklyLeaves = 0;
            $weeklyAbsents = 0;
            $weeklyHolidays = 0;

            foreach ($weekDays as $day) {
                $dayOfWeek = $day['raw_date']->dayOfWeekIso;
                $isDayLeave = $day['is_leave'] ?? false;
                if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                    if (!$isDayLeave) {
                        $weekExpectedSeconds += $expectedHoursPerDay * 3600;
                    }
                }
                if (!$day['is_filler']) {
                    $weekTimeGivenSeconds += $day['total_seconds'];
                }
                if ($isDayLeave && !$day['is_filler']) {
                    $weeklyLeaves++;
                }
            }

            $weekDifferenceSeconds = $weekTimeGivenSeconds - $weekExpectedSeconds;

            // Accumulate grand totals
            $totalExpectedSeconds += $weekExpectedSeconds;
            $totalTimeGivenSeconds += $weekTimeGivenSeconds;
            $totalLeaves += $weeklyLeaves;
            $totalAbsents += $weeklyAbsents;

            foreach ($weekDays as $day) {
                $dayOfWeek = $day['raw_date']->dayOfWeekIso;
                $isFiller = $day['is_filler'];
                $isDayLeave = $day['is_leave'] ?? false;

                $isBeforeFirstMonday = ($firstMondayDate && $day['raw_date']->lt($firstMondayDate));

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

                    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                        $dailyExpected = $isDayLeave ? 0 : ($expectedHoursPerDay * 3600);
                        if ($dailyExpected > 0) {
                            $pct = ($day['total_seconds'] ?? 0) / $dailyExpected;
                        }
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
                        $dailyExpected = $isDayLeave ? 0 : ($expectedHoursPerDay * 3600);
                        if ($dailyExpected > 0) {
                            $pct = ($day['total_seconds'] ?? 0) / $dailyExpected;
                        }
                    }
                }

                $dailyExpectedSeconds = ($dayOfWeek >= 1 && $dayOfWeek <= 5 && !$isDayLeave) ? (int)round($expectedHoursPerDay * 3600) : 0;

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
                    'day_of_week' => $dayOfWeek,
                    'is_weekend' => ($dayOfWeek === 6 || $dayOfWeek === 7),
                    'weekly_summary_diff_seconds' => ($dayOfWeek === 7) ? $weekDifferenceSeconds : 0,
                    'raw_date_string' => $isFiller ? '' : $day['raw_date']->format('Y-m-d'),
                    'is_leave' => $isDayLeave,
                ];
            }
        }

        // Grand Totals for Summary Block
        $totalDiffSeconds = $totalTimeGivenSeconds - $totalExpectedSeconds;
        $expectedSalary = $user->expected_salary_amount ?? 30000;
        $deductions = 0;
        $takeAway = $expectedSalary - $deductions;

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
                'total_absents' => $totalAbsents,
                'expected_salary' => number_format($expectedSalary),
                'deductions' => number_format($deductions),
                'take_away' => number_format($takeAway),
                'show_summary' => $period !== 'today',
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
        if (preg_match('/at\s+(\d{1,2}):(\d{2})\s*(AM|PM)/i', $msg->message_text, $matches)) {
            $hours = (int)$matches[1];
            $minutes = (int)$matches[2];
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
        $messages = SlackMessage::with(['edits', 'mapping.user'])
            ->where('message_at', '>=', now()->subDays(90))
            ->where(function ($query) {
                $query->where('is_auto_appended', false)
                      ->orWhere(function ($sub) {
                          $sub->where('is_auto_appended', true)
                              ->whereIn('message_text', ['signout', 'sign out']);
                      });
            })
            ->latest('message_at')
            ->get()
            ->map(function ($msg) {
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
            });

        return response()->json($messages);
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

            // 2. Delete any other override checkout or check-in messages for the same user on that calendar day surgically
            $text = strtolower(trim($message->message_text));
            $isSignIn = (str_contains($text, 'signin') || str_contains($text, 'sign in'));
            $isSignOut = (str_contains($text, 'signout') || str_contains($text, 'sign out'));
            $isBack = preg_match('/^back\b/i', $text);

            if ($isSignOut) {
                SlackMessage::where('slack_user_id', $message->slack_user_id)
                    ->where('id', '!=', $message->id)
                    ->whereBetween('message_at', [$start, $end])
                    ->where(function ($q) {
                        $q->where('message_text', 'like', '%signout%')
                          ->orWhere('message_text', 'like', '%sign out%');
                    })
                    ->delete();
            } elseif ($isSignIn) {
                SlackMessage::where('slack_user_id', $message->slack_user_id)
                    ->where('id', '!=', $message->id)
                    ->whereBetween('message_at', [$start, $end])
                    ->where(function ($q) {
                        $q->where('message_text', 'like', '%signin%')
                          ->orWhere('message_text', 'like', '%sign in%');
                    })
                    ->delete();
            } elseif ($isBack) {
                SlackMessage::where('slack_user_id', $message->slack_user_id)
                    ->where('id', '!=', $message->id)
                    ->where('is_auto_appended', true)
                    ->where('message_text', 'back')
                    ->whereBetween('message_at', [$start, $end])
                    ->delete();
            }

            $tz = 'Asia/Karachi';
            $dateStr = $message->message_at->timezone($tz)->format('Y-m-d');
            \Illuminate\Support\Facades\Cache::forget("healed_{$message->slack_user_id}_{$dateStr}");
            SlackMessage::ensureDayAutoAppends($message->slack_user_id, $dateStr, false);
            SlackMessage::recalculateBreakExceeded($message->slack_user_id, $dateStr);
        }

        // Mark associated notifications as read
        \Illuminate\Support\Facades\DB::table('notifications')
            ->where('data->slack_message_id', $message->id)
            ->update(['read_at' => now(), 'updated_at' => now()]);

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
            \Illuminate\Support\Facades\Cache::forget("healed_{$message->slack_user_id}_{$dateStr}");
            SlackMessage::ensureDayAutoAppends($message->slack_user_id, $dateStr, false);
            SlackMessage::recalculateBreakExceeded($message->slack_user_id, $dateStr);
        }

        // Mark associated notifications as read
        \Illuminate\Support\Facades\DB::table('notifications')
            ->where('data->slack_message_id', $message->id)
            ->update(['read_at' => now(), 'updated_at' => now()]);

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
            \Illuminate\Support\Facades\Cache::forget("healed_{$message->slack_user_id}_{$dateStr}");
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
        $queryEndDate = $endDate->copy()->timezone('UTC');

        // Lazy-heal any dates in the queried range that are before today and have subsequent check-ins, but are not yet cached as healed.
        $checkDate = $startDate->copy();
        while ($checkDate->lte($endDate)) {
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

        $leaves = LeaveRequest::where('user_id', $userId)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->get();

        $records = [];
        foreach ($periodDates as $dateString => $dayData) {
            $rawDate = $dayData['raw_date'];
            $daySessions = $sessionsByDate[$dateString] ?? [];

            $dailySeconds = 0;
            $formattedSessions = [];

            foreach ($daySessions as $session) {
                $inStr = $session['in']->format('h:i:00 A');
                $outStr = '';

                if ($session['out']) {
                    $outStr = $session['out']->format('h:i:00 A');
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
            foreach ($leaves as $leave) {
                if ($leave->start_date->format('Y-m-d') <= $dateString && $leave->end_date->format('Y-m-d') >= $dateString) {
                    $isLeave = true;
                    break;
                }
            }

            $records[$dateString] = [
                'date' => $dayData['date_formatted'],
                'day_name' => $dayData['day_name'],
                'raw_date' => $rawDate,
                'sessions' => $formattedSessions,
                'total_seconds' => $dailySeconds,
                'is_leave' => $isLeave,
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
                    ];
                }
            }
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
        $totalAbsents = 0;

        foreach ($weeks as $weekKey => $weekDays) {
            $expectedHoursPerDay = $user->expected_hour_per_day ?? 8.0;
            
            $weekExpectedSeconds = 0;
            $weekTimeGivenSeconds = 0;
            $weeklyLeaves = 0;
            $weeklyAbsents = 0;
            $weeklyHolidays = 0;

            foreach ($weekDays as $day) {
                $dayOfWeek = $day['raw_date']->dayOfWeekIso;
                $isDayLeave = $day['is_leave'] ?? false;
                if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                    if (!$isDayLeave) {
                        $weekExpectedSeconds += $expectedHoursPerDay * 3600;
                    }
                }
                if (!$day['is_filler']) {
                    $weekTimeGivenSeconds += $day['total_seconds'];
                }
                if ($isDayLeave && !$day['is_filler']) {
                    $weeklyLeaves++;
                }
            }

            $weekDifferenceSeconds = $weekTimeGivenSeconds - $weekExpectedSeconds;

            // Accumulate grand totals
            $totalExpectedSeconds += $weekExpectedSeconds;
            $totalTimeGivenSeconds += $weekTimeGivenSeconds;
            $totalLeaves += $weeklyLeaves;
            $totalAbsents += $weeklyAbsents;

            foreach ($weekDays as $day) {
                $dayOfWeek = $day['raw_date']->dayOfWeekIso;
                $isFiller = $day['is_filler'];
                $isDayLeave = $day['is_leave'] ?? false;

                $sheet->setCellValue('B' . $rowNum, $isFiller ? '' : $day['date']);
                $sheet->setCellValue('C' . $rowNum, $day['day_name']);

                $isBeforeFirstMonday = ($firstMondayDate && $day['raw_date']->lt($firstMondayDate));

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
                        $dailyExpected = $isDayLeave ? 0 : ($expectedHoursPerDay * 3600);
                        if ($dailyExpected > 0) {
                            $pct = ($day['total_seconds'] ?? 0) / $dailyExpected;
                        }
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
                        $dailyExpected = $isDayLeave ? 0 : ($expectedHoursPerDay * 3600);
                        if ($dailyExpected > 0) {
                            $pct = ($day['total_seconds'] ?? 0) / $dailyExpected;
                        }
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

                // Day column C: weekdays #FFFFFF, weekends #EA4335, leaves #FFFF00
                $isWeekend = ($dayOfWeek === 6 || $dayOfWeek === 7);
                $dayBgColor = $isWeekend ? 'EA4335' : ($isDayLeave ? 'FFFF00' : 'FFFFFF');
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

                // Weekly Summary D & E: Mon-Sat: #C9DAF8, Sun: #6AA84F or #EA4335
                $sumBgColor = 'C9DAF8';
                $sumFontColor = '000000';
                $sumBold = false;
                if ($dayOfWeek === 7) {
                    $sumBgColor = ($weekDifferenceSeconds >= 0) ? '6AA84F' : 'EA4335';
                    $sumFontColor = 'FFFFFF';
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

                // Percentage F: dynamic colors
                $pctColor = 'FFFFFF';
                $pctFontColor = '000000';
                if ($isDayLeave) {
                    $pctColor = 'FFFF00';
                    $pctFontColor = '000000';
                } elseif ($dayOfWeek === 7) {
                    $pctColor = ($pct >= 0) ? '38761D' : 'EA4335';
                    $pctFontColor = 'FFFFFF';
                } elseif ($dayOfWeek === 6) {
                    $pctColor = ($pct >= 1.0) ? '6AA84F' : 'EA4335';
                    $pctFontColor = 'FFFFFF';
                } else {
                    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                        if ($pct >= 1.0) {
                            $pctColor = 'C6EFCE';
                            $pctFontColor = '006100';
                        } elseif ($pct >= 0.5) {
                            $pctColor = 'FFEB9C';
                            $pctFontColor = '9C6500';
                        } else {
                            $pctColor = 'FFC7CE';
                            $pctFontColor = '9C0006';
                        }
                    }
                }

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

                // Total time G and Session columns H-Y: Weekdays: #FFFFFF, Weekends: #EA4335, Leaves: #FFFF00
                $rowBgColor = $isWeekend ? 'EA4335' : ($isDayLeave ? 'FFFF00' : 'FFFFFF');
                $rowFontColor = $isWeekend ? 'FFFFFF' : '000000';

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

        for ($r = $sumRow1; $r <= $sumRow5; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(20);
        }

        $sheet->mergeCells("A{$sumRow1}:B{$sumRow5}");
        $sheet->setCellValue("A{$sumRow1}", 'SUMMARY');
        $sheet->getStyle("A{$sumRow1}:B{$sumRow5}")->applyFromArray([
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
        $diffBg = $totalDiffSeconds >= 0 ? '6AA84F' : 'EA4335';
        $diffFontColor = 'FFFFFF';
        
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
        $sheet->setCellValue("C{$sumRow4}", 'Leaves*');
        $sheet->setCellValue("D{$sumRow4}", $totalLeaves);
        $sheet->getStyle("C{$sumRow4}")->applyFromArray($labelStyle);
        $sheet->getStyle("D{$sumRow4}")->applyFromArray($valueStyle);

        // Row 5: Absents
        $sheet->setCellValue("C{$sumRow5}", 'Absents*');
        $sheet->setCellValue("D{$sumRow5}", $totalAbsents);
        $sheet->getStyle("C{$sumRow5}")->applyFromArray($labelStyle);
        $sheet->getStyle("D{$sumRow5}")->applyFromArray($valueStyle);

        // Salary section
        $sheet->setCellValue("E{$sumRow1}", 'Actual Salary');
        $sheet->setCellValue("F{$sumRow1}", number_format($user->expected_salary_amount ?? 30000));
        $sheet->getStyle("E{$sumRow1}")->applyFromArray($labelStyle);
        $sheet->getStyle("F{$sumRow1}")->applyFromArray($valueStyle);

        $sheet->setCellValue("E{$sumRow2}", 'Deductions*');
        $sheet->setCellValue("F{$sumRow2}", '0');
        $sheet->getStyle("E{$sumRow2}")->applyFromArray($labelStyle);
        $sheet->getStyle("F{$sumRow2}")->applyFromArray($valueStyle);

        $sheet->setCellValue("E{$sumRow3}", 'Take Away*');
        $sheet->setCellValue("F{$sumRow3}", '0');
        $sheet->getStyle("E{$sumRow3}")->applyFromArray($labelStyle);
        $sheet->getStyle("F{$sumRow3}")->applyFromArray($valueStyle);

        // Empty cells in E4/F4, E5/F5
        $sheet->getStyle("E{$sumRow4}:F{$sumRow5}")->applyFromArray([
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

        $sheet->mergeCells("G{$sumRow2}:S{$sumRow5}");
        $sheet->setCellValue("G{$sumRow2}", $request->input('comment', ''));
        $sheet->getStyle("G{$sumRow2}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("G{$sumRow2}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("G{$sumRow2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("G{$sumRow2}:S{$sumRow5}")->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFFFF'],
            ],
        ]);

        // Colour Codes Block
        $sheet->mergeCells("T{$sumRow1}:T{$sumRow5}");
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
        $sheet->getStyle("A{$sumRow1}:F{$sumRow5}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Borders for Comments Block (G-R)
        $sheet->getStyle("G{$sumRow1}:R{$sumRow5}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Borders for Colour Codes Block (T-Y)
        $sheet->getStyle("T{$sumRow1}:Y{$sumRow5}")->applyFromArray([
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

    private function formatSecondsToTime($seconds)
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
            if (($msg->approval_status === 'rejected') || ($msg->approval_status === 'pending' && !$msg->is_auto_appended)) {
                continue;
            }
            $filtered[] = $msg;
        }

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
                return $timeA->timestamp <=> $timeB->timestamp;
            });

            if (!empty($dayMsgs)) {
                $firstMsg = $dayMsgs[0];
                $firstParsedTime = $this->parseMessageTime($firstMsg, $dateStr, $tz);
                $firstText = strtolower(trim($firstMsg->message_text));

                $isFirstCheckOut = (str_contains($firstText, 'signout') || str_contains($firstText, 'sign out') || preg_match('/^(break|brb)/i', $firstText));
                $isFirstBack = preg_match('/^back\b/i', $firstText);

                if ($isFirstCheckOut) {
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
                } elseif ($isFirstBack) {
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

            foreach ($dayMsgs as $m) {
                $processedMessages[] = $m;
            }
        }

        // Pre-process processed messages to find short breaks (paired break & back <= 10 mins)
        $messagesArray = $processedMessages;
        $messageCount = count($messagesArray);
        $shortBreaks = [];

        for ($i = 0; $i < $messageCount; $i++) {
            $msg = $messagesArray[$i];
            $text = strtolower(trim($msg->message_text));
            
            if (preg_match('/^(break|brb)/i', $text)) {
                $nextBack = null;
                for ($j = $i + 1; $j < $messageCount; $j++) {
                    $nextMsg = $messagesArray[$j];
                    $nextText = strtolower(trim($nextMsg->message_text));
                    if (preg_match('/^back\b/i', $nextText)) {
                        $nextBack = $nextMsg;
                    }
                    break;
                }

                if ($nextBack) {
                    $isAutoAppendedMsg = (bool)($msg->is_auto_appended ?? false);
                    $isAutoAppendedNext = (bool)($nextBack->is_auto_appended ?? false);

                    if (!$isAutoAppendedMsg && !$isAutoAppendedNext) {
                        $diffInSeconds = abs($nextBack->message_at->diffInSeconds($msg->message_at));
                        if ($diffInSeconds <= 600) { // <= 10 minutes (600 seconds)
                            // Only treat as short break if there is a check-in before this break on the same day
                            $msgDate = $msg->message_at->timezone($tz)->format('Y-m-d');
                            $hasCheckInBefore = false;
                            for ($k = $i - 1; $k >= 0; $k--) {
                                $prevMsg = $messagesArray[$k];
                                $prevDate = $prevMsg->message_at->timezone($tz)->format('Y-m-d');
                                if ($prevDate !== $msgDate) {
                                    break;
                                }
                                $prevText = strtolower(trim($prevMsg->message_text));
                                $isPrevCheckIn = (str_contains($prevText, 'signin') || str_contains($prevText, 'sign in') || preg_match('/^back\b/i', $prevText));
                                if ($isPrevCheckIn) {
                                    $hasCheckInBefore = true;
                                    break;
                                }
                            }

                            if ($hasCheckInBefore) {
                                $shortBreaks[$msg->id] = true;
                                $shortBreaks[$nextBack->id] = true;
                            }
                        }
                    }
                }
            }
        }

        foreach ($processedMessages as $msg) {
            // Skip short break messages (break & back <= 10 mins)
            if (isset($shortBreaks[$msg->id])) {
                continue;
            }

            $msgLocalDateStr = $msg->message_at->timezone($tz)->format('Y-m-d');
            $parsedTime = $this->parseMessageTime($msg, $msgLocalDateStr, $tz);
            $text = strtolower(trim($msg->message_text));

            $isCheckIn = (str_contains($text, 'signin') || str_contains($text, 'sign in') || preg_match('/^back\b/i', $text));
            $isCheckOut = (str_contains($text, 'signout') || str_contains($text, 'sign out') || preg_match('/^(break|brb)/i', $text));

            if ($isCheckIn) {
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
                    if (empty($sessionsByDate[$msgLocalDateStr]) && preg_match('/^back\b/i', $text)) {
                        // First check-in of the day is a "back".
                        // Assume virtual break 1 minute before, and virtual sign-in 2 minutes before.
                        $virtualSignIn = $parsedTime->copy()->subMinutes(2);
                        $virtualBreak = $parsedTime->copy()->subMinute();

                        $this->addSessionAndSplit(
                            $sessionsByDate,
                            $virtualSignIn,
                            $virtualBreak,
                            false,
                            $startDate,
                            $endDate,
                            $tz,
                            true,
                            'Auto-Appended Sign In',
                            true,
                            'Auto-Appended Break'
                        );
                    }

                    $activeCheckIn = $parsedTime;
                    $activeCheckInIsVirtual = (bool)($msg->is_auto_appended ?? false);
                    $activeCheckInReason = $activeCheckInIsVirtual ? (preg_match('/^back/i', $text) ? 'Auto-Appended Back' : 'Auto-Appended Sign In') : '';
                }
            } elseif ($isCheckOut) {
                $outIsVirtual = (bool) $msg->is_auto_appended;
                $outVirtualReason = $outIsVirtual ? (preg_match('/^(break|brb)/i', $text) ? 'Auto-Appended Break' : 'Auto-Appended Sign Out') : '';

                if ($activeCheckIn) {
                    // Valid checkout
                    if ($parsedTime->gt($activeCheckIn)) {
                        $this->addSessionAndSplit(
                            $sessionsByDate,
                            $activeCheckIn,
                            $parsedTime,
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
                        $lastCheckoutTime = $parsedTime;
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

    public function saveOverrides(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date_entries' => 'required|array',
        ]);

        $user = User::findOrFail($request->user_id);
        $slackUserId = $user->slackMapping?->slack_user_id;

        if (!$slackUserId) {
            return response()->json([
                'status' => 'error',
                'message' => 'The selected user is not mapped to any Slack User ID.'
            ], 422);
        }

        $tz = 'Asia/Karachi';

        // Validate time formats, chronological order, and completeness before modifying database
        foreach ($request->date_entries as $dateStr => $sessions) {
            $timeline = [];
            foreach ($sessions as $index => $session) {
                $inTimeStr = $session['in'] ?? '';
                $outTimeStr = $session['out'] ?? '';

                if (!empty($inTimeStr)) {
                    try {
                        $inLocal = Carbon::createFromFormat('Y-m-d h:i A', $dateStr . ' ' . $inTimeStr, $tz);
                        $timeline[] = [
                            'label' => "Sign In " . ($index + 1),
                            'time' => $inLocal,
                            'str' => $inTimeStr
                        ];
                    } catch (\Exception $e) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "Invalid time format for Sign In " . ($index + 1) . " on {$dateStr}."
                        ], 422);
                    }
                }

                if (!empty($outTimeStr)) {
                    try {
                        $outLocal = Carbon::createFromFormat('Y-m-d h:i A', $dateStr . ' ' . $outTimeStr, $tz);
                        $timeline[] = [
                            'label' => "Sign Out " . ($index + 1),
                            'time' => $outLocal,
                            'str' => $outTimeStr
                        ];
                    } catch (\Exception $e) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "Invalid time format for Sign Out " . ($index + 1) . " on {$dateStr}."
                        ], 422);
                    }
                }

                if (!empty($outTimeStr) && empty($inTimeStr)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Validation Error on {$dateStr}: Sign Out " . ($index + 1) . " is provided without a corresponding Sign In."
                    ], 422);
                }
            }

            for ($i = 0; $i < count($timeline) - 1; $i++) {
                $current = $timeline[$i];
                $next = $timeline[$i + 1];
                if ($current['time']->greaterThanOrEqualTo($next['time'])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Chronological Error on {$dateStr}: {$next['label']} ({$next['str']}) must be strictly after {$current['label']} ({$current['str']})."
                    ], 422);
                }
            }
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($slackUserId, $request, $tz) {
                foreach ($request->date_entries as $dateStr => $sessions) {
                    $dateObj = Carbon::createFromFormat('Y-m-d', $dateStr, $tz)->startOfDay();

                    // Calculate local day start and end in UTC
                    $dayStartUtc = $dateObj->copy()->startOfDay()->timezone('UTC');
                    $dayEndUtc = $dateObj->copy()->endOfDay()->timezone('UTC');

                    // Forget the healed cache tag
                    \Illuminate\Support\Facades\Cache::forget("healed_{$slackUserId}_{$dateStr}");

                    // First delete all auto-appended records for this day so they don't get mixed in the matching
                    SlackMessage::where('slack_user_id', $slackUserId)
                        ->whereBetween('message_at', [$dayStartUtc, $dayEndUtc])
                        ->where('is_auto_appended', true)
                        ->delete();

                    // Gather all submitted non-virtual inputs sequentially
                    $submittedEvents = [];
                    $checkInCount = 0;
                    foreach ($sessions as $index => $session) {
                        $inTimeStr = $session['in'] ?? '';
                        $outTimeStr = $session['out'] ?? '';
                        $inIsVirtual = filter_var($session['in_is_virtual'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        $outIsVirtual = filter_var($session['out_is_virtual'] ?? false, FILTER_VALIDATE_BOOLEAN);

                        if (!empty($inTimeStr) && !$inIsVirtual) {
                            $inLocal = Carbon::createFromFormat('Y-m-d h:i A', $dateStr . ' ' . $inTimeStr, $tz);
                            $inUtc = $inLocal->copy()->timezone('UTC');
                            $type = ($checkInCount === 0) ? 'signin' : 'back';
                            $submittedEvents[] = [
                                'type' => $type,
                                'time_utc' => $inUtc,
                            ];
                            $checkInCount++;
                        }

                        if (!empty($outTimeStr) && !$outIsVirtual) {
                            $outLocal = Carbon::createFromFormat('Y-m-d h:i A', $dateStr . ' ' . $outTimeStr, $tz);
                            $outUtc = $outLocal->copy()->timezone('UTC');
                            
                            // Check if a subsequent check-in is present in the submitted array
                            $isBreak = false;
                            for ($nextIdx = $index + 1; $nextIdx < count($sessions); $nextIdx++) {
                                if (isset($sessions[$nextIdx])) {
                                    $nextIn = $sessions[$nextIdx]['in'] ?? '';
                                    $nextInIsVirtual = filter_var($sessions[$nextIdx]['in_is_virtual'] ?? false, FILTER_VALIDATE_BOOLEAN);
                                    if (!empty($nextIn) && !$nextInIsVirtual) {
                                        $isBreak = true;
                                        break;
                                    }
                                }
                            }
                            
                            $type = $isBreak ? 'break' : 'signout';
                            $submittedEvents[] = [
                                'type' => $type,
                                'time_utc' => $outUtc,
                            ];
                        }
                    }

                    // Get existing real (non-auto-appended) records
                    $existingRealMessages = SlackMessage::where('slack_user_id', $slackUserId)
                        ->whereBetween('message_at', [$dayStartUtc, $dayEndUtc])
                        ->where('is_auto_appended', false)
                        ->orderBy('message_at', 'asc')
                        ->get();

                    $existingCount = $existingRealMessages->count();
                    $submittedCount = count($submittedEvents);

                    for ($i = 0; $i < max($existingCount, $submittedCount); $i++) {
                        if ($i < $submittedCount && $i < $existingCount) {
                            // Match and patch
                            $dbMsg = $existingRealMessages[$i];
                            $submitted = $submittedEvents[$i];

                            $dbTimeUtc = $dbMsg->message_at;
                            $dbType = strtolower(trim($dbMsg->message_text));
                            $subType = $submitted['type'];
                            
                            $normalize = function($t) {
                                $t = strtolower(trim($t));
                                if ($t === 'sign in') return 'signin';
                                if ($t === 'sign out') return 'signout';
                                return $t;
                            };

                            $typeChanged = ($normalize($dbType) !== $normalize($subType));
                            $timeChanged = ($dbTimeUtc->timestamp !== $submitted['time_utc']->timestamp);

                            if ($typeChanged || $timeChanged) {
                                $dbMsg->update([
                                    'channel_id' => 'manual_override',
                                    'message_text' => $subType,
                                    'message_at' => $submitted['time_utc'],
                                    'override_at' => $submitted['time_utc'],
                                    'approval_status' => 'approved',
                                    'is_auto_appended' => false,
                                ]);
                            }
                        } elseif ($i < $submittedCount) {
                            // Create new manual override record
                            $submitted = $submittedEvents[$i];
                            $inEventId = 'manual_' . $submitted['type'] . '_' . $dateStr . '_' . $i . '_' . uniqid();
                            SlackMessage::create([
                                'slack_user_id' => $slackUserId,
                                'channel_id' => 'manual_override',
                                'event_id' => $inEventId,
                                'slack_ts' => 'manual_ts_' . uniqid(),
                                'message_text' => $submitted['type'],
                                'message_at' => $submitted['time_utc'],
                                'override_at' => $submitted['time_utc'],
                                'approval_status' => 'approved',
                                'is_auto_appended' => false,
                            ]);
                        } elseif ($i < $existingCount) {
                            // Delete unmatched real records
                            $dbMsg = $existingRealMessages[$i];
                            $dbMsg->delete();
                        }
                    }

                    // Run healing triggers for the target date
                    SlackMessage::ensureDayAutoAppends($slackUserId, $dateStr, false);
                    SlackMessage::recalculateBreakExceeded($slackUserId, $dateStr);
                }
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error saving overrides: ' . $e->getMessage()
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Timesheet manual overrides saved successfully!'
        ]);
    }
}
