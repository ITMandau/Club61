{{-- Cadangan untuk kode 4xx lain yang tidak punya halaman sendiri. --}}
@include('errors.layout', [
    'code' => ($exception ?? null) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 400,
    'title' => 'Permintaan tidak bisa diproses',
    'message' => 'Data atau alamat yang dikirim tidak bisa diproses oleh sistem. Biasanya karena link sudah tidak berlaku atau form dikirim tidak lengkap.',
    'steps' => [
        'Kembali ke halaman sebelumnya dan ulangi langkahnya dari tombol di aplikasi.',
        'Kalau membuka dari link, minta link yang baru.',
    ],
    'actions' => ['back', 'home'],
])
