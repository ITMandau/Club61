@include('errors.layout', [
    'code' => 503,
    'title' => 'Sedang dalam perbaikan',
    'message' => 'Sistem Club 61 sedang diperbarui atau dirawat sebentar. Semua data Anda aman, halaman akan kembali normal setelah perbaikan selesai.',
    'steps' => [
        'Tunggu beberapa menit, lalu muat ulang halaman.',
        'Untuk booking atau pembayaran yang mendesak, hubungi front desk Club 61.',
    ],
    'actions' => ['reload'],
])
