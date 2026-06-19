<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlackMessage extends Model
{
    use HasFactory;

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
     * Recalculate and update the break_exceeded flags for a user on a specific calendar day.
     */
    public static function recalculateBreakExceeded(string $slackUserId, string $date): void
    {
        $tz = 'Asia/Karachi';
        $carbonDate = \Carbon\Carbon::parse($date)->timezone($tz);
        $start = $carbonDate->copy()->startOfDay()->timezone('UTC');
        $end = $carbonDate->copy()->endOfDay()->timezone('UTC');

        // Reset break_exceeded for all messages of this user on this day to false first
        self::where('slack_user_id', $slackUserId)
            ->whereBetween('message_at', [$start, $end])
            ->update(['break_exceeded' => false]);

        // Fetch all check-in/checkout messages for the user on this day
        // We include approved/null status messages, and pending auto-appended messages.
        // We exclude rejected overrides and pending manual overrides (which aren't active yet).
        $messages = self::where('slack_user_id', $slackUserId)
            ->whereBetween('message_at', [$start, $end])
            ->where(function ($q) {
                $q->whereNull('approval_status')
                  ->orWhere('approval_status', 'approved')
                  ->orWhere(function ($sub) {
                      $sub->where('approval_status', 'pending')
                          ->where('is_auto_appended', true);
                  })
                  ->orWhere('approval_status', 'pending_edit');
            })
            ->orderBy('message_at')
            ->get();

        // Pair break and back messages in order
        $count = count($messages);
        for ($i = 0; $i < $count; $i++) {
            $msg = $messages[$i];
            $text = strtolower(trim($msg->message_text));
            $isBreak = preg_match('/^(break|brb)/i', $text);

            if ($isBreak) {
                // Find the next back message
                $nextBack = null;
                for ($j = $i + 1; $j < $count; $j++) {
                    $nextMsg = $messages[$j];
                    $nextText = strtolower(trim($nextMsg->message_text));
                    if (preg_match('/^back\b/i', $nextText)) {
                        $nextBack = $nextMsg;
                        break;
                    }
                    if (preg_match('/^(break|brb)/i', $nextText)) {
                        break;
                    }
                }

                if ($nextBack) {
                    $diff = abs($nextBack->message_at->diffInSeconds($msg->message_at));
                    if ($diff > 600) {
                        $msg->update(['break_exceeded' => true]);
                        $nextBack->update(['break_exceeded' => true]);
                    }
                }
            }
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

        $isToday = ($date === \Carbon\Carbon::now($tz)->format('Y-m-d'));

        if (!$isToday && !$hasLaterCheckIn) {
            return;
        }

        // 1. Delete all existing auto-appended records for this day
        self::where('slack_user_id', $slackUserId)
            ->where('is_auto_appended', true)
            ->whereBetween('message_at', [$start, $end])
            ->delete();

        // 2. Fetch all real/approved messages for this day
        $dayMsgs = self::where('slack_user_id', $slackUserId)
            ->whereBetween('message_at', [$start, $end])
            ->where('is_auto_appended', false)
            ->where(function ($q) {
                $q->whereNull('approval_status')
                  ->orWhere('approval_status', 'approved')
                  ->orWhere('approval_status', 'pending_edit');
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
            if (preg_match('/at\s+(\d{1,2}):(\d{2})\s*(AM|PM)/i', $msg->message_text, $matches)) {
                $hours = (int)$matches[1];
                $minutes = (int)$matches[2];
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

        // Let's trace the list to determine start-of-day sequence healing
        $firstMsg = $dayMsgs[0];
        $firstParsedTime = $parseTime($firstMsg);
        $firstText = strtolower(trim($firstMsg->message_text));

        $isFirstCheckOut = (str_contains($firstText, 'signout') || str_contains($firstText, 'sign out') || preg_match('/^(break|brb)/i', $firstText));
        $isFirstBack = preg_match('/^back\b/i', $firstText);

        if ($isFirstCheckOut) {
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
        } elseif ($isFirstBack) {
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
                $autoSignOutAt = $activeCheckIn->copy()->addMinute();
                $autoAppendsToCreate[] = [
                    'message_text' => 'sign out',
                    'message_at' => $autoSignOutAt->timezone('UTC'),
                    'approval_status' => 'pending',
                ];
            } else {
                if ($lastMsgInList) {
                    $lastText = strtolower(trim($lastMsgInList['message_text']));
                    $lastIsCheckOut = (str_contains($lastText, 'signout') || str_contains($lastText, 'sign out'));
                    if (!$lastIsCheckOut) {
                        $isLastBreak = preg_match('/^(break|brb)/i', $lastText);
                        if ($isLastBreak) {
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
                if ($newAutoMsg->mapping) {
                    if ($newAutoMsg->mapping->slack_username) {
                        $employeeName = $newAutoMsg->mapping->slack_username;
                    } elseif ($newAutoMsg->mapping->user) {
                        $employeeName = $newAutoMsg->mapping->user->name;
                    }
                }
                
                $dateFormatted = $carbonDate->format('l, F jS');
                $refTimeStr = '';
                if (isset($append['ref_time'])) {
                    $refTimeStr = $append['ref_time']->timezone($tz)->format('h:i A');
                }

                $notificationMsg = '';
                $notificationClass = null;

                if ($isSignOut) {
                    $notificationMsg = "[Forgot Sign Out] {$employeeName}: \"Forgot to sign out on {$dateFormatted}.\"";
                    $notificationClass = \App\Notifications\ForgotToSignOutNotification::class;
                } elseif ($append['message_text'] === 'signin') {
                    $notificationMsg = "[Forgot Sign In] {$employeeName}: \"Forgot to sign in on {$dateFormatted}.\"";
                    $notificationClass = \App\Notifications\AutoAppendNotification::class;
                } elseif ($append['message_text'] === 'break') {
                    $notificationMsg = "[Forgot Break Start] {$employeeName}: \"Forgot to start break, came back at {$refTimeStr} on {$dateFormatted}.\"";
                    $notificationClass = \App\Notifications\AutoAppendNotification::class;
                } elseif ($append['message_text'] === 'back') {
                    $notificationMsg = "[Forgot Break End] {$employeeName}: \"Forgot to end break which started at {$refTimeStr} on {$dateFormatted}.\"";
                    $notificationClass = \App\Notifications\AutoAppendNotification::class;
                }

                if ($notificationClass) {
                    $alreadyNotified = \Illuminate\Support\Facades\DB::table('notifications')
                        ->where('data->message', $notificationMsg)
                        ->exists();

                    if (!$alreadyNotified) {
                        $superAdmins = \App\Models\User::whereHas('role', function($q) {
                            $q->where('name', \App\Models\Role::SUPER_ADMIN);
                        })->get();

                        foreach ($superAdmins as $admin) {
                            $admin->notify(new $notificationClass($newAutoMsg, $employeeName, $notificationMsg));
                        }
                    }
                }
            }
        }

        // 4. Cache this day as healed
        \Illuminate\Support\Facades\Cache::forever("healed_{$slackUserId}_{$date}", true);
    }
}
