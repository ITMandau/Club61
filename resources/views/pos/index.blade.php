<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VANTAGE POS - Frontdesk & Cashier Terminal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-[#051811] text-slate-100 font-sans antialiased overflow-hidden flex flex-col">

    <!-- Top POS Navigation Header -->
    <header class="bg-[#09281D] border-b border-emerald-600/30 px-5 py-3 flex items-center justify-between shadow-lg shrink-0">
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-[#CCFF00] text-[#051811] flex items-center justify-center font-black text-lg shadow-md shadow-lime-400/20">
                    VP
                </div>
                <div>
                    <div class="font-extrabold text-white text-base tracking-wider flex items-center gap-2">
                        <span>VANTAGE POS</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#CCFF00]/20 text-[#CCFF00] border border-[#CCFF00]/40 uppercase tracking-widest">
                            Terminal 01
                        </span>
                    </div>
                    <div class="text-[11px] text-emerald-300/70 -mt-0.5">Frontdesk, Sewa Alat &amp; Cafe Cashier</div>
                </div>
            </div>

            <!-- Quick Status Badge -->
            <div class="hidden md:flex items-center gap-2 pl-4 border-l border-emerald-700/40 text-xs text-emerald-200">
                <span class="w-2 h-2 rounded-full bg-[#CCFF00] animate-pulse"></span>
                <span>Kasir Siap &bull; Printer Kasir Terhubung</span>
            </div>
        </div>

        <!-- Cashier Profile & Logout -->
        <div class="flex items-center gap-4">
            <div class="text-right hidden sm:block">
                <div class="text-xs font-bold text-white">{{ Auth::user()->name ?? 'Kasir Frontdesk' }}</div>
                <div class="text-[10px] font-mono text-[#CCFF00] uppercase tracking-wider">Peran: {{ Auth::user()->role ?? 'CASHIER' }}</div>
            </div>

            <!-- Switch / Logout Form -->
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-500/40 text-rose-300 hover:text-white text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Keluar (Logout)</span>
                </button>
            </form>
        </div>
    </header>

    <!-- Main POS Interface (Split-screen) -->
    <main class="flex-1 flex overflow-hidden">

        <!-- LEFT PANEL: Product Catalog, Equipment, & F&B (65% Width) -->
        <section class="flex-1 flex flex-col border-r border-emerald-700/30 overflow-hidden bg-[#061D15]">
            
            <!-- Category Tabs & Quick Search -->
            <div class="p-4 border-b border-emerald-700/30 flex flex-wrap items-center justify-between gap-3 bg-[#082319]">
                <!-- Category Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 max-w-full">
                    <button class="px-3.5 py-2 rounded-xl bg-[#CCFF00] text-[#051811] text-xs font-bold shadow-sm whitespace-nowrap">
                        Semua Menu
                    </button>
                    <button class="px-3.5 py-2 rounded-xl bg-white/[0.05] hover:bg-emerald-800/40 text-emerald-200 text-xs font-semibold whitespace-nowrap transition-colors">
                        ☕ Coffee &amp; Drinks
                    </button>
                    <button class="px-3.5 py-2 rounded-xl bg-white/[0.05] hover:bg-emerald-800/40 text-emerald-200 text-xs font-semibold whitespace-nowrap transition-colors">
                        🥪 Toast &amp; Meals
                    </button>
                    <button class="px-3.5 py-2 rounded-xl bg-white/[0.05] hover:bg-emerald-800/40 text-emerald-200 text-xs font-semibold whitespace-nowrap transition-colors">
                        🎾 Sewa Raket &amp; Bola
                    </button>
                    <button class="px-3.5 py-2 rounded-xl bg-white/[0.05] hover:bg-emerald-800/40 text-emerald-200 text-xs font-semibold whitespace-nowrap transition-colors">
                        👕 Jersey &amp; Merch
                    </button>
                </div>

                <!-- Fast Search Input -->
                <div class="w-full sm:w-64 relative">
                    <input type="text" placeholder="Cari item atau scan barcode..." class="w-full pl-9 pr-3 py-2 rounded-xl bg-[#04150F] border border-emerald-700/50 text-xs text-white placeholder-emerald-600 focus:outline-none focus:border-[#CCFF00] focus:ring-1 focus:ring-[#CCFF00]">
                    <svg class="w-4 h-4 text-emerald-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="flex-1 overflow-y-auto p-4 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3.5">
                
                <!-- Item 1: Spanish Latte -->
                <div onclick="addToCart('Iced Spanish Latte', 38000, '☕ BAR')" class="p-3.5 rounded-2xl bg-[#092B20] border border-emerald-600/25 hover:border-[#CCFF00]/50 transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-[10px] text-emerald-400 font-semibold mb-1">
                            <span>Specialty Coffee</span>
                            <span class="px-1.5 py-0.5 rounded bg-emerald-950 text-[#CCFF00] font-mono">BAR</span>
                        </div>
                        <div class="font-bold text-sm text-white group-hover:text-[#CCFF00] transition-colors">Iced Spanish Latte</div>
                        <div class="text-[11px] text-emerald-200/60 mt-0.5 line-clamp-2">Espresso double shot with condensed milk and chilled fresh milk.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-emerald-800/40 flex items-center justify-between font-mono">
                        <span class="text-xs font-bold text-[#CCFF00]">Rp 38.000</span>
                        <span class="w-6 h-6 rounded-lg bg-[#CCFF00]/20 text-[#CCFF00] flex items-center justify-center font-bold text-sm group-hover:bg-[#CCFF00] group-hover:text-[#051811] transition-all">+</span>
                    </div>
                </div>

                <!-- Item 2: Oat Matcha Latte -->
                <div onclick="addToCart('Ceremonial Oat Matcha', 45000, '☕ BAR')" class="p-3.5 rounded-2xl bg-[#092B20] border border-emerald-600/25 hover:border-[#CCFF00]/50 transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-[10px] text-emerald-400 font-semibold mb-1">
                            <span>Wellness Drinks</span>
                            <span class="px-1.5 py-0.5 rounded bg-emerald-950 text-[#CCFF00] font-mono">BAR</span>
                        </div>
                        <div class="font-bold text-sm text-white group-hover:text-[#CCFF00] transition-colors">Ceremonial Oat Matcha</div>
                        <div class="text-[11px] text-emerald-200/60 mt-0.5 line-clamp-2">Stone-ground Uji matcha whisked with Oatside barista milk.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-emerald-800/40 flex items-center justify-between font-mono">
                        <span class="text-xs font-bold text-[#CCFF00]">Rp 45.000</span>
                        <span class="w-6 h-6 rounded-lg bg-[#CCFF00]/20 text-[#CCFF00] flex items-center justify-center font-bold text-sm group-hover:bg-[#CCFF00] group-hover:text-[#051811] transition-all">+</span>
                    </div>
                </div>

                <!-- Item 3: Avocado Sourdough -->
                <div onclick="addToCart('Smashed Avocado Toast', 55000, '🍳 KITCHEN')" class="p-3.5 rounded-2xl bg-[#092B20] border border-emerald-600/25 hover:border-[#CCFF00]/50 transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-[10px] text-emerald-400 font-semibold mb-1">
                            <span>Artisan Bakery</span>
                            <span class="px-1.5 py-0.5 rounded bg-emerald-950 text-amber-300 font-mono">KITCHEN</span>
                        </div>
                        <div class="font-bold text-sm text-white group-hover:text-[#CCFF00] transition-colors">Smashed Avocado Toast</div>
                        <div class="text-[11px] text-emerald-200/60 mt-0.5 line-clamp-2">Rustic sourdough with poached egg, hass avocado, and feta.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-emerald-800/40 flex items-center justify-between font-mono">
                        <span class="text-xs font-bold text-[#CCFF00]">Rp 55.000</span>
                        <span class="w-6 h-6 rounded-lg bg-[#CCFF00]/20 text-[#CCFF00] flex items-center justify-center font-bold text-sm group-hover:bg-[#CCFF00] group-hover:text-[#051811] transition-all">+</span>
                    </div>
                </div>

                <!-- Item 4: Babolat Viper Racket -->
                <div onclick="addToCart('Sewa Raket Babolat Viper', 50000, '🎾 RENTAL')" class="p-3.5 rounded-2xl bg-[#092B20] border border-emerald-600/25 hover:border-[#CCFF00]/50 transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-[10px] text-emerald-400 font-semibold mb-1">
                            <span>Sewa Raket (1 Jam)</span>
                            <span class="px-1.5 py-0.5 rounded bg-emerald-950 text-cyan-300 font-mono">COURT</span>
                        </div>
                        <div class="font-bold text-sm text-white group-hover:text-[#CCFF00] transition-colors">Raket Babolat Counter Viper</div>
                        <div class="text-[11px] text-emerald-200/60 mt-0.5 line-clamp-2">Raket karbon pro power &amp; toleransi smash tinggi.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-emerald-800/40 flex items-center justify-between font-mono">
                        <span class="text-xs font-bold text-[#CCFF00]">Rp 50.000</span>
                        <span class="w-6 h-6 rounded-lg bg-[#CCFF00]/20 text-[#CCFF00] flex items-center justify-center font-bold text-sm group-hover:bg-[#CCFF00] group-hover:text-[#051811] transition-all">+</span>
                    </div>
                </div>

                <!-- Item 5: Nox AT10 Genius -->
                <div onclick="addToCart('Sewa Raket Nox AT10 Genius', 65000, '🎾 RENTAL')" class="p-3.5 rounded-2xl bg-[#092B20] border border-emerald-600/25 hover:border-[#CCFF00]/50 transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-[10px] text-emerald-400 font-semibold mb-1">
                            <span>Sewa Raket (1 Jam)</span>
                            <span class="px-1.5 py-0.5 rounded bg-emerald-950 text-cyan-300 font-mono">COURT</span>
                        </div>
                        <div class="font-bold text-sm text-white group-hover:text-[#CCFF00] transition-colors">Raket Nox AT10 Genius 18K</div>
                        <div class="text-[11px] text-emerald-200/60 mt-0.5 line-clamp-2">Raket presisi kontrol Agustin Tapia Edition.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-emerald-800/40 flex items-center justify-between font-mono">
                        <span class="text-xs font-bold text-[#CCFF00]">Rp 65.000</span>
                        <span class="w-6 h-6 rounded-lg bg-[#CCFF00]/20 text-[#CCFF00] flex items-center justify-center font-bold text-sm group-hover:bg-[#CCFF00] group-hover:text-[#051811] transition-all">+</span>
                    </div>
                </div>

                <!-- Item 6: Bola Padel Pro Can -->
                <div onclick="addToCart('Bola Padel Pro (3 Pcs)', 35000, '🎾 PROSHOP')" class="p-3.5 rounded-2xl bg-[#092B20] border border-emerald-600/25 hover:border-[#CCFF00]/50 transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-[10px] text-emerald-400 font-semibold mb-1">
                            <span>Bola Padel Resmi</span>
                            <span class="px-1.5 py-0.5 rounded bg-emerald-950 text-lime-300 font-mono">BALL</span>
                        </div>
                        <div class="font-bold text-sm text-white group-hover:text-[#CCFF00] transition-colors">Bola Padel Pro (1 Can / 3 Pcs)</div>
                        <div class="text-[11px] text-emerald-200/60 mt-0.5 line-clamp-2">Bola tekanan turnamen resmi WPT.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-emerald-800/40 flex items-center justify-between font-mono">
                        <span class="text-xs font-bold text-[#CCFF00]">Rp 35.000</span>
                        <span class="w-6 h-6 rounded-lg bg-[#CCFF00]/20 text-[#CCFF00] flex items-center justify-center font-bold text-sm group-hover:bg-[#CCFF00] group-hover:text-[#051811] transition-all">+</span>
                    </div>
                </div>

                <!-- Item 7: Club 61 Pro Jersey -->
                <div onclick="addToCart('Club 61 Pro Match Jersey', 299000, '👕 MERCH')" class="p-3.5 rounded-2xl bg-[#092B20] border border-emerald-600/25 hover:border-[#CCFF00]/50 transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-[10px] text-emerald-400 font-semibold mb-1">
                            <span>Official Merch</span>
                            <span class="px-1.5 py-0.5 rounded bg-emerald-950 text-indigo-300 font-mono">RETAIL</span>
                        </div>
                        <div class="font-bold text-sm text-white group-hover:text-[#CCFF00] transition-colors">Pro Match Jersey (Size L)</div>
                        <div class="text-[11px] text-emerald-200/60 mt-0.5 line-clamp-2">Aeroready breathable fabric stealth black.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-emerald-800/40 flex items-center justify-between font-mono">
                        <span class="text-xs font-bold text-[#CCFF00]">Rp 299.000</span>
                        <span class="w-6 h-6 rounded-lg bg-[#CCFF00]/20 text-[#CCFF00] flex items-center justify-center font-bold text-sm group-hover:bg-[#CCFF00] group-hover:text-[#051811] transition-all">+</span>
                    </div>
                </div>

                <!-- Item 8: Ice Bath Session -->
                <div onclick="addToCart('Sesi Cold Plunge (45 Mnt)', 125000, '❄️ WELLNESS')" class="p-3.5 rounded-2xl bg-[#092B20] border border-emerald-600/25 hover:border-[#CCFF00]/50 transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] shadow-md group flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-[10px] text-emerald-400 font-semibold mb-1">
                            <span>Thermal Recovery</span>
                            <span class="px-1.5 py-0.5 rounded bg-emerald-950 text-sky-300 font-mono">SPA</span>
                        </div>
                        <div class="font-bold text-sm text-white group-hover:text-[#CCFF00] transition-colors">Ice Bath / Cold Plunge</div>
                        <div class="text-[11px] text-emerald-200/60 mt-0.5 line-clamp-2">Sesi pemulihan otot air es 4°C selama 45 menit.</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-emerald-800/40 flex items-center justify-between font-mono">
                        <span class="text-xs font-bold text-[#CCFF00]">Rp 125.000</span>
                        <span class="w-6 h-6 rounded-lg bg-[#CCFF00]/20 text-[#CCFF00] flex items-center justify-center font-bold text-sm group-hover:bg-[#CCFF00] group-hover:text-[#051811] transition-all">+</span>
                    </div>
                </div>

            </div>
        </section>

        <!-- RIGHT PANEL: Cart, Order Summary, & Split Bill (35% Width) -->
        <aside class="w-full sm:w-96 lg:w-[420px] bg-[#07241A] flex flex-col justify-between shrink-0 shadow-2xl">
            
            <!-- Order Header -->
            <div class="p-4 border-b border-emerald-700/40 bg-[#092D21]">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#CCFF00] flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        Pesanan Baru
                    </span>
                    <span class="font-mono text-xs text-emerald-300">#ORD-{{ date('His') }}</span>
                </div>
                
                <!-- Table / Customer Selector -->
                <div class="grid grid-cols-2 gap-2 mt-2.5">
                    <select class="px-2.5 py-1.5 rounded-xl bg-[#04150F] border border-emerald-700/60 text-xs text-white focus:outline-none focus:border-[#CCFF00]">
                        <option>🎾 Lapangan 01 (Court 1)</option>
                        <option selected>🎾 Lapangan 02 (Court 2)</option>
                        <option>☕ Meja 01 (Table 01)</option>
                        <option>☕ VIP Lounge 1</option>
                        <option>🛍️ Bawa Pulang (Takeaway)</option>
                    </select>
                    <input type="text" value="Member: Andi Wijaya" class="px-2.5 py-1.5 rounded-xl bg-[#04150F] border border-emerald-700/60 text-xs text-white focus:outline-none focus:border-[#CCFF00]" />
                </div>
            </div>

            <!-- Cart Items List (Interactive) -->
            <div id="cart-list" class="flex-1 overflow-y-auto p-4 space-y-2.5">
                <!-- Initial Pre-loaded Item -->
                <div class="cart-item p-3 rounded-xl bg-[#051811] border border-emerald-700/40 flex items-center justify-between">
                    <div class="flex-1 pr-2">
                        <div class="text-xs font-bold text-white">Iced Spanish Latte</div>
                        <div class="text-[10px] text-emerald-400 font-mono">Rp 38.000 x 2</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs font-bold text-[#CCFF00]">Rp 76.000</span>
                    </div>
                </div>

                <div class="cart-item p-3 rounded-xl bg-[#051811] border border-emerald-700/40 flex items-center justify-between">
                    <div class="flex-1 pr-2">
                        <div class="text-xs font-bold text-white">Raket Babolat Counter Viper</div>
                        <div class="text-[10px] text-emerald-400 font-mono">Rp 50.000 x 1 (1 Jam)</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs font-bold text-[#CCFF00]">Rp 50.000</span>
                    </div>
                </div>
            </div>

            <!-- Billing Calculation & Action Buttons -->
            <div class="p-4 border-t border-emerald-700/40 bg-[#092D21] space-y-3">
                <div class="space-y-1.5 text-xs text-emerald-200/80 font-mono">
                    <div class="flex justify-between">
                        <span>Subtotal:</span>
                        <span id="subtotal" class="text-white">Rp 126.000</span>
                    </div>
                    <div class="flex justify-between text-[11px] text-emerald-300/60">
                        <span>PB1 Resto &amp; Venue Tax (10%):</span>
                        <span id="tax">Rp 12.600</span>
                    </div>
                    <div class="flex justify-between text-base font-extrabold text-white pt-2 border-t border-emerald-800/60">
                        <span>Total Tagihan:</span>
                        <span id="grand-total" class="text-[#CCFF00] text-lg">Rp 138.600</span>
                    </div>
                </div>

                <!-- POS Buttons (Split Bill & Pay) -->
                <div class="grid grid-cols-2 gap-2 pt-1">
                    <button type="button" onclick="alert('Fitur Split Bill: Tagihan berhasil dibagi rata per pemain (Rp 34.650 / 4 orang)!')" class="py-2.5 px-3 rounded-xl bg-white/[0.08] hover:bg-white/[0.15] border border-emerald-500/30 text-xs font-bold text-white transition-all active:scale-95 flex items-center justify-center gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5 text-[#CCFF00]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <span>Pisah Tagihan (Split)</span>
                    </button>

                    <button type="button" onclick="alert('Pesanan Lunas via QRIS! Struk thermal sedang dicetak & KOT dikirim ke dapur.')" class="py-2.5 px-3 rounded-xl bg-[#CCFF00] hover:bg-[#d8ff33] text-[#051811] text-xs font-black tracking-wide uppercase transition-all active:scale-95 shadow-lg shadow-lime-400/20 flex items-center justify-center gap-1.5 cursor-pointer">
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
            itemDiv.className = 'cart-item p-3 rounded-xl bg-[#051811] border border-emerald-700/40 flex items-center justify-between animate-fadeIn';
            itemDiv.innerHTML = `
                <div class="flex-1 pr-2">
                    <div class="text-xs font-bold text-white">${name}</div>
                    <div class="text-[10px] text-emerald-400 font-mono">Rp ${price.toLocaleString('id-ID')} x 1 (${tag})</div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="font-mono text-xs font-bold text-[#CCFF00]">Rp ${price.toLocaleString('id-ID')}</span>
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
    </script>
</body>
</html>