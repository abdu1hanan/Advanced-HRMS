<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlackMessage extends Model
{
    use HasFactory;

    /**
     * Default daily break balance in seconds (60 minutes).
     * Shown on frontend UI and mentioned in employee alerts.
     */
    const BREAK_BALANCE_SECONDS = 3600;

    /**
     * Default hidden daily break grace period in seconds (15 minutes).
     * Added invisibly to the official balance before session-out deductions or alerts kick in.
     */
    const BREAK_GRACE_PERIOD_SECONDS = 900;

    /**
     * Get the dynamic daily break balance in seconds.
     * Uses cached HR settings if configured, otherwise falls back to default constant.
     * Automatically updates in real time for long-running queue workers without restarting!
     */
    public static function getBreakBalanceSeconds(): int
    {
        return (int) \Illuminate\Support\Facades\Cache::remember('hrms_break_balance_seconds', 86400, function () {
            $settingsFile = storage_path('app/break_settings.json');
            if (file_exists($settingsFile)) {
                $data = json_decode(file_get_contents($settingsFile), true);
                if (isset($data['break_balance_minutes']) && is_numeric($data['break_balance_minutes'])) {
                    return ((int) $data['break_balance_minutes']) * 60;
                }
            }
            return self::BREAK_BALANCE_SECONDS;
        });
    }

    /**
     * Get the dynamic hidden daily break grace period in seconds.
     * Uses cached HR settings if configured, otherwise falls back to default constant.
     * Automatically updates in real time for long-running queue workers without restarting!
     */
    public static function getBreakGracePeriodSeconds(): int
    {
        return (int) \Illuminate\Support\Facades\Cache::remember('hrms_break_grace_period_seconds', 86400, function () {
            $settingsFile = storage_path('app/break_settings.json');
            if (file_exists($settingsFile)) {
                $data = json_decode(file_get_contents($settingsFile), true);
                if (isset($data['break_grace_period_minutes']) && is_numeric($data['break_grace_period_minutes'])) {
                    return ((int) $data['break_grace_period_minutes']) * 60;
                }
            }
            return self::BREAK_GRACE_PERIOD_SECONDS;
        });
    }

    protected $fillable = [
        'event_id',
        'slack_user_id',
        'channel_id',
        'message_text',
        'is_edited',
        'slack_ts',
        'message_at',
        'approval_status',
        'original_message_at',
        'override_at',
        'break_exceeded',
        'is_auto_appended',
        'pending_message_text',
        'pending_override_at',
    ];

    protected $casts = [
        'message_at' => 'datetime',
        'is_edited' => 'boolean',
        'original_message_at' => 'datetime',
        'override_at' => 'datetime',
        'break_exceeded' => 'boolean',
        'is_auto_appended' => 'boolean',
        'pending_override_at' => 'datetime',
    ];

    /**
     * Get the edit history for this message.
     */
    public function edits()
    {
        return $this->hasMany(SlackMessageEdit::class)->latest('edited_at');
    }

    /**
     * Get the user mapping for this message.
     */
    public function mapping()
    {
        return $this->belongsTo(SlackUserMapping::class, 'slack_user_id', 'slack_user_id');
    }

    /**
     * Recreate the auto-appended sign-out message if an approved checkout is reset/pending
     * and a subsequent check-in exists on a later calendar day.
     */
    public static function recreateAutoAppendIfNecessary(self $message): void
    {
        if (!$message->override_at) {
            return;
        }

        $isCheckOut = (str_contains(strtolower($message->message_text), 'signout') || str_contains(strtolower($message->message_text), 'sign out'));
        if (!$isCheckOut) {
            return;
        }

        $tz = 'Asia/Karachi';
        $overrideDate = $message->override_at->timezone($tz)->format('Y-m-d');
        $start = \Carbon\Carbon::parse($overrideDate, $tz)->startOfDay()->timezone('UTC');
        $end = \Carbon\Carbon::parse($overrideDate, $tz)->endOfDay()->timezone('UTC');

        // Check if an auto-appended sign out already exists
        $exists = self::where('slack_user_id', $message->slack_user_id)
            ->where('is_auto_appended', true)
            ->whereBetween('message_at', [$start, $end])
            ->exists();

        if (!$exists) {
            // Only recreate if a check-in exists on a LATER calendar day (meaning they actually forgot to sign out)
            $hasSubsequentCheckIn = self::where('slack_user_id', $message->slack_user_id)
                ->where('message_at', '>', $end)
                ->where(function($q) {
                    $q->where('message_text', 'like', 'signin%')
                      ->orWhere('message_text', 'like', 'sign in%')
                      ->orWhere('message_text', 'back');
                })
                ->where(function ($q) {
                    $q->whereNull('approval_status')
                      ->orWhere('approval_status', '!=', 'rejected');
                })
                ->exists();

            if ($hasSubsequentCheckIn) {
                // Find the check-in on that day to link with
                $prevSignIn = self::where('slack_user_id', $message->slack_user_id)
                    ->whereBetween('message_at', [$start, $end])
                    ->where(function($q) {
                        $q->where('message_text', 'like', 'signin%')
                          ->orWhere('message_text', 'like', 'sign in%')
                          ->orWhere('message_text', 'back');
                    })
                    ->where(function ($q) {
                        $q->whereNull('approval_status')
                          ->orWhere('approval_status', '!=', 'rejected');
                    })
                    ->first();

                if ($prevSignIn) {
                    $autoMsgAt = $prevSignIn->message_at->copy()->addMinute();
                    self::create([
                        'event_id' => 'auto_out_' . $prevSignIn->id,
                        'slack_user_id' => $message->slack_user_id,
                        'channel_id' => $prevSignIn->channel_id,
                        'message_text' => 'sign out',
                        'is_edited' => false,
                        'slack_ts' => 'auto_ts_' . $prevSignIn->id,
                        'message_at' => $autoMsgAt,
                        'approval_status' => 'pending',
                        'is_auto_appended' => true,
                    ]);
                }
            }
        }
    }

    /**
     * Automatically append a pending "back" 1 minute after a break if a subsequent checkout (sign out or break) is initiated without checking back in first.
     */
    public static function autoAppendBackIfForgotten(self $message): void
    {
        // Removed - break recovery is now handled dynamically in track record
    }

    /**
     * Recreate the auto-appended back message if an approved back override is reset/pending.
     */
    public static function recreateAutoAppendBackIfNecessary(self $message): void
    {
        // Removed - break recovery is now handled dynamically in track record
    }

    /**
     * Compute the daily break balance for a user on a specific calendar day.
     *
     * Walks all break+back pairs chronologically, deducts from the balance,
     * and returns structured data about each pair including any split point
     * where the balance ran out mid-break.
     *
     * @param  string       $slackUserId
     * @param  string       $dateStr       'Y-m-d' in Asia/Karachi timezone
     * @return array{balance_seconds:int, consumed_seconds:int, remaining_seconds:int, exhausted_at:\Carbon\Carbon|null, break_pairs:array}
     */
    public static function computeDayBreakBalance(string $slackUserId, string $dateStr, bool $checkPrevDayExhaustion = true): array
    {
        $tz = 'Asia/Karachi';
        $carbonDate = \Carbon\Carbon::parse($dateStr, $tz);
        $dayStart   = $carbonDate->copy()->startOfDay()->timezone('UTC');
        $dayEnd     = $carbonDate->copy()->endOfDay()->timezone('UTC');
        $midnightNext = $carbonDate->copy()->addDay()->startOfDay()->timezone('UTC');

        // -----------------------------------------------------------------------
        // 1. Look back for an overnight break that started BEFORE today ($dayStart)
        //    and crossed midnight into today.
        // -----------------------------------------------------------------------
        $prevBreak = self::where('slack_user_id', $slackUserId)
            ->where('message_at', '<', $dayStart)
            ->where('message_at', '>=', $dayStart->copy()->subHours(24))
            ->where(function ($q) {
                $q->whereNull('approval_status')
                  ->orWhere('approval_status', 'approved')
                  ->orWhere(function ($sub) {
                      $sub->where('approval_status', 'pending')
                          ->where('is_auto_appended', true);
                  })
                  ->orWhere('approval_status', 'pending_edit');
            })
            ->orderBy('message_at', 'desc')
            ->first();

        $carryoverBreak = null;
        if ($prevBreak && preg_match('/^(break|brb)/i', trim($prevBreak->message_text))) {
            $nextBack = self::where('slack_user_id', $slackUserId)
                ->where('message_at', '>', $prevBreak->message_at)
                ->where(function ($q) {
                    $q->whereNull('approval_status')
                      ->orWhere('approval_status', 'approved')
                      ->orWhere(function ($sub) {
                          $sub->where('approval_status', 'pending')
                              ->where('is_auto_appended', true);
                      })
                      ->orWhere('approval_status', 'pending_edit');
                })
                ->orderBy('message_at', 'asc')
                ->first();

            $isActualBack = ($nextBack && preg_match('/^back\b/i', strtolower(trim($nextBack->message_text))));
            $isOpen = ($nextBack === null || !$isActualBack);
            $backTime = ($nextBack && $isActualBack) ? $nextBack->message_at : ($nextBack ? $nextBack->message_at : \Carbon\Carbon::now('UTC'));

            // If backTime is after dayStart, the break crossed midnight into today!
            if ($backTime->gt($dayStart)) {
                $effectiveEndTime = $backTime->gt($dayEnd) ? $dayEnd : $backTime;
                $carryoverDuration = (int)max(0, abs($effectiveEndTime->diffInSeconds($dayStart)));

                if ($carryoverDuration > 0) {
                    $carryoverBreak = [
                        'break_msg'        => $prevBreak,
                        'back_msg'         => $isActualBack ? $nextBack : null,
                        'break_start'      => $dayStart, // Portions on today starts at 00:00:00 UTC (midnight)
                        'back_time'        => $isOpen ? null : $effectiveEndTime,
                        'duration_seconds' => $carryoverDuration,
                        'is_carryover'     => true,
                        'is_open'          => $isOpen,
                        'is_auto_break'    => (bool)($prevBreak->is_auto_appended ?? false),
                        'is_auto_back'     => ($isActualBack && (bool)($nextBack->is_auto_appended ?? false)),
                    ];
                }
            }
        }

        // -----------------------------------------------------------------------
        // 2. Fetch all messages on today and look forward 24h for overnight backs
        // -----------------------------------------------------------------------
        $todayMsgs = self::where('slack_user_id', $slackUserId)
            ->whereBetween('message_at', [$dayStart, $dayEnd])
            ->where(function ($q) {
                $q->whereNull('approval_status')
                  ->orWhere('approval_status', 'approved')
                  ->orWhere(function ($sub) {
                      $sub->where('approval_status', 'pending')
                          ->where('is_auto_appended', true);
                  })
                  ->orWhere('approval_status', 'pending_edit');
            })
            ->orderBy('message_at', 'asc')
            ->get();

        $nextDayMsgs = self::where('slack_user_id', $slackUserId)
            ->where('message_at', '>', $dayEnd)
            ->where('message_at', '<=', $dayEnd->copy()->addHours(24))
            ->where(function ($q) {
                $q->whereNull('approval_status')
                  ->orWhere('approval_status', 'approved')
                  ->orWhere(function ($sub) {
                      $sub->where('approval_status', 'pending')
                          ->where('is_auto_appended', true);
                  })
                  ->orWhere('approval_status', 'pending_edit');
            })
            ->orderBy('message_at', 'asc')
            ->get();

        $allForward = $todayMsgs->concat($nextDayMsgs)->values();

        $balanceSeconds        = self::getBreakBalanceSeconds();
        $gracePeriodSeconds    = self::getBreakGracePeriodSeconds();
        $totalEffectiveBalance = $balanceSeconds + $gracePeriodSeconds;
        $balanceLeft           = $totalEffectiveBalance;
        $totalRawBreakSeconds  = 0;
        $exhaustedAt           = null;
        $breakPairs            = [];

        // Process carryover break first against today's balance
        if ($carryoverBreak && $carryoverBreak['duration_seconds'] > 0) {
            $dur = $carryoverBreak['duration_seconds'];
            $bStart = $carryoverBreak['break_start'];
            $bEnd = $carryoverBreak['back_time'];
            $totalRawBreakSeconds += $dur;

            $pData = [
                'break_msg_id'           => $carryoverBreak['break_msg']->id,
                'back_msg_id'            => $carryoverBreak['back_msg'] ? $carryoverBreak['back_msg']->id : null,
                'break_start'            => $bStart->toIso8601String(),
                'back_time'              => $carryoverBreak['is_open'] ? null : $bEnd->toIso8601String(),
                'is_auto_appended_break' => $carryoverBreak['is_auto_break'],
                'is_auto_appended_back'  => $carryoverBreak['is_auto_back'],
                'duration_seconds'       => $dur,
                'balance_consumed'       => 0,
                'session_out_seconds'    => 0,
                'split_time'             => null,
                'is_fully_session_out'   => false,
                'break_exceeded'         => false,
                'is_open_break'          => $carryoverBreak['is_open'],
                'is_carryover'           => true,
            ];

            if ($balanceLeft >= $dur) {
                $pData['balance_consumed'] = $dur;
                $balanceLeft -= $dur;
            } elseif ($balanceLeft > 0) {
                $consumed = $balanceLeft;
                $pData['balance_consumed']    = $consumed;
                $pData['session_out_seconds'] = $dur - $consumed;
                $splitTime = $bStart->copy()->addSeconds($consumed);
                $pData['split_time']          = $splitTime->toIso8601String();
                $pData['break_exceeded']      = true;
                if ($exhaustedAt === null) $exhaustedAt = $splitTime->toIso8601String();
                $balanceLeft = 0;
            } else {
                $pData['balance_consumed']    = 0;
                $pData['session_out_seconds'] = $dur;
                $pData['split_time']          = $bStart->toIso8601String();
                $pData['is_fully_session_out']= true;
                $pData['break_exceeded']      = true;
                if ($exhaustedAt === null) $exhaustedAt = $bStart->toIso8601String();
            }

            $breakPairs[] = $pData;
        }

        // Process breaks that started on today
        $count = count($allForward);
        for ($i = 0; $i < $count; $i++) {
            $msg = $allForward[$i];
            if ($msg->message_at->gt($dayEnd)) continue; // Not today
            $text = strtolower(trim($msg->message_text));
            if (!preg_match('/^(break|brb)/i', $text)) continue;

            $breakStart = $msg->message_at;
            $breakMsgId = $msg->id;
            $isAutoBreak = (bool)($msg->is_auto_appended ?? false);

            // Find next back
            $nextBack = null;
            for ($j = $i + 1; $j < $count; $j++) {
                $nxt = $allForward[$j];
                $nxtText = strtolower(trim($nxt->message_text));
                if (preg_match('/^back\b/i', $nxtText)) {
                    $nextBack = $nxt;
                    break;
                }
                if (preg_match('/^(break|brb)/i', $nxtText) || str_contains($nxtText, 'signout') || str_contains($nxtText, 'sign out')) {
                    break;
                }
            }

            $isOpenBreak = ($nextBack === null);
            $backTime = $isOpenBreak ? \Carbon\Carbon::now('UTC') : $nextBack->message_at;
            $isAutoBack = $isOpenBreak ? false : (bool)($nextBack->is_auto_appended ?? false);

            // Cap break duration at midnight for today's balance deduction!
            $effectiveBackTimeForToday = $backTime->gt($midnightNext) ? $midnightNext : $backTime;
            $durationSeconds = (int)max(0, abs($effectiveBackTimeForToday->diffInSeconds($breakStart)));
            $totalRawBreakSeconds += $durationSeconds;

            $pairData = [
                'break_msg_id'          => $breakMsgId,
                'back_msg_id'           => $nextBack ? $nextBack->id : null,
                'break_start'           => $breakStart->toIso8601String(),
                'back_time'             => $isOpenBreak ? null : $backTime->toIso8601String(),
                'is_auto_appended_break'=> $isAutoBreak,
                'is_auto_appended_back' => $isAutoBack,
                'duration_seconds'      => $durationSeconds,
                'balance_consumed'      => 0,
                'session_out_seconds'   => 0,
                'split_time'            => null,
                'is_fully_session_out'  => false,
                'break_exceeded'        => false,
                'is_open_break'         => $isOpenBreak,
                'is_carryover'          => false,
            ];

            if ($balanceLeft >= $durationSeconds) {
                $pairData['balance_consumed'] = $durationSeconds;
                $balanceLeft -= $durationSeconds;
            } elseif ($balanceLeft > 0) {
                $consumed = $balanceLeft;
                $pairData['balance_consumed']    = $consumed;
                $pairData['session_out_seconds'] = $durationSeconds - $consumed;
                $splitTime = $breakStart->copy()->addSeconds($consumed);
                $pairData['split_time']          = $splitTime->toIso8601String();
                $pairData['break_exceeded']      = true;
                if ($exhaustedAt === null) $exhaustedAt = $splitTime->toIso8601String();
                $balanceLeft = 0;
            } else {
                $pairData['balance_consumed']    = 0;
                $pairData['session_out_seconds'] = $durationSeconds;
                $pairData['split_time']          = $breakStart->toIso8601String();
                $pairData['is_fully_session_out']= true;
                $pairData['break_exceeded']      = true;
                if ($exhaustedAt === null) $exhaustedAt = $breakStart->toIso8601String();
            }

            $breakPairs[] = $pairData;
        }

        $totalSessionOutSeconds = 0;
        foreach ($breakPairs as $pair) {
            $totalSessionOutSeconds += (int)($pair['session_out_seconds'] ?? 0);
        }

        // Official frontend metrics (strictly based on official limit, e.g. 60 mins)
        $officialConsumed = min($balanceSeconds, $totalRawBreakSeconds);
        $officialRemaining = max(0, $balanceSeconds - $totalRawBreakSeconds);
        $officialExceeded = max(0, $totalRawBreakSeconds - $balanceSeconds);

        // Effective backend metrics (including hidden grace period, e.g. 75 mins total)
        $effectiveRemaining = max(0, $totalEffectiveBalance - $totalRawBreakSeconds);

        return [
            'balance_seconds'             => $balanceSeconds,
            'grace_period_seconds'        => $gracePeriodSeconds,
            'consumed_seconds'            => $officialConsumed,
            'remaining_seconds'           => $officialRemaining,
            'exceeded_seconds'            => $officialExceeded,
            'effective_remaining_seconds' => $effectiveRemaining,
            'session_out_seconds'         => $totalSessionOutSeconds,
            'exhausted_at'                => $exhaustedAt,
            'break_pairs'                 => $breakPairs,
        ];
    }

    /**
     * Recalculate and persist the break_exceeded flags for a user on a specific calendar day.
     * Uses the new cumulative daily balance system.
     * Also caches the full balance result for use by reconstructSessions().
     */
    public static function recalculateBreakExceeded(string $slackUserId, string $date, bool $recalcNext = true): void
    {
        $tz          = 'Asia/Karachi';
        $carbonDate  = \Carbon\Carbon::parse($date, $tz);
        $start       = $carbonDate->copy()->startOfDay()->timezone('UTC');
        $end         = $carbonDate->copy()->endOfDay()->timezone('UTC');

        // Clear cache and compute fresh balance data
        \Illuminate\Support\Facades\Cache::forget("break_balance_{$slackUserId}_{$date}");
        $balanceData = self::computeDayBreakBalance($slackUserId, $date);

        // Reset all break_exceeded flags for the day
        self::where('slack_user_id', $slackUserId)
            ->whereBetween('message_at', [$start, $end])
            ->update(['break_exceeded' => false]);

        // Set break_exceeded = true on pairs that resulted in a session-out (strictly break/back records only)
        foreach ($balanceData['break_pairs'] as $pair) {
            if ($pair['break_exceeded']) {
                if (!empty($pair['break_msg_id'])) {
                    self::where('id', $pair['break_msg_id'])
                        ->where(function($q) {
                            $q->where('message_text', 'like', 'break%')
                              ->orWhere('message_text', 'like', 'brb%');
                        })
                        ->update(['break_exceeded' => true]);
                }
                if (!empty($pair['back_msg_id'])) {
                    self::where('id', $pair['back_msg_id'])
                        ->where(function($q) {
                            $q->where('message_text', 'like', 'back%');
                        })
                        ->update(['break_exceeded' => true]);
                }
            }
        }

        // Cache the full balance data for fast retrieval by reconstructSessions()
        $isToday = ($date === \Carbon\Carbon::now($tz)->format('Y-m-d'));
        if ($isToday) {
            \Illuminate\Support\Facades\Cache::put("break_balance_{$slackUserId}_{$date}", $balanceData, now()->addSeconds(15));
        } else {
            \Illuminate\Support\Facades\Cache::forever("break_balance_{$slackUserId}_{$date}", $balanceData);
        }

        // Also refresh the next day's balance (for carryover calculation)
        if ($recalcNext) {
            $nextDate = $carbonDate->copy()->addDay()->format('Y-m-d');
            \Illuminate\Support\Facades\Cache::forget("break_balance_{$slackUserId}_{$nextDate}");
            self::recalculateBreakExceeded($slackUserId, $nextDate, false);
        }
    }

    /**
     * Ensure the correct auto-appended back and sign-out messages exist for a given day.
     * This is called when a new day's sign-in occurs, or when overrides on that day are approved/reset.
     */
    public static function ensureDayAutoAppends(string $slackUserId, string $date, bool $sendNotifications = true): void
    {
        $tz = 'Asia/Karachi';
        $carbonDate = \Carbon\Carbon::parse($date)->timezone($tz);
        $start = $carbonDate->copy()->startOfDay()->timezone('UTC');
        $end = $carbonDate->copy()->endOfDay()->timezone('UTC');

        // Check if there is a check-in on a subsequent day
        $hasLaterCheckIn = self::where('slack_user_id', $slackUserId)
            ->where('message_at', '>', $end)
            ->where(function ($q) {
                $q->whereNull('approval_status')
                  ->orWhere('approval_status', 'approved')
                  ->orWhere('approval_status', 'pending_edit');
            })
            ->where(function ($q) {
                $q->where('message_text', 'like', 'signin%')
                  ->orWhere('message_text', 'like', 'sign in%')
                  ->orWhere('message_text', 'like', 'back%');
            })
            ->exists();

        // Check if there is an explicit sign-out on this day (day is closed)
        $hasSignOut = self::where('slack_user_id', $slackUserId)
            ->whereBetween('message_at', [$start, $end])
            ->where(function ($q) {
                $q->whereNull('approval_status')
                  ->orWhere('approval_status', 'approved')
                  ->orWhere('approval_status', 'pending_edit');
            })
            ->where(function ($q) {
                $q->where('message_text', 'like', 'signout%')
                  ->orWhere('message_text', 'like', 'sign out%');
            })
            ->exists();

        $isToday = ($date === \Carbon\Carbon::now($tz)->format('Y-m-d'));

        if (!$isToday && !$hasLaterCheckIn && !$hasSignOut) {
            return;
        }

        // Suppress notifications if there is a pending manual override on this day (so we don't double notify),
        // but still run the healing engine to prevent overnight session spillover.
        $hasPendingOverride = self::where('slack_user_id', $slackUserId)
            ->where('is_auto_appended', false)
            ->where('approval_status', 'pending')
            ->whereBetween('message_at', [$start, $end])
            ->exists();

        if ($hasPendingOverride) {
            $sendNotifications = false;
        }

        // Check if there is any rejected auto-append on this day
        $hasRejectedAutoAppend = self::where('slack_user_id', $slackUserId)
            ->where('is_auto_appended', true)
            ->where('approval_status', 'rejected')
            ->whereBetween('message_at', [$start, $end])
            ->exists();

        if ($hasRejectedAutoAppend) {
            \Illuminate\Support\Facades\Cache::forever("healed_{$slackUserId}_{$date}", true);
            return;
        }

        // 1. Delete existing auto-appended records for this day that are pending, 
        // or are auto-approved virtual adjustments (signin, break, back)
        self::where('slack_user_id', $slackUserId)
            ->where('is_auto_appended', true)
            ->whereBetween('message_at', [$start, $end])
            ->where(function ($q) {
                $q->where('approval_status', 'pending')
                  ->orWhereIn('message_text', ['signin', 'break', 'back']);
            })
            ->delete();

        // 2. Fetch all real and approved/decisioned auto-appended messages for this day
        $dayMsgs = self::where('slack_user_id', $slackUserId)
            ->whereBetween('message_at', [$start, $end])
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('is_auto_appended', false)
                        ->where(function ($s) {
                            $s->whereNull('approval_status')
                              ->orWhere('approval_status', 'approved')
                              ->orWhere('approval_status', 'pending_edit');
                        });
                })
                ->orWhere(function ($sub) {
                    $sub->where('is_auto_appended', true)
                        ->whereIn('approval_status', ['approved', 'pending_edit']);
                });
            })
            ->get();

        // If there are no messages at all, we don't need any auto-appends
        if ($dayMsgs->isEmpty()) {
            \Illuminate\Support\Facades\Cache::forever("healed_{$slackUserId}_{$date}", true);
            return;
        }

        // Helper function to parse message time with override text
        $parseTime = function ($msg) use ($date, $tz) {
            if ($msg->approval_status !== null && $msg->approval_status !== 'pending_edit') {
                return $msg->message_at->timezone($tz);
            }
            $defaultTime = $msg->message_at->timezone($tz);
            if (preg_match('/at\s+(\d{1,2})(?:[:\s]+(\d{2}))?\s*(AM|PM)/i', $msg->message_text, $matches)) {
                $hours = (int)$matches[1];
                $minutes = isset($matches[2]) && $matches[2] !== '' ? (int)$matches[2] : 0;
                $ampm = strtoupper($matches[3]);
                try {
                    $timeStr = sprintf("%s %02d:%02d %s", $date, $hours, $minutes, $ampm);
                    return \Carbon\Carbon::parse($timeStr, $tz);
                } catch (\Exception $e) {
                    return $defaultTime;
                }
            }
            return $defaultTime;
        };

        // Sort day messages chronologically
        $dayMsgs = $dayMsgs->all();
        usort($dayMsgs, function($a, $b) use ($parseTime) {
            return $parseTime($a)->timestamp <=> $parseTime($b)->timestamp;
        });

        $autoAppendsToCreate = [];
        $virtualList = [];

        // Determine if the preceding day's session was left OPEN (Checked In)
        $isOvernightContinuation = false;
        $isOvernightBreakContinuation = false;
        $prevMsg = self::where('slack_user_id', $slackUserId)
            ->where('message_at', '<', $start)
            ->where(function ($q) {
                $q->whereNull('approval_status')
                  ->orWhere('approval_status', 'approved')
                  ->orWhere('approval_status', 'pending_edit');
            })
            ->latest('message_at')
            ->first();

        if ($prevMsg) {
            $prevText = strtolower(trim($prevMsg->message_text));
            $isPrevCheckIn = (str_contains($prevText, 'signin') || str_contains($prevText, 'sign in') || preg_match('/^back\b/i', $prevText));
            if ($isPrevCheckIn) {
                $prevTime = $prevMsg->message_at->timezone($tz);
                $firstMsg = $dayMsgs[0];
                $firstParsedTime = $parseTime($firstMsg);
                if ($prevTime->diffInHours($firstParsedTime) <= 14) {
                    $isOvernightContinuation = true;
                }
            }

            $isPrevBreak = preg_match('/^(break|brb)/i', $prevText);
            if ($isPrevBreak) {
                $prevTime = $prevMsg->message_at->timezone($tz);
                // FirstMsg parsing is done here
                $firstMsg = $dayMsgs[0];
                $firstParsedTime = $parseTime($firstMsg);
                if ($prevTime->diffInHours($firstParsedTime) < 6) {
                    $isOvernightBreakContinuation = true;
                }
            }
        }

        // Let's trace the list to determine start-of-day sequence healing
        $firstMsg = $dayMsgs[0];
        $firstParsedTime = $parseTime($firstMsg);
        $firstText = strtolower(trim($firstMsg->message_text));

        $isFirstCheckOut = (str_contains($firstText, 'signout') || str_contains($firstText, 'sign out') || preg_match('/^(break|brb)/i', $firstText));
        $isFirstBack = preg_match('/^back\b/i', $firstText);

        if ($isFirstCheckOut) {
            // Isolate forgotten back scenario: break followed directly by checkout within 6 hours
            $isForgottenBack = false;
            if ($prevMsg) {
                $prevText = strtolower(trim($prevMsg->message_text));
                $isPrevBreak = preg_match('/^(break|brb)/i', $prevText);
                if ($isPrevBreak) {
                    $prevTime = $prevMsg->message_at->timezone($tz);
                    if ($prevTime->diffInHours($firstParsedTime) < 6) {
                        $isForgottenBack = true;
                    }
                }
            }

            if ($isForgottenBack) {
                // Instead of appending a signin today, create a virtual back for yesterday!
                $virtualBackTime = $prevMsg->message_at->copy()->addMinute();
                $exists = self::where('slack_user_id', $slackUserId)
                    ->where('message_text', 'back')
                    ->where('message_at', $virtualBackTime)
                    ->exists();
                if (!$exists) {
                    $slackTs = 'auto_ts_back_' . $prevMsg->id . '_' . rand(1000, 9999);
                    self::create([
                        'event_id' => 'auto_back_' . $prevMsg->id . '_' . rand(1000, 9999),
                        'slack_user_id' => $slackUserId,
                        'channel_id' => 'manual_override',
                        'message_text' => 'back',
                        'is_edited' => false,
                        'slack_ts' => $slackTs,
                        'message_at' => $virtualBackTime,
                        'approval_status' => 'approved',
                        'is_auto_appended' => true,
                    ]);
                }
                // Mark overnight continuation as true so we skip signin auto-append for today
                $isOvernightContinuation = true;
            }

            if (!$isOvernightContinuation) {
                $virtualTime = $firstParsedTime->copy()->subMinute();
                $virtualList[] = [
                    'message_text' => 'signin',
                    'message_at' => $virtualTime->timezone('UTC'),
                    'approval_status' => 'approved',
                ];
                $autoAppendsToCreate[] = [
                    'message_text' => 'signin',
                    'message_at' => $virtualTime->timezone('UTC'),
                    'approval_status' => 'approved',
                ];
            }
        } elseif ($isFirstBack) {
            if ($isOvernightBreakContinuation) {
                // Continuous break crossing midnight. No virtual messages needed!
            } elseif ($isOvernightContinuation) {
                $virtualBreakTime = $firstParsedTime->copy()->subMinute();
                $virtualList[] = [
                    'message_text' => 'break',
                    'message_at' => $virtualBreakTime->timezone('UTC'),
                    'approval_status' => 'approved',
                ];
                $autoAppendsToCreate[] = [
                    'message_text' => 'break',
                    'message_at' => $virtualBreakTime->timezone('UTC'),
                    'approval_status' => 'approved',
                ];
            } else {
                $virtualInTime = $firstParsedTime->copy()->subMinutes(2);
                $virtualBreakTime = $firstParsedTime->copy()->subMinute();
                $virtualList[] = [
                    'message_text' => 'signin',
                    'message_at' => $virtualInTime->timezone('UTC'),
                    'approval_status' => 'approved',
                ];
                $virtualList[] = [
                    'message_text' => 'break',
                    'message_at' => $virtualBreakTime->timezone('UTC'),
                    'approval_status' => 'approved',
                ];
                $autoAppendsToCreate[] = [
                    'message_text' => 'signin',
                    'message_at' => $virtualInTime->timezone('UTC'),
                    'approval_status' => 'approved',
                ];
                $autoAppendsToCreate[] = [
                    'message_text' => 'break',
                    'message_at' => $virtualBreakTime->timezone('UTC'),
                    'approval_status' => 'approved',
                ];
            }
        }

        // Add first real message to the virtual list
        $virtualList[] = [
            'message_text' => $firstMsg->message_text,
            'message_at' => $firstMsg->message_at,
            'approval_status' => $firstMsg->approval_status ?? 'approved',
            'id' => $firstMsg->id,
        ];

        // Process other messages and intermediate healing
        $countReal = count($dayMsgs);
        for ($i = 1; $i < $countReal; $i++) {
            $msg = $dayMsgs[$i];
            $msgTime = $parseTime($msg);
            $text = strtolower(trim($msg->message_text));

            // Determine active status from virtualList up to here
            $activeCheckIn = null;
            $lastCheckoutTime = null;

            foreach ($virtualList as $vMsg) {
                $vText = strtolower(trim($vMsg['message_text']));
                $isVCheckIn = (str_contains($vText, 'signin') || str_contains($vText, 'sign in') || preg_match('/^back\b/i', $vText));
                $isVCheckOut = (str_contains($vText, 'signout') || str_contains($vText, 'sign out') || preg_match('/^(break|brb)/i', $vText));

                if ($isVCheckIn) {
                    $activeCheckIn = \Carbon\Carbon::parse($vMsg['message_at'])->timezone($tz);
                } elseif ($isVCheckOut) {
                    $activeCheckIn = null;
                    $lastCheckoutTime = \Carbon\Carbon::parse($vMsg['message_at'])->timezone($tz);
                }
            }

            $isCheckIn = (str_contains($text, 'signin') || str_contains($text, 'sign in') || preg_match('/^back\b/i', $text));
            $isCheckOut = (str_contains($text, 'signout') || str_contains($text, 'sign out') || preg_match('/^(break|brb)/i', $text));

            if ($isCheckIn) {
                if ($activeCheckIn) {
                    $virtualBreak = $msgTime->copy()->subMinute();
                    if ($virtualBreak->lte($activeCheckIn)) {
                        $virtualBreak = $activeCheckIn->copy()->addSecond();
                    }
                    $virtualList[] = [
                        'message_text' => 'break',
                        'message_at' => $virtualBreak->timezone('UTC'),
                        'approval_status' => 'approved',
                    ];
                    $autoAppendsToCreate[] = [
                        'message_text' => 'break',
                        'message_at' => $virtualBreak->timezone('UTC'),
                        'approval_status' => 'approved',
                        'ref_time' => $msgTime, // Came back at this time
                    ];
                }
            } elseif ($isCheckOut) {
                if (!$activeCheckIn) {
                    if (!$lastCheckoutTime) {
                        $virtualSignIn = $msgTime->copy()->subMinute();
                        $virtualList[] = [
                            'message_text' => 'signin',
                            'message_at' => $virtualSignIn->timezone('UTC'),
                            'approval_status' => 'approved',
                        ];
                        $autoAppendsToCreate[] = [
                            'message_text' => 'signin',
                            'message_at' => $virtualSignIn->timezone('UTC'),
                            'approval_status' => 'approved',
                        ];
                    } else {
                        $virtualBack = $lastCheckoutTime->copy()->addMinute();
                        if ($virtualBack->gte($msgTime)) {
                            $virtualBack = $msgTime->copy()->subSecond();
                        }
                        $virtualList[] = [
                            'message_text' => 'back',
                            'message_at' => $virtualBack->timezone('UTC'),
                            'approval_status' => 'approved',
                        ];
                        $autoAppendsToCreate[] = [
                            'message_text' => 'back',
                            'message_at' => $virtualBack->timezone('UTC'),
                            'approval_status' => 'approved',
                            'ref_time' => $lastCheckoutTime, // Break started at this time
                        ];
                    }
                }
            }

            $virtualList[] = [
                'message_text' => $msg->message_text,
                'message_at' => $msg->message_at,
                'approval_status' => $msg->approval_status ?? 'approved',
                'id' => $msg->id,
            ];
        }

        if (!$isToday) {
            // End of day healing
            $activeCheckIn = null;
            $lastMsgInList = null;
            foreach ($virtualList as $vMsg) {
                $vText = strtolower(trim($vMsg['message_text']));
                $isVCheckIn = (str_contains($vText, 'signin') || str_contains($vText, 'sign in') || preg_match('/^back\b/i', $vText));
                $isVCheckOut = (str_contains($vText, 'signout') || str_contains($vText, 'sign out') || preg_match('/^(break|brb)/i', $vText));

                if ($isVCheckIn) {
                    $activeCheckIn = \Carbon\Carbon::parse($vMsg['message_at'])->timezone($tz);
                } elseif ($isVCheckOut) {
                    $activeCheckIn = null;
                }
                $lastMsgInList = $vMsg;
            }

            if ($activeCheckIn) {
                // Check if the subsequent day starts with a checkout or back (night shift continuation)
                $nextDayMsg = self::where('slack_user_id', $slackUserId)
                    ->where('message_at', '>', $end)
                    ->where(function ($q) {
                        $q->whereNull('approval_status')
                          ->orWhere('approval_status', 'approved')
                          ->orWhere('approval_status', 'pending_edit');
                    })
                    ->orderBy('message_at', 'asc')
                    ->first();

                $isNextDayNightShift = false;
                if ($nextDayMsg) {
                    $nextText = strtolower(trim($nextDayMsg->message_text));
                    $isNextDayNightShift = (str_contains($nextText, 'signout') || str_contains($nextText, 'sign out') || preg_match('/^(break|brb|back)/i', $nextText));
                }

                if (!$isNextDayNightShift) {
                    $autoSignOutAt = $activeCheckIn->copy()->addMinute();
                    $autoAppendsToCreate[] = [
                        'message_text' => 'sign out',
                        'message_at' => $autoSignOutAt->timezone('UTC'),
                        'approval_status' => 'pending',
                    ];
                }
            } else {
                if ($lastMsgInList) {
                    $lastText = strtolower(trim($lastMsgInList['message_text']));
                    $lastIsCheckOut = (str_contains($lastText, 'signout') || str_contains($lastText, 'sign out'));
                    if (!$lastIsCheckOut) {
                        $isLastBreak = preg_match('/^(break|brb)/i', $lastText);
                        if ($isLastBreak) {
                            // Check if the subsequent day starts with a checkout or back
                            $nextDayMsg = self::where('slack_user_id', $slackUserId)
                                ->where('message_at', '>', $end)
                                ->where(function ($q) {
                                    $q->whereNull('approval_status')
                                      ->orWhere('approval_status', 'approved')
                                      ->orWhere('approval_status', 'pending_edit');
                                })
                                ->orderBy('message_at', 'asc')
                                ->first();

                            $isNextDayNightShift = false;
                            if ($nextDayMsg) {
                                $nextText = strtolower(trim($nextDayMsg->message_text));
                                $isNextDayNightShift = (str_contains($nextText, 'signout') || str_contains($nextText, 'sign out') || preg_match('/^(break|brb|back)/i', $nextText));
                            }

                            if (!$isNextDayNightShift) {
                                $breakAt = \Carbon\Carbon::parse($lastMsgInList['message_at'])->timezone($tz);
                                $virtualBackAt = $breakAt->copy()->addMinute();
                                $autoSignOutAt = $breakAt->copy()->addMinutes(2);

                                $autoAppendsToCreate[] = [
                                    'message_text' => 'back',
                                    'message_at' => $virtualBackAt->timezone('UTC'),
                                    'approval_status' => 'approved',
                                    'ref_time' => $breakAt, // Break started at this time
                                ];
                                $autoAppendsToCreate[] = [
                                    'message_text' => 'sign out',
                                    'message_at' => $autoSignOutAt->timezone('UTC'),
                                    'approval_status' => 'pending',
                                ];
                            }
                        }
                    }
                }
            }
        }

        // 3. Save all the generated auto-appends to the database
        foreach ($autoAppendsToCreate as $index => $append) {
            $refMsg = $dayMsgs[0]; // fallback
            
            $isSignOut = (str_contains(strtolower($append['message_text']), 'signout') || str_contains(strtolower($append['message_text']), 'sign out'));
            $typeStr = $isSignOut ? 'out' : ($append['message_text'] === 'signin' ? 'in' : ($append['message_text'] === 'break' ? 'break' : 'back'));
            
            $eventId = 'auto_' . $typeStr . '_' . $refMsg->id . '_' . $index . '_' . uniqid();
            $slackTs = 'auto_ts_' . $refMsg->id . '_' . $index . '_' . uniqid();

            $newAutoMsg = self::create([
                'event_id' => $eventId,
                'slack_user_id' => $slackUserId,
                'channel_id' => 'manual_override',
                'message_text' => $append['message_text'],
                'is_edited' => false,
                'slack_ts' => $slackTs,
                'message_at' => $append['message_at'],
                'approval_status' => $append['approval_status'],
                'is_auto_appended' => true,
            ]);

            if ($sendNotifications) {
                $employeeName = $slackUserId;
                if (!$newAutoMsg->relationLoaded('mapping')) {
                    $newAutoMsg->load('mapping.user');
                }
                $targetUser = $newAutoMsg->mapping?->user;
                if ($newAutoMsg->mapping) {
                    if ($newAutoMsg->mapping->slack_username) {
                        $employeeName = $newAutoMsg->mapping->slack_username;
                    } elseif ($targetUser) {
                        $employeeName = $targetUser->name;
                    }
                }
                
                $dateFormatted = $carbonDate->format('l, F jS');
                $refTimeStr = '';
                if (isset($append['ref_time'])) {
                    $refTimeStr = $append['ref_time']->timezone($tz)->format('h:i A');
                }

                $adminNotificationMsg = '';
                $employeeNotificationMsg = '';
                $notificationClass = null;

                if ($isSignOut) {
                    $adminNotificationMsg = "[Forgot Sign Out] {$employeeName}: \"Forgot to sign out on {$dateFormatted}.\"";
                    $employeeNotificationMsg = "[Forgot Sign Out] You forgot to sign out on {$dateFormatted}. The system auto-healed this session.";
                    $notificationClass = \App\Notifications\ForgotToSignOutNotification::class;
                } elseif ($append['message_text'] === 'signin') {
                    $adminNotificationMsg = "[Forgot Sign In] {$employeeName}: \"Forgot to sign in on {$dateFormatted}.\"";
                    $employeeNotificationMsg = "[Forgot Sign In] You forgot to sign in on {$dateFormatted}. The system auto-healed this session.";
                    $notificationClass = \App\Notifications\AutoAppendNotification::class;
                } elseif ($append['message_text'] === 'break') {
                    $adminNotificationMsg = "[Forgot Break Start] {$employeeName}: \"Forgot to start break, came back at {$refTimeStr} on {$dateFormatted}.\"";
                    $employeeNotificationMsg = "[Forgot Break Start] You forgot to start break before returning at {$refTimeStr} on {$dateFormatted}. The system auto-healed this session.";
                    $notificationClass = \App\Notifications\AutoAppendNotification::class;
                } elseif ($append['message_text'] === 'back') {
                    $adminNotificationMsg = "[Forgot Break End] {$employeeName}: \"Forgot to end break which started at {$refTimeStr} on {$dateFormatted}.\"";
                    $employeeNotificationMsg = "[Forgot Break End] You forgot to end break which started at {$refTimeStr} on {$dateFormatted}. The system auto-healed this session.";
                    $notificationClass = \App\Notifications\AutoAppendNotification::class;
                }

                if ($notificationClass) {
                    // 1. Notify Super Admins
                    $alreadyNotifiedAdmin = \Illuminate\Support\Facades\DB::table('notifications')
                        ->where('data->message', $adminNotificationMsg)
                        ->exists();

                    if (!$alreadyNotifiedAdmin) {
                        $superAdmins = \App\Models\User::whereHas('role', function($q) {
                            $q->where('name', \App\Models\Role::SUPER_ADMIN);
                        })->get();

                        foreach ($superAdmins as $admin) {
                            $admin->notify(new $notificationClass($newAutoMsg, $employeeName, $adminNotificationMsg, false));
                        }
                    }

                    // 2. Notify Target Employee
                    if ($targetUser) {
                        $alreadyNotifiedEmployee = \Illuminate\Support\Facades\DB::table('notifications')
                            ->where('notifiable_id', $targetUser->id)
                            ->where('data->message', $employeeNotificationMsg)
                            ->exists();

                        if (!$alreadyNotifiedEmployee) {
                            $targetUser->notify(new $notificationClass($newAutoMsg, $employeeName, $employeeNotificationMsg, true));
                        }
                    }
                }
            }
        }

        // Bust the break balance cache now that new auto-appended rows exist,
        // so the next reconstructSessions / dashboard call always reads fresh data
        // that includes these newly inserted pairs. Without this, a stale 15-second
        // cache could cause the 1-minute auto-break to not be counted, producing
        // a session-out 1 minute early.
        \Illuminate\Support\Facades\Cache::forget("break_balance_{$slackUserId}_{$date}");

        // 4. Cache this day as healed
        \Illuminate\Support\Facades\Cache::forever("healed_{$slackUserId}_{$date}", true);
    }
}