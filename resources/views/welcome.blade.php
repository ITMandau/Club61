<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CLUB 61 - Padel Court Medan</title>
    <link rel="icon" type="image/png" href="{{ asset('images/club61-logo.png') }}">
    <!-- Luxury Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Alex+Brush&family=Cinzel:wght@600;700;800;900&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans antialiased text-[#1F170D] bg-[#FBF9F5] selection:bg-[#D4AF37] selection:text-[#1E160A] relative overflow-x-hidden flex flex-col justify-between"
      style="background-image: url('{{ asset('images/white-gold-marble.jpg') }}'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;">

    <!-- Ambient Warm Gold Luxury Lighting -->
    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
        <div class="absolute -top-32 left-1/4 w-[850px] h-[550px] bg-gradient-to-b from-amber-300/20 via-yellow-500/10 to-transparent blur-3xl rounded-full"></div>
        <div class="absolute -bottom-32 right-1/4 w-[700px] h-[500px] bg-[#D4AF37]/15 blur-3xl rounded-full"></div>
    </div>

    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-50 bg-white/85 backdrop-blur-xl border-b border-[#D4AF37]/40 px-6 sm:px-10 py-4 flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/club61-logo.png') }}" alt="Club 61 Padel Court" class="w-11 h-11 object-contain rounded-2xl shadow-sm border border-[#E5C378] bg-white p-0.5">
            <div>
                <div class="font-serif text-xl sm:text-2xl font-black tracking-[0.25em] text-[#1F170D] uppercase">
                    CLUB 61
                </div>
                <div class="text-[9px] tracking-[0.35em] text-[#8C6418] font-bold uppercase -mt-0.5">
                    PADEL COURT
                </div>
            </div>
        </div>

        <nav class="flex items-center gap-3 sm:gap-4">
            @auth
                <a href="{{ url('/dashboard') }}" 
                   class="px-5 py-2.5 rounded-full text-xs font-black uppercase tracking-wider transition-all shadow-md transform active:scale-95"
                   style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1.5px solid #FBF0CE; color: #281A05; box-shadow: 0 4px 15px rgba(184, 134, 11, 0.35);">
                    Buka Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" 
                   class="px-5 py-2.5 rounded-full text-xs font-black uppercase tracking-wider transition-all shadow-md transform active:scale-95"
                   style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1.5px solid #FBF0CE; color: #281A05; box-shadow: 0 4px 15px rgba(184, 134, 11, 0.35);">
                    Masuk ke Club
                </a>
                <a href="{{ route('register') }}" 
                   class="hidden sm:inline-flex px-5 py-2.5 rounded-full text-xs font-bold uppercase tracking-wider transition-all shadow-sm"
                   style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #C59B46; color: #5C410F;">
                    Daftar Member
                </a>
            @endauth
        </nav>
    </header>

    <!-- Main Hero Landing -->
    <main class="flex-1 flex flex-col justify-center relative z-10 px-6 sm:px-10 py-12 lg:py-20">
        <div class="max-w-4xl mx-auto text-center space-y-6">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-widest shadow-sm"
                 style="background: #FAF2DE; border: 1.5px solid #D9BE84; color: #7A5818;">
                <span class="w-2 h-2 rounded-full animate-pulse" style="background-color: #D4AF37; box-shadow: 0 0 8px #D4AF37;"></span>
                <span>Medan Flagship Venue &bull; Gedung Indosat &bull; Open Daily</span>
            </div>

            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold text-[#1F170D] tracking-tight leading-[1.1] drop-shadow-sm font-serif">
                The Sanctuary for <br />
                <span class="italic font-normal" style="color: #8C6418; text-shadow: 0 0 25px rgba(212,175,55,0.4);">Padel Athletes</span> in Medan.
            </h1>

            <p class="text-sm sm:text-lg text-[#5A4523] max-w-2xl mx-auto leading-relaxed font-medium">
                Fasilitas terpadu berstandar internasional di Gedung Indosat Medan: 4 Lapangan Padel Panoramic ber-AC, Thermal Wellness Recovery (Sauna &amp; Ice Bath 4&deg;C), Specialty Artisan Lounge, dan Sistem POS Kasir terintegrasi.
            </p>

            <!-- CTA Action Buttons -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
                @auth
                    <a href="{{ route('dashboard') }}" 
                       class="w-full sm:w-auto px-8 py-4 rounded-full font-black text-sm uppercase tracking-wider transition-all transform active:scale-95 shadow-xl flex items-center justify-center gap-2 cursor-pointer"
                       style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1.5px solid #FBF0CE; color: #281A05; box-shadow: 0 10px 30px rgba(184, 134, 11, 0.4);">
                        <span>Buka Member Dashboard</span>
                        <svg class="w-4 h-4 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>

                    <form method="POST" action="{{ route('logout') }}" class="w-full sm:w-auto">
                        @csrf
                        <button type="submit" 
                                class="w-full sm:w-auto px-8 py-4 rounded-full font-bold text-sm uppercase tracking-wider transition-all shadow-md backdrop-blur-md cursor-pointer text-rose-700 bg-rose-50/80 hover:bg-rose-100 border border-rose-200">
                            Keluar / Logout
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" 
                       class="w-full sm:w-auto px-8 py-4 rounded-full font-black text-sm uppercase tracking-wider transition-all transform active:scale-95 shadow-xl flex items-center justify-center gap-2 cursor-pointer"
                       style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1.5px solid #FBF0CE; color: #281A05; box-shadow: 0 10px 30px rgba(184, 134, 11, 0.4);">
                        <span>Masuk ke Club Portal</span>
                        <svg class="w-4 h-4 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>

                    <a href="{{ route('register') }}" 
                       class="w-full sm:w-auto px-8 py-4 rounded-full font-bold text-sm uppercase tracking-wider transition-all shadow-md backdrop-blur-md cursor-pointer"
                       style="background: rgba(255, 255, 255, 0.92); border: 1.5px solid #C59B46; color: #5C410F;">
                        Gabung Membership VIP
                    </a>
                @endauth
            </div>

            <!-- 4 Feature Highlights Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-8 max-w-3xl mx-auto text-left">
                <div class="p-4 rounded-2xl backdrop-blur-md shadow-md hover:-translate-y-0.5 transition-all"
                     style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #DFC387; box-shadow: 0 10px 25px -10px rgba(160, 120, 30, 0.15);">
                    <div class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase mb-1">ARENA</div>
                    <div class="font-extrabold text-xs text-[#1F170D]">4 Lapangan Padel</div>
                    <div class="text-[10px] text-[#7A5818] font-medium">Panoramic Glass Arena</div>
                </div>
                <div class="p-4 rounded-2xl backdrop-blur-md shadow-md hover:-translate-y-0.5 transition-all"
                     style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #DFC387; box-shadow: 0 10px 25px -10px rgba(160, 120, 30, 0.15);">
                    <div class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase mb-1">WELLNESS</div>
                    <div class="font-extrabold text-xs text-[#1F170D]">Thermal Wellness</div>
                    <div class="text-[10px] text-[#7A5818] font-medium">Ice Bath &amp; Sauna</div>
                </div>
                <div class="p-4 rounded-2xl backdrop-blur-md shadow-md hover:-translate-y-0.5 transition-all"
                     style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #DFC387; box-shadow: 0 10px 25px -10px rgba(160, 120, 30, 0.15);">
                    <div class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase mb-1">CAFE</div>
                    <div class="font-extrabold text-xs text-[#1F170D]">Artisan Lounge</div>
                    <div class="text-[10px] text-[#7A5818] font-medium">Specialty Coffee &amp; Bar</div>
                </div>
                <div class="p-4 rounded-2xl backdrop-blur-md shadow-md hover:-translate-y-0.5 transition-all"
                     style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #DFC387; box-shadow: 0 10px 25px -10px rgba(160, 120, 30, 0.15);">
                    <div class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase mb-1">GATE</div>
                    <div class="font-extrabold text-xs text-[#1F170D]">Frontdesk POS</div>
                    <div class="text-[10px] text-[#7A5818] font-medium">Split Bill &amp; KDS Sync</div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bottom Footer -->
    <footer class="relative z-10 bg-white/75 backdrop-blur-md border-t border-[#D4AF37]/30 px-6 py-4 text-center text-xs text-[#7A643E]">
        &copy; {{ date('Y') }} Club 61 Padel Court. Gedung Indosat, Jl. Perintis Kemerdekaan No. 39, Medan, Sumatera Utara.
    </footer>

</body>
</html>