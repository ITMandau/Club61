@include('errors.layout', [
    'code' => 403,
    'title' => 'Akses ditolak',
    'message' => 'Akun yang sedang Anda pakai tidak punya izin untuk membuka halaman atau menjalankan aksi ini. Hak akses tiap akun diatur oleh admin Club 61.',
    'steps' => [
        'Pastikan Anda masuk dengan akun yang benar (misalnya akun kasir untuk layar POS, akun admin untuk panel admin).',
        'Kalau seharusnya punya akses, minta admin Club 61 menambahkan izin untuk role akun Anda.',
        'Kembali ke halaman sebelumnya untuk melanjutkan pekerjaan lain.',
    ],
    'actions' => ['back', 'home', 'login'],
])
