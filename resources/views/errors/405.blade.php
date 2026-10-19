@include('errors.layout', [
    'code' => 405,
    'title' => 'Cara membuka halaman ini tidak didukung',
    'message' => 'Halaman ini hanya bisa dijalankan lewat tombol di aplikasi (misalnya tombol Keluar atau Simpan), bukan dibuka langsung dari address bar atau dari riwayat browser.',
    'steps' => [
        'Kembali ke halaman sebelumnya, lalu tekan tombolnya lagi dari sana.',
        'Jangan menyalin / membuka ulang alamat halaman proses dari riwayat browser.',
    ],
    'actions' => ['back', 'home'],
])
