<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CLUB 61 KDS - Kitchen &amp; Bar Display</title>
    <link rel="icon" type="image/png" href="{{ asset('images/club61-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@87.5..125,400..800&family=Source+Serif+4:opsz,wght@8..60,400..700&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{--
    Layar dapur (KDS) belum dibangun. Dulu halaman ini berisi tiket contoh yang tertulis di kode (#TKT-041 "Smashed
    Avocado Toast", meja 01…) dan tombol "Sajikan" palsu, padahal ini beranda akun dapur. Pesanan dapur saat ini
    tetap berjalan lewat slip pesanan yang dicetak kasir F&B ke printer tiap stasiun.
--}}
<body class="min-h-full font-brand antialiased text-[#4F2F2A] bg-[#F7F0DB] flex flex-col">

    <header class="bg-[#662721] px-4 sm:px-6 py-3 flex items-center justify-between gap-4 shadow-sm">
        <div class="flex items-center gap-3 min-w-0">
            <img src="{{ asset('images/identity/monogram-cream.png') }}" alt="Club 61" class="w-10 h-10 object-contain shrink-0">
            <div class="min-w-0">
                <div class="font-display font-bold text-[#F7F0DB] text-lg tracking-wide">CLUB 61 KDS</div>
                <div class="text-[11px] text-[#F7F0DB]/70 font-medium -mt-0.5 truncate">Kitchen &amp; Bar Display</div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
                <div class="text-xs font-bold text-[#F7F0DB]">{{ Auth::user()->name }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" title="Keluar"
                        class="p-2 rounded-xl bg-white/10 hover:bg-white/20 border border-[#F7F0DB]/30 text-[#F7F0DB] text-xs font-bold transition-colors flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                    <span class="hidden sm:inline">Keluar</span>
                </button>
            </form>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center px-4 py-10">
        <div data-kds-in-progress class="w-full max-w-xl bg-white rounded-2xl border border-[#E6DAC0] shadow-sm p-6 sm:p-10 text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-[#F7F0DB] border border-[#E6DAC0] text-[#662721] flex items-center justify-center">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z" /></svg>
            </div>
            <span class="inline-flex items-center gap-1.5 mt-5 px-3 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-200">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                Sedang dalam pengerjaan
            </span>
            <h1 class="mt-3 font-display font-bold text-2xl sm:text-3xl text-[#4F2F2A] leading-tight">Layar dapur belum aktif</h1>
            <p class="mt-3 text-sm text-[#7A5A52] leading-relaxed">
                Pesanan dapur &amp; bar untuk sementara tetap lewat <strong class="text-[#4F2F2A]">slip pesanan yang dicetak kasir F&amp;B</strong> ke printer stasiun masing-masing.
            </p>

            <div class="mt-6 pt-5 border-t border-[#E6DAC0] text-left">
                <div class="text-[11px] font-extrabold uppercase tracking-wider text-[#662721]">Yang direncanakan</div>
                <ul class="mt-2 space-y-1.5 text-sm text-[#4F2F2A]">
                    <li class="flex gap-2"><span class="mt-2 w-1.5 h-1.5 rounded-full bg-[#662721]/60 shrink-0"></span>Pesanan F&amp;B lunas langsung tampil per stasiun (dapur, bar kopi).</li>
                    <li class="flex gap-2"><span class="mt-2 w-1.5 h-1.5 rounded-full bg-[#662721]/60 shrink-0"></span>Tandai pesanan diproses / siap disajikan.</li>
                    <li class="flex gap-2"><span class="mt-2 w-1.5 h-1.5 rounded-full bg-[#662721]/60 shrink-0"></span>Status pesanan terlihat di kasir.</li>
                </ul>
            </div>
        </div>
    </main>
</body>
</html>
