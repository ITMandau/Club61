<?php

/*
 * Hanya bagian yang berbeda dari bawaan Livewire (sisanya digabung otomatis dari konfigurasi paket).
 *
 * Upload sementara: batas 100 MB (= Signature::VIDEO_MAX_MB) supaya video Signature (Modul 26) bisa di-upload. Setiap
 * form tetap memvalidasi batas ukurannya sendiri (mis. foto menu tetap maks 2 MB). Server juga harus mengizinkan ukuran
 * ini di PHP (upload_max_filesize & post_max_size) dan web server (client_max_body_size di Nginx).
 */
return [
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => ['required', 'file', 'max:102400'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 15,
        'cleanup' => true,
    ],
];
