<x-guest-layout>
    <div class="w-full max-w-7xl mx-auto my-auto">
        <!-- Main Responsive Split Screen Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-stretch">
            
            <!-- LEFT COLUMN: Brand Showcase & Architectural Padel Arena (7 Cols on Desktop) -->
            <div class="lg:col-span-7 flex flex-col justify-between overflow-hidden relative min-h-[580px] lg:min-h-[720px] bg-[#120E07] text-white"
                 style="border: 2px solid #C59B46; border-radius: 28px; box-shadow: 0 25px 60px -15px rgba(160, 120, 40, 0.35), 0 0 20px rgba(212, 175, 55, 0.25); color: #FFFFFF;">
                <!-- Background Cinematic Imagery with Warm Golden Architectural Overlay -->
                <div class="absolute inset-0 z-0">
                    <img src="{{ asset('images/club-hero.jpg') }}" 
                         alt="Vantage Racquet & Social Club" 
                         class="w-full h-full object-cover object-center scale-105 transition-transform duration-1000 hover:scale-100" />
                    <!-- Rich Warm Espresso / Bronze Charcoal Gradient Overlay for Ultimate Readability -->
                    <div class="absolute inset-0 bg-gradient-to-t from-[#0E0A04]/98 via-[#181105]/85 to-[#0E0A04]/75"></div>
                    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(212,175,55,0.3)_0%,transparent_65%)]"></div>
                </div>

                <!-- Top Header: Crest & Live Status Badge -->
                <div class="relative z-10 p-6 sm:p-8 flex items-center justify-between">
                    <div class="flex items-center gap-3.5">
                        <!-- Custom Polished Gold Crest Icon -->
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-lg shadow-black/60"
                             style="background: linear-gradient(135deg, #2D210F 0%, #171006 100%); border: 1.5px solid #E5C378;">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="#E5C378" style="stroke: #E5C378;">
                                <circle cx="12" cy="12" r="9" stroke-width="2" />
                                <path d="M12 3a9 9 0 0 1 9 9" stroke-width="2.5" stroke-linecap="round" />
                                <path d="M7 10l5 5 5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <div>
                            <div class="font-serif text-2xl font-black tracking-[0.25em] uppercase drop-shadow-md"
                                 style="color: #FFFFFF !important; text-shadow: 0 2px 10px rgba(0,0,0,0.9);">
                                VANTAGE
                            </div>
                            <div class="text-[10px] tracking-[0.38em] font-bold uppercase -mt-0.5"
                                 style="color: #F5E2B5 !important; text-shadow: 0 1px 6px rgba(0,0,0,0.9);">
                                RACQUET &amp; SOCIAL CLUB
                            </div>
                        </div>
                    </div>

                    <!-- Live Venue Status Pill with Gold Accent -->
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full shadow-md backdrop-blur-md"
                         style="color: #FFFFFF !important; background: rgba(0,0,0,0.75); border: 1.5px solid #D4AF37;">
                        <span class="w-2 h-2 rounded-full animate-pulse" style="background-color: #D4AF37; box-shadow: 0 0 8px #D4AF37;"></span>
                        <span class="font-bold tracking-wider text-[11px] uppercase" style="color: #FFFFFF !important;">&bull; VENUE LIVE &bull; 4 COURTS OPEN</span>
                    </div>
                </div>

                <!-- Center Content: Editorial Typography & Luxury Value Proposition -->
                <div class="relative z-10 px-6 sm:px-8 py-4 my-auto">
                    <div class="inline-block px-3.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-widest mb-3.5 shadow-sm"
                         style="color: #FFFFFF !important; background: rgba(212, 175, 55, 0.25); border: 1.5px solid #E5C378;">
                        EXCLUSIVE MEMBER SANCTUARY
                    </div>

                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight leading-[1.15] drop-shadow-lg"
                        style="color: #FFFFFF !important; text-shadow: 0 2px 12px rgba(0,0,0,0.9);">
                        Where Competition Meets <br class="hidden sm:inline" />
                        <span class="italic font-serif font-normal"
                              style="color: #F7E7B4; text-shadow: 0 0 20px rgba(212,175,55,0.7);">Refined Luxury.</span>
                    </h2>

                    <!-- Crisp High-Contrast Description Box -->
                    <div class="mt-4 max-w-xl p-3.5 sm:p-4 rounded-2xl backdrop-blur-md"
                         style="background: rgba(14, 10, 4, 0.65); border: 1px solid rgba(212, 175, 55, 0.35); box-shadow: 0 4px 20px rgba(0,0,0,0.5);">
                        <p class="text-sm sm:text-base leading-relaxed"
                           style="color: #FFFFFF !important; text-shadow: 0 1px 4px rgba(0,0,0,0.8); font-weight: 500;">
                            Satu gerbang akses terintegrasi untuk reservasi lapangan padel panoramic, thermal recovery suite (ice bath &amp; sauna), pemesanan F&amp;B lounge, hingga point-of-sale kasir venue.
                        </p>
                    </div>

                    <!-- 4 Brushed Metallic Gold Experience Cards -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mt-6">
                        <!-- Card 1: Padel Arena -->
                        <div class="p-3 rounded-2xl backdrop-blur-md transition-all group shadow-md hover:-translate-y-0.5 cursor-pointer"
                             style="background: linear-gradient(135deg, rgba(235, 205, 130, 0.45) 0%, rgba(184, 134, 11, 0.35) 50%, rgba(120, 85, 20, 0.45) 100%); border: 1.5px solid rgba(245, 222, 145, 0.6); box-shadow: 0 4px 15px rgba(0,0,0,0.4), inset 0 1px 1px rgba(255,255,255,0.4);">
                            <div class="mb-1.5 group-hover:scale-110 transition-transform" style="color: #FFFFFF !important;">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="stroke: #FFFFFF;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16M9 6v12m6-12v12" />
                                </svg>
                            </div>
                            <div class="font-extrabold text-xs" style="color: #FFFFFF !important; text-shadow: 0 1px 3px rgba(0,0,0,0.8);">Padel Arena</div>
                            <div class="text-[10px] font-semibold" style="color: #FFF2D1 !important; text-shadow: 0 1px 3px rgba(0,0,0,0.8);">+ Panoramic Courts</div>
                        </div>

                        <!-- Card 2: Wellness Suite -->
                        <div class="p-3 rounded-2xl backdrop-blur-md transition-all group shadow-md hover:-translate-y-0.5 cursor-pointer"
                             style="background: linear-gradient(135deg, rgba(235, 205, 130, 0.45) 0%, rgba(184, 134, 11, 0.35) 50%, rgba(120, 85, 20, 0.45) 100%); border: 1.5px solid rgba(245, 222, 145, 0.6); box-shadow: 0 4px 15px rgba(0,0,0,0.4), inset 0 1px 1px rgba(255,255,255,0.4);">
                            <div class="mb-1.5 group-hover:scale-110 transition-transform" style="color: #FFFFFF !important;">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="stroke: #FFFFFF;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <div class="font-extrabold text-xs" style="color: #FFFFFF !important; text-shadow: 0 1px 3px rgba(0,0,0,0.8);">Wellness Suite</div>
                            <div class="text-[10px] font-semibold" style="color: #FFF2D1 !important; text-shadow: 0 1px 3px rgba(0,0,0,0.8);">Sauna &amp; Ice Plunge</div>
                        </div>

                        <!-- Card 3: Social Lounge -->
                        <div class="p-3 rounded-2xl backdrop-blur-md transition-all group shadow-md hover:-translate-y-0.5 cursor-pointer"
                             style="background: linear-gradient(135deg, rgba(235, 205, 130, 0.45) 0%, rgba(184, 134, 11, 0.35) 50%, rgba(120, 85, 20, 0.45) 100%); border: 1.5px solid rgba(245, 222, 145, 0.6); box-shadow: 0 4px 15px rgba(0,0,0,0.4), inset 0 1px 1px rgba(255,255,255,0.4);">
                            <div class="mb-1.5 group-hover:scale-110 transition-transform" style="color: #FFFFFF !important;">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="stroke: #FFFFFF;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V4a2 2 0 10-2 2h2m0 13c-3 0-6-2-6-5V8h12v6c0 3-3 5-6 5z" />
                                </svg>
                            </div>
                            <div class="font-extrabold text-xs" style="color: #FFFFFF !important; text-shadow: 0 1px 3px rgba(0,0,0,0.8);">Social Lounge</div>
                            <div class="text-[10px] font-semibold" style="color: #FFF2D1 !important; text-shadow: 0 1px 3px rgba(0,0,0,0.8);">Artisan Cafe &amp; Bar</div>
                        </div>

                        <!-- Card 4: Tournaments -->
                        <div class="p-3 rounded-2xl backdrop-blur-md transition-all group shadow-md hover:-translate-y-0.5 cursor-pointer"
                             style="background: linear-gradient(135deg, rgba(235, 205, 130, 0.45) 0%, rgba(184, 134, 11, 0.35) 50%, rgba(120, 85, 20, 0.45) 100%); border: 1.5px solid rgba(245, 222, 145, 0.6); box-shadow: 0 4px 15px rgba(0,0,0,0.4), inset 0 1px 1px rgba(255,255,255,0.4);">
                            <div class="mb-1.5 group-hover:scale-110 transition-transform" style="color: #FFFFFF !important;">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="stroke: #FFFFFF;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                </svg>
                            </div>
                            <div class="font-extrabold text-xs" style="color: #FFFFFF !important; text-shadow: 0 1px 3px rgba(0,0,0,0.8);">Tournaments</div>
                            <div class="text-[10px] font-semibold" style="color: #FFF2D1 !important; text-shadow: 0 1px 3px rgba(0,0,0,0.8);">Ranked League 2026</div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Footer Strip of Left Card -->
                <div class="relative z-10 p-5 sm:p-6 flex flex-col sm:flex-row items-center justify-between gap-2 rounded-b-[26px] backdrop-blur-md"
                     style="background: rgba(10, 7, 3, 0.85); border-top: 1.5px solid #C59B46; color: #FFFFFF !important;">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full" style="background-color: #D4AF37; box-shadow: 0 0 8px #D4AF37;"></span>
                        <span style="color: #FFFFFF !important; font-weight: 500;">Jakarta Selatan Flagship Venue &bull; Open 06:00 &ndash; 23:00</span>
                    </div>
                    <div class="font-mono text-[11px]" style="color: #F5E2B5 !important; font-weight: 700; letter-spacing: 0.05em;">
                        portal.vantageclub.id
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Luxury White Gold Authentication Portal (5 Cols on Desktop) -->
            <div class="lg:col-span-5 flex flex-col justify-center">
                <div class="p-6 sm:p-8 lg:p-9 relative overflow-hidden"
                     style="border: 3px solid #D4AF37; border-radius: 28px; box-shadow: 0 20px 50px -10px rgba(160, 120, 30, 0.35), 0 0 25px rgba(212, 175, 55, 0.3), inset 0 1px 2px rgba(255, 255, 255, 0.95); background-image: url('{{ asset('images/white-gold-marble.jpg') }}'); background-size: cover; background-position: center;">
                    
                    <!-- Pearlescent Soft White Glass Frosted Sheen Overlay -->
                    <div class="absolute inset-0 bg-white/88 backdrop-blur-xl pointer-events-none"></div>

                    <!-- Ambient Gold Corner Glow -->
                    <div class="absolute -right-20 -top-20 w-48 h-48 bg-[#D4AF37]/20 blur-3xl rounded-full pointer-events-none"></div>

                    <!-- Card Header -->
                    <div class="relative z-10 mb-6">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-[#7A5818] bg-[#FDF9ED] border border-[#D9BE84] px-3 py-1 rounded-full shadow-sm">
                                SINGLE SMART GATEWAY
                            </span>
                            <span class="text-[10px] font-mono px-2.5 py-0.5 rounded-full bg-[#EDE0C4]/70 border border-[#D1B679]/60 text-[#6B4F1B] font-semibold">
                                v2.4 &bull; ENTERPRISE
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-[#1F170D] font-serif mt-2 tracking-tight">
                            Masuk ke Club Portal
                        </h1>
                        <p class="text-xs text-[#6B5738] mt-1 leading-relaxed">
                            Akses terpusat untuk Member, Kasir POS, Barista Cafe, dan Manajemen Venue.
                        </p>
                    </div>

                    <!-- Interactive Quick Demo Role Selector (1-Click Auto Fill) -->
                    <div class="relative z-10 mb-6 p-3.5 bg-white/70 shadow-inner backdrop-blur-sm"
                         style="border: 1.5px solid #DFC387; border-radius: 20px;">
                        <div class="flex items-center justify-between mb-2.5">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-[#634812] flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-[#B8860B]" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                                </svg>
                                ⚡ CARA CEPAT (PILIH AKUN):
                            </span>
                            <span id="role-destination" class="text-[10px] font-semibold px-2.5 py-0.5 rounded-full bg-[#EEDBB2] text-[#5C410F] border border-[#C59B46]/50">
                                Siap Masuk
                            </span>
                        </div>

                        <!-- 4 Quick Metallic Gold Role Buttons -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <button type="button" onclick="selectRole('budi@gmail.com', 'Password123!', 'Dashboard Member (/dashboard)', '🎾')" 
                                    class="role-btn text-left p-2 transition-all duration-200 text-xs active:scale-95 group shadow-sm hover:shadow-md cursor-pointer"
                                    style="background: linear-gradient(145deg, #FDF9EE 0%, #EEDBB0 55%, #CF9E46 100%); border: 1.5px solid #BD923E; border-radius: 14px;">
                                <div class="flex items-center gap-1 mb-0.5">
                                    <span class="text-sm">🎾</span>
                                    <span class="font-bold text-[11px] text-[#241808]">Member</span>
                                </div>
                                <div class="text-[9px] text-[#6B4E15] font-medium truncate">Customer VIP</div>
                            </button>

                            <button type="button" onclick="selectRole('cashier@club61.com', 'Password123!', 'Layar Kasir Frontdesk (/pos)', '💼')" 
                                    class="role-btn text-left p-2 transition-all duration-200 text-xs active:scale-95 group shadow-sm hover:shadow-md cursor-pointer"
                                    style="background: linear-gradient(145deg, #FDF9EE 0%, #EEDBB0 55%, #CF9E46 100%); border: 1.5px solid #BD923E; border-radius: 14px;">
                                <div class="flex items-center gap-1 mb-0.5">
                                    <span class="text-sm">💼</span>
                                    <span class="font-bold text-[11px] text-[#241808]">Kasir</span>
                                </div>
                                <div class="text-[9px] text-[#6B4E15] font-medium truncate">POS Venue</div>
                            </button>

                            <button type="button" onclick="selectRole('barista@club61.com', 'Password123!', 'Monitor KOT Kitchen (/kitchen)', '🍳')" 
                                    class="role-btn text-left p-2 transition-all duration-200 text-xs active:scale-95 group shadow-sm hover:shadow-md cursor-pointer"
                                    style="background: linear-gradient(145deg, #FDF9EE 0%, #EEDBB0 55%, #CF9E46 100%); border: 1.5px solid #BD923E; border-radius: 14px;">
                                <div class="flex items-center gap-1 mb-0.5">
                                    <span class="text-sm">🍳</span>
                                    <span class="font-bold text-[11px] text-[#241808]">Kitchen</span>
                                </div>
                                <div class="text-[9px] text-[#6B4E15] font-medium truncate">Display KDS</div>
                            </button>

                            <button type="button" onclick="selectRole('admin@club61.com', 'Password123!', 'Admin Panel Filament (/admin)', '⚡')" 
                                    class="role-btn text-left p-2 transition-all duration-200 text-xs active:scale-95 group shadow-sm hover:shadow-md cursor-pointer"
                                    style="background: linear-gradient(145deg, #FDF9EE 0%, #EEDBB0 55%, #CF9E46 100%); border: 1.5px solid #BD923E; border-radius: 14px;">
                                <div class="flex items-center gap-1 mb-0.5">
                                    <span class="text-sm">⚡</span>
                                    <span class="font-bold text-[11px] text-[#241808]">Admin</span>
                                </div>
                                <div class="text-[9px] text-[#6B4E15] font-medium truncate">Super Admin</div>
                            </button>
                        </div>
                    </div>

                    <!-- Session Status Alert -->
                    <x-auth-session-status class="relative z-10 mb-4 text-[#7A5818] bg-[#FFF9E6] p-3 rounded-xl border border-[#D4AF37] text-xs font-medium shadow-sm" :status="session('status')" />

                    <!-- Login Form -->
                    <form method="POST" action="{{ route('login') }}" class="relative z-10 space-y-4">
                        @csrf

                        <!-- Email Address Input -->
                        <div>
                            <label for="email" class="block text-xs font-bold text-[#3B2B11] mb-1.5">
                                Email / ID Kredensial
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#AA771C]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" />
                                    </svg>
                                </div>
                                <input id="email" 
                                       type="email" 
                                       name="email" 
                                       value="{{ old('email') }}" 
                                       required 
                                       autofocus 
                                       autocomplete="username" 
                                       placeholder="nama@domain.com"
                                       class="w-full pl-10 pr-4 py-3.5 bg-white/95 text-[#1E1609] placeholder-[#9E8A68] text-sm shadow-inner transition-all outline-none"
                                       style="border: 1.5px solid #D6BC82; border-radius: 16px;"
                                       onfocus="this.style.borderColor='#AA771C'; this.style.boxShadow='0 0 0 3px rgba(212,175,55,0.3)';"
                                       onblur="this.style.borderColor='#D6BC82'; this.style.boxShadow='none';" />
                            </div>
                            <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-xs text-rose-600 font-medium" />
                        </div>

                        <!-- Password Input -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="password" class="block text-xs font-bold text-[#3B2B11]">
                                    Kata Sandi (Password)
                                </label>
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="text-[11px] font-semibold text-[#8C6418] hover:text-[#B8860B] transition-colors">
                                        Lupa kata sandi?
                                    </a>
                                @endif
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#AA771C]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <input id="password" 
                                       type="password" 
                                       name="password" 
                                       required 
                                       autocomplete="current-password" 
                                       placeholder="••••••••••••"
                                       class="w-full pl-10 pr-11 py-3.5 bg-white/95 text-[#1E1609] placeholder-[#9E8A68] text-sm shadow-inner transition-all outline-none"
                                       style="border: 1.5px solid #D6BC82; border-radius: 16px;"
                                       onfocus="this.style.borderColor='#AA771C'; this.style.boxShadow='0 0 0 3px rgba(212,175,55,0.3)';"
                                       onblur="this.style.borderColor='#D6BC82'; this.style.boxShadow='none';" />
                                <!-- Eye Toggle Button -->
                                <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#AA771C] hover:text-[#634812] transition-colors focus:outline-none cursor-pointer" title="Lihat/Sembunyikan sandi">
                                    <svg id="eye-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                            <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-xs text-rose-600 font-medium" />
                        </div>

                        <!-- Remember Me Option -->
                        <div class="flex items-center justify-between pt-1">
                            <label for="remember_me" class="inline-flex items-center cursor-pointer">
                                <input id="remember_me" 
                                       type="checkbox" 
                                       name="remember" 
                                       class="w-4 h-4 rounded bg-white border-[#C59B46] text-[#B8860B] focus:ring-[#D4AF37] focus:ring-offset-0 transition-colors cursor-pointer accent-[#B8860B]">
                                <span class="ms-2 text-xs text-[#523F1C] font-semibold">{{ __('Ingat sesi masuk saya') }}</span>
                            </label>
                        </div>

                        <!-- Primary Submit Button: 3D Luxury Polished Gold -->
                        <div class="pt-2">
                            <button type="submit" 
                                    class="w-full py-4 px-6 text-[#281A05] font-black text-sm sm:text-base tracking-widest uppercase transition-all duration-200 transform active:scale-[0.98] hover:brightness-105 flex items-center justify-center gap-2.5 cursor-pointer"
                                    style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1.5px solid #FBF0CE; box-shadow: 0 8px 25px -4px rgba(184, 134, 11, 0.5), inset 0 1px 1px rgba(255, 255, 255, 0.9); border-radius: 18px;">
                                <span>Masuk ke Club</span>
                                <svg class="w-4 h-4 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </button>
                        </div>
                    </form>

                    <!-- VIP Membership Registration Strip -->
                    <div class="relative z-10 mt-6 pt-5 border-t border-[#D9BE84]/60 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs">
                        <div class="text-center sm:text-left">
                            <span style="font-family: 'Alex Brush', cursive; font-size: 26px; color: #6E4F18; line-height: 1;">
                                Belum memiliki akun?
                            </span>
                        </div>
                        <a href="{{ route('register') }}" 
                           class="inline-flex items-center gap-1.5 font-bold text-[#8A6318] hover:text-[#B8860B] transition-colors underline-offset-4 hover:underline">
                            <span>Daftar Membership</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Footer Tagline Under Card -->
                <div class="text-center mt-4 text-[11px] text-[#7A643E]/70 font-medium">
                    &copy; {{ date('Y') }} Vantage Racquet &amp; Social Club. All Rights Reserved.
                </div>
            </div>

        </div>
    </div>

    <!-- Interactive Script for Quick Role Fill and Password Visibility -->
    <script>
        function selectRole(email, password, destinationLabel, emoji) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
            
            const destBadge = document.getElementById('role-destination');
            if (destBadge) {
                destBadge.innerHTML = emoji + ' ' + destinationLabel;
                destBadge.className = 'text-[10px] font-semibold px-2.5 py-0.5 rounded-full bg-[#D4AF37] text-[#1E1508] transition-all scale-105 shadow-md shadow-amber-500/30';
                setTimeout(() => {
                    destBadge.className = 'text-[10px] font-semibold px-2.5 py-0.5 rounded-full bg-[#EEDBB2] text-[#5C410F] border border-[#C59B46]/50 transition-all';
                }, 2000);
            }
        }

        function togglePasswordVisibility() {
            const pwdInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />';
            } else {
                pwdInput.type = 'password';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
            }
        }
    </script>
</x-guest-layout>