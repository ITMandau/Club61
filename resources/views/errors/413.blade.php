@include('errors.layout', [
    'code' => 413,
    'title' => 'File terlalu besar',
    'message' => 'File yang Anda unggah melebihi batas ukuran yang diizinkan server, jadi tidak bisa diterima.',
    'steps' => [
        'Perkecil ukuran file dulu (kompres foto / video), lalu unggah lagi.',
        'Cek batas ukuran yang tertulis di form unggah (misalnya gambar maks. 10 MB, video maks. 200 MB).',
        'Kalau ukurannya sudah sesuai tapi tetap ditolak, hubungi admin — batas unggah di server mungkin perlu dinaikkan.',
    ],
    'actions' => ['back', 'home'],
])
