<nav x-data="{ open: false }" class="bg-[#061E15] border-b border-emerald-700/40 sticky top-0 z-50">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <!-- Logo -->
                <div class="shrink-0 flex items-center gap-3">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-[#092B20] border border-[#CCFF00]/40 flex items-center justify-center text-[#CCFF00] font-black text-base shadow-sm">
                            <svg class="w-5 h-5 text-[#CCFF00]" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <circle cx="12" cy="12" r="9" stroke-width="2" />
                                <path d="M12 3a9 9 0 0 1 9 9" stroke-width="2.5" stroke-linecap="round" />
                                <path d="M7 10l5 5 5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <div>
                            <span class="font-serif font-extrabold text-white text-lg tracking-[0.2em] uppercase">VANTAGE</span>
                            <span class="block text-[8px] tracking-[0.3em] text-emerald-300 font-semibold uppercase -mt-1">SPORTS &amp; SOCIAL</span>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-6 sm:-my-px sm:ms-8 sm:flex">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('dashboard') ? 'border-[#CCFF00] text-[#CCFF00]' : 'border-transparent text-emerald-200/80 hover:text-white hover:border-emerald-400' }} text-xs font-bold uppercase tracking-wider transition-colors">
                        Dashboard
                    </a>
                    <a href="#padel" onclick="alert('Modul Booking Lapangan Padel')" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-emerald-200/80 hover:text-white hover:border-emerald-400 text-xs font-bold uppercase tracking-wider transition-colors">
                        Padel Court
                    </a>
                    <a href="#wellness" onclick="alert('Modul Sesi Cold Plunge & Sauna')" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-emerald-200/80 hover:text-white hover:border-emerald-400 text-xs font-bold uppercase tracking-wider transition-colors">
                        Wellness
                    </a>
                    <a href="#lounge" onclick="alert('Modul F&B Social Lounge')" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-emerald-200/80 hover:text-white hover:border-emerald-400 text-xs font-bold uppercase tracking-wider transition-colors">
                        Lounge &amp; Cafe
                    </a>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <div class="text-xs font-bold text-white">{{ Auth::user()->name }}</div>
                        <div class="text-[10px] text-[#CCFF00] font-mono uppercase">{{ Auth::user()->role }}</div>
                    </div>

                    <!-- Logout Button Directly in Navbar -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="p-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-500/40 text-rose-300 hover:text-white text-xs font-bold transition-all flex items-center gap-1 cursor-pointer" title="Keluar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Hamburger (Mobile) -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="p-2 rounded-xl text-emerald-300 hover:text-white hover:bg-emerald-800/40 focus:outline-none">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden bg-[#07241A] border-b border-emerald-700/40 px-4 py-3 space-y-2">
        <a href="{{ route('dashboard') }}" class="block text-xs font-bold text-[#CCFF00] uppercase">Dashboard</a>
        <div class="pt-2 border-t border-emerald-800/40 flex items-center justify-between">
            <span class="text-xs text-white">{{ Auth::user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-xs text-rose-400 font-bold">Keluar</button>
            </form>
        </div>
    </div>
</nav>