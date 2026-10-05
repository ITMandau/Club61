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
        'token' => env('POSTMARK_TOKEN'),
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

    'payment' => [
        'driver' => env('PAYMENT_GATEWAY_DRIVER', 'midtrans'),
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        // Tanpa nilai cadangan: key palsu 'SB-Mid-client-demo-61' dulu membuat popup bayar gagal tanpa penjelasan.
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        // Opsional: URL webhook per transaksi (header X-Override-Notification) — untuk laptop developer lewat ngrok
        // yang memakai akun sandbox yang sama dengan server. Kosong = pakai "Payment Notification URL" di dashboard.
        'notification_url' => env('MIDTRANS_NOTIFICATION_URL'),
        'is_sanitized' => true,
        'is_3ds' => true,
        // Notifikasi "lunas" dikonfirmasi ulang ke Status API Midtrans sebelum order dilunasi (signature Midtrans
        // tidak mencakup transaction_status, jadi signature notifikasi lain bisa dipakai ulang). Jangan dimatikan
        // di server; hanya dimatikan di test otomatis (phpunit.xml).
        'verify_webhook_with_status_api' => env('MIDTRANS_VERIFY_WEBHOOK_STATUS', true),
    ],

];
