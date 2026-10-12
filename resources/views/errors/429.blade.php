@php
    $retry = (int) (($exception ?? null) && method_exists($exception, 'getHeaders') ? ($exception->getHeaders()['Retry-After'] ?? 0) : 0);
    $wait = $retry >= 60 ? ceil($retry / 60) . ' menit' : ($retry > 0 ? $retry . ' detik' : 'sekitar 1 menit');
@endphp
@include('errors.layout', [
    'code' => 429,
    'title' => 'Terlalu banyak percobaan',
    'message' => 'Permintaan dari perangkat Anda terlalu banyak dalam waktu singkat (misalnya salah sandi berkali-kali atau tombol ditekan berulang). Demi keamanan, sistem menahan sementara.',
    'steps' => [
        'Tunggu ' . $wait . ', lalu coba lagi.',
        'Tekan tombol satu kali saja dan tunggu sampai prosesnya selesai.',
        'Kalau lupa kata sandi, gunakan "Lupa kata sandi" di halaman masuk daripada mencoba terus.',
    ],
    'actions' => ['back', 'home'],
])
