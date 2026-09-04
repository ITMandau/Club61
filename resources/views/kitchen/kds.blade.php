<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VANTAGE KDS - Kitchen & Bar Display Monitor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-[#03110C] text-slate-100 font-sans antialiased flex flex-col">

    <!-- Top KDS Bar -->
    <header class="bg-[#071F17] border-b border-emerald-600/30 px-6 py-3 flex items-center justify-between shadow-xl shrink-0">
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-amber-400 text-[#03110C] flex items-center justify-center font-black text-xl shadow-lg shadow-amber-400/20">
                    🍳
                </div>
                <div>
                    <div class="font-extrabold text-white text-base tracking-wider flex items-center gap-2">
                        <span>VANTAGE KDS</span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#CCFF00]/20 text-[#CCFF00] border border-[#CCFF00]/40 uppercase tracking-widest">
                            Live Monitor
                        </span>
                    </div>
                    <div class="text-[11px] text-emerald-300/70 -mt-0.5">Kitchen &amp; Barista Order Dispatch System</div>
                </div>
            </div>

            <!-- Station Filters -->
            <div class="hidden sm:flex items-center gap-1.5 pl-6 border-l border-emerald-700/40">
                <button class="px-3 py-1.5 rounded-xl bg-[#CCFF00] text-[#03110C] text-xs font-bold shadow-sm">
                    Semua Stasiun (3)
                </button>
                <button class="px-3 py-1.5 rounded-xl bg-white/[0.06] hover:bg-emerald-800/40 text-emerald-200 text-xs font-medium transition-colors">
                    ☕ Bar Kopi (2)
                </button>
                <button class="px-3 py-1.5 rounded-xl bg-white/[0.06] hover:bg-emerald-800/40 text-emerald-200 text-xs font-medium transition-colors">
                    🍳 Dapur Masak (1)
                </button>
            </div>
        </div>

        <!-- Operator & Logout -->
        <div class="flex items-center gap-4">
            <div class="text-right hidden sm:block">
                <div class="text-xs font-bold text-white">{{ Auth::user()->name ?? 'Barista Cafe' }}</div>
                <div class="text-[10px] font-mono text-amber-300 uppercase tracking-wider">Peran: {{ Auth::user()->role ?? 'KITCHEN' }}</div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-500/40 text-rose-300 hover:text-white text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Keluar</span>
                </button>
            </form>
        </div>
    </header>

    <!-- Tickets Board (Live Grid) -->
    <main class="flex-1 overflow-y-auto p-6 bg-[radial-gradient(ellipse_at_top,#09281D_0%,#03110C_70%)]">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            
            <!-- TICKET 1: In Progress (Amber) -->
            <div id="ticket-1" class="rounded-3xl bg-[#07241A] border-2 border-amber-400/70 p-5 shadow-2xl flex flex-col justify-between relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-amber-400"></div>
                
                <div>
                    <!-- Ticket Header -->
                    <div class="flex items-center justify-between pb-3 border-b border-emerald-700/40">
                        <div>
                            <span class="font-mono text-base font-black text-white">#TKT-041</span>
                            <div class="text-[11px] font-bold text-amber-300">☕ Meja 01 (Table 01)</div>
                        </div>
                        <div class="text-right">
                            <div class="font-mono text-sm font-extrabold text-amber-400 flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                                <span>03:45</span>
                            </div>
                            <span class="text-[9px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-amber-400/20 text-amber-300 border border-amber-400/30">
                                Sedang Dibuat
                            </span>
                        </div>
                    </div>

                    <!-- Ticket Items -->
                    <div class="py-4 space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-sm font-extrabold text-white flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-lg bg-emerald-800/60 text-[#CCFF00] font-mono text-xs flex items-center justify-center font-bold">2x</span>
                                    <span>Iced Spanish Latte</span>
                                </div>
                                <div class="text-[11px] text-amber-200/80 pl-8 space-y-0.5">
                                    <div>&bull; Less Sugar (50%)</div>
                                    <div>&bull; Normal Ice</div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-sm font-extrabold text-white flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-lg bg-emerald-800/60 text-[#CCFF00] font-mono text-xs flex items-center justify-center font-bold">1x</span>
                                    <span>Ceremonial Oat Matcha</span>
                                </div>
                                <div class="text-[11px] text-amber-200/80 pl-8">
                                    <div>&bull; Extra Oatside Milk</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ticket Action Button -->
                <button onclick="serveTicket('ticket-1')" class="w-full py-3 rounded-2xl bg-[#CCFF00] hover:bg-[#d8ff33] text-[#03110C] font-black text-xs uppercase tracking-wider transition-all active:scale-95 flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-lime-400/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Selesai &amp; Siap Sajikan</span>
                </button>
            </div>

            <!-- TICKET 2: Kitchen Queued (Red Urgent) -->
            <div id="ticket-2" class="rounded-3xl bg-[#07241A] border-2 border-rose-500/70 p-5 shadow-2xl flex flex-col justify-between relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-rose-500"></div>

                <div>
                    <!-- Ticket Header -->
                    <div class="flex items-center justify-between pb-3 border-b border-emerald-700/40">
                        <div>
                            <span class="font-mono text-base font-black text-white">#TKT-042</span>
                            <div class="text-[11px] font-bold text-rose-300">🎾 Lapangan 02 (Court 2)</div>
                        </div>
                        <div class="text-right">
                            <div class="font-mono text-sm font-extrabold text-rose-400 flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                                <span>08:12</span>
                            </div>
                            <span class="text-[9px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                Antrean Masak
                            </span>
                        </div>
                    </div>

                    <!-- Ticket Items -->
                    <div class="py-4 space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-sm font-extrabold text-white flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-lg bg-emerald-800/60 text-rose-300 font-mono text-xs flex items-center justify-center font-bold">2x</span>
                                    <span>Smashed Avocado Toast</span>
                                </div>
                                <div class="text-[11px] text-rose-200/80 pl-8 space-y-0.5">
                                    <div>&bull; Telur Poached Setengah Matang</div>
                                    <div>&bull; Extra Feta Cheese</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ticket Action Button -->
                <button onclick="startCook('ticket-2')" class="w-full py-3 rounded-2xl bg-rose-500 hover:bg-rose-600 text-white font-black text-xs uppercase tracking-wider transition-all active:scale-95 flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-rose-500/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Mulai Masak Sekarang</span>
                </button>
            </div>

            <!-- TICKET 3: New Order (Neon Lime) -->
            <div id="ticket-3" class="rounded-3xl bg-[#07241A] border-2 border-emerald-500/40 p-5 shadow-2xl flex flex-col justify-between relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-[#CCFF00]"></div>

                <div>
                    <!-- Ticket Header -->
                    <div class="flex items-center justify-between pb-3 border-b border-emerald-700/40">
                        <div>
                            <span class="font-mono text-base font-black text-white">#TKT-043</span>
                            <div class="text-[11px] font-bold text-emerald-300">☕ VIP Lounge 1</div>
                        </div>
                        <div class="text-right">
                            <div class="font-mono text-sm font-extrabold text-[#CCFF00] flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-[#CCFF00]"></span>
                                <span>01:15</span>
                            </div>
                            <span class="text-[9px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-[#CCFF00]/20 text-[#CCFF00] border border-[#CCFF00]/30">
                                Pesanan Baru
                            </span>
                        </div>
                    </div>

                    <!-- Ticket Items -->
                    <div class="py-4 space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-sm font-extrabold text-white flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-lg bg-emerald-800/60 text-[#CCFF00] font-mono text-xs flex items-center justify-center font-bold">1x</span>
                                    <span>Single Origin Americano</span>
                                </div>
                                <div class="text-[11px] text-emerald-200/70 pl-8">
                                    <div>&bull; Hot / Chilled Double Shot</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ticket Action Button -->
                <button onclick="serveTicket('ticket-3')" class="w-full py-3 rounded-2xl bg-[#CCFF00] hover:bg-[#d8ff33] text-[#03110C] font-black text-xs uppercase tracking-wider transition-all active:scale-95 flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-lime-400/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Kerjakan &amp; Sajikan</span>
                </button>
            </div>

        </div>
    </main>

    <!-- KDS Simple Script -->
    <script>
        function serveTicket(id) {
            const ticket = document.getElementById(id);
            ticket.style.opacity = '0.4';
            ticket.style.transform = 'scale(0.95)';
            ticket.innerHTML = `
                <div class="p-8 text-center my-auto">
                    <div class="text-3xl mb-2">✅</div>
                    <div class="font-extrabold text-base text-[#CCFF00]">Pesanan Selesai Disajikan!</div>
                    <div class="text-xs text-emerald-300/70 mt-1">Status KDS terupdate otomatis.</div>
                </div>
            `;
        }

        function startCook(id) {
            const ticket = document.getElementById(id);
            ticket.className = 'rounded-3xl bg-[#07241A] border-2 border-amber-400/70 p-5 shadow-2xl flex flex-col justify-between relative overflow-hidden';
            ticket.innerHTML = `
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-amber-400"></div>
                <div class="pb-3 border-b border-emerald-700/40 flex justify-between">
                    <div>
                        <span class="font-mono text-base font-black text-white">#TKT-042</span>
                        <div class="text-[11px] font-bold text-amber-300">🎾 Lapangan 02</div>
                    </div>
                    <span class="text-[9px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-amber-400/20 text-amber-300 border border-amber-400/30">
                        Sedang Dimasak
                    </span>
                </div>
                <div class="py-4 text-sm text-white font-extrabold">2x Smashed Avocado Toast</div>
                <button onclick="serveTicket('${id}')" class="w-full py-3 rounded-2xl bg-[#CCFF00] hover:bg-[#d8ff33] text-[#03110C] font-black text-xs uppercase tracking-wider transition-all active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                    ✅ Selesai & Sajikan
                </button>
            `;
        }
    </script>
</body>
</html>