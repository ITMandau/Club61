<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VANTAGE POS - Frontdesk &amp; Cashier Terminal</title>
    <!-- Google Fonts: Luxury Serif, Athletic Sans, & Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700;800;900&family=JetBrains+Mono:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased text-[#1F170D] bg-[#FBF9F5] selection:bg-[#D4AF37] selection:text-[#1E160A] overflow-hidden flex flex-col relative"
      style="background-image: url('{{ asset('images/white-gold-marble.jpg') }}'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;">

    <!-- Ambient Warm Gold Luxury Lighting -->
    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
        <div class="absolute -top-32 left-1/4 w-[850px] h-[550px] bg-gradient-to-b from-amber-300/20 via-yellow-500/10 to-transparent blur-3xl rounded-full"></div>
        <div class="absolute -bottom-32 right-1/4 w-[700px] h-[500px] bg-[#D4AF37]/15 blur-3xl rounded-full"></div>
    </div>

    <!-- Top POS Navigation Header -->
    <header class="relative z-20 bg-white/90 backdrop-blur-xl border-b border-[#D4AF37]/50 px-5 py-3 flex items-center justify-between shadow-sm shrink-0">
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center font-black text-lg shadow-sm"
                     style="background: linear-gradient(135deg, #2D210F 0%, #171006 100%); border: 1.5px solid #E5C378; color: #E5C378;">
                    VP
                </div>
                <div>
                    <div class="font-serif font-black text-[#1F170D] text-base tracking-wider flex items-center gap-2">
                        <span>VANTAGE POS</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-widest shadow-sm"
                              style="background: #FAF2DE; border: 1px solid #D9BE84; color: #7A5818;">
                            Terminal 01
                        </span>
                    </div>
                    <div class="text-[11px] text-[#7A643E] font-medium -mt-0.5">Frontdesk, Sewa Alat &amp; Cafe Cashier</div>
                </div>
            </div>

            <!-- Quick Status Badge -->
            <div class="hidden md:flex items-center gap-2 pl-4 border-l border-[#DFC387]/60 text-xs font-semibold text-[#5C410F]">
                <span class="w-2 h-2 rounded-full animate-pulse" style="background-color: #D4AF37; box-shadow: 0 0 8px #D4AF37;"></span>
                <span>Kasir Siap &bull; Printer Kasir Terhubung</span>
            </div>
        </div>

        <!-- Cashier Profile & Logout -->
        <div class="flex items-center gap-3">
            <button type="button" 
                    onclick="openPosCheckInModal()" 
                    class="px-3.5 py-1.5 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-sm active:scale-95"
                    style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05; border: 1px solid #FBF0CE; box-shadow: 0 4px 12px rgba(184, 134, 11, 0.25);">
                <span>🎟️ Check-In Tiket</span>
            </button>

            <div class="text-right hidden sm:block">
                <div class="text-xs font-bold text-[#1F170D]">{{ Auth::user()->name ?? 'Kasir Frontdesk POS' }}</div>
                <div class="text-[10px] font-mono font-bold uppercase tracking-wider px-2 py-0.5 rounded-full inline-block mt-0.5 shadow-sm"
                     style="background: #FAF2DE; border: 1px solid #D9BE84; color: #7A5818;">
                    Peran: {{ Auth::user()->role ?? 'CASHIER' }}
                </div>
            </div>

            <!-- Switch / Logout Form -->
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" 
                        class="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-600 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-sm" 
                        title="Keluar">
                    <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span class="hidden sm:inline font-bold">Keluar</span>
                </button>
            </form>
        </div>
    </header>

    <!-- Main POS Interface (Split-screen) -->
    <main class="relative z-10 flex-1 flex overflow-hidden">

        <!-- LEFT PANEL: Product Catalog, Equipment, & F&B (65% Width) -->
        <section class="flex-1 flex flex-col border-r border-[#D4AF37]/50 overflow-hidden"
                 style="background: rgba(255, 255, 255, 0.72); backdrop-filter: blur(12px);">
            
            <!-- Category Tabs & Quick Search -->
            <div class="p-4 border-b border-[#DFC387]/60 flex flex-wrap items-center justify-between gap-3 bg-white/80">
                <!-- Category Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 max-w-full">
                    <button class="px-3.5 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all shadow-md transform active:scale-95 whitespace-nowrap cursor-pointer"
                            style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1px solid #FBF0CE; color: #281A05; box-shadow: 0 4px 12px rgba(184, 134, 11, 0.3);">
                        Semua Menu
                    </button>
                    <button class="px-3.5 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all shadow-sm hover:bg-white whitespace-nowrap cursor-pointer"
                            style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #DFC387; color: #5C410F;">
                        ☕ Coffee &amp; Drinks
                    </button>
                    <button class="px-3.5 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all shadow-sm hover:bg-white whitespace-nowrap cursor-pointer"
                            style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #DFC387; color: #5C410F;">
                        🥪 Toast &amp; Meals
                    </button>
                    <button class="px-3.5 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all shadow-sm hover:bg-white whitespace-nowrap cursor-pointer"
                            style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #DFC387; color: #5C410F;">
                        🎾 Sewa Raket &amp; Bola
                    </button>
                    <button class="px-3.5 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all shadow-sm hover:bg-white whitespace-nowrap cursor-pointer"
                            style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #DFC387; color: #5C410F;">
                        👕 Jersey &amp; Merch
                    </button>
                </div>

                <!-- Fast Search Input -->
                <div class="w-full sm:w-64 relative">
                    <input type="text" placeholder="Cari item atau scan barcode..." 
                           class="w-full pl-9 pr-3 py-2 rounded-xl text-xs font-semibold text-[#1C150B] placeholder-[#9E8555] transition-all outline-none"
                           style="background: #FAF5E8; border: 1.5px solid #DFC387;"
                           onfocus="this.style.borderColor='#B8860B'; this.style.boxShadow='0 0 0 3px rgba(212,175,55,0.25)';"
                           onblur="this.style.borderColor='#DFC387'; this.style.boxShadow='none';" />
                    <svg class="w-4 h-4 absolute left-3 top-2.5 pointer-events-none text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="flex-1 overflow-y-auto p-4 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3.5">
                
                <!-- Item 1: Spanish Latte -->
                <div onclick="addToCart('Iced Spanish Latte', 38000, '☕ BAR')" 
                     class="p-3.5 rounded-2xl transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between"
                     style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(253, 249, 240, 0.92) 100%); border: 1.5px solid #DFC387; box-shadow: 0 8px 20px -8px rgba(160, 120, 30, 0.12);">
                    <div>
                        <div class="flex items-center justify-between text-[10px] font-bold mb-1">
                            <span class="text-[#7A5818]">Specialty Coffee</span>
                            <span class="px-1.5 py-0.5 rounded-md font-mono" style="background: #FAF2DE; border: 1px solid #D9BE84; color: #7A5818;">BAR</span>
                        </div>
                        <div class="font-bold text-sm text-[#1F170D] group-hover:text-[#8C6418] transition-colors">Iced Spanish Latte</div>
                        <div class="text-[11px] text-[#6B5738] mt-0.5 line-clamp-2">Espresso double shot with condensed milk and chilled fresh milk.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-[#DFC387]/50 flex items-center justify-between font-mono">
                        <span class="text-xs font-black text-[#8C6418]">Rp 38.000</span>
                        <span class="w-6 h-6 rounded-lg flex items-center justify-center font-bold text-sm shadow-sm transition-all"
                              style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05;">+</span>
                    </div>
                </div>

                <!-- Item 2: Oat Matcha Latte -->
                <div onclick="addToCart('Ceremonial Oat Matcha', 45000, '☕ BAR')" 
                     class="p-3.5 rounded-2xl transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between"
                     style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(253, 249, 240, 0.92) 100%); border: 1.5px solid #DFC387; box-shadow: 0 8px 20px -8px rgba(160, 120, 30, 0.12);">
                    <div>
                        <div class="flex items-center justify-between text-[10px] font-bold mb-1">
                            <span class="text-[#7A5818]">Wellness Drinks</span>
                            <span class="px-1.5 py-0.5 rounded-md font-mono" style="background: #FAF2DE; border: 1px solid #D9BE84; color: #7A5818;">BAR</span>
                        </div>
                        <div class="font-bold text-sm text-[#1F170D] group-hover:text-[#8C6418] transition-colors">Ceremonial Oat Matcha</div>
                        <div class="text-[11px] text-[#6B5738] mt-0.5 line-clamp-2">Stone-ground Uji matcha whisked with Oatside barista milk.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-[#DFC387]/50 flex items-center justify-between font-mono">
                        <span class="text-xs font-black text-[#8C6418]">Rp 45.000</span>
                        <span class="w-6 h-6 rounded-lg flex items-center justify-center font-bold text-sm shadow-sm transition-all"
                              style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05;">+</span>
                    </div>
                </div>

                <!-- Item 3: Avocado Sourdough -->
                <div onclick="addToCart('Smashed Avocado Toast', 55000, '🍳 KITCHEN')" 
                     class="p-3.5 rounded-2xl transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between"
                     style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(253, 249, 240, 0.92) 100%); border: 1.5px solid #DFC387; box-shadow: 0 8px 20px -8px rgba(160, 120, 30, 0.12);">
                    <div>
                        <div class="flex items-center justify-between text-[10px] font-bold mb-1">
                            <span class="text-[#7A5818]">Artisan Bakery</span>
                            <span class="px-1.5 py-0.5 rounded-md font-mono" style="background: #FAF2DE; border: 1px solid #D9BE84; color: #7A5818;">KITCHEN</span>
                        </div>
                        <div class="font-bold text-sm text-[#1F170D] group-hover:text-[#8C6418] transition-colors">Smashed Avocado Toast</div>
                        <div class="text-[11px] text-[#6B5738] mt-0.5 line-clamp-2">Rustic sourdough with poached egg, hass avocado, and feta.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-[#DFC387]/50 flex items-center justify-between font-mono">
                        <span class="text-xs font-black text-[#8C6418]">Rp 55.000</span>
                        <span class="w-6 h-6 rounded-lg flex items-center justify-center font-bold text-sm shadow-sm transition-all"
                              style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05;">+</span>
                    </div>
                </div>

                <!-- Item 4: Babolat Viper Racket -->
                <div onclick="addToCart('Sewa Raket Babolat Viper', 50000, '🎾 RENTAL')" 
                     class="p-3.5 rounded-2xl transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between"
                     style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(253, 249, 240, 0.92) 100%); border: 1.5px solid #DFC387; box-shadow: 0 8px 20px -8px rgba(160, 120, 30, 0.12);">
                    <div>
                        <div class="flex items-center justify-between text-[10px] font-bold mb-1">
                            <span class="text-[#7A5818]">Sewa Raket (1 Jam)</span>
                            <span class="px-1.5 py-0.5 rounded-md font-mono" style="background: #FAF2DE; border: 1px solid #D9BE84; color: #7A5818;">COURT</span>
                        </div>
                        <div class="font-bold text-sm text-[#1F170D] group-hover:text-[#8C6418] transition-colors">Raket Babolat Counter Viper</div>
                        <div class="text-[11px] text-[#6B5738] mt-0.5 line-clamp-2">Raket karbon pro power &amp; toleransi smash tinggi.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-[#DFC387]/50 flex items-center justify-between font-mono">
                        <span class="text-xs font-black text-[#8C6418]">Rp 50.000</span>
                        <span class="w-6 h-6 rounded-lg flex items-center justify-center font-bold text-sm shadow-sm transition-all"
                              style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05;">+</span>
                    </div>
                </div>

                <!-- Item 5: Nox AT10 Genius -->
                <div onclick="addToCart('Sewa Raket Nox AT10 Genius', 65000, '🎾 RENTAL')" 
                     class="p-3.5 rounded-2xl transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between"
                     style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(253, 249, 240, 0.92) 100%); border: 1.5px solid #DFC387; box-shadow: 0 8px 20px -8px rgba(160, 120, 30, 0.12);">
                    <div>
                        <div class="flex items-center justify-between text-[10px] font-bold mb-1">
                            <span class="text-[#7A5818]">Sewa Raket (1 Jam)</span>
                            <span class="px-1.5 py-0.5 rounded-md font-mono" style="background: #FAF2DE; border: 1px solid #D9BE84; color: #7A5818;">COURT</span>
                        </div>
                        <div class="font-bold text-sm text-[#1F170D] group-hover:text-[#8C6418] transition-colors">Raket Nox AT10 Genius 18K</div>
                        <div class="text-[11px] text-[#6B5738] mt-0.5 line-clamp-2">Raket presisi kontrol Agustin Tapia Edition.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-[#DFC387]/50 flex items-center justify-between font-mono">
                        <span class="text-xs font-black text-[#8C6418]">Rp 65.000</span>
                        <span class="w-6 h-6 rounded-lg flex items-center justify-center font-bold text-sm shadow-sm transition-all"
                              style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05;">+</span>
                    </div>
                </div>

                <!-- Item 6: Bola Padel Pro Can -->
                <div onclick="addToCart('Bola Padel Pro (3 Pcs)', 35000, '🎾 PROSHOP')" 
                     class="p-3.5 rounded-2xl transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between"
                     style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(253, 249, 240, 0.92) 100%); border: 1.5px solid #DFC387; box-shadow: 0 8px 20px -8px rgba(160, 120, 30, 0.12);">
                    <div>
                        <div class="flex items-center justify-between text-[10px] font-bold mb-1">
                            <span class="text-[#7A5818]">Bola Padel Resmi</span>
                            <span class="px-1.5 py-0.5 rounded-md font-mono" style="background: #FAF2DE; border: 1px solid #D9BE84; color: #7A5818;">BALL</span>
                        </div>
                        <div class="font-bold text-sm text-[#1F170D] group-hover:text-[#8C6418] transition-colors">Bola Padel Pro (1 Can / 3 Pcs)</div>
                        <div class="text-[11px] text-[#6B5738] mt-0.5 line-clamp-2">Bola tekanan turnamen resmi WPT.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-[#DFC387]/50 flex items-center justify-between font-mono">
                        <span class="text-xs font-black text-[#8C6418]">Rp 35.000</span>
                        <span class="w-6 h-6 rounded-lg flex items-center justify-center font-bold text-sm shadow-sm transition-all"
                              style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05;">+</span>
                    </div>
                </div>

                <!-- Item 7: Club 61 Pro Jersey -->
                <div onclick="addToCart('Club 61 Pro Match Jersey', 299000, '👕 MERCH')" 
                     class="p-3.5 rounded-2xl transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between"
                     style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(253, 249, 240, 0.92) 100%); border: 1.5px solid #DFC387; box-shadow: 0 8px 20px -8px rgba(160, 120, 30, 0.12);">
                    <div>
                        <div class="flex items-center justify-between text-[10px] font-bold mb-1">
                            <span class="text-[#7A5818]">Official Merch</span>
                            <span class="px-1.5 py-0.5 rounded-md font-mono" style="background: #FAF2DE; border: 1px solid #D9BE84; color: #7A5818;">RETAIL</span>
                        </div>
                        <div class="font-bold text-sm text-[#1F170D] group-hover:text-[#8C6418] transition-colors">Pro Match Jersey (Size L)</div>
                        <div class="text-[11px] text-[#6B5738] mt-0.5 line-clamp-2">Aeroready breathable fabric stealth black.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-[#DFC387]/50 flex items-center justify-between font-mono">
                        <span class="text-xs font-black text-[#8C6418]">Rp 299.000</span>
                        <span class="w-6 h-6 rounded-lg flex items-center justify-center font-bold text-sm shadow-sm transition-all"
                              style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05;">+</span>
                    </div>
                </div>

                <!-- Item 8: Ice Bath Session -->
                <div onclick="addToCart('Sesi Cold Plunge (45 Mnt)', 125000, '❄️ WELLNESS')" 
                     class="p-3.5 rounded-2xl transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between"
                     style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(253, 249, 240, 0.92) 100%); border: 1.5px solid #DFC387; box-shadow: 0 8px 20px -8px rgba(160, 120, 30, 0.12);">
                    <div>
                        <div class="flex items-center justify-between text-[10px] font-bold mb-1">
                            <span class="text-[#7A5818]">Thermal Recovery</span>
                            <span class="px-1.5 py-0.5 rounded-md font-mono" style="background: #FAF2DE; border: 1px solid #D9BE84; color: #7A5818;">SPA</span>
                        </div>
                        <div class="font-bold text-sm text-[#1F170D] group-hover:text-[#8C6418] transition-colors">Ice Bath / Cold Plunge</div>
                        <div class="text-[11px] text-[#6B5738] mt-0.5 line-clamp-2">Sesi pemulihan otot air es 4°C selama 45 menit.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-[#DFC387]/50 flex items-center justify-between font-mono">
                        <span class="text-xs font-black text-[#8C6418]">Rp 125.000</span>
                        <span class="w-6 h-6 rounded-lg flex items-center justify-center font-bold text-sm shadow-sm transition-all"
                              style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05;">+</span>
                    </div>
                </div>

            </div>
        </section>

        <!-- RIGHT PANEL: Cart, Order Summary, & Split Bill (35% Width) -->
        <aside class="w-full sm:w-96 lg:w-[420px] flex flex-col justify-between shrink-0 shadow-2xl border-l border-[#D4AF37]/50"
               style="background: rgba(255, 255, 255, 0.94); backdrop-filter: blur(20px);">
            
            <!-- Order Header -->
            <div class="p-4 border-b border-[#DFC387]/60 bg-white/80">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#7A5818] flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span>Pesanan Baru</span>
                    </span>
                    <span class="font-mono text-xs font-bold text-[#8C6418] bg-[#FAF2DE] px-2 py-0.5 rounded-md border border-[#D9BE84]">
                        #ORD-{{ date('His') }}
                    </span>
                </div>
                
                <!-- Table / Customer Selector -->
                <div class="grid grid-cols-2 gap-2 mt-2.5">
                    <select class="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-[#1C150B] outline-none"
                            style="background: #FAF5E8; border: 1.5px solid #DFC387;">
                        <option>🎾 Lapangan 01 (Court 1)</option>
                        <option selected>🎾 Lapangan 02 (Court 2)</option>
                        <option>☕ Meja 01 (Table 01)</option>
                        <option>☕ VIP Lounge 1</option>
                        <option>🛍️ Bawa Pulang (Takeaway)</option>
                    </select>
                    <input type="text" value="Member: Andi Wijaya" 
                           class="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-[#1C150B] outline-none"
                           style="background: #FAF5E8; border: 1.5px solid #DFC387;" />
                </div>
            </div>

            <!-- Cart Items List (Interactive) -->
            <div id="cart-list" class="flex-1 overflow-y-auto p-4 space-y-2.5">
                <!-- Initial Pre-loaded Item 1 -->
                <div class="cart-item p-3 rounded-xl flex items-center justify-between shadow-sm transition-all"
                     style="background: rgba(255, 255, 255, 0.95); border: 1.5px solid #DFC387;">
                    <div class="flex-1 pr-2">
                        <div class="text-xs font-bold text-[#1F170D]">Iced Spanish Latte</div>
                        <div class="text-[10px] text-[#7A5818] font-mono font-medium">Rp 38.000 x 2</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs font-extrabold text-[#8C6418]">Rp 76.000</span>
                    </div>
                </div>

                <!-- Initial Pre-loaded Item 2 -->
                <div class="cart-item p-3 rounded-xl flex items-center justify-between shadow-sm transition-all"
                     style="background: rgba(255, 255, 255, 0.95); border: 1.5px solid #DFC387;">
                    <div class="flex-1 pr-2">
                        <div class="text-xs font-bold text-[#1F170D]">Raket Babolat Counter Viper</div>
                        <div class="text-[10px] text-[#7A5818] font-mono font-medium">Rp 50.000 x 1 (1 Jam)</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs font-extrabold text-[#8C6418]">Rp 50.000</span>
                    </div>
                </div>
            </div>

            <!-- Billing Calculation & Action Buttons -->
            <div class="p-4 border-t border-[#DFC387]/60 bg-white/90 space-y-3">
                <div class="space-y-1.5 text-xs text-[#6B5738] font-mono font-medium">
                    <div class="flex justify-between">
                        <span>Subtotal:</span>
                        <span id="subtotal" class="font-bold text-[#1F170D]">Rp 126.000</span>
                    </div>
                    <div class="flex justify-between text-[11px] text-[#8C7A58]">
                        <span>PB1 Resto &amp; Venue Tax (10%):</span>
                        <span id="tax" class="font-bold">Rp 12.600</span>
                    </div>
                    <div class="flex justify-between text-base font-extrabold text-[#1F170D] pt-2 border-t border-[#DFC387]/50">
                        <span class="font-serif">Total Tagihan:</span>
                        <span id="grand-total" class="font-mono font-black text-[#8C6418] text-lg">Rp 138.600</span>
                    </div>
                </div>

                <!-- POS Buttons (Split Bill & Pay) -->
                <div class="grid grid-cols-2 gap-2 pt-1">
                    <button type="button" onclick="alert('Fitur Split Bill: Tagihan berhasil dibagi rata per pemain (Rp 34.650 / 4 orang)!')" 
                            class="py-2.5 px-3 rounded-xl text-xs font-bold text-[#5C410F] transition-all active:scale-95 flex items-center justify-center gap-1.5 cursor-pointer shadow-sm hover:bg-white"
                            style="background: rgba(255, 255, 255, 0.95); border: 1.5px solid #C59B46;">
                        <svg class="w-3.5 h-3.5 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <span>Pisah Tagihan (Split)</span>
                    </button>

                    <button type="button" onclick="alert('Pesanan Lunas via QRIS! Struk thermal sedang dicetak & KOT dikirim ke monitor dapur/KDS.')" 
                            class="py-2.5 px-3 rounded-xl text-xs font-black tracking-wide uppercase transition-all active:scale-95 shadow-lg flex items-center justify-center gap-1.5 cursor-pointer"
                            style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1.5px solid #FBF0CE; color: #281A05; box-shadow: 0 6px 20px rgba(184, 134, 11, 0.35);">
                        <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>Bayar (Lunas)</span>
                    </button>
                </div>
            </div>

        </aside>
    </main>

    <!-- Quick Cart Script -->
    <script>
        let currentSubtotal = 126000;
        function addToCart(name, price, tag) {
            const list = document.getElementById('cart-list');
            const itemDiv = document.createElement('div');
            itemDiv.className = 'cart-item p-3 rounded-xl flex items-center justify-between shadow-sm transition-all animate-fadeIn';
            itemDiv.style.background = 'rgba(255, 255, 255, 0.95)';
            itemDiv.style.border = '1.5px solid #DFC387';
            itemDiv.innerHTML = `
                <div class="flex-1 pr-2">
                    <div class="text-xs font-bold text-[#1F170D]">${name}</div>
                    <div class="text-[10px] text-[#7A5818] font-mono font-medium">Rp ${price.toLocaleString('id-ID')} x 1 (${tag})</div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="font-mono text-xs font-extrabold text-[#8C6418]">Rp ${price.toLocaleString('id-ID')}</span>
                </div>
            `;
            list.appendChild(itemDiv);
            currentSubtotal += price;
            const tax = Math.round(currentSubtotal * 0.1);
            const total = currentSubtotal + tax;
            document.getElementById('subtotal').innerText = 'Rp ' + currentSubtotal.toLocaleString('id-ID');
            document.getElementById('tax').innerText = 'Rp ' + tax.toLocaleString('id-ID');
            document.getElementById('grand-total').innerText = 'Rp ' + total.toLocaleString('id-ID');
        }

        function openPosCheckInModal() {
            document.getElementById('pos-checkin-modal').classList.remove('hidden');
            document.getElementById('pos-checkin-result').classList.add('hidden');
            const input = document.getElementById('pos-ticket-input');
            input.value = '';
            setTimeout(() => input.focus(), 100);
        }

        function closePosCheckInModal() {
            document.getElementById('pos-checkin-modal').classList.add('hidden');
        }

        async function submitPosCheckIn() {
            const input = document.getElementById('pos-ticket-input');
            const code = input.value.trim();
            if (!code) {
                alert('Silakan scan barcode atau masukkan kode tiket.');
                return;
            }

            const resultContainer = document.getElementById('pos-checkin-result');
            resultContainer.classList.remove('hidden');
            resultContainer.className = 'rounded-2xl p-4 border text-xs space-y-2 bg-amber-50 border-amber-200 text-amber-900';
            resultContainer.innerHTML = '⏳ Memverifikasi tiket ke server...';

            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const response = await fetch('/pos/check-in', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ code: code })
                });

                const data = await response.json();
                if (data.success) {
                    const res = data.data;
                    let equipmentsHtml = '';
                    if (res.equipments && res.equipments.length > 0) {
                        equipmentsHtml = '<div class="mt-2 pt-2 border-t border-emerald-200"><div class="font-bold text-emerald-900 mb-1">🎒 Serah-Terima Alat:</div>' +
                            res.equipments.map(e => `<div class="flex justify-between py-0.5"><span>🎾 ${e.name}</span><span class="font-bold font-mono">${e.quantity} Pcs</span></div>`).join('') +
                            '<div class="mt-1 text-[11px] text-emerald-700 font-semibold">👉 Wajib serahkan raket & bola ke pemain.</div></div>';
                    } else {
                        equipmentsHtml = '<div class="text-[11px] text-gray-500 italic mt-1">Tidak ada sewa raket/bola tambahan.</div>';
                    }

                    resultContainer.className = 'rounded-2xl p-4 border text-xs space-y-2 bg-emerald-50 border-emerald-300 text-emerald-900';
                    resultContainer.innerHTML = `
                        <div class="flex justify-between items-center">
                            <span class="px-2 py-0.5 rounded-md font-bold text-[10px] bg-emerald-200 text-emerald-900 uppercase">
                                ${res.already_checked_in ? '⚠️ Sudah Pernah Check-In' : '✅ Check-In Berhasil'}
                            </span>
                            <span class="font-mono text-[10px] text-emerald-700">${res.booking_code}</span>
                        </div>
                        <div class="font-bold text-sm text-gray-900">${res.player_name}</div>
                        <div class="text-xs text-gray-700">🎾 <strong>${res.court_name}</strong> &bull; ${res.schedule}</div>
                        ${equipmentsHtml}
                    `;
                } else {
                    resultContainer.className = 'rounded-2xl p-4 border text-xs space-y-2 bg-rose-50 border-rose-300 text-rose-900';
                    resultContainer.innerHTML = `<strong>❌ Gagal Check-In:</strong><br>${data.message || 'Tiket tidak ditemukan.'}`;
                }
            } catch (err) {
                resultContainer.className = 'rounded-2xl p-4 border text-xs space-y-2 bg-rose-50 border-rose-300 text-rose-900';
                resultContainer.innerHTML = `<strong>❌ Terjadi Kesalahan:</strong><br>${err.message}`;
            }
        }
    </script>

    <!-- MODAL CHECK-IN GATE VANTAGE POS -->
    <div id="pos-checkin-modal" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl border border-[#DFC387] shadow-2xl w-full max-w-lg overflow-hidden animate-fadeIn">
            <!-- Header -->
            <div class="p-5 border-b border-[#DFC387] flex items-center justify-between"
                 style="background: linear-gradient(135deg, #FAF5E8 0%, #F5E8C7 100%);">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-amber-100 text-amber-900 border border-amber-300">
                        Frontdesk Scanner
                    </span>
                    <div class="font-serif font-black text-lg text-[#1F170D] mt-1">🎟️ Check-In Tiket Lapangan</div>
                </div>
                <button type="button" onclick="closePosCheckInModal()" class="text-2xl text-[#78350F] hover:text-black leading-none cursor-pointer">&times;</button>
            </div>

            <!-- Body -->
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-[#1F170D] mb-1.5">Scan Barcode / Input Kode Tiket (BK-PAD-XXXX):</label>
                    <div class="flex gap-2">
                        <input type="text" id="pos-ticket-input" 
                               placeholder="Tembak barcode gun atau ketik kode tiket..." 
                               class="flex-1 px-3.5 py-2.5 rounded-xl border border-[#D4AF37] text-sm font-mono font-bold text-[#1F170D] bg-[#FFFDF5] outline-none"
                               onkeydown="if(event.key === 'Enter') submitPosCheckIn();" />
                        <button type="button" onclick="submitPosCheckIn()" 
                                class="px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider text-[#281A05] cursor-pointer active:scale-95 transition-all shadow-md"
                                style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1px solid #FBF0CE;">
                            Check-In ⚡
                        </button>
                    </div>
                </div>

                <!-- Alert Result Container -->
                <div id="pos-checkin-result" class="hidden rounded-2xl p-4 border text-xs space-y-2"></div>
            </div>

            <!-- Footer -->
            <div class="p-4 bg-[#FAF5E8] border-t border-[#DFC387] flex justify-end gap-2">
                <button type="button" onclick="closePosCheckInModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-[#5C410F] bg-white border border-[#DFC387]">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</body>
</html>