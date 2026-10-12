@include('errors.layout', [
    'code' => 404,
    'title' => 'Halaman tidak ditemukan',
    'message' => 'Alamat yang Anda buka tidak ada, sudah dipindah, atau datanya sudah dihapus. Bisa juga karena link-nya terpotong saat disalin.',
    'steps' => [
        'Periksa lagi alamat di address bar — pastikan tidak ada huruf yang salah atau terpotong.',
        'Kalau membuka dari link (WhatsApp / email), minta link yang baru dari pengirimnya.',
        'Mulai lagi dari beranda dan buka menunya dari sana.',
    ],
    'actions' => ['home', 'back'],
])
