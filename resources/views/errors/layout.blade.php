{{--
    Kerangka halaman error Club 61 (403, 404, 419, 429, 500, 503, …). Tiap halaman menjelaskan KENAPA error terjadi dan
    APA yang bisa dilakukan user. Sengaja berdiri sendiri: CSS inline, tanpa @vite / query database / cek login —
    halaman ini juga dipakai saat build aset rusak atau database mati, jadi tidak boleh ikut error.

    Variabel: $code, $title, $message, $steps (array), $actions (array: back | reload | home | login), $note (opsional).
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#662721">
    <title>{{ $title }} &middot; Club 61 Padel Court</title>
    <link rel="icon" type="image/png" href="{{ asset('images/identity/monogram-terracotta.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap" rel="stylesheet">
    <style>
        :root { --terra: #662721; --terra-dark: #511D18; --brown: #4F2F2A; --cream: #F7F0DB; --paper: #FCF8EE; --line: #E6DAC0; --muted: #7A5A52; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { background: var(--cream); color: var(--brown); font-family: 'Archivo', system-ui, -apple-system, 'Segoe UI', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px 16px; -webkit-font-smoothing: antialiased; }
        .card { width: 100%; max-width: 560px; background: var(--paper); border: 1px solid var(--line); border-radius: 20px; box-shadow: 0 24px 60px -28px rgba(79, 47, 42, .45); overflow: hidden; }
        .band { background: var(--terra); color: var(--cream); padding: 22px 28px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .band img { height: 44px; width: auto; display: block; }
        .code { font-family: 'Source Serif 4', Georgia, serif; font-size: 44px; font-weight: 700; line-height: 1; opacity: .9; letter-spacing: .02em; }
        .body { padding: 28px; }
        h1 { font-family: 'Source Serif 4', Georgia, serif; font-size: 28px; line-height: 1.2; font-weight: 700; margin: 0 0 10px; color: var(--brown); }
        .lead { font-size: 15px; line-height: 1.6; color: var(--muted); margin: 0; }
        .steps { margin: 22px 0 0; padding: 16px 18px; background: #fff; border: 1px solid var(--line); border-radius: 14px; }
        .steps-title { font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: var(--terra); margin: 0 0 10px; }
        .steps ol { margin: 0; padding-left: 20px; }
        .steps li { font-size: 14px; line-height: 1.55; margin: 6px 0; }
        .note { margin-top: 14px; font-size: 13px; line-height: 1.5; color: var(--muted); }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 46px; padding: 0 20px; border-radius: 12px; font: 700 14px 'Archivo', system-ui, sans-serif; text-decoration: none; cursor: pointer; border: 1px solid var(--line); background: #fff; color: var(--brown); transition: border-color .15s, background .15s; }
        .btn:hover { border-color: var(--terra); }
        .btn.primary { background: var(--terra); border-color: var(--terra); color: var(--cream); }
        .btn.primary:hover { background: var(--terra-dark); }
        .foot { padding: 14px 28px; border-top: 1px solid var(--line); font-size: 12px; color: var(--muted); display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        @media (max-width: 480px) {
            .band { padding: 18px 20px; } .body { padding: 22px 20px; } .foot { padding: 12px 20px; }
            h1 { font-size: 24px; } .code { font-size: 36px; }
            .actions .btn { flex: 1 1 100%; }
        }
    </style>
</head>
<body>
    <main class="card" role="main">
        <div class="band">
            <img src="{{ asset('images/identity/logo-cream.png') }}" alt="Club 61 Padel Court">
            <span class="code" aria-label="Kode error {{ $code }}">{{ $code }}</span>
        </div>
        <div class="body">
            <h1>{{ $title }}</h1>
            <p class="lead">{{ $message }}</p>

            @if (! empty($steps))
                <div class="steps">
                    <p class="steps-title">Yang bisa Anda lakukan</p>
                    <ol>
                        @foreach ($steps as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                </div>
            @endif

            @if (! empty($note))
                <p class="note">{{ $note }}</p>
            @endif

            <div class="actions">
                @foreach ($actions ?? ['back', 'home'] as $i => $action)
                    @php $cls = $i === 0 ? 'btn primary' : 'btn'; @endphp
                    @if ($action === 'reload')
                        <button type="button" class="{{ $cls }}" onclick="window.location.reload()">Muat Ulang Halaman</button>
                    @elseif ($action === 'back')
                        <button type="button" class="{{ $cls }}" onclick="if (history.length > 1) { history.back(); } else { window.location.href = '{{ url('/') }}'; }">&larr; Kembali</button>
                    @elseif ($action === 'login')
                        <a class="{{ $cls }}" href="{{ url('/login') }}">Masuk ke Akun</a>
                    @else
                        <a class="{{ $cls }}" href="{{ url('/') }}">Ke Beranda</a>
                    @endif
                @endforeach
            </div>
        </div>
        <div class="foot">
            <span>Masih bermasalah? Hubungi front desk / admin Club 61 dan sebutkan kode <strong>{{ $code }}</strong>.</span>
            <span>{{ now('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</span>
        </div>
    </main>
</body>
</html>
