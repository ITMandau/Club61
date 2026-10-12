<?php

return [
    // Sengaja tidak membedakan "akun tidak ada" dan "kata sandi salah": pesan yang berbeda membocorkan
    // email / nomor HP mana yang terdaftar di Club 61.
    'failed' => 'Email / nomor HP atau kata sandi salah. Periksa lagi penulisannya (huruf besar-kecil pada kata sandi berpengaruh), lalu coba lagi.',
    'password' => 'Kata sandi yang Anda masukkan salah.',
    'remaining' => 'Sisa :count kali percobaan lagi sebelum masuk dikunci sementara.',
    'throttle' => 'Terlalu banyak percobaan masuk yang gagal. Demi keamanan akun, coba lagi dalam :minutes menit, atau gunakan "Lupa kata sandi?".',
];
