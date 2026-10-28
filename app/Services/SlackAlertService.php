<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackAlertService
{
    protected ?string $botToken;

    public function __construct()
    {
        $this->botToken = config('services.slack.notifications.bot_user_oauth_token');
    }

    /**
     * Send a direct message to a Slack user by their Slack User ID.
     */
    public function sendDM(string $slackUserId, string $message): bool
    {
        if (empty($this->botToken)) {
            Log::warning("SlackAlertService: SLACK_BOT_USER_OAUTH_TOKEN is not configured. Message not sent: {$message}");
            return false;
        }

        try {
            // Skip SSL verification only in local dev (Windows cURL SSL cert issue).
            // In production on Linux, certificates are properly configured.
            $request = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->botToken,
                'Content-Type' => 'application/json; charset=utf-8',
            ]);

            if (app()->isLocal()) {
                $request = $request->withoutVerifying();
            }

            $response = $request->post('https://slack.com/api/chat.postMessage', [
                    'channel' => $slackUserId,
                    'text' => $message,
                ]);

            if ($response->successful() && ($response->json('ok') ?? false)) {
                return true;
            }

            Log::error("SlackAlertService: Failed to send DM. Slack response: " . json_encode($response->json()));
            return false;
        } catch (\Exception $e) {
            Log::error("SlackAlertService: Exception while sending DM: " . $e->getMessage());
            return false;
        }
    }
}
