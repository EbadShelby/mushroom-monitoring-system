<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'firebase' => [
        'database_url' => env('FIREBASE_DATABASE_URL'),
        // Path to the service account JSON downloaded from Firebase Console →
        // Project Settings → Service Accounts → Generate new private key.
        // Store it in storage/app/ and NEVER commit it to git.
        'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH'),
    ],

    'semaphore' => [
        'api_key' => env('SEMAPHORE_API_KEY'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME', 'MushroomIoT'),
    ],

    'camera' => [
        // Shared secret the ESP32-CAM sends in the X-Camera-Token header.
        'token' => env('CAMERA_TOKEN'),
    ],

    'device' => [
        // Shared secret the main ESP32 sends in the X-Device-Token header.
        'token' => env('DEVICE_TOKEN'),
    ],

];
