@include('errors.layout', [
    'code' => 500,
    'title' => 'Terjadi kesalahan di sistem',
    'message' => 'Maaf, ada gangguan di server Club 61 saat memproses permintaan Anda. Ini bukan kesalahan Anda, dan kejadiannya sudah tercatat di sistem.',
    'steps' => [
        'Muat ulang halaman setelah beberapa saat.',
        'Kalau sedang transaksi / pembayaran, cek dulu riwayat transaksinya sebelum mengulang, supaya tidak tercatat dua kali.',
        'Kalau terus terjadi, hubungi admin Club 61 dan sebutkan jam kejadian di bawah.',
    ],
    'actions' => ['reload', 'home'],
])
