<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VANTAGE - Racquet & Social Club</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Alex+Brush&family=Cinzel:wght@600;700;800;900&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans antialiased text-slate-100 bg-[#04160F] selection:bg-[#CCFF00] selection:text-[#04160F] flex flex-col justify-between">

    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-50 bg-[#061E15]/90 backdrop-blur-xl border-b border-emerald-600/30 px-6 sm:px-10 py-4 flex items-center justify-between shadow-2xl">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-[#092B20] border border-[#CCFF00]/40 flex items-center justify-center text-[#CCFF00] shadow-md">
                <svg class="w-6 h-6 text-[#CCFF00]" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="9" stroke-width="2" />
                    <path d="M12 3a9 9 0 0 1 9 9" stroke-width="2.5" stroke-linecap="round" />
                    <path d="M7 10l5 5 5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>
            <div>
                <div class="font-serif text-xl sm:text-2xl font-black tracking-[0.25em] text-white uppercase">
                    VANTAGE
                </div>
                <div class="text-[9px] tracking-[0.35em] text-emerald-300 font-semibold uppercase -mt-0.5">
                    RACQUET &amp; SOCIAL CLUB
                </div>
            </div>
        </div>

        <nav class="flex items-center gap-3 sm:gap-4">
            @auth
                <a href="{{ url('/dashboard') }}" class="px-5 py-2.5 rounded-full bg-[#CCFF00] hover:bg-[#d8ff33] text-[#051811] text-xs font-extrabold uppercase tracking-wider transition-all shadow-md shadow-lime-400/20">
                    Buka Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="px-5 py-2.5 rounded-full bg-[#CCFF00] hover:bg-[#d8ff33] text-[#051811] text-xs font-extrabold uppercase tracking-wider transition-all shadow-md shadow-lime-400/20">
                    Masuk ke Club
                </a>
                <a href="{{ route('register') }}" class="hidden sm:inline-flex px-5 py-2.5 rounded-full bg-white/[0.08] hover:bg-white/[0.15] border border-emerald-500/30 text-white text-xs font-bold uppercase tracking-wider transition-all">
                    Daftar Member
                </a>
            @endauth
        </nav>
    </header>

    <!-- Main Hero Landing -->
    <main class="flex-1 flex flex-col justify-center relative overflow-hidden px-6 sm:px-10 py-12 lg:py-20">
        <!-- Background Image with Dark Emerald Luxury Overlay -->
        <div class="absolute inset-0 z-0">
            <img src="{{ asset('images/club-hero.jpg') }}" alt="Vantage Club Court" class="w-full h-full object-cover object-center scale-105" />
            <div class="absolute inset-0 bg-gradient-to-t from-[#04160F] via-[#04160F]/85 to-[#06261A]/75"></div>
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,#0F4C38_0%,transparent_70%)] opacity-60"></div>
        </div>

        <!-- Ambient Glow -->
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[700px] h-[500px] bg-emerald-600/15 blur-3xl rounded-full pointer-events-none"></div>

        <div class="relative z-10 max-w-4xl mx-auto text-center space-y-6">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-black/40 backdrop-blur-md border border-[#CCFF00]/40 text-xs text-[#CCFF00] font-bold uppercase tracking-widest">
                <span class="w-2 h-2 rounded-full bg-[#CCFF00] animate-ping"></span>
                <span>Jakarta Selatan Flagship Venue &bull; Live &amp; Open Daily</span>
            </div>

            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold text-white tracking-tight leading-[1.1] drop-shadow-2xl">
                The Sanctuary for <br />
                <span class="italic font-serif text-[#CCFF00] font-normal drop-shadow-[0_4px_20px_rgba(204,255,0,0.35)]">Modern Racquet</span> Athletes.
            </h1>

            <p class="text-sm sm:text-lg text-emerald-100/80 max-w-2xl mx-auto leading-relaxed drop-shadow">
                Fasilitas terpadu berstandar internasional: 4 Lapangan Padel Panoramic ber-AC, Thermal Wellness Recovery (Sauna &amp; Ice Bath 4&deg;C), Specialty Artisan Lounge, dan Sistem POS Kasir terintegrasi.
            </p>

            <!-- CTA Action Buttons -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
                <a href="{{ route('login') }}" class="w-full sm:w-auto px-8 py-4 rounded-full bg-[#CCFF00] hover:bg-[#d8ff33] text-[#051811] font-black text-sm uppercase tracking-wider transition-all transform active:scale-95 shadow-xl shadow-lime-400/25 flex items-center justify-center gap-2">
                    <span>Masuk ke Club Portal</span>
                    <svg class="w-4 h-4 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>

                <a href="{{ route('register') }}" class="w-full sm:w-auto px-8 py-4 rounded-full bg-white/10 hover:bg-white/20 border border-white/20 text-white font-bold text-sm uppercase tracking-wider backdrop-blur-md transition-all">
                    Gabung Membership VIP
                </a>
            </div>

            <!-- 4 Feature Highlights Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-8 max-w-3xl mx-auto text-left">
                <div class="p-4 rounded-2xl bg-black/40 backdrop-blur-md border border-emerald-500/20">
                    <div class="text-[#CCFF00] text-xl mb-1">🎾</div>
                    <div class="font-extrabold text-xs text-white">4 Lapangan Padel</div>
                    <div class="text-[10px] text-emerald-300/70">Panoramic Glass Arena</div>
                </div>
                <div class="p-4 rounded-2xl bg-black/40 backdrop-blur-md border border-emerald-500/20">
                    <div class="text-[#CCFF00] text-xl mb-1">❄️</div>
                    <div class="font-extrabold text-xs text-white">Thermal Wellness</div>
                    <div class="text-[10px] text-emerald-300/70">Ice Bath &amp; Sauna</div>
                </div>
                <div class="p-4 rounded-2xl bg-black/40 backdrop-blur-md border border-emerald-500/20">
                    <div class="text-[#CCFF00] text-xl mb-1">☕</div>
                    <div class="font-extrabold text-xs text-white">Artisan Lounge</div>
                    <div class="text-[10px] text-emerald-300/70">Specialty Coffee &amp; Bar</div>
                </div>
                <div class="p-4 rounded-2xl bg-black/40 backdrop-blur-md border border-emerald-500/20">
                    <div class="text-[#CCFF00] text-xl mb-1">💳</div>
                    <div class="font-extrabold text-xs text-white">Frontdesk POS</div>
                    <div class="text-[10px] text-emerald-300/70">Split Bill &amp; KDS Sync</div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bottom Footer -->
    <footer class="bg-[#03110C] border-t border-emerald-800/40 px-6 py-4 text-center text-xs text-emerald-400/50">
        &copy; {{ date('Y') }} VANTAGE Racquet &amp; Social Club. Powered by Laravel 11 &amp; Sanctum.
    </footer>

</body>
</html>