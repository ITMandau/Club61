@include('errors.layout', [
    'code' => 401,
    'title' => 'Anda perlu masuk dulu',
    'message' => 'Halaman ini hanya bisa dibuka setelah masuk ke akun Club 61. Bisa juga karena sesi login Anda sudah berakhir atau Anda keluar dari perangkat lain.',
    'steps' => [
        'Masuk lagi dengan email / nomor HP dan kata sandi Anda.',
        'Setelah masuk, buka kembali halaman yang tadi.',
    ],
    'actions' => ['login', 'home'],
])
