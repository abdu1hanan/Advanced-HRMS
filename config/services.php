<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'signing_secret' => env('SLACK_SIGNING_SECRET'),
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
        'leave_sync_channel_id' => env('SLACK_LEAVE_SYNC_CHANNEL_ID'),
    ],

    'salary_slip' => [
        'cc_emails' => env('SALARY_SLIP_CC_EMAILS'),
        'node_path' => env('NODE_PATH'),
        'npm_path' => env('NPM_PATH'),
        'chrome_path' => env('CHROME_PATH'),
        'node_modules_path' => env('NODE_MODULES_PATH'),
    ],

    'salary' => [
        'encrypt_key' => env('encrypt_key'),
        'cipher' => env('SALARY_CIPHER', 'AES-256-CBC'),
    ],

];
