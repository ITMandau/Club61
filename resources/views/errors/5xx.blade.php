{{-- Cadangan untuk kode 5xx lain (502, 504, …) yang tidak punya halaman sendiri. --}}
@include('errors.layout', [
    'code' => ($exception ?? null) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500,
    'title' => 'Server sedang bermasalah',
    'message' => 'Server Club 61 tidak bisa menyelesaikan permintaan Anda saat ini. Ini bukan kesalahan Anda.',
    'steps' => [
        'Tunggu sebentar, lalu muat ulang halaman.',
        'Kalau sedang transaksi / pembayaran, cek dulu riwayat transaksinya sebelum mengulang.',
        'Kalau terus terjadi, hubungi admin Club 61.',
    ],
    'actions' => ['reload', 'home'],
])
