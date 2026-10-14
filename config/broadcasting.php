<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | "reverb" = update realtime aktif (papan jadwal, TV, POS, Kelola Pemesanan ikut berubah sendiri).
    | "null" / "log" = realtime mati; semua layar memakai polling cadangan (lihat docs/DEPLOY_REVERB.md).
    |
    */

    // "reverb" tanpa REVERB_APP_KEY → dianggap mati (polling cadangan). Dulu salah isi .env seperti ini membuat SELURUH
    // aplikasi gagal start (halaman, artisan, webhook Midtrans) karena broadcaster Reverb dibuat tanpa key.
    'default' => env('BROADCAST_CONNECTION') === 'reverb' && ! env('REVERB_APP_KEY') ? 'null' : env('BROADCAST_CONNECTION', 'null'),

    'connections' => [

        // Aplikasi mengirim sinyal ke server Reverb di VPS yang sama (alamat internal, bukan domain publik).
        // Alamat yang dipakai browser / TV diatur terpisah lewat REVERB_CLIENT_* (lihat App\Support\Realtime).
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST', '127.0.0.1'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            // Reverb mati / lambat tidak boleh menahan request: sinyal dikirim setelah respons, dengan batas waktu pendek.
            'client_options' => [
                'connect_timeout' => 1,
                'timeout' => 2,
            ],
            // Alamat yang dibuka browser / TV. Kosong = ikut APP_URL (domain aplikasi, port 443 untuk https).
            'client' => [
                'host' => env('REVERB_CLIENT_HOST'),
                'port' => env('REVERB_CLIENT_PORT'),
                'scheme' => env('REVERB_CLIENT_SCHEME'),
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
