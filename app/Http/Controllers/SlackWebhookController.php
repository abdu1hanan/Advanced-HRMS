<?php

namespace App\Http\Controllers;

use App\Models\SlackMessage;
use App\Models\SlackMessageEdit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SlackWebhookController extends Controller
{
    /**
     * Normalize and validate message text against Whitelisted Session Keywords.
     * Returns the standardized keyword if valid, otherwise null.
     */
    private function getNormalizedMessageText(string $text): ?string
    {
        $raw = trim($text);
        
        // 0. Check for auto user mapping function (must be full and valid syntax)
        if (preg_match('/^{\s*"?auto_connect_user_profile"?\s*:\s*["\'”“’‘][^"\'”“’‘]+["\'”“’‘]\s*}$/iu', $raw)) {
            // Check if feature is enabled
            $autoConnectEnabled = true;
            $settingFile = storage_path('app/slack_auto_connect_setting.json');
            if (file_exists($settingFile)) {
                $settingData = json_decode(file_get_contents($settingFile), true);
                $autoConnectEnabled = isset($settingData['enabled']) ? (bool) $settingData['enabled'] : true;
            }

            if ($autoConnectEnabled) {
                return $raw;
            }
            return null;
        }

        $lowerRaw = strtolower($raw);

        // 1. Check for break, brb (with optional hyphen and reasons) or back (optional override, e.g. "back at 1:15 pm")
        if (preg_match('/^back(?:\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm|AM|PM|Am|Pm|aM|pM))?$/i', $raw, $matches)) {
            if (isset($matches[1]) && isset($matches[3])) {
                $hour = (int) $matches[1];
                $minute = (isset($matches[2]) && $matches[2] !== '') ? (int) $matches[2] : 0;
                $period = strtoupper($matches[3]);

                // Validate realistic AM/PM hours and minutes
                if ($hour >= 1 && $hour <= 12 && $minute >= 0 && $minute <= 59) {
                    return "back at {$hour}:" . str_pad($minute, 2, '0', STR_PAD_LEFT) . " {$period}";
                }
                return null;
            }
            return 'back';
        }
        if (preg_match('/^(break|brb)(?:\s*-\s*(.*))?$/i', $raw, $matches)) {
            $prefix = strtolower($matches[1]);
            $reason = isset($matches[2]) ? trim($matches[2]) : '';
            return $reason !== '' ? "{$prefix} - {$reason}" : $prefix;
        }

        // 2. Check for Sign In (optional override, e.g. "signin at 7:50 AM" or "sign in at 3pm")
        if (preg_match('/^(signin|sign\s+in)(?:\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm|AM|PM|Am|Pm|aM|pM))?$/i', $raw, $matches)) {
            if (isset($matches[2]) && isset($matches[4])) {
                $hour = (int) $matches[2];
                $minute = (isset($matches[3]) && $matches[3] !== '') ? (int) $matches[3] : 0;
                $period = strtoupper($matches[4]);

                // Validate realistic AM/PM hours and minutes
                if ($hour >= 1 && $hour <= 12 && $minute >= 0 && $minute <= 59) {
                    return "sign in at {$hour}:" . str_pad($minute, 2, '0', STR_PAD_LEFT) . " {$period}";
                }
                return null;
            }
            return 'sign in';
        }

        // 3. Check for Sign Out (optional override, e.g. "signout at 5:00 PM" or "sign out at 3pm")
        if (preg_match('/^(signout|sign\s+out)(?:\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm|AM|PM|Am|Pm|aM|pM))?$/i', $raw, $matches)) {
            if (isset($matches[2]) && isset($matches[4])) {
                $hour = (int) $matches[2];
                $minute = (isset($matches[3]) && $matches[3] !== '') ? (int) $matches[3] : 0;
                $period = strtoupper($matches[4]);

                // Validate realistic AM/PM hours and minutes
                if ($hour >= 1 && $hour <= 12 && $minute >= 0 && $minute <= 59) {
                    return "sign out at {$hour}:" . str_pad($minute, 2, '0', STR_PAD_LEFT) . " {$period}";
                }
                return null;
            }
            return 'sign out';
        }

        return null;
    }

    public function handle(Request $request): JsonResponse
    {
        // 1. Verify Request Signature from Slack (Production Security)
        $signingSecret = config('services.slack.signing_secret');
        
        if ($signingSecret) {
            $timestamp = $request->header('X-Slack-Request-Timestamp');
            $signature = $request->header('X-Slack-Signature');

            // Prevent replay attacks
            if (!$timestamp || !$signature || abs(time() - $timestamp) > 300) {
                return response()->json(['error' => 'Invalid timestamp or headers.'], 400);
            }

            $rawBody = $request->getContent();
            $baseString = "v0:{$timestamp}:{$rawBody}";
            $computedSignature = 'v0=' . hash_hmac('sha256', $baseString, $signingSecret);

            if (!hash_equals($computedSignature, $signature)) {
                return response()->json(['error' => 'Unauthorized signature.'], 401);
            }
        } else {
            // Log warning in local development if signing secret is missing
            Log::warning('Slack signing secret is not configured in services.php config. Skipping signature check for testing purposes.');
        }

        $payload = $request->all();

        // 2. Handle Slack URL Verification Handshake
        if (isset($payload['type']) && $payload['type'] === 'url_verification') {
            return response()->json([
                'challenge' => $payload['challenge']
            ]);
        }

        // 3. Handle Event Callbacks
        if (isset($payload['type']) && $payload['type'] === 'event_callback') {
            $event = $payload['event'] ?? null;
            $eventId = $payload['event_id'] ?? null;

            // Only listen to message events
            if ($event && isset($event['type']) && $event['type'] === 'message') {
                
                // 4. Gracefully ignore bot messages, bot loops, and system messages
                $ignoredSubtypes = [
                    'bot_message',
                    'channel_join',
                    'channel_leave',
                    'channel_topic',
                    'channel_purpose',
                    'channel_name',
                    'tombstone'
                ];

                if (isset($event['bot_id']) || (isset($event['subtype']) && in_array($event['subtype'], $ignoredSubtypes))) {
                    return response()->json(['status' => 'ignored_bot_event']);
                }

                // CASE C: Handle Message Deletions (message_deleted subtype)
                if (isset($event['subtype']) && $event['subtype'] === 'message_deleted') {
                    $deletedTs = $event['deleted_ts'] ?? null;
                    $channelId = $event['channel'] ?? null;

                    if ($deletedTs && $channelId) {
                        $message = SlackMessage::where('slack_ts', $deletedTs)
                            ->where('channel_id', $channelId)
                            ->first();

                        if ($message) {
                            $slackUserId = $message->slack_user_id;
                            $tz = 'Asia/Karachi';

                            // Gather affected dates (original date, target date if override, etc.)
                            $affectedDates = [];
                            if ($message->override_at) {
                                $affectedDates[] = $message->override_at->timezone($tz)->format('Y-m-d');
                            }
                            if ($message->original_message_at) {
                                $affectedDates[] = $message->original_message_at->timezone($tz)->format('Y-m-d');
                            } else {
                                $affectedDates[] = $message->message_at->timezone($tz)->format('Y-m-d');
                            }
                            $affectedDates = array_unique($affectedDates);

                            // Hard delete the message record and associated edits
                            $messageId = $message->id;
                            $message->delete();
                            SlackMessageEdit::where('slack_message_id', $messageId)->delete();

                            // Recalculate auto-appends and break exceeded flags on all affected days
                            foreach ($affectedDates as $dateStr) {
                                $start = Carbon::parse($dateStr, $tz)->startOfDay()->timezone('UTC');
                                $end = Carbon::parse($dateStr, $tz)->endOfDay()->timezone('UTC');

                                // Delete existing auto-appended sign-outs for this day
                                SlackMessage::where('slack_user_id', $slackUserId)
                                    ->where('is_auto_appended', true)
                                    ->where('message_text', 'sign out')
                                    ->whereBetween('message_at', [$start, $end])
                                    ->delete();

                                // Regenerate auto-appends and recalculate break exceeded flags
                                \Illuminate\Support\Facades\Cache::forget("healed_{$slackUserId}_{$dateStr}");
                                SlackMessage::ensureDayAutoAppends($slackUserId, $dateStr, false);
                                SlackMessage::recalculateBreakExceeded($slackUserId, $dateStr);
                            }
                        }
                    }
                    return response()->json(['status' => 'message_deleted_processed']);
                }

                // CASE A: Handle Message Edits (message_changed subtype)
                if (isset($event['subtype']) && $event['subtype'] === 'message_changed') {
                    $editedMessage = $event['message'] ?? null;
                    if ($editedMessage) {
                        $slackTs = $editedMessage['ts'] ?? null;
                        $channelId = $event['channel'] ?? null;
                        $newTextRaw = $editedMessage['text'] ?? '';
                        $newText = $this->getNormalizedMessageText($newTextRaw);

                        // Look up the original message record via slack_ts and channel_id
                        $originalRecord = SlackMessage::where('slack_ts', $slackTs)
                            ->where('channel_id', $channelId)
                            ->first();

                        if ($originalRecord) {
                            $previousText = $originalRecord->message_text;

                            // Only proceed if normalized text is valid
                            if ($newText) {
                                // If there is an actual change in content
                                if ($previousText !== $newText) {
                                    $editTime = isset($editedMessage['edited']['ts']) 
                                        ? Carbon::createFromTimestamp((float) $editedMessage['edited']['ts'])
                                        : Carbon::now();

                                    if (in_array($originalRecord->approval_status, ['approved', 'rejected', 'pending_edit'])) {
                                        // If original was already approved/rejected/pending_edit, flag it as edited and process the edit
                                        $originalRecord->update([
                                            'is_edited' => true,
                                        ]);

                                        $this->processOverrideApproval($originalRecord, true, $newText);

                                        SlackMessageEdit::create([
                                            'slack_message_id' => $originalRecord->id,
                                            'previous_text' => $previousText,
                                            'new_text' => $newText,
                                            'edited_at' => $editTime,
                                        ]);
                                    } else {
                                        // Update record in slack_messages (leaving timestamp unchanged)
                                        $originalRecord->update([
                                            'message_text' => $newText,
                                            'is_edited' => true,
                                        ]);

                                        $this->attemptAutoMapping($originalRecord->slack_user_id, $newText);
                                        $this->processOverrideApproval($originalRecord);
                                        $this->checkBreakExceeded($originalRecord);
                                        $this->autoAppendSignOutIfForgotten($originalRecord);

                                        $tz = 'Asia/Karachi';
                                        $dateStr = $originalRecord->message_at->timezone($tz)->format('Y-m-d');
                                        \Illuminate\Support\Facades\Cache::forget("healed_{$originalRecord->slack_user_id}_{$dateStr}");
                                        \App\Models\SlackMessage::ensureDayAutoAppends($originalRecord->slack_user_id, $dateStr, true);

                                        // Write to edit history log
                                        SlackMessageEdit::create([
                                            'slack_message_id' => $originalRecord->id,
                                            'previous_text' => $previousText,
                                            'new_text' => $newText,
                                            'edited_at' => $editTime,
                                        ]);
                                    }
                                }
                                return response()->json(['status' => 'edit_recorded']);
                            }
                        } else {
                            // The original message was ignored because it wasn't a keyword (e.g. "hello").
                            // If the edited message is now a valid keyword, record it as a new message.
                            if ($newText) {
                                $slackUserId = $editedMessage['user'] ?? null;
                                if ($slackUserId && $channelId && $slackTs) {
                                    $messageAt = Carbon::createFromTimestamp((float) $slackTs);
                                    $newMessage = SlackMessage::create([
                                        'event_id' => $eventId ?? ('edit_' . $slackTs),
                                        'slack_user_id' => $slackUserId,
                                        'channel_id' => $channelId,
                                        'message_text' => $newText,
                                        'slack_ts' => $slackTs,
                                        'message_at' => $messageAt,
                                        'is_edited' => true,
                                    ]);

                                    $previousMessage = $event['previous_message'] ?? null;
                                    $previousTextRaw = $previousMessage['text'] ?? 'Testing message';

                                    $editTime = isset($editedMessage['edited']['ts']) 
                                        ? Carbon::createFromTimestamp((float) $editedMessage['edited']['ts'])
                                        : Carbon::now();

                                    // Create the edit history log for this transition
                                    SlackMessageEdit::create([
                                        'slack_message_id' => $newMessage->id,
                                        'previous_text' => $previousTextRaw,
                                        'new_text' => $newText,
                                        'edited_at' => $editTime,
                                    ]);

                                    $this->attemptAutoMapping($slackUserId, $newText);
                                    $this->processOverrideApproval($newMessage);
                                    $this->checkBreakExceeded($newMessage);
                                    $this->autoAppendSignOutIfForgotten($newMessage);

                                    $tz = 'Asia/Karachi';
                                    $dateStr = $newMessage->message_at->timezone($tz)->format('Y-m-d');
                                    \Illuminate\Support\Facades\Cache::forget("healed_{$slackUserId}_{$dateStr}");
                                    \App\Models\SlackMessage::ensureDayAutoAppends($slackUserId, $dateStr, true);
                                    return response()->json(['status' => 'new_message_from_edit']);
                                }
                            }
                        }
                    }
                    return response()->json(['status' => 'ignored_edit']);
                }

                // CASE B: Handle Standard New Messages
                $slackUserId = $event['user'] ?? null;
                $channelId = $event['channel'] ?? null;
                $messageTextRaw = $event['text'] ?? '';
                $messageText = $this->getNormalizedMessageText($messageTextRaw);
                $slackTs = $event['ts'] ?? null;

                if ($slackUserId && $channelId && $slackTs) {
                    // Filter messages matching keyword whitelist only
                    if (!$messageText) {
                        return response()->json(['status' => 'ignored_unallowed_keyword']);
                    }

                    // Ensure event is not already recorded (deduplication)
                    if ($eventId && SlackMessage::where('event_id', $eventId)->exists()) {
                        return response()->json(['status' => 'duplicate_event']);
                    }

                    $messageAt = Carbon::createFromTimestamp((float) $slackTs);

                    $newMessage = SlackMessage::create([
                        'event_id' => $eventId,
                        'slack_user_id' => $slackUserId,
                        'channel_id' => $channelId,
                        'message_text' => $messageText,
                        'slack_ts' => $slackTs,
                        'message_at' => $messageAt,
                    ]);

                    $this->attemptAutoMapping($slackUserId, $messageText);
                    $this->processOverrideApproval($newMessage);
                    $this->checkBreakExceeded($newMessage);
                    $this->autoAppendSignOutIfForgotten($newMessage);

                    $tz = 'Asia/Karachi';
                    $dateStr = $newMessage->message_at->timezone($tz)->format('Y-m-d');
                    \Illuminate\Support\Facades\Cache::forget("healed_{$slackUserId}_{$dateStr}");
                    \App\Models\SlackMessage::ensureDayAutoAppends($slackUserId, $dateStr, true);

                    return response()->json(['status' => 'success']);
                }
            }
        }

        return response()->json(['status' => 'unhandled_event']);
    }

    /**
     * Auto connect Slack user profile to registered HRMS User account if matching name found.
     */
    private function attemptAutoMapping(string $slackUserId, string $messageText): void
    {
        if (preg_match('/{\s*"?auto_connect_user_profile"?\s*:\s*["\'”“’‘]([^"\'”“’‘]+)["\'”“’‘]\s*}/iu', $messageText, $matches)) {
            $usernameVal = trim($matches[1]);
            
            // Find matching user
            $matchedUser = null;
            
            // Match 1: Exact case-insensitive match on name
            $users = \App\Models\User::whereRaw('LOWER(name) = ?', [strtolower($usernameVal)])->get();
            if ($users->count() === 1) {
                $matchedUser = $users->first();
            } else {
                // Match 2: Replace separators with space (e.g. john.doe -> john doe)
                $normalized = str_replace(['.', '_', '-'], ' ', $usernameVal);
                $users = \App\Models\User::whereRaw('LOWER(name) = ?', [strtolower($normalized)])->get();
                if ($users->count() === 1) {
                    $matchedUser = $users->first();
                } else {
                    // Match 3: substring match
                    $users = \App\Models\User::where('name', 'like', '%' . $usernameVal . '%')
                        ->orWhere('name', 'like', '%' . $normalized . '%')
                        ->get();
                    if ($users->count() === 1) {
                        $matchedUser = $users->first();
                    }
                }
            }

            // Auto-assign if exactly one unique match is found
            if ($matchedUser) {
                \App\Models\SlackUserMapping::updateOrCreate(
                    ['slack_user_id' => $slackUserId],
                    ['user_id' => $matchedUser->id]
                );
            }
        }
    }

    /**
     * Process override approvals for signin/signout messages containing custom time suffixes.
     */
    public function processOverrideApproval(SlackMessage $message, bool $isPendingEdit = false, ?string $pendingText = null): void
    {
        $text = $isPendingEdit ? $pendingText : $message->message_text;
        $text = strtolower(trim($text));

        // Match overrides (e.g. "signin at 10 am" or "signout at 5:00 PM" or "sign out at 11:00 PM")
        if (preg_match('/at\s+(\d{1,2}):(\d{2})\s*(am|pm)/i', $text, $matches)) {
            $hour = (int)$matches[1];
            $minute = (int)$matches[2];
            $period = strtoupper($matches[3]);

            if ($period === 'PM' && $hour < 12) {
                $hour += 12;
            } elseif ($period === 'AM' && $hour === 12) {
                $hour = 0;
            }

            // Convert original message_at to local, apply override time, convert back to UTC
            $refTime = ($isPendingEdit && $message->original_message_at) ? $message->original_message_at : $message->message_at;
            $messageAtLocal = $refTime->copy()->timezone('Asia/Karachi');
            
            // Check if there is an active auto-appended sign-out or back for this user
            $isCheckOut = (str_contains($text, 'signout') || str_contains($text, 'sign out'));
            $isBack = preg_match('/^back\b/i', $text);
            $autoMsg = null;
            if ($isCheckOut) {
                $autoMsg = SlackMessage::where('slack_user_id', $message->slack_user_id)
                    ->where('is_auto_appended', true)
                    ->where('approval_status', 'pending')
                    ->latest('message_at')
                    ->first();
            } elseif ($isBack) {
                $autoMsg = SlackMessage::where('slack_user_id', $message->slack_user_id)
                    ->where('is_auto_appended', true)
                    ->where('message_text', 'back')
                    ->where('approval_status', 'pending')
                    ->latest('message_at')
                    ->first();
            }

            if ($autoMsg) {
                // If there's an auto-appended message, we apply the override to its date!
                // We do NOT delete it here. It is deleted only when HR approves this override.
                $targetLocalDate = $autoMsg->message_at->copy()->timezone('Asia/Karachi');
                $targetLocal = $targetLocalDate->setTime($hour, $minute, 0);
            } else {
                $targetLocal = $messageAtLocal->copy()->setTime($hour, $minute, 0);
                if ($targetLocal->gt($messageAtLocal)) {
                    if ($isCheckOut || $isBack) {
                        // Loop backwards day by day (up to 7 days) to find the first day with any message entries
                        $foundTarget = false;
                        for ($d = 1; $d <= 7; $d++) {
                            $checkDate = $messageAtLocal->copy()->subDays($d);
                            $start = $checkDate->copy()->startOfDay()->timezone('UTC');
                            $end = $checkDate->copy()->endOfDay()->timezone('UTC');

                            $hasEntries = SlackMessage::where('slack_user_id', $message->slack_user_id)
                                ->where(function ($q) {
                                    $q->whereNull('approval_status')
                                      ->orWhere('approval_status', '!=', 'rejected');
                                })
                                ->whereBetween('message_at', [$start, $end])
                                ->exists();

                            if ($hasEntries) {
                                $targetLocal = $checkDate->setTime($hour, $minute, 0);
                                $foundTarget = true;
                                break;
                            }
                        }
                        if (!$foundTarget) {
                            $targetLocal->subDay();
                        }
                    } else {
                        $targetLocal->subDay();
                    }
                }
            }

            $overrideAt = $targetLocal->timezone(config('app.timezone', 'UTC'));

            if ($isPendingEdit) {
                $message->update([
                    'approval_status' => 'pending_edit',
                    'pending_message_text' => $pendingText,
                    'pending_override_at' => $overrideAt,
                ]);
            } else {
                $message->update([
                    'approval_status' => 'pending',
                    'original_message_at' => $message->message_at,
                    'override_at' => $overrideAt,
                ]);
            }

            // Notify all super admins
            $employeeName = $message->slack_user_id;
            if (!$message->relationLoaded('mapping')) {
                $message->load('mapping.user');
            }

            if ($message->mapping) {
                if ($message->mapping->slack_username) {
                    $employeeName = $message->mapping->slack_username;
                } elseif ($message->mapping->user) {
                    $employeeName = $message->mapping->user->name;
                }
            }

            $superAdmins = \App\Models\User::whereHas('role', function($q) {
                $q->where('name', \App\Models\Role::SUPER_ADMIN);
            })->get();

            foreach ($superAdmins as $admin) {
                $existingNotification = \Illuminate\Support\Facades\DB::table('notifications')
                    ->where('notifiable_id', $admin->id)
                    ->where('notifiable_type', get_class($admin))
                    ->where('data->slack_message_id', $message->id)
                    ->first();

                if ($existingNotification) {
                    $currentData = json_decode($existingNotification->data, true);
                    $textToShow = $message->approval_status === 'pending_edit' ? $message->pending_message_text : $message->message_text;
                    $currentData['message_text'] = $textToShow;
                    $currentData['message'] = "[Session Override] {$employeeName}: \"Requested override: {$textToShow}\"";

                    \Illuminate\Support\Facades\DB::table('notifications')
                        ->where('id', $existingNotification->id)
                        ->update([
                            'data' => json_encode($currentData),
                            'read_at' => null, // Reset to unread so they see the edit
                            'updated_at' => now(),
                        ]);
                } else {
                    $admin->notify(new \App\Notifications\SlackOverrideAwaitingApproval($message, $employeeName));
                }
            }
        }
    }

    private function checkBreakExceeded(SlackMessage $message): void
    {
        $tz = 'Asia/Karachi';
        $dateStr = $message->message_at->timezone($tz)->format('Y-m-d');
        SlackMessage::recalculateBreakExceeded($message->slack_user_id, $dateStr);
    }



    /**
     * Automatically append a pending sign-out 1 minute after a check-in if the user signs in on a different day.
     */
    private function autoAppendSignOutIfForgotten(SlackMessage $newSignIn): void
    {
        $text = strtolower(trim($newSignIn->message_text));

        // Only run this logic if the new message is a check-in
        $isCheckIn = (str_contains($text, 'signin') || str_contains($text, 'sign in') || preg_match('/^back\b/i', $text));
        if (!$isCheckIn) {
            return;
        }

        // Find the latest preceding valid message from the same user (ordered by message_at)
        $prevMessage = SlackMessage::where('slack_user_id', $newSignIn->slack_user_id)
            ->where('id', '!=', $newSignIn->id)
            ->where('message_at', '<', $newSignIn->message_at)
            ->where(function ($q) {
                $q->whereNull('approval_status')
                  ->orWhere('approval_status', '!=', 'rejected');
            })
            ->latest('message_at')
            ->first();

        if ($prevMessage) {
            $tz = 'Asia/Karachi';
            $prevDate = $prevMessage->message_at->timezone($tz)->format('Y-m-d');
            $newDate = $newSignIn->message_at->timezone($tz)->format('Y-m-d');

            if ($prevDate !== $newDate) {
                \Illuminate\Support\Facades\Cache::forget("healed_{$newSignIn->slack_user_id}_{$prevDate}");
                SlackMessage::ensureDayAutoAppends($newSignIn->slack_user_id, $prevDate, true);
            }
        }
    }
}
