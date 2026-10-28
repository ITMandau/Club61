{{--
    Pembaca panduan (GuideController): halaman berupa gambar ber-watermark dari server — PDF aslinya tidak pernah
    dikirim. Penghalang di bawah (klik kanan, seleksi, simpan, cetak, layar buram saat pindah tab) hanya mempersulit;
    yang benar-benar melindungi: file asli tidak keluar & watermark tanggal-jam + IP menempel di setiap gambar.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>{{ $guide->title }} · Panduan Club 61</title>
    <link rel="icon" href="{{ asset('images/identity/favicon.png') }}?v=2">
    <style>
        :root { --terra: #662721; --terra-dark: #3F1713; --cream: #F5EEE4; --ink: #231815; --muted: #B9A89D; }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #2B211E; color: var(--cream); font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { -webkit-user-select: none; user-select: none; -webkit-touch-callout: none; }

        .bar { position: sticky; top: 0; z-index: 20; display: flex; align-items: center; gap: 14px; padding: 10px 16px; background: var(--terra-dark); border-bottom: 1px solid rgba(245, 238, 228, .12); }
        .bar img { height: 34px; width: auto; flex: none; }
        .bar .meta { min-width: 0; flex: 1; }
        .bar .title { font-size: 15px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bar .sub { margin-top: 2px; font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bar .count { flex: none; padding: 5px 10px; border-radius: 999px; background: rgba(245, 238, 228, .1); font-size: 12px; font-weight: 600; font-variant-numeric: tabular-nums; }
        .preview { padding: 8px 16px; background: #8A5A12; color: #FFF7E6; font-size: 13px; text-align: center; }

        main { max-width: 920px; margin: 0 auto; padding: 20px 16px 8px; }
        .page { position: relative; margin: 0 auto 16px; background: #FFFFFF; border-radius: 4px; box-shadow: 0 6px 24px rgba(0, 0, 0, .35); overflow: hidden; min-height: 240px; }
        .page img { display: block; width: 100%; height: auto; pointer-events: none; -webkit-user-drag: none; }
        /* Lapisan transparen di atas gambar: klik kanan / tekan lama mengenai lapisan ini, bukan gambarnya. */
        .page::after { content: ""; position: absolute; inset: 0; }
        .page .num { position: absolute; right: 8px; bottom: 8px; z-index: 1; padding: 2px 8px; border-radius: 999px; background: rgba(35, 24, 21, .55); color: #FFF; font-size: 11px; }

        .note { max-width: 920px; margin: 0 auto; padding: 4px 16px 28px; text-align: center; font-size: 12px; line-height: 1.6; color: var(--muted); }

        /* Pindah tab / jendela kehilangan fokus / PrintScreen → isi diburamkan. */
        body.shield main { filter: blur(22px); }
        .shield-msg { display: none; position: fixed; inset: 0; z-index: 30; align-items: center; justify-content: center; padding: 16px; text-align: center; font-size: 15px; font-weight: 600; }
        body.shield .shield-msg { display: flex; }

        .gone { max-width: 460px; margin: 18vh auto 0; padding: 0 16px; text-align: center; }
        .gone h1 { margin: 0 0 8px; font-size: 20px; }
        .gone p { margin: 0; color: var(--muted); line-height: 1.6; }

        @media print { body * { display: none !important; } body::before { content: "Panduan ini tidak bisa dicetak."; display: block; padding: 40px; color: #000; } }
    </style>
</head>
<body oncontextmenu="return false">
    <div class="bar">
        <img src="{{ asset('images/identity/logo-cream.png') }}?v=2" alt="Club 61">
        <div class="meta">
            <div class="title">{{ $guide->title }}</div>
            <div class="sub">Panduan internal{{ $guide->version ? ' · '.$guide->version : '' }} · {{ $guide->pages_count }} halaman</div>
        </div>
        <div class="count" id="count">1 / {{ $guide->pages_count }}</div>
    </div>
    @if ($isPreview)
        <div class="preview">Pratinjau admin — {{ $guide->is_active ? 'panduan ini AKTIF, link bisa dibuka.' : 'panduan ini masih NONAKTIF, link belum bisa dibuka.' }}</div>
    @endif

    <main id="pages">
        @for ($i = 1; $i <= $guide->pages_count; $i++)
            <div class="page" data-page="{{ $i }}">
                <img src="{{ $pageUrl($i) }}" alt="Halaman {{ $i }}" loading="{{ $i <= 2 ? 'eager' : 'lazy' }}" decoding="async" draggable="false">
                <span class="num">{{ $i }}</span>
            </div>
        @endfor
    </main>
    <p class="note">Dokumen internal Club 61 — hanya untuk dibaca, dilarang disalin atau disebarkan.<br>Setiap halaman memuat tanda waktu &amp; alamat IP pembuka, dan setiap pembukaan link dicatat.</p>

    <div class="shield-msg">Konten disembunyikan. Klik halaman ini untuk melanjutkan membaca.</div>

    <script>
        (function () {
            var body = document.body;
            var block = function (e) { e.preventDefault(); return false; };
            ['contextmenu', 'dragstart', 'selectstart', 'copy', 'cut'].forEach(function (type) { document.addEventListener(type, block); });

            document.addEventListener('keydown', function (e) {
                var key = (e.key || '').toLowerCase();
                var mod = e.ctrlKey || e.metaKey;
                if ((mod && ['s', 'p', 'u', 'c', 'a'].indexOf(key) !== -1) || key === 'f12' || (mod && e.shiftKey && ['i', 'j', 'c', 's'].indexOf(key) !== -1)) {
                    e.preventDefault();
                }
                if (key === 'printscreen') { body.classList.add('shield'); }
            });
            document.addEventListener('keyup', function (e) {
                if ((e.key || '').toLowerCase() === 'printscreen') {
                    body.classList.add('shield');
                    try { navigator.clipboard && navigator.clipboard.writeText(''); } catch (err) {}
                }
            });
            window.addEventListener('blur', function () { body.classList.add('shield'); });
            document.addEventListener('visibilitychange', function () { if (document.hidden) { body.classList.add('shield'); } });
            window.addEventListener('focus', function () { setTimeout(function () { body.classList.remove('shield'); }, 300); });
            document.addEventListener('click', function () { if (document.hasFocus()) { body.classList.remove('shield'); } });

            // Nomor halaman yang sedang dibaca.
            var count = document.getElementById('count');
            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) { count.textContent = entry.target.dataset.page + ' / {{ $guide->pages_count }}'; }
                    });
                }, { rootMargin: '-45% 0px -45% 0px' });
                document.querySelectorAll('.page').forEach(function (page) { observer.observe(page); });
            }

            @if ($statusUrl)
                // Panduan dinonaktifkan / link dibuat ulang saat sedang dibuka → isi langsung ditutup.
                var close = function () {
                    document.getElementById('pages').innerHTML = '<div class="gone"><h1>Panduan tidak tersedia</h1><p>Link ini sudah dinonaktifkan atau diganti. Minta link terbaru ke admin.</p></div>';
                    count.textContent = '–';
                };
                setInterval(function () {
                    fetch(@json($statusUrl), { cache: 'no-store', credentials: 'omit' })
                        .then(function (res) { if (res.status === 404) { close(); } })
                        .catch(function () {});
                }, 30000);
            @endif
        })();
    </script>
</body>
</html>
