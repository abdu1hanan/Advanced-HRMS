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
                        $wasAlreadyExceeded = $msg->break_exceeded && $nextBack->break_exceeded;
                        $msg->update(['break_exceeded' => true]);
                        $nextBack->update(['break_exceeded' => true]);
                        
                        if (!$wasAlreadyExceeded) {
                            self::notifyHRBreakExceeded($nextBack, $msg);
                        }
                    }
                }
            }
        }
    }

    /**
     * Notify Super Admins when break is exceeded.
     */
    public static function notifyHRBreakExceeded(self $backMsg, self $breakMsg): void
    {
        $employeeName = $backMsg->slack_user_id;
        if (!$backMsg->relationLoaded('mapping')) {
            $backMsg->load('mapping.user');
        }

        if ($backMsg->mapping) {
            if ($backMsg->mapping->slack_username) {
                $employeeName = $backMsg->mapping->slack_username;
            } elseif ($backMsg->mapping->user) {
                $employeeName = $backMsg->mapping->user->name;
            }
        }

        $tz = 'Asia/Karachi';
        $breakTimeFormatted = $breakMsg->message_at->timezone($tz)->format('h:i A');
        $backTimeFormatted = $backMsg->message_at->timezone($tz)->format('h:i A');
        $durationMinutes = round($backMsg->message_at->diffInMinutes($breakMsg->message_at));

        $notificationMsg = "{$employeeName} exceeded their break at {$breakTimeFormatted} (came back at {$backTimeFormatted}, duration: {$durationMinutes} mins) and their sign in/out times were set accordingly.";

        $superAdmins = \App\Models\User::whereHas('role', function($q) {
            $q->where('name', \App\Models\Role::SUPER_ADMIN);
        })->get();

        foreach ($superAdmins as $admin) {
            $admin->notify(new \App\Notifications\BreakExceededNotification($backMsg, $employeeName, $notificationMsg));
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

        if (!$hasLaterCheckIn) {
            return;
        }

        // Complete the day with an auto sign-out if needed
        // Fetch the last message today
        $lastMsg = self::where('slack_user_id', $slackUserId)
            ->whereBetween('message_at', [$start, $end])
            ->where(function ($q) {
                $q->whereNull('approval_status')
                  ->orWhere('approval_status', 'approved')
                  ->orWhere('approval_status', 'pending_edit')
                  ->orWhere(function ($sub) {
                      $sub->where('approval_status', 'pending')
                          ->where('is_auto_appended', true);
                  });
            })
            ->orderBy('message_at', 'desc')
            ->first();

        if ($lastMsg) {
            $lastText = strtolower(trim($lastMsg->message_text));
            // A day must end with a sign-out. If the last message is not a sign-out (e.g. it is signin, back, or break), they forgot to sign out.
            $lastIsCheckOut = (str_contains($lastText, 'signout') || str_contains($lastText, 'sign out'));

            if (!$lastIsCheckOut) {
                // We need an auto-appended sign-out
                $isLastBreak = preg_match('/^(break|brb)/i', $lastText);
                $minutesToAdd = $isLastBreak ? 2 : 1;
                $autoSignOutAt = $lastMsg->message_at->copy()->addMinutes($minutesToAdd);

                // Avoid duplicate
                $exists = self::where('slack_user_id', $slackUserId)
                    ->where('is_auto_appended', true)
                    ->where('message_text', 'sign out')
                    ->whereBetween('message_at', [
                        $autoSignOutAt->copy()->subSeconds(5),
                        $autoSignOutAt->copy()->addSeconds(5)
                    ])
                    ->exists();

                if (!$exists) {
                    $newAutoSignOut = self::create([
                        'event_id' => 'auto_out_' . $lastMsg->id,
                        'slack_user_id' => $slackUserId,
                        'channel_id' => $lastMsg->channel_id,
                        'message_text' => 'sign out',
                        'is_edited' => false,
                        'slack_ts' => 'auto_ts_' . $lastMsg->id,
                        'message_at' => $autoSignOutAt,
                        'approval_status' => 'pending',
                        'is_auto_appended' => true,
                    ]);

                    if ($sendNotifications) {
                        $employeeName = $slackUserId;
                        if (!$newAutoSignOut->relationLoaded('mapping')) {
                            $newAutoSignOut->load('mapping.user');
                        }
                        if ($newAutoSignOut->mapping) {
                            if ($newAutoSignOut->mapping->slack_username) {
                                $employeeName = $newAutoSignOut->mapping->slack_username;
                            } elseif ($newAutoSignOut->mapping->user) {
                                $employeeName = $newAutoSignOut->mapping->user->name;
                            }
                        }
                        $prevDateFormatted = $lastMsg->message_at->timezone($tz)->format('l, F jS');
                        $autoTimeFormatted = $newAutoSignOut->message_at->timezone($tz)->format('h:i A');
                        $notificationMsg = "{$employeeName} forgot to sign out on {$prevDateFormatted}. An auto-appended sign-out has been pending at {$autoTimeFormatted}.";

                        $superAdmins = \App\Models\User::whereHas('role', function($q) {
                            $q->where('name', \App\Models\Role::SUPER_ADMIN);
                        })->get();

                        foreach ($superAdmins as $admin) {
                            $admin->notify(new \App\Notifications\ForgotToSignOutNotification($newAutoSignOut, $employeeName, $notificationMsg));
                        }
                    }
                }
            }
        }
    }
}
