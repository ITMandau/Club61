<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mode "Coming Soon" halaman login
    |--------------------------------------------------------------------------
    |
    | Isi tanggal & jam grand opening (zona APP_TIMEZONE), mis. "2026-11-26 00:00". Selama waktu itu belum lewat:
    | /login tampil hitung mundur + tombol "Login Staf", akun customer ditolak masuk, dan pendaftaran ditutup.
    | Lewat dari waktu itu otomatis kembali normal. Kosong / tidak valid = mode mati (bawaan).
    |
    */

    'until' => env('COMING_SOON_UNTIL'),

];
