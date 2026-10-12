{{-- Biasanya tidak terlihat: form web yang kedaluwarsa otomatis dikembalikan ke halamannya dengan pesan (bootstrap/app.php).
     Halaman ini muncul kalau browser tidak menyimpan cookie sesi sama sekali, jadi sarannya fokus ke cookie. --}}
@include('errors.layout', [
    'code' => 419,
    'title' => 'Sesi halaman sudah habis',
    'message' => 'Demi keamanan, setiap form punya kode sesi yang ada batas waktunya. Kode ini tidak cocok lagi — biasanya karena halaman dibuka terlalu lama sebelum dikirim, atau browser tidak menyimpan cookie dari Club 61.',
    'steps' => [
        'Muat ulang halaman, lalu isi dan kirim form-nya lagi (jangan terlalu lama didiamkan).',
        'Pastikan cookie tidak diblokir untuk situs ini (Pengaturan browser → Cookie), lalu coba lagi.',
        'Kalau tetap muncul, tutup tab ini dan buka alamat Club 61 dari awal di tab baru.',
    ],
    'actions' => ['reload', 'home'],
])
