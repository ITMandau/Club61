<x-guest-layout>
    <div class="w-full max-w-7xl mx-auto">
        <!-- Main Responsive Grid: Split-screen on Desktop (lg:grid-cols-12), Stacked on Mobile -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-stretch">
            
            <!-- LEFT COLUMN: Brand Showcase & Club Atmosphere (7 Cols on Desktop) -->
            <div class="lg:col-span-7 flex flex-col justify-between rounded-3xl overflow-hidden relative border border-emerald-500/25 shadow-2xl min-h-[520px] lg:min-h-[680px]">
                <!-- Background Cinematic Imagery with Dark Emerald Gradient Overlay -->
                <div class="absolute inset-0 z-0">
                    <img src="{{ asset('images/club-hero.jpg') }}" 
                         alt="Vantage Racquet & Social Club" 
                         class="w-full h-full object-cover object-center scale-105 transition-transform duration-1000 hover:scale-100" />
                    <!-- Multilayered Emerald/Charcoal Gradient for Text Legibility -->
                    <div class="absolute inset-0 bg-gradient-to-t from-[#03140D] via-[#041B12]/85 to-[#06261A]/75"></div>
                    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,#0F4C38_0%,transparent_65%)] opacity-70"></div>
                </div>

                <!-- Top Header: Crest & Live Status Badge -->
                <div class="relative z-10 p-6 sm:p-8 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <!-- Custom Crest Icon -->
                        <div class="w-11 h-11 rounded-2xl bg-[#092B20]/90 backdrop-blur-md border border-[#CCFF00]/40 flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-[#CCFF00]" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <circle cx="12" cy="12" r="9" stroke-width="2" />
                                <path d="M12 3a9 9 0 0 1 9 9" stroke-width="2.5" stroke-linecap="round" />
                                <path d="M7 10l5 5 5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <div>
                            <div class="font-serif text-2xl font-black tracking-[0.25em] text-white uppercase drop-shadow-md">
                                VANTAGE
                            </div>
                            <div class="text-[10px] tracking-[0.35em] text-emerald-300 font-semibold uppercase -mt-0.5">
                                RACQUET &amp; SOCIAL CLUB
                            </div>
                        </div>
                    </div>

                    <!-- Live Venue Status Pill -->
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-black/40 backdrop-blur-md border border-emerald-400/30 text-xs text-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-[#CCFF00] animate-pulse"></span>
                        <span class="font-medium tracking-wider text-[11px] uppercase">Venue Live &bull; 4 Courts Open</span>
                    </div>
                </div>

                <!-- Center Content: Editorial Typography & Value Proposition -->
                <div class="relative z-10 px-6 sm:px-8 py-4 my-auto">
                    <div class="inline-block px-3 py-1 rounded-lg bg-[#CCFF00]/15 border border-[#CCFF00]/30 text-[#CCFF00] text-xs font-bold uppercase tracking-widest mb-3">
                        Exclusive Member Sanctuary
                    </div>

                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white tracking-tight leading-[1.15] drop-shadow-lg">
                        Where Competition Meets <br class="hidden sm:inline" />
                        <span class="italic font-serif text-[#CCFF00] font-normal drop-shadow-[0_2px_10px_rgba(204,255,0,0.3)]">Refined Luxury.</span>
                    </h2>

                    <p class="text-sm sm:text-base text-emerald-100/80 max-w-xl mt-3 leading-relaxed drop-shadow">
                        Satu gerbang akses terintegrasi untuk reservasi lapangan padel panoramic, thermal recovery suite (ice bath &amp; sauna), pemesanan F&amp;B lounge, hingga point-of-sale kasir venue.
                    </p>

                    <!-- 4 Live Experience Cards (Padel, Wellness, Lounge, Tournament) -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mt-6">
                        <div class="p-3 rounded-2xl bg-black/30 backdrop-blur-md border border-emerald-500/20 hover:border-[#CCFF00]/40 transition-all group">
                            <div class="text-[#CCFF00] mb-1.5 group-hover:scale-110 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16M9 6v12m6-12v12" />
                                </svg>
                            </div>
                            <div class="font-bold text-xs text-white">Padel Arena</div>
                            <div class="text-[10px] text-emerald-300/70">4 Panoramic Courts</div>
                        </div>

                        <div class="p-3 rounded-2xl bg-black/30 backdrop-blur-md border border-emerald-500/20 hover:border-[#CCFF00]/40 transition-all group">
                            <div class="text-[#CCFF00] mb-1.5 group-hover:scale-110 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <div class="font-bold text-xs text-white">Wellness Suite</div>
                            <div class="text-[10px] text-emerald-300/70">Sauna &amp; Ice Plunge</div>
                        </div>

                        <div class="p-3 rounded-2xl bg-black/30 backdrop-blur-md border border-emerald-500/20 hover:border-[#CCFF00]/40 transition-all group">
                            <div class="text-[#CCFF00] mb-1.5 group-hover:scale-110 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V4a2 2 0 10-2 2h2m0 13c-3 0-6-2-6-5V8h12v6c0 3-3 5-6 5z" />
                                </svg>
                            </div>
                            <div class="font-bold text-xs text-white">Social Lounge</div>
                            <div class="text-[10px] text-emerald-300/70">Artisan Cafe &amp; Bar</div>
                        </div>

                        <div class="p-3 rounded-2xl bg-black/30 backdrop-blur-md border border-emerald-500/20 hover:border-[#CCFF00]/40 transition-all group">
                            <div class="text-[#CCFF00] mb-1.5 group-hover:scale-110 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                </svg>
                            </div>
                            <div class="font-bold text-xs text-white">Tournaments</div>
                            <div class="text-[10px] text-emerald-300/70">Ranked League 2026</div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Footer Strip -->
                <div class="relative z-10 p-6 sm:p-8 pt-4 flex flex-col sm:flex-row items-center justify-between border-t border-white/10 text-xs text-emerald-200/70 gap-2">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#CCFF00]"></span>
                        <span>Jakarta Selatan Flagship Venue &bull; Open 06:00 &ndash; 23:00</span>
                    </div>
                    <div class="font-mono text-[11px] text-emerald-300/80">
                        portal.vantageclub.id
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Luxury Authentication Portal (5 Cols on Desktop) -->
            <div class="lg:col-span-5 flex flex-col justify-center">
                <div class="rounded-3xl bg-[#07241A]/95 backdrop-blur-2xl border border-emerald-600/30 p-6 sm:p-8 lg:p-9 shadow-2xl relative overflow-hidden">
                    
                    <!-- Ambient Glow Accent in Card Corner -->
                    <div class="absolute -right-20 -top-20 w-48 h-48 bg-[#CCFF00]/10 blur-3xl rounded-full pointer-events-none"></div>

                    <!-- Card Header -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-widest text-[#CCFF00]">
                                Single Smart Gateway
                            </span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-md bg-emerald-900/60 border border-emerald-700/50 text-emerald-300">
                                v2.4 SECURE
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-1 tracking-tight">
                            Masuk ke Club Portal
                        </h1>
                        <p class="text-xs text-emerald-200/75 mt-1 leading-relaxed">
                            Akses terpadu untuk Member, Kasir POS, Barista KDS, dan Manajemen Venue.
                        </p>
                    </div>

                    <!-- Interactive Quick Demo Role Selector (1-Click Auto Fill) -->
                    <div class="mb-6 p-3.5 rounded-2xl bg-[#04160F] border border-emerald-700/40 shadow-inner">
                        <div class="flex items-center justify-between mb-2.5">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-300 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-[#CCFF00]" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                                </svg>
                                Uji Coba Cepat (Pilih Akun):
                            </span>
                            <span id="role-destination" class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-emerald-800/60 text-[#CCFF00] border border-emerald-600/40">
                                Siap Masuk
                            </span>
                        </div>

                        <!-- 4 Quick Role Chips -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <button type="button" onclick="selectRole('budi@gmail.com', 'Password123!', 'Dashboard Member (/dashboard)', '🎾')" class="role-btn text-left p-2 rounded-xl bg-white/[0.04] hover:bg-emerald-800/40 border border-emerald-600/30 hover:border-[#CCFF00]/60 transition-all text-xs text-slate-200 active:scale-95 group">
                                <div class="flex items-center gap-1 mb-0.5">
                                    <span class="text-sm">🎾</span>
                                    <span class="font-bold text-[11px] text-white group-hover:text-[#CCFF00]">Member</span>
                                </div>
                                <div class="text-[9px] text-emerald-400 truncate">Customer VIP</div>
                            </button>

                            <button type="button" onclick="selectRole('cashier@club61.com', 'Password123!', 'Layar Kasir Frontdesk (/pos)', '💼')" class="role-btn text-left p-2 rounded-xl bg-white/[0.04] hover:bg-emerald-800/40 border border-emerald-600/30 hover:border-[#CCFF00]/60 transition-all text-xs text-slate-200 active:scale-95 group">
                                <div class="flex items-center gap-1 mb-0.5">
                                    <span class="text-sm">💼</span>
                                    <span class="font-bold text-[11px] text-white group-hover:text-[#CCFF00]">Kasir</span>
                                </div>
                                <div class="text-[9px] text-emerald-400 truncate">POS Venue</div>
                            </button>

                            <button type="button" onclick="selectRole('barista@club61.com', 'Password123!', 'Monitor KOT Kitchen (/kitchen)', '🍳')" class="role-btn text-left p-2 rounded-xl bg-white/[0.04] hover:bg-emerald-800/40 border border-emerald-600/30 hover:border-[#CCFF00]/60 transition-all text-xs text-slate-200 active:scale-95 group">
                                <div class="flex items-center gap-1 mb-0.5">
                                    <span class="text-sm">🍳</span>
                                    <span class="font-bold text-[11px] text-white group-hover:text-[#CCFF00]">Kitchen</span>
                                </div>
                                <div class="text-[9px] text-emerald-400 truncate">Display KDS</div>
                            </button>

                            <button type="button" onclick="selectRole('admin@club61.com', 'Password123!', 'Admin Panel Filament (/admin)', '⚡')" class="role-btn text-left p-2 rounded-xl bg-white/[0.04] hover:bg-emerald-800/40 border border-emerald-600/30 hover:border-[#CCFF00]/60 transition-all text-xs text-slate-200 active:scale-95 group">
                                <div class="flex items-center gap-1 mb-0.5">
                                    <span class="text-sm">⚡</span>
                                    <span class="font-bold text-[11px] text-white group-hover:text-[#CCFF00]">Admin</span>
                                </div>
                                <div class="text-[9px] text-emerald-400 truncate">Super Admin</div>
                            </button>
                        </div>
                    </div>

                    <!-- Session Status Alert -->
                    <x-auth-session-status class="mb-4 text-emerald-300 bg-emerald-950/70 p-3 rounded-xl border border-emerald-500/40 text-xs" :status="session('status')" />

                    <!-- Login Form -->
                    <form method="POST" action="{{ route('login') }}" class="space-y-4">
                        @csrf

                        <!-- Email Address Input -->
                        <div>
                            <label for="email" class="block text-xs font-semibold text-emerald-200/90 mb-1.5">
                                Email / ID Kredensial
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-emerald-400">
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
                                       class="w-full pl-10 pr-4 py-3.5 rounded-2xl bg-[#04160F] border border-emerald-700/60 text-white placeholder-emerald-600/60 text-sm focus:outline-none focus:border-[#CCFF00] focus:ring-2 focus:ring-[#CCFF00]/25 transition-all shadow-inner" />
                            </div>
                            <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-xs text-rose-400" />
                        </div>

                        <!-- Password Input -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="password" class="block text-xs font-semibold text-emerald-200/90">
                                    Kata Sandi (Password)
                                </label>
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="text-[11px] text-emerald-300 hover:text-[#CCFF00] transition-colors">
                                        Lupa kata sandi?
                                    </a>
                                @endif
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-emerald-400">
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
                                       class="w-full pl-10 pr-11 py-3.5 rounded-2xl bg-[#04160F] border border-emerald-700/60 text-white placeholder-emerald-600/60 text-sm focus:outline-none focus:border-[#CCFF00] focus:ring-2 focus:ring-[#CCFF00]/25 transition-all shadow-inner" />
                                <!-- Eye Toggle Button -->
                                <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-emerald-400 hover:text-[#CCFF00] transition-colors focus:outline-none" title="Lihat/Sembunyikan sandi">
                                    <svg id="eye-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                            <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-xs text-rose-400" />
                        </div>

                        <!-- Remember Me Option -->
                        <div class="flex items-center justify-between pt-1">
                            <label for="remember_me" class="inline-flex items-center cursor-pointer">
                                <input id="remember_me" 
                                       type="checkbox" 
                                       name="remember" 
                                       class="w-4 h-4 rounded bg-[#04160F] border-emerald-700 text-emerald-500 focus:ring-[#CCFF00] focus:ring-offset-0 transition-colors">
                                <span class="ms-2 text-xs text-emerald-200/80">{{ __('Ingat sesi masuk saya') }}</span>
                            </label>
                        </div>

                        <!-- Primary Submit Button (Electric Tennis Lime) -->
                        <div class="pt-2">
                            <button type="submit" 
                                    class="w-full py-4 px-6 rounded-2xl bg-[#CCFF00] hover:bg-[#d8ff33] text-[#051E15] font-black text-sm sm:text-base tracking-wider uppercase transition-all duration-200 transform active:scale-[0.98] shadow-xl shadow-lime-400/25 hover:shadow-lime-400/40 flex items-center justify-center gap-2.5 cursor-pointer">
                                <span>Masuk ke Club</span>
                                <svg class="w-4 h-4 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </button>
                        </div>
                    </form>

                    <!-- VIP Membership Registration Strip -->
                    <div class="mt-6 pt-5 border-t border-emerald-800/40 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                        <div class="text-center sm:text-left">
                            <span class="text-emerald-300/80">Belum memiliki akun Member VIP?</span>
                        </div>
                        <a href="{{ route('register') }}" 
                           class="inline-flex items-center gap-1.5 font-bold text-[#CCFF00] hover:text-white transition-colors underline-offset-4 hover:underline">
                            <span>Daftar Membership</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Footer Tagline -->
                <div class="text-center mt-4 text-[11px] text-emerald-400/40">
                    &copy; {{ date('Y') }} Vantage Racquet &amp; Social Club. High Performance Architecture.
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
                destBadge.className = 'text-[10px] font-semibold px-2 py-0.5 rounded-full bg-[#CCFF00] text-[#051E15] transition-all scale-105 shadow-md shadow-lime-400/30';
                setTimeout(() => {
                    destBadge.className = 'text-[10px] font-medium px-2 py-0.5 rounded-full bg-emerald-800/60 text-[#CCFF00] border border-emerald-600/40 transition-all';
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