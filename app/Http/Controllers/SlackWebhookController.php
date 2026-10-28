<?php

namespace App\Http\Controllers;

use App\Models\SlackMessage;
use App\Models\SlackMessageEdit;
use App\Models\SlackLeaveSyncMessage;
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

        // Define patterns and their canonical names
        $patterns = [
            'back' => '/\bback\b/i',
            'break' => '/\b(?:break|brb|brbr)\b/i',
            'sign in' => '/\b(?:sign(?:ed|ing)?\s*[-_]?\s*in|signin)\b/i',
            'sign out' => '/\b(?:sign(?:ed|ing)?\s*[-_]?\s*out|signout)\b/i',
        ];

        // Find all matches with their offsets
        $matchedCategories = [];
        foreach ($patterns as $category => $pattern) {
            if (preg_match($pattern, $raw, $matches, PREG_OFFSET_CAPTURE)) {
                $offset = $matches[0][1];
                $matchedCategories[$category] = $offset;
            }
        }

        if (empty($matchedCategories)) {
            return null;
        }

        // Sort by offset to find the first keyword that appears in the message
        asort($matchedCategories);
        $selectedCategory = key($matchedCategories);

        // If it's a break, normalize it to 'break'
        if ($selectedCategory === 'break') {
            return 'break';
        }

        // Otherwise, check for override time (making 'at' optional)
        if (preg_match('/\b(?:at\s+)?(\d{1,2})(?:[:\s]+(\d{2}))?\s*(am|pm)\b/i', $raw, $matches)) {
            $hour = (int) $matches[1];
            $minute = (isset($matches[2]) && $matches[2] !== '') ? (int) $matches[2] : 0;
            $period = strtoupper($matches[3]);

            // Validate realistic AM/PM hours and minutes
            if ($hour >= 1 && $hour <= 12 && $minute >= 0 && $minute <= 59) {
                return "{$selectedCategory} at {$hour}:" . str_pad($minute, 2, '0', STR_PAD_LEFT) . " {$period}";
            }
            return null;
        }

        return $selectedCategory;
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
                        $leaveSyncChannel = config('services.slack.leave_sync_channel_id');
                        if ($channelId === $leaveSyncChannel) {
                            return $this->handleLeaveSyncMessageDelete($deletedTs, $channelId);
                        }

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

                            // Track deleted message ID in memory cache for live-stream polling removal
                            $recentlyDeleted = \Illuminate\Support\Facades\Cache::get('recently_deleted_slack_msg_ids', []);
                            $recentlyDeleted[] = (int) $messageId;
                            \Illuminate\Support\Facades\Cache::put('recently_deleted_slack_msg_ids', array_unique($recentlyDeleted), now()->addMinutes(10));

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
                                $this->clearHealedCache($slackUserId, $dateStr);
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

                        $leaveSyncChannel = config('services.slack.leave_sync_channel_id');
                        if ($channelId && $leaveSyncChannel && $channelId === $leaveSyncChannel) {
                            return $this->handleLeaveSyncMessageEdit($slackTs, $channelId, $newTextRaw, $event);
                        }

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
                                        if (str_starts_with(strtolower($newText), 'sign out') || str_starts_with(strtolower($newText), 'signout')) {
                                            $this->lockRemoteDeclarationsOnSignOut($originalRecord->slack_user_id, $originalRecord->message_at);
                                        }
                                        $this->processOverrideApproval($originalRecord);
                                        $this->checkBreakExceeded($originalRecord);
                                        $this->autoAppendSignOutIfForgotten($originalRecord);

                                        $tz = 'Asia/Karachi';
                                        $dateStr = $originalRecord->message_at->timezone($tz)->format('Y-m-d');
                                        $this->clearHealedCache($originalRecord->slack_user_id, $dateStr);
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
                                    if (str_starts_with(strtolower($newText), 'sign out') || str_starts_with(strtolower($newText), 'signout')) {
                                        $this->lockRemoteDeclarationsOnSignOut($slackUserId, $messageAt);
                                    }
                                    $this->processOverrideApproval($newMessage);
                                    $this->checkBreakExceeded($newMessage);
                                    $this->autoAppendSignOutIfForgotten($newMessage);
                                    $this->checkRealTimeSlackAlerts($newMessage);

                                    $tz = 'Asia/Karachi';
                                    $dateStr = $newMessage->message_at->timezone($tz)->format('Y-m-d');
                                    $this->clearHealedCache($slackUserId, $dateStr);
                                    \App\Models\SlackMessage::ensureDayAutoAppends($slackUserId, $dateStr, true);
                                    return response()->json(['status' => 'new_message_from_edit']);
                                }
                            } else {
                                // If edited text is a typo of session commands, send DM alert
                                $slackUserId = $editedMessage['user'] ?? null;
                                $typoSuggestion = null;
                                $misspelledWord = null;
                                if ($slackUserId && $this->detectSessionTypo($newTextRaw, $typoSuggestion, $misspelledWord)) {
                                    $mapping = \App\Models\SlackUserMapping::where('slack_user_id', $slackUserId)->first();
                                    $user = $mapping?->user;
                                    if ($user) {
                                        $tz = 'Asia/Karachi';
                                        $editTs = $editedMessage['edited']['ts'] ?? $slackTs ?? time();
                                        $msgTimeLocal = Carbon::createFromTimestamp((float) $editTs)->timezone($tz);
                                        $timeFormatted = $msgTimeLocal->format('h:i A');

                                        $slackAlertService = app(\App\Services\SlackAlertService::class);
                                        $slackAlertService->sendDM(
                                            $slackUserId,
                                            "Hey {$user->name}, did you mean '{$typoSuggestion}' in your edited message? You typed '{$misspelledWord}' in your message '{$newTextRaw}' at {$timeFormatted}. Please verify spelling and try again."
                                        );
                                        return response()->json(['status' => 'session_edit_typo_notified']);
                                    }
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
                $slackTs = $event['ts'] ?? null;

                $leaveSyncChannel = config('services.slack.leave_sync_channel_id');
                if ($channelId && $leaveSyncChannel && $channelId === $leaveSyncChannel) {
                    return $this->handleLeaveSyncMessage($event, $eventId);
                }

                $messageText = $this->getNormalizedMessageText($messageTextRaw);

                if ($slackUserId && $channelId && $slackTs) {
                    // Filter messages matching keyword whitelist only
                    if (!$messageText) {
                        $typoSuggestion = null;
                        $misspelledWord = null;
                        if ($this->detectSessionTypo($messageTextRaw, $typoSuggestion, $misspelledWord)) {
                            $mapping = \App\Models\SlackUserMapping::where('slack_user_id', $slackUserId)->first();
                            $user = $mapping?->user;
                            $tz = 'Asia/Karachi';
                            $msgTimeLocal = Carbon::createFromTimestamp((float) $slackTs)->timezone($tz);
                            $timeFormatted = $msgTimeLocal->format('h:i A');

                            if ($user) {
                                $tz = 'Asia/Karachi';
                                $msgTimeLocal = Carbon::createFromTimestamp((float) $slackTs)->timezone($tz);
                                $timeFormatted = $msgTimeLocal->format('h:i A');
                                $dateFormatted = $msgTimeLocal->format('l, M d, Y');
                                $slackAlertService = app(\App\Services\SlackAlertService::class);
                                $slackAlertService->sendDM(
                                    $slackUserId,
                                    "Hey {$user->name}, did you mean '{$typoSuggestion}'? You typed '{$misspelledWord}' in your message '{$messageTextRaw}' at {$timeFormatted} on {$dateFormatted}. Please verify spelling and try again."
                                );
                                return response()->json(['status' => 'session_typo_notified']);
                            }
                        }
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
                    if (str_starts_with(strtolower($messageText), 'sign out') || str_starts_with(strtolower($messageText), 'signout')) {
                        $this->lockRemoteDeclarationsOnSignOut($slackUserId, $messageAt);
                    }
                    $this->processOverrideApproval($newMessage);
                    $this->checkBreakExceeded($newMessage);
                    $this->autoAppendSignOutIfForgotten($newMessage);
                    $this->checkRealTimeSlackAlerts($newMessage);

                    $tz = 'Asia/Karachi';
                    $dateStr = $newMessage->message_at->timezone($tz)->format('Y-m-d');
                    $this->clearHealedCache($slackUserId, $dateStr);
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
        if (preg_match('/at\s+(\d{1,2})(?:[:\s]+(\d{2}))?\s*(am|pm)/i', $text, $matches)) {
            $hour = (int)$matches[1];
            $minute = (isset($matches[2]) && $matches[2] !== '') ? (int)$matches[2] : 0;
            $period = strtoupper($matches[3]);

            if ($period === 'PM' && $hour < 12) {
                $hour += 12;
            } elseif ($period === 'AM' && $hour === 12) {
                $hour = 0;
            }

            // Convert original message_at to local, apply override time, convert back to UTC
            $refTime = $message->original_message_at ?: $message->message_at;
            $messageAtLocal = $refTime->copy()->timezone('Asia/Karachi');
            
            // Target date is today or yesterday based strictly on whether override time is in the future compared to sent time
            $targetLocal = $messageAtLocal->copy()->setTime($hour, $minute, 0);
            if ($targetLocal->gt($messageAtLocal)) {
                $targetLocal->subDay();
            }

            $overrideAt = $targetLocal->timezone(config('app.timezone', 'UTC'));

            if ($isPendingEdit) {
                $updateData = [
                    'approval_status' => 'pending_edit',
                    'pending_message_text' => $pendingText,
                    'pending_override_at' => $overrideAt,
                ];
                if (!$message->original_message_at) {
                    $updateData['original_message_at'] = $message->message_at;
                }
                $message->update($updateData);
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
                    $tz = 'Asia/Karachi';
                    $dateFormatted = $message->message_at->timezone($tz)->format('l, F jS');
                    $currentData['message'] = "[Session Override] {$employeeName}: \"Requested override: {$textToShow} on {$dateFormatted}\"";

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
            ->where('is_auto_appended', false)
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
                $prevText = strtolower(trim($prevMessage->message_text));
                $prevIsCheckIn = (str_contains($prevText, 'signin') || str_contains($prevText, 'sign in') || preg_match('/^back\b/i', $prevText));

                // If their last state on the previous day was checked-in, they forgot to sign out!
                if ($prevIsCheckIn) {
                    $mapping = \App\Models\SlackUserMapping::where('slack_user_id', $newSignIn->slack_user_id)->first();
                    $user = $mapping?->user;
                    if ($user) {
                        $slackAlertService = app(\App\Services\SlackAlertService::class);
                        $prevTimeLocal = $prevMessage->message_at->timezone($tz);
                        $timeFormatted = $prevTimeLocal->format('h:i A');
                        $dateFormatted = $prevTimeLocal->format('l, M d, Y');
                        $slackAlertService->sendDM(
                            $newSignIn->slack_user_id,
                            "Hey {$user->name}, you just signed in today, but you forgot to sign out from your last shift. You checked in at {$timeFormatted} on {$dateFormatted}. The system auto-healed this, but please remember to complete your cycles!"
                        );
                    }
                }

                $this->clearHealedCache($newSignIn->slack_user_id, $prevDate);
                SlackMessage::ensureDayAutoAppends($newSignIn->slack_user_id, $prevDate, true);
            }
        }
    }

    /**
     * Clear cache for the target date and adjacent days to ensure proper overnight boundary healing.
     * Also clears the break balance cache for the same dates.
     */
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

    /**
     * Check for real-time Slack sequence anomalies and dispatch break balance alerts.
     */
    private function checkRealTimeSlackAlerts(SlackMessage $message): void
    {
        // If this message is a pending manual override (e.g. "break at 1:00 PM"),
        // it is a retroactive request awaiting admin approval, not a real-time live event.
        if ($message->approval_status === 'pending' || $message->approval_status === 'pending_edit') {
            return;
        }

        $mapping = \App\Models\SlackUserMapping::where('slack_user_id', $message->slack_user_id)->first();
        $user = $mapping?->user;
        if (!$user) {
            return;
        }

        $text = strtolower(trim($message->message_text));
        $isSignOut = (str_contains($text, 'signout') || str_contains($text, 'sign out'));
        $isBreak = (preg_match('/\b(?:break|brb|brbr)\b/i', $text) > 0);
        $isBack = (preg_match('/^back\b/i', $text) > 0);

        // Fetch preceding message (ignoring auto-appended system messages and rejected overrides)
        $prevMessage = SlackMessage::where('slack_user_id', $message->slack_user_id)
            ->where('id', '!=', $message->id)
            ->where('is_auto_appended', false)
            ->where('message_at', '<', $message->message_at)
            ->where(function ($q) {
                $q->whereNull('approval_status')
                  ->orWhere('approval_status', '!=', 'rejected');
            })
            ->latest('message_at')
            ->first();

        $tz = 'Asia/Karachi';
        $msgTimeLocal = $message->message_at->timezone($tz);
        $timeFormatted = $msgTimeLocal->format('h:i A');
        $dateFormatted = $msgTimeLocal->format('l, M d, Y');
        $dateStr = $msgTimeLocal->format('Y-m-d');
        $prevText = $prevMessage ? strtolower(trim($prevMessage->message_text)) : '';
        $prevIsCheckIn = (str_contains($prevText, 'signin') || str_contains($prevText, 'sign in') || preg_match('/^back\b/i', $prevText));
        $prevIsBreak = ($prevMessage ? (preg_match('/\b(?:break|brb|brbr)\b/i', $prevText) > 0) : false);
        $prevIsSignOut = ($prevMessage ? (str_contains($prevText, 'signout') || str_contains($prevText, 'sign out')) : false);
        $hasNoPriorEvent = ($prevMessage === null);
        // An unclosed check-in from more than 16 hours ago is considered a stale session rather than an active overnight shift
        $isStaleCheckIn = $prevIsCheckIn && ($prevMessage ? $prevMessage->message_at->diffInHours($message->message_at) > 16 : false);

        $slackAlertService = app(\App\Services\SlackAlertService::class);

        // ---------------------------------------------------------------
        // 1. BREAK — dispatch balance alerts & validate state
        // ---------------------------------------------------------------
        if ($isBreak) {
            // Compute remaining balance BEFORE this break pair is closed
            // (computeDayBreakBalance includes this open break but with duration=null, so effective_remaining_seconds
            //  still reflects what was left from prior completed breaks today including grace period)
            $balanceData          = SlackMessage::computeDayBreakBalance($message->slack_user_id, $dateStr);
            $remainingSeconds     = $balanceData['effective_remaining_seconds'];
            $officialRemainingSec = $balanceData['remaining_seconds'];
            $gracePeriodSec       = $balanceData['grace_period_seconds'];

            $officialBalanceMin   = (int) round(SlackMessage::getBreakBalanceSeconds() / 60);

            if ($remainingSeconds <= 0) {
                // Balance already zero — this break is immediately a session-out.
                // Send the DM right away (no need to wait).
                $slackAlertService->sendDM(
                    $message->slack_user_id,
                    "Hey {$user->name}, your break sent at {$timeFormatted} on {$dateFormatted} is being treated as a sign out since your daily break balance ({$officialBalanceMin} min) is already exhausted."
                );

                // Send In-App database notification to the employee
                $inAppMsg = "[Break Exceeded] Your break sent at {$timeFormatted} on {$dateFormatted} was treated as a sign out because your {$officialBalanceMin}-minute daily break balance was already exhausted.";
                $alreadyNotified = \Illuminate\Support\Facades\DB::table('notifications')
                    ->where('notifiable_id', $user->id)
                    ->where('data->slack_message_id', $message->id)
                    ->where('data->type', 'break_exceeded')
                    ->exists();

                if (!$alreadyNotified) {
                    $user->notify(new \App\Notifications\BreakExceededNotification(
                        $message,
                        $user->name,
                        $inAppMsg,
                        true
                    ));
                }
            } else {
                // Grace Period Alert for HR (Super Admin): Dispatch when official balance is reached and grace buffer begins
                if ($gracePeriodSec > 0) {
                    if ($officialRemainingSec > 0) {
                        \App\Jobs\BreakGracePeriodAlertJob::dispatch(
                            $message->slack_user_id,
                            $message->id,
                            $message->message_at->toIso8601String(),
                            $dateStr,
                            $user->name,
                            true // isMidBreak = true
                        )->delay(now()->addSeconds($officialRemainingSec));
                    } else {
                        // Already in grace period right now — notify HR immediately
                        \App\Jobs\BreakGracePeriodAlertJob::dispatch(
                            $message->slack_user_id,
                            $message->id,
                            $message->message_at->toIso8601String(),
                            $dateStr,
                            $user->name,
                            false // isMidBreak = false
                        );
                    }
                }

                // 10-Minute Warning: Dispatch if remaining effective balance is more than 10 minutes (600s)
                if ($remainingSeconds > 600) {
                    \App\Jobs\BreakBalanceWarningJob::dispatch(
                        $message->slack_user_id,
                        $message->id,
                        $message->message_at->toIso8601String(),
                        $dateStr,
                        $user->name,
                        10
                    )->delay(now()->addSeconds($remainingSeconds - 600));
                }

                // 5-Minute Warning: Dispatch if remaining effective balance is more than 5 minutes (300s)
                if ($remainingSeconds > 300) {
                    \App\Jobs\BreakBalanceWarningJob::dispatch(
                        $message->slack_user_id,
                        $message->id,
                        $message->message_at->toIso8601String(),
                        $dateStr,
                        $user->name,
                        5
                    )->delay(now()->addSeconds($remainingSeconds - 300));
                }

                // 0-Minute Exhaustion: Dispatch job to fire exactly when remaining balance runs out mid-break
                \App\Jobs\BreakBalanceAlertJob::dispatch(
                    $message->slack_user_id,
                    $message->id,
                    $message->message_at->toIso8601String(),
                    $dateStr,
                    $user->name
                )->delay(now()->addSeconds($remainingSeconds));
            }

            // Sequence validation for break: user must be checked in
            if ($prevIsSignOut || $hasNoPriorEvent || $isStaleCheckIn) {
                $slackAlertService->sendDM(
                    $message->slack_user_id,
                    "Hey {$user->name}, you sent a 'break' message at {$timeFormatted} on {$dateFormatted} but you are not currently signed in. Please fix this by sending 'sign in at [time]' on Slack!"
                );
                $user->notify(new \App\Notifications\SessionAnomalyNotification(
                    "[Session Anomaly] You sent a 'break' message at {$timeFormatted} without signing in. Please send 'sign in at [time]' to fix.",
                    $user->id,
                    'session_anomaly'
                ));
            } elseif ($prevIsBreak) {
                $slackAlertService->sendDM(
                    $message->slack_user_id,
                    "Hey {$user->name}, you sent a 'break' message at {$timeFormatted} on {$dateFormatted} but you are already on break. If you returned, please send 'back' instead!"
                );
                $user->notify(new \App\Notifications\SessionAnomalyNotification(
                    "[Session Anomaly] You sent a duplicate 'break' message at {$timeFormatted} while already on break.",
                    $user->id,
                    'session_anomaly'
                ));
            }
        }

        // ---------------------------------------------------------------
        // 2. BACK — sequence check (with backtrack context)
        // ---------------------------------------------------------------
        if ($isBack) {
            if ($prevIsBreak) {
                // Completely valid return from break (supports both same-day and overnight breaks)
                // If this back's preceding break was on a prior day, recalculate that prior day's balance too
                $prevDateStr = $prevMessage->message_at->timezone($tz)->format('Y-m-d');
                if ($prevDateStr !== $dateStr) {
                    $this->clearHealedCache($message->slack_user_id, $prevDateStr);
                    SlackMessage::recalculateBreakExceeded($message->slack_user_id, $prevDateStr);
                }
            } elseif ($prevIsCheckIn && !$isStaleCheckIn) {
                // User sent 'back' while already working (no preceding break)
                $slackAlertService->sendDM(
                    $message->slack_user_id,
                    "Hey {$user->name}, you sent a 'back' message at {$timeFormatted} on {$dateFormatted} but you did not mark a break. Please fix this immediately by sending 'break at [time]' on Slack!"
                );
                $user->notify(new \App\Notifications\SessionAnomalyNotification(
                    "[Session Anomaly] You sent a 'back' message at {$timeFormatted} without marking a break. Please send 'break at [time]' to fix.",
                    $user->id,
                    'session_anomaly'
                ));
            } else {
                // User is signed out or has no prior event
                $slackAlertService->sendDM(
                    $message->slack_user_id,
                    "Hey {$user->name}, you sent a 'back' message at {$timeFormatted} on {$dateFormatted} but you are not currently signed in. Please fix this by sending 'sign in at [time]' and 'break at [time]' on Slack!"
                );
                $user->notify(new \App\Notifications\SessionAnomalyNotification(
                    "[Session Anomaly] You sent a 'back' message at {$timeFormatted} without signing in. Please send 'sign in at [time]' to fix.",
                    $user->id,
                    'session_anomaly'
                ));
            }
        }

        // ---------------------------------------------------------------
        // 3. SIGN OUT — sequence check
        // ---------------------------------------------------------------
        if ($isSignOut) {
            if ($prevIsBreak) {
                // Signed out directly from break without typing 'back'
                $slackAlertService->sendDM(
                    $message->slack_user_id,
                    "Hey {$user->name}, you signed out at {$timeFormatted} on {$dateFormatted} directly from a break. Please remember to send a 'back' message before signing out next time!"
                );
                $user->notify(new \App\Notifications\SessionAnomalyNotification(
                    "[Forgot Back] You signed out at {$timeFormatted} on {$dateFormatted} directly from a break without typing 'back'.",
                    $user->id,
                    'session_anomaly'
                ));
            } elseif ($prevIsSignOut || $hasNoPriorEvent || $isStaleCheckIn) {
                $slackAlertService->sendDM(
                    $message->slack_user_id,
                    "Hey {$user->name}, you sent a 'sign out' message at {$timeFormatted} on {$dateFormatted} but you are already signed out."
                );
                $user->notify(new \App\Notifications\SessionAnomalyNotification(
                    "[Session Anomaly] You sent a 'sign out' message at {$timeFormatted} while already signed out.",
                    $user->id,
                    'session_anomaly'
                ));
            }
        }
    }

    /**
     * Check if the raw text contains a slight typo of one of the Whitelisted Session Keywords.
     * Returns true with suggestion and misspelled word if a typo is detected, otherwise false.
     */
    private function detectSessionTypo(string $rawText, ?string &$suggestion, ?string &$misspelledWord): bool
    {
        $raw = strtolower(trim($rawText));
        if (empty($raw)) {
            return false;
        }

        // Direct multi-word / compact patterns for sign in / sign out / break / back
        $multiWordDirect = [
            'sign in' => [
                'sin in', 'sgn in', 'sig in', 'sing in', 'signn in', 'sin-in', 'sgin in', 'si in', 's in', 'sign inn',
                's-in-in', 's/in', 's.in', 'sin', 's-in', 's_in', 'sgnin', 'sigin', 'singin', 'signn', 'signni', 'sginin', 'sinn', 'siin'
            ],
            'sign out' => [
                'sout out', 'sgn out', 'sig out', 'sing out', 'signn out', 'sin out', 'sgin out', 'so out', 's out', 'sign outt',
                's-out-out', 's/out', 's.out', 'sout', 's-out', 's_out', 'sgnout', 'sigout', 'singout', 'signoutt', 'sinout', 'sginout', 'soutt'
            ],
            'break' => [
                'brk', 'braek', 'brek', 'breack', 'brkb', 'brbb', 'b-r-b', 'b.r.b', 'breka', 'breake', 'brak', 'brk time',
                'breack time', 'braik', 'breeak', 'beak', 'brek time', 'brb time'
            ],
            'back' => [
                'bk', 'bck', 'bakc', 'baack', 'bak', 'bac', 'b-a-c-k', 'bakk', 'backk', 'bkc', 'bkk', 'bacc', 'bk time'
            ]
        ];

        // Check full cleaned raw string against direct mappings
        $compactRaw = str_replace([' ', '-', '_', '.', '/'], '', $raw);
        foreach ($multiWordDirect as $target => $typos) {
            if (in_array($raw, $typos, true)) {
                $suggestion = $target;
                $misspelledWord = $rawText;
                return true;
            }
            foreach ($typos as $typo) {
                $compactTypo = str_replace([' ', '-', '_', '.', '/'], '', $typo);
                if ($compactRaw === $compactTypo && !empty($compactRaw)) {
                    $suggestion = $target;
                    $misspelledWord = $rawText;
                    return true;
                }
            }
        }

        // Split words
        $words = preg_split('/[\s,\.\?\!\:\(\)\/_-]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($words as $word) {
            // Direct word checks
            foreach ($multiWordDirect as $target => $typos) {
                if (in_array($word, $typos, true)) {
                    $suggestion = $target;
                    $misspelledWord = $word;
                    return true;
                }
            }

            // Fuzzy Levenshtein checks
            // 1. Check against 'signin' / 'signout'
            if (str_starts_with($word, 's')) {
                if (levenshtein($word, 'signin') <= 2 || levenshtein($word, 'sign') <= 1) {
                    $suggestion = 'sign in';
                    $misspelledWord = $word;
                    return true;
                }
                if (levenshtein($word, 'signout') <= 2) {
                    $suggestion = 'sign out';
                    $misspelledWord = $word;
                    return true;
                }
            }

            // 2. Check against 'break' / 'brb' / 'back'
            if (str_starts_with($word, 'b')) {
                if (levenshtein($word, 'break') <= 2 || levenshtein($word, 'brb') <= 1) {
                    $suggestion = 'break';
                    $misspelledWord = $word;
                    return true;
                }
                if (levenshtein($word, 'back') <= 1) {
                    $suggestion = 'back';
                    $misspelledWord = $word;
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Backward-compatible helper to detect session typo.
     */
    private function detectTypo(string $rawText): ?string
    {
        $suggestion = null;
        $misspelledWord = null;
        return $this->detectSessionTypo($rawText, $suggestion, $misspelledWord) ? $suggestion : null;
    }

    /**
     * Handle Slack messages in the #leave-sync channel.
     */
    private function handleLeaveSyncMessage(array $event, ?string $eventId): JsonResponse
    {
        $slackUserId = $event['user'] ?? null;
        $channelId = $event['channel'] ?? null;
        $messageTextRaw = $event['text'] ?? '';
        $slackTs = $event['ts'] ?? null;

        if (!$slackUserId || !$channelId || !$slackTs) {
            return response()->json(['status' => 'missing_params']);
        }

        // Deduplication
        if ($eventId && SlackLeaveSyncMessage::where('event_id', $eventId)->exists()) {
            return response()->json(['status' => 'duplicate_event']);
        }

        // Resolve user mapping
        $mapping = \App\Models\SlackUserMapping::where('slack_user_id', $slackUserId)->first();
        $user = $mapping?->user;
        if (!$user) {
            // Unmapped user - notify via DM
            $slackAlertService = app(\App\Services\SlackAlertService::class);
            $slackAlertService->sendDM(
                $slackUserId,
                "Hello! I couldn't map your Slack profile to the HRMS. Please ask HR or an Administrator to map your Slack profile so you can use this channel."
            );
            return response()->json(['status' => 'unmapped_user']);
        }

        // Clean & Normalize raw text (normalize non-breaking spaces and collapse multiple spaces)
        $cleanText = preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}]/u', ' ', $messageTextRaw);
        $cleanText = strtolower(trim(preg_replace('/\s+/', ' ', $cleanText)));
        $tz = 'Asia/Karachi';
        $now = Carbon::now($tz);

        // Check if message contains the exact keywords
        $words = preg_split('/[\s,\.\?\!\:\(\)]+/', $cleanText, -1, PREG_SPLIT_NO_EMPTY);
        $hasExactKeyword = false;
        foreach ($words as $word) {
            if (str_starts_with($word, 'remote') || $word === 'wfh') {
                $hasExactKeyword = true;
                break;
            }
        }

        $typoSuggestion = null;
        $misspelledWord = null;
        $isAttempt = $this->isAttemptingRemote($cleanText, $typoSuggestion, $misspelledWord);

        if (!$isAttempt && !$hasExactKeyword) {
            // Ignore completely if they are not trying to request remote work
            return response()->json(['status' => 'ignored_non_remote_message']);
        }

        $tz = 'Asia/Karachi';
        $msgTimeLocal = Carbon::createFromTimestamp((float) $slackTs)->timezone($tz);
        $timeFormatted = $msgTimeLocal->format('h:i A');
        $dateFormatted = $msgTimeLocal->format('l, M d, Y');

        // Case 1: Misspelled keyword (Typo check)
        if (!$hasExactKeyword) {
            if ($typoSuggestion && $misspelledWord) {
                $slackAlertService = app(\App\Services\SlackAlertService::class);
                $slackAlertService->sendDM(
                    $slackUserId,
                    "Hey {$user->name}, did you mean '{$typoSuggestion}'? You typed '{$misspelledWord}' in your message '{$messageTextRaw}' at {$timeFormatted} on {$dateFormatted}. Please verify spelling and try again."
                );
                return response()->json(['status' => 'typo_notified']);
            }
            return response()->json(['status' => 'ignored_non_remote_message']);
        }

        // Parse Date & Time (Supports both Single Date, Multi-Day Date Ranges, and Time Ranges)
        $parsed = $this->parseRemoteRequest($cleanText, $now, $tz);

        if (!$parsed['start_date'] || !$parsed['end_date']) {
            $slackAlertService = app(\App\Services\SlackAlertService::class);
            $slackAlertService->sendDM(
                $slackUserId,
                "Hey {$user->name}, the format of your remote work request was incorrect. You typed '{$messageTextRaw}' at {$timeFormatted} on {$dateFormatted}. Please use one of the allowed formats:\n- 'remote today'\n- 'remote on [date]'\n- 'remote from [start_date] to [end_date]'\n- 'remote [today/on date] from [start_time] to [end_time]'"
            );
            return response()->json(['status' => 'invalid_date_format']);
        }

        // Format check: If user attempted a time range or date range with invalid format
        if ($this->isAttemptingInvalidRange($cleanText, $parsed)) {
            $slackAlertService = app(\App\Services\SlackAlertService::class);
            $slackAlertService->sendDM(
                $slackUserId,
                "Hey {$user->name}, the format of your remote work request was incorrect. You typed '{$messageTextRaw}' at {$timeFormatted} on {$dateFormatted}. For a time range, specify 'from [start_time] to [end_time]' (e.g. from 3:00pm to 4:00pm). For a date range, specify 'from [start_date] to [end_date]' (e.g. from Aug 25 to Aug 28)."
            );
            $user->notify(new \App\Notifications\SessionAnomalyNotification(
                "[Remote Request Error] Incorrect format. Specify 'from [time] to [time]' or 'from [date] to [date]'.",
                $user->id,
                'session_anomaly'
            ));
            return response()->json(['status' => 'invalid_format']);
        }

        if ($parsed['has_time_range']) {
            $startTime = trim($parsed['time_matches'][0]);
            $endTime = trim($parsed['time_matches'][1]);

            // Validate chronological order of start time and end time
            $startCarbon = null;
            $endCarbon = null;
            try {
                $startClean = str_replace('-', ' ', $startTime);
                $endClean = str_replace('-', ' ', $endTime);
                $startCarbon = Carbon::parse($startClean, $tz);
                $endCarbon = Carbon::parse($endClean, $tz);
            } catch (\Throwable $e) {
                $startCarbon = null;
                $endCarbon = null;
            }

            if (!$startCarbon || !$endCarbon || $startCarbon->gte($endCarbon)) {
                $slackAlertService = app(\App\Services\SlackAlertService::class);
                $slackAlertService->sendDM(
                    $slackUserId,
                    "Hey {$user->name}, the time range for your remote work request was invalid. Start time '{$startTime}' must be earlier than end time '{$endTime}'. You typed '{$messageTextRaw}' at {$timeFormatted}."
                );
                $user->notify(new \App\Notifications\SessionAnomalyNotification(
                    "[Remote Request Error] Start time '{$startTime}' must be earlier than end time '{$endTime}'.",
                    $user->id,
                    'session_anomaly'
                ));
                return response()->json(['status' => 'invalid_time_range']);
            }

            $notes = "Remote Work: {$startCarbon->format('h:i A')} to {$endCarbon->format('h:i A')} (Declared via Slack)";
        } else {
            $notes = "Remote Work: Full Day (Declared via Slack)";
        }

        $startDateCarbon = Carbon::parse($parsed['start_date'], $tz);
        $endDateCarbon = Carbon::parse($parsed['end_date'], $tz);

        // Validation 1: Start date must be <= End date
        if ($startDateCarbon->gt($endDateCarbon)) {
            $slackAlertService = app(\App\Services\SlackAlertService::class);
            $slackAlertService->sendDM(
                $slackUserId,
                "Hey {$user->name}, start date '{$startDateCarbon->format('M d, Y')}' must be on or before end date '{$endDateCarbon->format('M d, Y')}'."
            );
            $user->notify(new \App\Notifications\SessionAnomalyNotification(
                "[Remote Request Error] Start date {$startDateCarbon->format('M d')} must be on or before end date {$endDateCarbon->format('M d, Y')}.",
                $user->id,
                'session_anomaly'
            ));
            return response()->json(['status' => 'invalid_date_range']);
        }

        // Validation 2: Cannot declare remote work for past dates
        if ($startDateCarbon->lt($now->copy()->startOfDay())) {
            $slackAlertService = app(\App\Services\SlackAlertService::class);
            $slackAlertService->sendDM(
                $slackUserId,
                "Hey {$user->name}, you cannot declare remote work for past dates ({$startDateCarbon->format('M d, Y')}). Please contact HR if you need an attendance adjustment."
            );
            $user->notify(new \App\Notifications\SessionAnomalyNotification(
                "[Remote Request Error] You cannot declare remote work for past dates.",
                $user->id,
                'session_anomaly'
            ));
            return response()->json(['status' => 'invalid_past_date']);
        }

        // Collect all dates in the range
        $allDates = [];
        $cur = $startDateCarbon->copy();
        while ($cur->lte($endDateCarbon)) {
            $allDates[] = $cur->copy();
            $cur->addDay();
        }

        // For single date: specific weekend/holiday messages
        if (!$parsed['has_date_range']) {
            if ($startDateCarbon->isWeekend()) {
                $slackAlertService = app(\App\Services\SlackAlertService::class);
                $slackAlertService->sendDM(
                    $slackUserId,
                    "Hey {$user->name}, {$startDateCarbon->format('M d, Y')} is a weekend ({$startDateCarbon->format('l')}). Remote work declaration is only applicable for scheduled working days."
                );
                $user->notify(new \App\Notifications\SessionAnomalyNotification(
                    "[Remote Request Error] {$startDateCarbon->format('M d, Y')} is a weekend. Remote work is only applicable for working days.",
                    $user->id,
                    'session_anomaly'
                ));
                return response()->json(['status' => 'conflict_weekend']);
            }

            $userHoliday = \App\Models\CompanyHoliday::isHolidayForUser($user, $parsed['start_date']);
            if ($userHoliday) {
                $slackAlertService = app(\App\Services\SlackAlertService::class);
                $slackAlertService->sendDM(
                    $slackUserId,
                    "Hey {$user->name}, {$startDateCarbon->format('M d, Y')} is registered as an official holiday for you ({$userHoliday->name}). Remote work declaration is not required."
                );
                $user->notify(new \App\Notifications\SessionAnomalyNotification(
                    "[Remote Request Error] {$startDateCarbon->format('M d, Y')} is registered as an official holiday for you.",
                    $user->id,
                    'session_anomaly'
                ));
                return response()->json(['status' => 'conflict_company_holiday']);
            }
        }

        // Filter into working days, skipped company holidays, and skipped weekends
        $workingDates = [];
        $skippedHolidays = [];
        $skippedWeekends = [];

        foreach ($allDates as $d) {
            $dStr = $d->format('Y-m-d');
            $hol = \App\Models\CompanyHoliday::isHolidayForUser($user, $dStr);
            if ($hol) {
                $skippedHolidays[] = $d->format('M d') . " ({$hol->name})";
            } elseif ($d->isWeekend()) {
                $skippedWeekends[] = $d->format('M d') . " (" . $d->format('D') . ")";
            } else {
                $workingDates[] = $d;
            }
        }

        if (empty($workingDates)) {
            $slackAlertService = app(\App\Services\SlackAlertService::class);
            $slackAlertService->sendDM(
                $slackUserId,
                "Hey {$user->name}, the requested date range ({$startDateCarbon->format('M d')} – {$endDateCarbon->format('M d, Y')}) contains no working days (all dates fall on weekends or registered company holidays)."
            );
            $user->notify(new \App\Notifications\SessionAnomalyNotification(
                "[Remote Request Error] Requested range contains no working days (weekends/holidays).",
                $user->id,
                'session_anomaly'
            ));
            return response()->json(['status' => 'no_working_days']);
        }

        // Conflict checks across all working dates
        foreach ($workingDates as $wDate) {
            $dateStr = $wDate->format('Y-m-d');
            $dateFmt = $wDate->format('M d, Y');

            // 1. Approved Leave Conflict
            $hasApprovedLeave = \App\Models\LeaveRequest::where('user_id', $user->id)
                ->where('status', \App\Models\LeaveRequest::STATUS_APPROVED)
                ->where('start_date', '<=', $dateStr)
                ->where('end_date', '>=', $dateStr)
                ->first();

            if ($hasApprovedLeave) {
                $leaveName = $hasApprovedLeave->leaveType?->name ?? 'Leave';
                $slackAlertService = app(\App\Services\SlackAlertService::class);
                $slackAlertService->sendDM(
                    $slackUserId,
                    "Hey {$user->name}, you cannot declare remote work because you already have an approved {$leaveName} on {$dateFmt}."
                );
                $user->notify(new \App\Notifications\SessionAnomalyNotification(
                    "[Remote Request Error] You already have an approved {$leaveName} on {$dateFmt}.",
                    $user->id,
                    'session_anomaly'
                ));
                return response()->json(['status' => 'conflict_approved_leave']);
            }

            // 2. Pending Leave Conflict
            $pendingLeave = \App\Models\LeaveRequest::with('leaveType')
                ->where('user_id', $user->id)
                ->where(function($q) {
                    $q->whereIn('status', [\App\Models\LeaveRequest::STATUS_PENDING, 'approved_by_manager', 'pending_cancellation'])
                      ->orWhere('change_request_status', 'pending');
                })
                ->where('start_date', '<=', $dateStr)
                ->where('end_date', '>=', $dateStr)
                ->first();

            if ($pendingLeave) {
                $leaveName = $pendingLeave->leaveType?->name ?? 'Leave';
                $leaveFrom = Carbon::parse($pendingLeave->start_date)->format('M d');
                $leaveTo   = Carbon::parse($pendingLeave->end_date)->format('M d, Y');
                $slackAlertService = app(\App\Services\SlackAlertService::class);
                $slackAlertService->sendDM(
                    $slackUserId,
                    "Hey {$user->name}, you cannot declare remote work because you have a pending {$leaveName} request ({$leaveFrom} – {$leaveTo}) awaiting approval for {$dateFmt}."
                );
                $user->notify(new \App\Notifications\SessionAnomalyNotification(
                    "[Remote Request Error] You have a pending {$leaveName} request awaiting approval for {$dateFmt}.",
                    $user->id,
                    'session_anomaly'
                ));
                return response()->json(['status' => 'conflict_pending_leave']);
            }

            // 3. Marked Absent Conflict
            $isAbsent = \App\Models\AttendanceRecord::where('user_id', $user->id)
                ->where('date', $dateStr)
                ->exists();

            if ($isAbsent) {
                $slackAlertService = app(\App\Services\SlackAlertService::class);
                $slackAlertService->sendDM(
                    $slackUserId,
                    "Hey {$user->name}, you cannot declare remote work because you are already marked as absent on {$dateFmt}."
                );
                $user->notify(new \App\Notifications\SessionAnomalyNotification(
                    "[Remote Request Error] You are already marked as absent on {$dateFmt}.",
                    $user->id,
                    'session_anomaly'
                ));
                return response()->json(['status' => 'conflict_marked_absent']);
            }
        }

        // All checks passed -> create/update records for each working date
        foreach ($workingDates as $wDate) {
            \App\Models\RemoteWorkLog::updateOrCreate(
                ['user_id' => $user->id, 'date' => $wDate->format('Y-m-d')],
                [
                    'notes' => $notes,
                    'marked_by' => null, // null represents self-declared via system/Slack
                ]
            );
        }

        // Save event in slack_leave_sync_messages for audit trail
        SlackLeaveSyncMessage::create([
            'event_id' => $eventId,
            'slack_user_id' => $slackUserId,
            'channel_id' => $channelId,
            'message_text' => $cleanText,
            'slack_ts' => $slackTs,
            'message_at' => Carbon::createFromTimestamp((float) $slackTs),
        ]);

        // Formatted display date for in-app notification and Slack confirmation
        $workingCount = count($workingDates);
        $skipSummaryParts = [];
        if (!empty($skippedHolidays)) {
            $skipSummaryParts[] = "Company Holidays: " . implode(', ', $skippedHolidays);
        }
        if (!empty($skippedWeekends)) {
            $skipSummaryParts[] = "Weekends: " . implode(', ', $skippedWeekends);
        }

        if ($workingCount === 1 && !$parsed['has_date_range']) {
            $formattedDate = $workingDates[0]->format('M d, Y');
        } else {
            $skipNotice = !empty($skipSummaryParts) ? " (" . implode('; ', $skipSummaryParts) . " skipped)" : "";
            $formattedDate = $startDateCarbon->format('M d, Y') === $endDateCarbon->format('M d, Y')
                ? $startDateCarbon->format('M d, Y')
                : $startDateCarbon->format('M d, Y') . ' – ' . $endDateCarbon->format('M d, Y') . " ({$workingCount} working day" . ($workingCount > 1 ? 's' : '') . " marked{$skipNotice})";
        }

        // Send Slack Confirmation DM with complete breakdown
        $slackAlertService = app(\App\Services\SlackAlertService::class);
        if ($parsed['has_date_range']) {
            $confMsg = "Hey {$user->name}, your remote work declaration for {$startDateCarbon->format('M d, Y')} – {$endDateCarbon->format('M d, Y')} has been confirmed ({$workingCount} working day" . ($workingCount > 1 ? 's' : '') . " marked).";
            if (!empty($skipSummaryParts)) {
                $confMsg .= "\n_Note: Non-working days skipped: " . implode('; ', $skipSummaryParts) . "._";
            }
        } else {
            $confMsg = "Hey {$user->name}, your remote work declaration for {$formattedDate} has been confirmed.";
        }
        $slackAlertService->sendDM($slackUserId, $confMsg);

        // Send in-app notifications
        $user->notify(new \App\Notifications\RemoteMarkedNotification(
            employeeName:  $user->name,
            dateFormatted: $formattedDate,
            notes:         $notes,
            isSelf:        true,
            employeeId:    $user->id,
        ));

        $bossChain = app(\App\Services\HierarchyService::class)->getBossChain($user);
        foreach ($bossChain as $boss) {
            $boss->notify(new \App\Notifications\RemoteMarkedNotification(
                employeeName:  $user->name,
                dateFormatted: $formattedDate,
                notes:         $notes,
                isSelf:        false,
                employeeId:    $user->id,
            ));
        }

        // Notify Super Admins
        $superAdmins = \App\Models\User::whereHas('role', function($q) {
            $q->where('name', \App\Models\Role::SUPER_ADMIN);
        })->get();

        foreach ($superAdmins as $admin) {
            if ($admin->id !== $user->id && (!$user->manager || $admin->id !== $user->manager->id)) {
                $admin->notify(new \App\Notifications\RemoteMarkedNotification(
                    employeeName:  $user->name,
                    dateFormatted: $formattedDate,
                    notes:         $notes,
                    isSelf:        false,
                    employeeId:    $user->id,
                ));
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Helper to detect if user is attempting to declare remote work.
     */
    private function isAttemptingRemote(string $text, ?string &$suggestion, ?string &$misspelledWord = null): bool
    {
        $words = preg_split('/[\s,\.\?\!\:\(\)]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $candidates = ['remote', 'wfh'];
        
        foreach ($words as $word) {
            if (in_array($word, $candidates)) {
                return true;
            }
            
            // Levenshtein distance checks for typos
            foreach ($candidates as $target) {
                $dist = levenshtein($word, $target);
                $maxDist = ($target === 'wfh') ? 1 : 2;
                if ($dist > 0 && $dist <= $maxDist) {
                    if (isset($word[0]) && $word[0] === $target[0]) {
                        $suggestion = $target;
                        $misspelledWord = $word;
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Determine if user attempted to specify a time or date range with improper formatting.
     */
    private function isAttemptingInvalidRange(string $cleanText, array $parsed): bool
    {
        if ($parsed['has_time_range'] || $parsed['has_date_range']) {
            return false;
        }

        // 1. Time range attempt:
        // Examples: "from 3pm", "from 10:00", "3pm to 5pm", "to 4pm", "from 3 to 5"
        $hasTimeAttempt = preg_match('/\bfrom\s+\d{1,2}(?::\d{2})?[\s-]*(?:am|pm)?\b/i', $cleanText) ||
                          preg_match('/\b\d{1,2}(?::\d{2})?[\s-]*(?:am|pm)?\s+to\s+\d{1,2}(?::\d{2})?[\s-]*(?:am|pm)?\b/i', $cleanText) ||
                          preg_match('/\bto\s+\d{1,2}(?::\d{2})?[\s-]*(?:am|pm)\b/i', $cleanText);

        if ($hasTimeAttempt) {
            return true;
        }

        // 2. Date range attempt:
        // "from [date] to [something]" or "from [date]" where date is a month name, date number, or day
        // Exclude "from home", "from office", "from bed", etc.
        $dateTokens = '(?:today|tomorrow|monday|tuesday|wednesday|thursday|friday|saturday|sunday|mon|tue|wed|thu|fri|sat|sun|jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?|\d{1,2}(?:st|nd|rd|th)?|\d{1,4}[-\/\.]\d{1,2})';

        if (preg_match('/\bfrom\s+' . $dateTokens . '\b/i', $cleanText)) {
            return true;
        }

        return false;
    }

    /**
     * Unified helper to parse date range or single date & time range from message.
     */
    private function parseRemoteRequest(string $cleanText, Carbon $now, string $tz = 'Asia/Karachi'): array
    {
        // 1. Check for time range: "from [time] to [time]"
        $timeMatches = [];
        $hasTimeRange = false;
        $timePattern = '/\bfrom\s+(\d{1,2}(?::\d{2})?[\s-]*(?:am|pm)?)\s+to\s+(\d{1,2}(?::\d{2})?[\s-]*(?:am|pm)?)/i';
        
        if (preg_match($timePattern, $cleanText, $timeMatches)) {
            $t1 = trim($timeMatches[1]);
            $t2 = trim($timeMatches[2]);
            
            $hasTimeIndicator = preg_match('/(?:am|pm|:)/i', $t1) || preg_match('/(?:am|pm|:)/i', $t2);
            $isNotDateWord = !preg_match('/(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|today|tomorrow|mon|tue|wed|thu|fri|sat|sun|\/|-|\.)/i', $t1) &&
                             !preg_match('/(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|today|tomorrow|mon|tue|wed|thu|fri|sat|sun|\/|-|\.)/i', $t2);

            if ($hasTimeIndicator && $isNotDateWord) {
                $hasTimeRange = true;
            }
        }

        // 2. Check for Date Range: "from [date1] to [date2]"
        $dateRangeMatches = [];
        $hasDateRange = false;
        $startDateStr = null;
        $endDateStr = null;

        if (!$hasTimeRange && preg_match('/\bfrom\s+(.+?)\s+to\s+(.+)$/i', $cleanText, $dateRangeMatches)) {
            $rawStart = trim($dateRangeMatches[1]);
            $rawEnd = trim($dateRangeMatches[2]);
            $rawEnd = preg_replace('/[\.\?\!]+$/', '', $rawEnd);

            $parsedStart = $this->parseSingleDateFromText($rawStart, $now, $tz);
            $parsedEnd = $this->parseSingleDateFromText($rawEnd, $now, $tz);

            if ($parsedStart && $parsedEnd) {
                $hasDateRange = true;
                $startDateStr = $parsedStart;
                $endDateStr = $parsedEnd;
            }
        }

        // 3. Single date
        if (!$hasDateRange) {
            $parsedSingle = $this->parseSingleDateFromText($cleanText, $now, $tz);
            if ($parsedSingle) {
                $startDateStr = $parsedSingle;
            } else {
                // Only default to today if NO date/number was attempted in message (e.g. "remote", "working remotely")
                $stripped = trim(preg_replace('/\b(remote|wfh|working|work|remotely|from|home|full|day|for)\b/i', '', $cleanText));
                if (empty($stripped)) {
                    $startDateStr = $now->format('Y-m-d');
                } else {
                    $startDateStr = null;
                }
            }
            $endDateStr = $startDateStr;
        }

        return [
            'has_date_range' => $hasDateRange,
            'has_time_range' => $hasTimeRange,
            'start_date'     => $startDateStr,
            'end_date'       => $endDateStr,
            'time_matches'   => $hasTimeRange ? [$timeMatches[1], $timeMatches[2]] : null,
        ];
    }

    /**
     * Unified helper to parse a single date string.
     */
    private function parseSingleDateFromText(string $text, Carbon $now, string $tz = 'Asia/Karachi'): ?string
    {
        $clean = strtolower(trim($text));
        
        // 1. Direct YYYY-MM-DD or YYYY/MM/DD anywhere in text
        if (preg_match('/\b(\d{4})[-\/\.](\d{1,2})[-\/\.](\d{1,2})\b/', $clean, $m)) {
            try {
                return Carbon::createFromDate((int)$m[1], (int)$m[2], (int)$m[3], $tz)->format('Y-m-d');
            } catch (\Throwable $e) {}
        }

        // 2. Direct DD-MM-YYYY or MM/DD/YYYY or DD/MM/YYYY anywhere in text
        if (preg_match('/\b(\d{1,2})[-\/\.](\d{1,2})[-\/\.](\d{4})\b/', $clean, $m)) {
            try {
                $p1 = (int)$m[1];
                $p2 = (int)$m[2];
                $year = (int)$m[3];

                if ($p1 <= 12 && $p2 <= 12) {
                    return Carbon::createFromDate($year, $p1, $p2, $tz)->format('Y-m-d');
                } elseif ($p1 > 12) {
                    return Carbon::createFromDate($year, $p2, $p1, $tz)->format('Y-m-d');
                } else {
                    return Carbon::createFromDate($year, $p1, $p2, $tz)->format('Y-m-d');
                }
            } catch (\Throwable $e) {}
        }

        // 3. Direct DD-MM or DD/MM or MM/DD
        if (preg_match('/\b(\d{1,2})[-\/\.](\d{1,2})\b/', $clean, $m)) {
            try {
                $p1 = (int)$m[1];
                $p2 = (int)$m[2];
                if ($p1 <= 12 && $p2 <= 12) {
                    return Carbon::createFromDate($now->year, $p1, $p2, $tz)->format('Y-m-d');
                } elseif ($p1 > 12) {
                    return Carbon::createFromDate($now->year, $p2, $p1, $tz)->format('Y-m-d');
                } else {
                    return Carbon::createFromDate($now->year, $p1, $p2, $tz)->format('Y-m-d');
                }
            } catch (\Throwable $e) {}
        }

        // 4. Month name with day: e.g. "2 sep", "2nd september", "sep 2", "september 2 2026"
        $monthPattern = '(?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)';
        if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(' . $monthPattern . ')(?:\s+(\d{4}))?\b/i', $clean, $m)) {
            try {
                $d = Carbon::parse($m[1] . ' ' . $m[2] . ' ' . ($m[3] ?? $now->year), $tz);
                return $d->format('Y-m-d');
            } catch (\Throwable $e) {}
        }
        if (preg_match('/\b(' . $monthPattern . ')\s+(\d{1,2})(?:st|nd|rd|th)?(?:\s*,\s*|\s+)?(\d{4})?\b/i', $clean, $m)) {
            try {
                $d = Carbon::parse($m[1] . ' ' . $m[2] . ' ' . ($m[3] ?? $now->year), $tz);
                return $d->format('Y-m-d');
            } catch (\Throwable $e) {}
        }

        // 5. Tomorrow
        if (preg_match('/\bto[m]{1,2}o[r]{1,2}ow\b/i', $clean)) {
            return $now->copy()->addDay()->format('Y-m-d');
        }

        // 6. Today
        if (str_contains($clean, 'today')) {
            return $now->format('Y-m-d');
        }

        // 7. Weekday names
        $weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($weekdays as $day) {
            if (preg_match('/\b' . $day . '\b/i', $clean)) {
                try {
                    $d = Carbon::parse("next " . $day, $tz);
                    return $d->format('Y-m-d');
                } catch (\Throwable $e) {}
            }
        }

        // 8. General Carbon parse on stripped text
        $stripped = trim(preg_replace('/\b(remote|wfh|working|work|remotely|from|home|on)\b/i', '', $clean));
        if (!empty($stripped)) {
            try {
                $d = Carbon::parse($stripped, $tz);
                if ($d) {
                    if ($d->year < 2000) {
                        $d->year($now->year);
                    }
                    return $d->format('Y-m-d');
                }
            } catch (\Throwable $e) {}
        }

        return null;
    }

    /**
     * Lock deletion on any active SlackLeaveSyncMessage records on or prior to signout.
     */
    private function lockRemoteDeclarationsOnSignOut(string $slackUserId, Carbon $signOutTime): void
    {
        SlackLeaveSyncMessage::where('slack_user_id', $slackUserId)
            ->where('message_at', '<=', $signOutTime)
            ->update(['is_deletion_locked' => true]);
    }

    /**
     * Handle edited messages in the #leave-sync channel.
     */
    private function handleLeaveSyncMessageEdit(string $slackTs, string $channelId, string $newTextRaw, array $event): JsonResponse
    {
        $originalRecord = SlackLeaveSyncMessage::where('slack_ts', $slackTs)
            ->where('channel_id', $channelId)
            ->first();

        if (!$originalRecord) {
            $editedMessage = $event['message'] ?? [];
            $mockEvent = [
                'user' => $editedMessage['user'] ?? null,
                'channel' => $channelId,
                'text' => $newTextRaw,
                'ts' => $slackTs,
            ];
            $eventId = 'evt_edit_' . str_replace('.', '_', $slackTs);
            return $this->handleLeaveSyncMessage($mockEvent, $eventId);
        }

        $cleanNewText = preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}]/u', ' ', $newTextRaw);
        $cleanNewText = strtolower(trim(preg_replace('/\s+/', ' ', $cleanNewText)));
        $tz = 'Asia/Karachi';
        $now = Carbon::now($tz);

        // Suggest format if they made a typo
        $typoSuggestion = null;
        $misspelledWord = null;
        $isAttempt = $this->isAttemptingRemote($cleanNewText, $typoSuggestion, $misspelledWord);

        $words = preg_split('/[\s,\.\?\!\:\(\)]+/', $cleanNewText, -1, PREG_SPLIT_NO_EMPTY);
        $hasExactKeyword = false;
        foreach ($words as $word) {
            if (str_starts_with($word, 'remote') || $word === 'wfh') {
                $hasExactKeyword = true;
                break;
            }
        }

        $msgTimeLocal = Carbon::now($tz);
        $timeFormatted = $msgTimeLocal->format('h:i A');
        $dateFormatted = $msgTimeLocal->format('l, M d, Y');

        if (!$hasExactKeyword) {
            if ($isAttempt && $typoSuggestion && $misspelledWord) {
                $mapping = \App\Models\SlackUserMapping::where('slack_user_id', $originalRecord->slack_user_id)->first();
                $user = $mapping?->user;
                if ($user) {
                    $slackAlertService = app(\App\Services\SlackAlertService::class);
                    $slackAlertService->sendDM(
                        $originalRecord->slack_user_id,
                        "Hey {$user->name}, did you mean '{$typoSuggestion}' in your edited message? You typed '{$misspelledWord}' in your message '{$newTextRaw}' at {$timeFormatted} on {$dateFormatted}. Please verify spelling and try again."
                    );
                }
                return response()->json(['status' => 'typo_notified']);
            }
            return response()->json(['status' => 'ignored_non_remote_message']);
        }

        $mapping = \App\Models\SlackUserMapping::where('slack_user_id', $originalRecord->slack_user_id)->first();
        $user = $mapping?->user;
        if (!$user) {
            return response()->json(['status' => 'user_unmapped']);
        }

        // Parse Date & Time using unified parser
        $parsed = $this->parseRemoteRequest($cleanNewText, $now, $tz);

        if (!$parsed['start_date'] || !$parsed['end_date']) {
            $slackAlertService = app(\App\Services\SlackAlertService::class);
            $slackAlertService->sendDM(
                $originalRecord->slack_user_id,
                "Hey {$user->name}, the format of your remote work edit was incorrect. You typed '{$newTextRaw}' at {$timeFormatted} on {$dateFormatted}. Please use one of the allowed formats:\n- 'remote today'\n- 'remote on [date]'\n- 'remote from [start_date] to [end_date]'\n- 'remote [today/on date] from [start_time] to [end_time]'"
            );
            return response()->json(['status' => 'invalid_date_format']);
        }

        // Format check: If user attempted a time range or date range with invalid format
        if ($this->isAttemptingInvalidRange($cleanNewText, $parsed)) {
            $slackAlertService = app(\App\Services\SlackAlertService::class);
            $slackAlertService->sendDM(
                $originalRecord->slack_user_id,
                "Hey {$user->name}, the format of your remote work edit was incorrect. You typed '{$newTextRaw}' at {$timeFormatted} on {$dateFormatted}. For a time range, specify 'from [start_time] to [end_time]' (e.g. from 3:00pm to 4:00pm). For a date range, specify 'from [start_date] to [end_date]' (e.g. from Aug 25 to Aug 28)."
            );
            $user->notify(new \App\Notifications\SessionAnomalyNotification(
                "[Remote Request Error] Incorrect format in edit. Specify 'from [time] to [time]' or 'from [date] to [date]'.",
                $user->id,
                'session_anomaly'
            ));
            return response()->json(['status' => 'invalid_format']);
        }

        if ($parsed['has_time_range']) {
            $startTime = trim($parsed['time_matches'][0]);
            $endTime = trim($parsed['time_matches'][1]);

            $startCarbon = null;
            $endCarbon = null;
            try {
                $startClean = str_replace('-', ' ', $startTime);
                $endClean = str_replace('-', ' ', $endTime);
                $startCarbon = Carbon::parse($startClean, $tz);
                $endCarbon = Carbon::parse($endClean, $tz);
            } catch (\Throwable $e) {
                $startCarbon = null;
                $endCarbon = null;
            }

            if (!$startCarbon || !$endCarbon || $startCarbon->gte($endCarbon)) {
                $slackAlertService = app(\App\Services\SlackAlertService::class);
                $slackAlertService->sendDM(
                    $originalRecord->slack_user_id,
                    "Hey {$user->name}, the time range for your remote work edit was invalid. Start time '{$startTime}' must be earlier than end time '{$endTime}'. You typed '{$newTextRaw}' at {$timeFormatted}."
                );
                $user->notify(new \App\Notifications\SessionAnomalyNotification(
                    "[Remote Request Error] Start time '{$startTime}' must be earlier than end time '{$endTime}'.",
                    $user->id,
                    'session_anomaly'
                ));
                return response()->json(['status' => 'invalid_time_range']);
            }

            $notes = "Remote Work: {$startCarbon->format('h:i A')} to {$endCarbon->format('h:i A')} (Declared via Slack)";
        } else {
            $notes = "Remote Work: Full Day (Declared via Slack)";
        }

        $startDateCarbon = Carbon::parse($parsed['start_date'], $tz);
        $endDateCarbon = Carbon::parse($parsed['end_date'], $tz);

        // Validation 1: Start date must be <= End date
        if ($startDateCarbon->gt($endDateCarbon)) {
            $slackAlertService = app(\App\Services\SlackAlertService::class);
            $slackAlertService->sendDM(
                $originalRecord->slack_user_id,
                "Hey {$user->name}, start date '{$startDateCarbon->format('M d, Y')}' must be on or before end date '{$endDateCarbon->format('M d, Y')}'."
            );
            $user->notify(new \App\Notifications\SessionAnomalyNotification(
                "[Remote Request Error] Start date {$startDateCarbon->format('M d')} must be on or before end date {$endDateCarbon->format('M d, Y')}.",
                $user->id,
                'session_anomaly'
            ));
            return response()->json(['status' => 'invalid_date_range']);
        }

        // Validation 2: Cannot declare remote work for past dates
        if ($startDateCarbon->lt($now->copy()->startOfDay())) {
            $slackAlertService = app(\App\Services\SlackAlertService::class);
            $slackAlertService->sendDM(
                $originalRecord->slack_user_id,
                "Hey {$user->name}, you cannot declare remote work for past dates ({$startDateCarbon->format('M d, Y')}). Please contact HR if you need an attendance adjustment."
            );
            $user->notify(new \App\Notifications\SessionAnomalyNotification(
                "[Remote Request Error] You cannot declare remote work for past dates.",
                $user->id,
                'session_anomaly'
            ));
            return response()->json(['status' => 'invalid_past_date']);
        }

        // Parse previous dates to clean up old remote work logs
        $parsedOld = $this->parseRemoteRequest(strtolower(trim($originalRecord->message_text)), $originalRecord->message_at->timezone($tz), $tz);
        if ($parsedOld['start_date'] && $parsedOld['end_date']) {
            $oldStart = Carbon::parse($parsedOld['start_date'], $tz);
            $oldEnd = Carbon::parse($parsedOld['end_date'], $tz);
            $curOld = $oldStart->copy();
            while ($curOld->lte($oldEnd)) {
                \App\Models\RemoteWorkLog::where('user_id', $user->id)->where('date', $curOld->format('Y-m-d'))->delete();
                $curOld->addDay();
            }
        }

        // Filter new dates into working days, company holidays, weekends
        $allDates = [];
        $cur = $startDateCarbon->copy();
        while ($cur->lte($endDateCarbon)) {
            $allDates[] = $cur->copy();
            $cur->addDay();
        }

        $workingDates = [];
        foreach ($allDates as $d) {
            $dStr = $d->format('Y-m-d');
            $hol = \App\Models\CompanyHoliday::isHolidayForUser($user, $dStr);
            if (!$hol && !$d->isWeekend()) {
                $workingDates[] = $d;
            }
        }

        // Create or update remote work logs for each new working date
        foreach ($workingDates as $wDate) {
            \App\Models\RemoteWorkLog::updateOrCreate(
                ['user_id' => $user->id, 'date' => $wDate->format('Y-m-d')],
                [
                    'notes' => $notes,
                    'marked_by' => null,
                ]
            );
        }

        $workingCount = count($workingDates);
        if ($workingCount === 1 && !$parsed['has_date_range']) {
            $formattedDate = $workingDates[0]->format('M d, Y');
        } else {
            $formattedDate = $startDateCarbon->format('M d, Y') === $endDateCarbon->format('M d, Y')
                ? $startDateCarbon->format('M d, Y')
                : $startDateCarbon->format('M d, Y') . ' – ' . $endDateCarbon->format('M d, Y');
        }

        // In-app notifications: User (self)
        $user->notify(new \App\Notifications\RemoteMarkedNotification(
            employeeName:  $user->name,
            dateFormatted: $formattedDate,
            notes:         $notes . ' (Edited)',
            isSelf:        true,
            employeeId:    $user->id,
        ));

        // In-app notifications: Boss chain
        $bossChain = app(\App\Services\HierarchyService::class)->getBossChain($user);
        foreach ($bossChain as $boss) {
            $boss->notify(new \App\Notifications\RemoteMarkedNotification(
                employeeName:  $user->name,
                dateFormatted: $formattedDate,
                notes:         $notes . ' (Edited)',
                isSelf:        false,
                employeeId:    $user->id,
            ));
        }

        // In-app notifications: Super Admins
        $superAdmins = \App\Models\User::whereHas('role', function($q) {
            $q->where('name', \App\Models\Role::SUPER_ADMIN);
        })->get();

        foreach ($superAdmins as $admin) {
            if ($admin->id !== $user->id && (!$user->manager || $admin->id !== $user->manager->id)) {
                $admin->notify(new \App\Notifications\RemoteMarkedNotification(
                    employeeName:  $user->name,
                    dateFormatted: $formattedDate,
                    notes:         $notes . ' (Edited)',
                    isSelf:        false,
                    employeeId:    $user->id,
                ));
            }
        }

        // Send Slack Confirmation DM
        $slackAlertService = app(\App\Services\SlackAlertService::class);
        $slackAlertService->sendDM(
            $originalRecord->slack_user_id,
            "Hey {$user->name}, your remote work declaration has been updated for {$formattedDate}."
        );

        // Update tracking record
        $originalRecord->update([
            'message_text' => $cleanNewText,
            'is_edited' => true,
        ]);

        return response()->json(['status' => 'leave_sync_message_edited']);
    }

    /**
     * Handle deleted messages in the #leave-sync channel.
     */
    private function handleLeaveSyncMessageDelete(string $slackTs, string $channelId): JsonResponse
    {
        $message = SlackLeaveSyncMessage::where('slack_ts', $slackTs)
            ->where('channel_id', $channelId)
            ->first();

        if (!$message) {
            return response()->json(['status' => 'message_not_found']);
        }

        // 1. Check if deletion is already locked
        $isLocked = (bool) $message->is_deletion_locked;

        // 2. Also check if any sign-out message exists in Slack Sync on or after the remote message timestamp
        if (!$isLocked) {
            $hasSignOutAfter = SlackMessage::where('slack_user_id', $message->slack_user_id)
                ->where('message_at', '>=', $message->message_at)
                ->where(function ($q) {
                    $q->where('message_text', 'like', 'sign out%')
                      ->orWhere('message_text', 'like', 'signout%');
                })
                ->exists();

            if ($hasSignOutAfter) {
                $message->update(['is_deletion_locked' => true]);
                $isLocked = true;
            }
        }

        // If locked due to a signout: do not delete RemoteWorkLog and do not trigger cancellation notifications
        if ($isLocked) {
            $mapping = \App\Models\SlackUserMapping::where('slack_user_id', $message->slack_user_id)->first();
            $user = $mapping?->user;
            if ($user) {
                $slackAlertService = app(\App\Services\SlackAlertService::class);
                $slackAlertService->sendDM(
                    $message->slack_user_id,
                    "Hey {$user->name}, your remote work declaration was not deleted from the system because you have already signed out / ended your shift. Please contact HR if you need a manual adjustment."
                );
            }
            return response()->json(['status' => 'deletion_locked_due_to_signout']);
        }

        // If not locked: cleanly delete RemoteWorkLog entries and send alerts
        $tz = 'Asia/Karachi';
        $cleanText = strtolower(trim($message->message_text));
        $parsed = $this->parseRemoteRequest($cleanText, $message->message_at->timezone($tz), $tz);

        $mapping = \App\Models\SlackUserMapping::where('slack_user_id', $message->slack_user_id)->first();
        $user = $mapping?->user;

        if ($user && $parsed['start_date'] && $parsed['end_date']) {
            $startDateCarbon = Carbon::parse($parsed['start_date'], $tz);
            $endDateCarbon = Carbon::parse($parsed['end_date'], $tz);

            $cur = $startDateCarbon->copy();
            $deletedDates = [];
            while ($cur->lte($endDateCarbon)) {
                $dStr = $cur->format('Y-m-d');
                \App\Models\RemoteWorkLog::where('user_id', $user->id)->where('date', $dStr)->delete();
                $deletedDates[] = $cur->copy();
                $cur->addDay();
            }

            // Format date string for notifications
            $count = count($deletedDates);
            if ($count === 1 && !$parsed['has_date_range']) {
                $formattedDate = $deletedDates[0]->format('M d, Y');
            } else {
                $formattedDate = $startDateCarbon->format('M d, Y') === $endDateCarbon->format('M d, Y')
                    ? $startDateCarbon->format('M d, Y')
                    : $startDateCarbon->format('M d, Y') . ' – ' . $endDateCarbon->format('M d, Y');
            }

            // Send Slack Confirmation DM
            $slackAlertService = app(\App\Services\SlackAlertService::class);
            $slackAlertService->sendDM(
                $message->slack_user_id,
                "Hey {$user->name}, your remote work declaration for {$formattedDate} has been cancelled and removed from the system."
            );

            // In-app notifications: User (self)
            $user->notify(new \App\Notifications\RemoteDeletedNotification(
                employeeName:  $user->name,
                dateFormatted: $formattedDate,
                isSelf:        true,
                employeeId:    $user->id,
            ));

            // In-app notifications: Boss chain
            $bossChain = app(\App\Services\HierarchyService::class)->getBossChain($user);
            foreach ($bossChain as $boss) {
                $boss->notify(new \App\Notifications\RemoteDeletedNotification(
                    employeeName:  $user->name,
                    dateFormatted: $formattedDate,
                    isSelf:        false,
                    employeeId:    $user->id,
                ));
            }

            // In-app notifications: Super Admins
            $superAdmins = \App\Models\User::whereHas('role', function($q) {
                $q->where('name', \App\Models\Role::SUPER_ADMIN);
            })->get();

            foreach ($superAdmins as $admin) {
                if ($admin->id !== $user->id && (!$user->manager || $admin->id !== $user->manager->id)) {
                    $admin->notify(new \App\Notifications\RemoteDeletedNotification(
                        employeeName:  $user->name,
                        dateFormatted: $formattedDate,
                        isSelf:        false,
                        employeeId:    $user->id,
                    ));
                }
            }
        }

        // Clean up the tracking row
        $message->delete();

        return response()->json(['status' => 'leave_sync_message_deleted']);
    }
}
