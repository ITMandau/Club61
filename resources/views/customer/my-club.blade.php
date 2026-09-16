<x-app-layout>
    <div class="py-6 sm:py-8 text-[#1F170D]">
        <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16 space-y-6">

            <!-- Top Header & Navigation -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white/95 backdrop-blur-xl p-5 rounded-3xl border border-[#DFC387] shadow-sm">
                <div class="flex items-center gap-3.5">
                    <a href="{{ route('dashboard') }}" class="p-2.5 rounded-2xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] transition-colors" title="Back to Home">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="font-serif font-black text-xl sm:text-2xl text-[#1F170D]">My Club &bull; CLUB 61 Padel Court</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-[#FAF2DE] text-[#7A5818] border border-[#DFC387]">Medan Venue</span>
                        </div>
                        <p class="text-xs text-[#7A643E] mt-0.5">Exclusive club facilities, operational hours, and membership privileges</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="px-4 py-2 rounded-2xl text-xs font-black bg-[#FAF2DE] text-[#7A5818] border border-[#DFC387] shadow-sm">
                        Club 61 Member
                    </span>
                    <a href="{{ route('customer.booking') }}" 
                       class="px-5 py-2.5 rounded-2xl bg-gradient-to-b from-[#F5DE9B] to-[#D4AF37] text-[#1E160A] text-xs font-black uppercase tracking-wider shadow-md hover:brightness-105 active:scale-95 transition-all">
                        + Book Court
                    </a>
                </div>
            </div>

            <!-- Club Hero Visual Banner -->
            <div class="relative overflow-hidden rounded-3xl p-6 sm:p-10 text-white shadow-[0_15px_40px_rgba(20,40,30,0.25)] border border-[#DFC387]/70"
                 style="background: linear-gradient(135deg, #162E24 0%, #0F2019 60%, #0A1611 100%);">
                <!-- Glowing Orb Accents -->
                <div class="absolute -right-10 -bottom-10 w-96 h-96 rounded-full bg-[#D4AF37]/20 blur-3xl pointer-events-none"></div>
                <div class="absolute top-0 right-1/3 w-80 h-80 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>

                <div class="relative z-10 max-w-2xl">
                    <div class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-[#DFC387]/20 text-[#F5E6BE] border border-[#DFC387]/40 mb-3 backdrop-blur-md shadow-sm">
                        <span>Club Profile &bull; Indosat Building, Medan, North Sumatra</span>
                    </div>
                    <h2 class="font-serif text-2xl sm:text-4xl font-extrabold tracking-tight text-[#FAF5E6] leading-tight">
                        Club 61 Padel Court Medan
                    </h2>
                    <p class="text-xs sm:text-sm text-emerald-100/80 mt-3 font-medium leading-relaxed">
                        Medan's premier padel sporting venue featuring 3 tournament-standard panoramic courts (2 indoor AC, 1 outdoor), cedarwood Finnish sauna, 4°C cold plunge, and specialty cafe lounge.
                    </p>

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-white/10 border border-white/20 text-xs font-bold text-white backdrop-blur-md">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            Venue Open Daily: 06:00 - 23:00 WIB
                        </div>
                        <a href="https://wa.me/6281261617233" target="_blank" 
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-[#DFC387]/20 hover:bg-[#DFC387]/30 border border-[#DFC387]/50 text-xs font-bold text-[#F5E6BE] backdrop-blur-md transition-colors">
                            <span>Contact Concierge via WhatsApp</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Key Info Bar (3 Cards) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-5 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 text-[#7A5818]">
                        <svg class="w-6 h-6 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase font-extrabold tracking-wider text-[#7A5818]">Operating Hours</div>
                        <div class="font-serif font-black text-base text-[#1F170D] mt-0.5">Monday &ndash; Sunday (Daily)</div>
                        <div class="text-xs text-[#8C7A58] mt-1 font-mono font-bold">06:00 &ndash; 23:00 WIB</div>
                    </div>
                </div>

                <div class="p-5 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 text-[#7A5818]">
                        <svg class="w-6 h-6 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase font-extrabold tracking-wider text-[#7A5818]">Prime Location</div>
                        <div class="font-serif font-black text-base text-[#1F170D] mt-0.5">Club 61 Padel Court Medan</div>
                        <div class="text-xs text-[#8C7A58] mt-1">Indosat Building, Jl. Perintis Kemerdekaan No. 39, Medan, North Sumatra</div>
                    </div>
                </div>

                <div class="p-5 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 text-[#7A5818]">
                        <svg class="w-6 h-6 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase font-extrabold tracking-wider text-[#7A5818]">Concierge &amp; Valet</div>
                        <div class="font-serif font-black text-base text-[#1F170D] mt-0.5">Dedicated Member Services</div>
                        <div class="text-xs text-[#8C7A58] mt-1 font-mono">0812-6161-PADEL &bull; Free VIP Valet</div>
                    </div>
                </div>
            </div>

            <!-- Facilities Showcase: 6 Premium Cards -->
            <div class="space-y-4">
                <div class="flex items-center justify-between px-1">
                    <div>
                        <h3 class="font-serif font-extrabold text-lg text-[#1F170D]">Exclusive Member Facilities</h3>
                        <p class="text-xs text-[#7A643E]">International championship standards tailored for peak athletic performance and recovery</p>
                    </div>
                    <span class="text-xs font-bold text-[#8C6418]">6 Integrated Facilities</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    
                    <!-- Facility 1: Courts -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex flex-col justify-between hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase">COURT</span>
                            </div>
                            <div>
                                <h4 class="font-serif font-black text-sm text-[#1F170D]">3 Panoramic Padel Courts</h4>
                                <p class="text-xs text-[#7A643E] mt-1.5 leading-relaxed">
                                    12mm tempered glass without center pillars, Mondo Supercourt XN turf, anti-glare 1000 lux LED illumination, and climate-controlled central AC.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#DFC387]/40 flex items-center justify-between text-[11px] font-bold text-[#7A5818]">
                            <span>FIP / WPT Standard</span>
                            <span class="text-emerald-700">Available Daily</span>
                        </div>
                    </div>

                    <!-- Facility 2: Sauna & Cold Plunge -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex flex-col justify-between hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase">SAUNA</span>
                            </div>
                            <div>
                                <h4 class="font-serif font-black text-sm text-[#1F170D]">Cold Plunge 4°C &amp; Sauna</h4>
                                <p class="text-xs text-[#7A643E] mt-1.5 leading-relaxed">
                                    High-speed ozone-circulated 4°C ice bath and Finnish red cedarwood sauna for optimal post-match muscle recovery.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#DFC387]/40 flex items-center justify-between text-[11px] font-bold text-[#7A5818]">
                            <span>Muscle Recovery</span>
                            <span class="text-emerald-700">Free for VIP Platinum</span>
                        </div>
                    </div>

                    <!-- Facility 3: Cafe & Lounge -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex flex-col justify-between hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase">CAFE</span>
                            </div>
                            <div>
                                <h4 class="font-serif font-black text-sm text-[#1F170D]">Club 61 Cafe &amp; Lounge</h4>
                                <p class="text-xs text-[#7A643E] mt-1.5 leading-relaxed">
                                    Artisan protein smoothie bar, specialty single-origin espresso, wholesome brunch menu, and panoramic court-viewing deck.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#DFC387]/40 flex items-center justify-between text-[11px] font-bold text-[#7A5818]">
                            <span>F&amp;B &bull; Social Lounge</span>
                            <span class="text-emerald-700">Open 07:00 - 22:30</span>
                        </div>
                    </div>

                    <!-- Facility 4: Locker & Shower -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex flex-col justify-between hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase">LOCKER</span>
                            </div>
                            <div>
                                <h4 class="font-serif font-black text-sm text-[#1F170D]">Smart Locker &amp; Rain Shower</h4>
                                <p class="text-xs text-[#7A643E] mt-1.5 leading-relaxed">
                                    RFID digital lockers with device charging ports, high-pressure rain showers, Dyson hair dryers, and Le Labo bath amenities.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#DFC387]/40 flex items-center justify-between text-[11px] font-bold text-[#7A5818]">
                            <span>Private Locker Room</span>
                            <span class="text-emerald-700">RFID Security</span>
                        </div>
                    </div>

                    <!-- Facility 5: Pro Shop -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex flex-col justify-between hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase">SHOP</span>
                            </div>
                            <div>
                                <h4 class="font-serif font-black text-sm text-[#1F170D]">Pro Shop &amp; Custom Gear</h4>
                                <p class="text-xs text-[#7A643E] mt-1.5 leading-relaxed">
                                    Curated selection of limited-edition padel rackets (Babolat, Bullpadel, Nox), complimentary racket demo testing, and official pro tour apparel.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#DFC387]/40 flex items-center justify-between text-[11px] font-bold text-[#7A5818]">
                            <span>Padel Gear &amp; Rental</span>
                            <span class="text-emerald-700">Demo Rackets</span>
                        </div>
                    </div>

                    <!-- Facility 6: Valet & EV -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex flex-col justify-between hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase">VALET</span>
                            </div>
                            <div>
                                <h4 class="font-serif font-black text-sm text-[#1F170D]">Dedicated Valet &amp; EV Charger</h4>
                                <p class="text-xs text-[#7A643E] mt-1.5 leading-relaxed">
                                    Private member parking area with complimentary zero-wait valet service and ultra-fast charging stations for electric vehicles.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#DFC387]/40 flex items-center justify-between text-[11px] font-bold text-[#7A5818]">
                            <span>Valet Service</span>
                            <span class="text-emerald-700">Free for Members</span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- 2-Column Section: Rules & Member Perks -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- Left: Club Etiquette & Rules (Col 7) -->
                <div class="lg:col-span-7 bg-white/95 backdrop-blur-xl rounded-3xl border border-[#DFC387] p-6 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-[#DFC387]/50 pb-3">
                        <div class="w-8 h-8 rounded-xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-serif font-black text-base text-[#1F170D]">Club Etiquette &amp; Rules</h3>
                            <p class="text-[11px] text-[#7A643E]">Maintaining an exceptional standard of comfort for all members</p>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs text-[#3B2B11]">
                        <div class="flex items-start gap-3 p-3 rounded-2xl bg-[#FAF8F2] border border-[#DFC387]/60">
                            <span class="font-black text-[#8C6418] shrink-0">01.</span>
                            <div>
                                <strong class="text-[#1F170D]">Dedicated Padel Footwear:</strong>
                                <span class="text-[#7A643E] block mt-0.5">Players are required to wear dedicated court/padel footwear with non-marking outsoles to protect turf integrity.</span>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-3 rounded-2xl bg-[#FAF8F2] border border-[#DFC387]/60">
                            <span class="font-black text-[#8C6418] shrink-0">02.</span>
                            <div>
                                <strong class="text-[#1F170D]">Punctual Turnstile Check-In:</strong>
                                <span class="text-[#7A643E] block mt-0.5">Present your E-Ticket QR Code at the turnstile gate at least 10 minutes prior to session start for automatic access.</span>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-3 rounded-2xl bg-[#FAF8F2] border border-[#DFC387]/60">
                            <span class="font-black text-[#8C6418] shrink-0">03.</span>
                            <div>
                                <strong class="text-[#1F170D]">Sauna &amp; Cold Plunge Protocol:</strong>
                                <span class="text-[#7A643E] block mt-0.5">Showering is mandatory prior to entering the cold plunge pool to preserve hygiene and community wellness.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Privilege Summary & CTA (Col 5) -->
                <div class="lg:col-span-5 space-y-6">
                    <div class="bg-gradient-to-br from-[#1C2E24] to-[#0E1A14] text-white rounded-3xl p-6 border border-[#DFC387]/70 shadow-lg space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-[#DFC387]/20 text-[#F5E6BE] border border-[#DFC387]/40">
                                Your Privileges
                            </span>
                            <span class="text-xs text-[#E5C378] font-bold">VIP Platinum</span>
                        </div>

                        <h4 class="font-serif font-black text-lg text-white">Member Status Benefits</h4>
                        
                        <ul class="space-y-2.5 text-xs text-emerald-100/85 font-medium">
                            <li class="flex items-center gap-2">
                                <span class="text-[#E5C378] font-bold">&bull;</span>
                                <span>Priority court booking up to 7 days in advance</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-[#E5C378] font-bold">&bull;</span>
                                <span>25% discount on court rentals &amp; equipment add-ons</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-[#E5C378] font-bold">&bull;</span>
                                <span>Unlimited access to Finnish Sauna &amp; Ice Bath</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-[#E5C378] font-bold">&bull;</span>
                                <span>Exclusive invitations to member-only tournaments</span>
                            </li>
                        </ul>

                        <div class="pt-3 border-t border-white/15">
                            <a href="{{ route('customer.booking') }}" 
                               class="w-full py-3.5 px-4 rounded-2xl bg-gradient-to-b from-[#F5DE9B] to-[#D4AF37] text-[#1E160A] text-xs font-black uppercase tracking-wider flex items-center justify-center gap-2 shadow-md hover:brightness-105 active:scale-95 transition-all">
                                <span>Book Court Now &rarr;</span>
                            </a>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
