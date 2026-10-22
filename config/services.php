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

    // DOKU Checkout (Modul 27). false / kunci kosong = DOKU tidak bisa dipilih untuk tagihan baru (OnlineGatewayRouter);
    // tagihan DOKU yang sudah ada tetap dicek sampai selesai. Kunci hanya di .env, tidak pernah ditulis ke log.
    'doku' => [
        'enabled' => (bool) env('DOKU_ENABLED', false),
        'client_id' => env('DOKU_CLIENT_ID'),
        'secret_key' => env('DOKU_SECRET_KEY'),
        'is_production' => (bool) env('DOKU_IS_PRODUCTION', false),
        // Opsional: path harus sama dengan URL notifikasi yang didaftarkan di dashboard DOKU.
        'notification_url' => env('DOKU_NOTIFICATION_URL'),

        // DOKU SNAP (Modul 27b) — QRIS & VA BCA lewat API SNAP Bank Indonesia, halaman bayar buatan kita sendiri.
        // Aktif → metode yang didukung SNAP (lihat DokuSnapService::SUPPORTED) memakai SNAP; metode DOKU lain tetap
        // lewat DOKU Checkout. Kunci privat & public key DOKU berupa FILE (path), bukan isi kunci di .env.
        'snap' => [
            'enabled' => (bool) env('DOKU_SNAP_ENABLED', false),
            'client_id' => env('DOKU_SNAP_CLIENT_ID', env('DOKU_CLIENT_ID')),
            'client_secret' => env('DOKU_SNAP_CLIENT_SECRET'),
            'private_key_path' => env('DOKU_SNAP_PRIVATE_KEY_PATH'),
            'private_key_passphrase' => env('DOKU_SNAP_PRIVATE_KEY_PASSPHRASE'),
            'doku_public_key_path' => env('DOKU_SNAP_DOKU_PUBLIC_KEY_PATH'),
            'qris_merchant_id' => env('DOKU_SNAP_QRIS_MERCHANT_ID'),
            'qris_terminal_id' => env('DOKU_SNAP_QRIS_TERMINAL_ID'),
            'qris_postal_code' => env('DOKU_SNAP_QRIS_POSTAL_CODE'),
            // BIN BCA DOKU (contoh resmi "   19008") + prefix sub-BIN merchant (contoh resmi "0"); sisa nomor VA dibuat DOKU (DGPC).
            'bca_partner_service_id' => env('DOKU_SNAP_BCA_PARTNER_SERVICE_ID'),
            'bca_customer_no' => env('DOKU_SNAP_BCA_CUSTOMER_NO', '0'),
        ],
    ],

];
