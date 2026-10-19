{{--
    Halaman admin yang modulnya belum dibangun. Dulu halaman-halaman ini berisi data contoh yang tertulis di kode
    (nama coach, kode voucher, turnamen, fasilitas) seolah-olah data asli — sekarang tampil jujur sebagai "Segera Hadir".

    @include('filament.partials.c61-coming-soon', [
        'eyebrow' => 'Modul …', 'title' => '…', 'sub' => '…',
        'planned' => ['Fitur yang direncanakan', …],
        'links'   => [['label' => '…', 'sub' => '…', 'url' => '…'], …],   // halaman yang SUDAH jalan (boleh kosong)
    ])
--}}
<div class="c61">
    @include('filament.partials.c61-admin-style')
    <style>
        .cs-wrap { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 1.25rem; align-items: start; }
        @media (max-width: 1023px) { .cs-wrap { grid-template-columns: minmax(0, 1fr); } }
        .cs-hero { text-align: center; padding: 2.75rem 1.5rem; }
        .cs-icon { width: 64px; height: 64px; margin: 0 auto; border-radius: 18px; background: var(--c-cream); border: 1px solid var(--c-line); color: var(--c-terra); display: flex; align-items: center; justify-content: center; }
        .cs-chip { display: inline-flex; align-items: center; gap: 0.4rem; margin-top: 1.1rem; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.6875rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
        .cs-chip .dot { width: 6px; height: 6px; border-radius: 999px; background: #D97706; }
        .cs-title { margin-top: 0.75rem; font-family: var(--font-serif); font-size: 1.5rem; font-weight: 600; color: var(--c-brown); line-height: 1.25; }
        .cs-text { margin: 0.5rem auto 0; max-width: 34rem; font-size: 0.8125rem; line-height: 1.6; color: var(--c-muted); }
        .cs-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.6rem; }
        .cs-list li { display: flex; gap: 0.6rem; align-items: flex-start; font-size: 0.8125rem; line-height: 1.5; color: var(--c-brown); }
        .cs-list li::before { content: ''; flex-shrink: 0; width: 7px; height: 7px; margin-top: 0.45rem; border-radius: 999px; background: var(--c-terra); opacity: 0.55; }
        .cs-link { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.8rem 0.9rem; border-radius: 12px; border: 1px solid var(--c-line); background: #FFFFFF; text-decoration: none; transition: border-color .15s, background .15s; }
        .cs-link:hover { border-color: var(--c-terra); background: var(--c-cream); }
        .cs-link-label { font-size: 0.8125rem; font-weight: 700; color: var(--c-brown); }
        .cs-link-sub { font-size: 0.6875rem; color: var(--c-muted); margin-top: 0.1rem; }
        .cs-link svg { flex-shrink: 0; width: 16px; height: 16px; color: var(--c-terra); }
    </style>

    <div class="c61-hero">
        <div>
            <div class="c61-eyebrow"><span class="dot"></span>{{ $eyebrow }}</div>
            <div class="c61-hero-title">{{ $title }}</div>
            <div class="c61-hero-sub">{{ $sub }}</div>
        </div>
    </div>

    <div class="cs-wrap">
        <div class="c61-card">
            <div class="cs-hero">
                <div class="cs-icon">
                    <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="cs-chip"><span class="dot"></span>Segera Hadir</span>
                <div class="cs-title">Modul ini sedang disiapkan</div>
                <p class="cs-text">Belum ada data yang bisa dikelola di halaman ini. Semua yang tampil nanti akan diambil dari data asli Club 61 — bukan contoh.</p>
            </div>
        </div>

        <div style="display: grid; gap: 1.25rem;">
            <div class="c61-card">
                <div class="c61-card-head"><div><div class="c61-card-title">Yang direncanakan</div><div class="c61-card-sub">Gambaran isi modul — bisa berubah.</div></div></div>
                <div class="c61-card-body">
                    <ul class="cs-list">
                        @foreach ($planned as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            @if (! empty($links))
                <div class="c61-card">
                    <div class="c61-card-head"><div><div class="c61-card-title">Sudah bisa dipakai sekarang</div><div class="c61-card-sub">Fungsi terkait yang sudah berjalan.</div></div></div>
                    <div class="c61-card-body" style="display: grid; gap: 0.6rem;">
                        @foreach ($links as $link)
                            <a href="{{ $link['url'] }}" class="cs-link">
                                <div>
                                    <div class="cs-link-label">{{ $link['label'] }}</div>
                                    <div class="cs-link-sub">{{ $link['sub'] }}</div>
                                </div>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
