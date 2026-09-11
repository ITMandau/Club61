<nav x-data="{ open: false }" class="bg-white/85 backdrop-blur-xl border-b border-[#DFC387]/70 sticky top-0 z-50 shadow-sm">
    <!-- Primary Navigation Menu -->
    <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16">
        <div class="flex justify-between h-16">
            <div class="flex items-center gap-4">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center shadow-sm shrink-0"
                             style="background: linear-gradient(135deg, #2D210F 0%, #171006 100%); border: 1.5px solid #E5C378;">
                            <svg class="w-5 h-5 text-[#E5C378]" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <circle cx="12" cy="12" r="9" stroke-width="2" />
                                <path d="M12 3a9 9 0 0 1 9 9" stroke-width="2.5" stroke-linecap="round" />
                                <path d="M7 10l5 5 5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <div>
                            <span class="font-serif font-extrabold text-[#1F170D] text-base sm:text-lg tracking-[0.15em] sm:tracking-[0.2em] uppercase">VANTAGE</span>
                            <span class="block text-[8px] tracking-[0.25em] sm:tracking-[0.3em] text-[#8C6418] font-bold uppercase -mt-1">SPORTS &amp; SOCIAL</span>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links (Desktop) -->
                <div class="hidden md:flex space-x-5 -my-px ms-6">
                    <a href="{{ route('dashboard') }}" 
                       class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('dashboard') ? 'border-[#D4AF37] text-[#8C6418]' : 'border-transparent text-[#6B5738] hover:text-[#1F170D] hover:border-[#D4AF37]/50' }} text-xs font-bold uppercase tracking-wider transition-colors">
                        Beranda
                    </a>
                    <a href="{{ route('customer.booking') }}" 
                       class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('customer.booking') ? 'border-[#D4AF37] text-[#8C6418]' : 'border-transparent text-[#6B5738] hover:text-[#1F170D] hover:border-[#D4AF37]/50' }} text-xs font-bold uppercase tracking-wider transition-colors">
                        Booking Court
                    </a>
                    <a href="{{ route('customer.my-club') }}" 
                       class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('customer.my-club') ? 'border-[#D4AF37] text-[#8C6418]' : 'border-transparent text-[#6B5738] hover:text-[#1F170D] hover:border-[#D4AF37]/50' }} text-xs font-bold uppercase tracking-wider transition-colors">
                        My Club
                    </a>
                    <a href="{{ route('customer.invoice') }}" 
                       class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('customer.invoice') ? 'border-[#D4AF37] text-[#8C6418]' : 'border-transparent text-[#6B5738] hover:text-[#1F170D] hover:border-[#D4AF37]/50' }} text-xs font-bold uppercase tracking-wider transition-colors">
                        Invoice
                    </a>
                </div>
            </div>

            <!-- Right Actions (Cart, Notifications, Profile) -->
            <div class="flex items-center gap-2 sm:gap-4">
                
                <!-- Notification Bell (Screen 1 header) -->
                <button type="button" 
                        class="p-2 rounded-xl text-[#7A5818] hover:text-[#1F170D] hover:bg-[#FAF2DE] relative transition-colors"
                        title="Notifikasi">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-amber-500 rounded-full ring-2 ring-white"></span>
                </button>

                <!-- Cart Button with Counter Badge (Screen 2 & 3) -->
                <a href="{{ route('customer.cart') }}" 
                   class="p-2 rounded-xl text-[#7A5818] hover:text-[#1F170D] hover:bg-[#FAF2DE] relative transition-colors"
                   title="Keranjang Booking">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span id="nav-cart-badge" class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 bg-[#D4AF37] text-[#1E160A] text-[10px] font-black rounded-full flex items-center justify-center shadow-sm border border-white">
                        2
                    </span>
                </a>

                <!-- User Info & Logout (Desktop) -->
                <div class="hidden sm:flex items-center gap-3 ms-2">
                    <div class="text-right">
                        <div class="text-xs font-bold text-[#1F170D]">{{ Auth::user()->name }}</div>
                        <div class="text-[9px] text-[#7A5818] font-mono uppercase bg-[#FAF2DE] px-2 py-0.5 rounded-full border border-[#D9BE84] inline-block">
                            VIP Platinum
                        </div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" 
                                class="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-600 text-xs font-bold transition-all flex items-center gap-1 cursor-pointer shadow-sm" 
                                title="Keluar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </form>
                </div>

                <!-- User Avatar Quick Menu (Mobile) -->
                <div class="sm:hidden flex items-center">
                    <a href="{{ route('profile.edit') }}" class="w-8 h-8 rounded-full bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center text-xs font-bold text-[#7A5818] shadow-sm">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </a>
                </div>

            </div>
        </div>
    </div>
</nav>