<x-app-layout>
    <div class="py-6 sm:py-8 text-[#1F170D]">
        <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16 space-y-6">

            <!-- Top Header & Navigation -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white/95 backdrop-blur-xl p-5 rounded-3xl border border-[#DFC387] shadow-sm">
                <div class="flex items-center gap-3.5">
                    <a href="{{ route('dashboard') }}" class="p-2.5 rounded-2xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] transition-colors" title="Kembali ke Beranda">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="font-serif font-black text-xl sm:text-2xl text-[#1F170D]">My Club &bull; CLUB 61 Padel Court</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-[#FAF2DE] text-[#7A5818] border border-[#DFC387]">Medan Venue</span>
                        </div>
                        <p class="text-xs text-[#7A643E] mt-0.5">Informasi fasilitas eksklusif, jam operasional venue, dan privilese keanggotaan</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="px-4 py-2 rounded-2xl text-xs font-black bg-[#FAF2DE] text-[#7A5818] border border-[#DFC387] shadow-sm">
                        Club 61 Member
                    </span>
                    <a href="{{ route('customer.booking') }}" 
                       class="px-5 py-2.5 rounded-2xl bg-gradient-to-b from-[#F5DE9B] to-[#D4AF37] text-[#1E160A] text-xs font-black uppercase tracking-wider shadow-md hover:brightness-105 active:scale-95 transition-all">
                        + Booking Court
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
                        <span>Club Profile &bull; Gedung Indosat, Medan, Sumatera Utara</span>
                    </div>
                    <h2 class="font-serif text-2xl sm:text-4xl font-extrabold tracking-tight text-[#FAF5E6] leading-tight">
                        Club 61 Padel Court Medan
                    </h2>
                    <p class="text-xs sm:text-sm text-emerald-100/80 mt-3 font-medium leading-relaxed">
                        Venue olahraga padel modern di Medan dengan 4 lapangan panoramic indoor ber-AC standar World Padel Tour, sauna kayu cedar, cold plunge 4°C, dan specialty cafe lounge.
                    </p>

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-white/10 border border-white/20 text-xs font-bold text-white backdrop-blur-md">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            Venue Terbuka Hari Ini: 06:00 - 23:00 WIB
                        </div>
                        <a href="https://wa.me/6281261617233" target="_blank" 
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-[#DFC387]/20 hover:bg-[#DFC387]/30 border border-[#DFC387]/50 text-xs font-bold text-[#F5E6BE] backdrop-blur-md transition-colors">
                            <span>Hubungi Concierge via WhatsApp</span>
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
                        <div class="text-[10px] uppercase font-extrabold tracking-wider text-[#7A5818]">Jam Operasional</div>
                        <div class="font-serif font-black text-base text-[#1F170D] mt-0.5">Senin &ndash; Minggu (Setiap Hari)</div>
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
                        <div class="text-[10px] uppercase font-extrabold tracking-wider text-[#7A5818]">Lokasi Strategis</div>
                        <div class="font-serif font-black text-base text-[#1F170D] mt-0.5">Club 61 Padel Court Medan</div>
                        <div class="text-xs text-[#8C7A58] mt-1">Gedung Indosat, Jl. Perintis Kemerdekaan No. 39, Medan, Sumatera Utara</div>
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
                        <div class="font-serif font-black text-base text-[#1F170D] mt-0.5">Layanan Dedicated Member</div>
                        <div class="text-xs text-[#8C7A58] mt-1 font-mono">0812-6161-PADEL &bull; Free Valet VIP</div>
                    </div>
                </div>
            </div>

            <!-- Facilities Showcase: 6 Premium Cards -->
            <div class="space-y-4">
                <div class="flex items-center justify-between px-1">
                    <div>
                        <h3 class="font-serif font-extrabold text-lg text-[#1F170D]">Fasilitas Eksklusif Member</h3>
                        <p class="text-xs text-[#7A643E]">Standar kejuaraan internasional yang dirancang untuk kenyamanan atletik tingkat tinggi</p>
                    </div>
                    <span class="text-xs font-bold text-[#8C6418]">6 Fasilitas Terintegrasi</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    
                    <!-- Facility 1 -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex flex-col justify-between hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase">COURT</span>
                            </div>
                            <div>
                                <h4 class="font-serif font-black text-sm text-[#1F170D]">4 Panoramic Padel Courts</h4>
                                <p class="text-xs text-[#7A643E] mt-1.5 leading-relaxed">
                                    Kaca tempered 12mm tanpa pilar tengah, rumput Mondo Supercourt XN, pencahayaan LED 1000 lux anti-silau, dan indoor ber-AC.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#DFC387]/40 flex items-center justify-between text-[11px] font-bold text-[#7A5818]">
                            <span>Standar FIP / WPT</span>
                            <span class="text-emerald-700">Tersedia Harian</span>
                        </div>
                    </div>

                    <!-- Facility 2 -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex flex-col justify-between hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase">SAUNA</span>
                            </div>
                            <div>
                                <h4 class="font-serif font-black text-sm text-[#1F170D]">Cold Plunge 4°C &amp; Sauna</h4>
                                <p class="text-xs text-[#7A643E] mt-1.5 leading-relaxed">
                                    Kolam es recovery dengan sirkulasi ozon berkecepatan tinggi bersuhu 4°C dan sauna kayu cedar merah Finlandia untuk regenerasi otot pasca tanding.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#DFC387]/40 flex items-center justify-between text-[11px] font-bold text-[#7A5818]">
                            <span>Regenerasi Otot</span>
                            <span class="text-emerald-700">Gratis VIP Platinum</span>
                        </div>
                    </div>

                    <!-- Facility 3 -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex flex-col justify-between hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase">CAFE</span>
                            </div>
                            <div>
                                <h4 class="font-serif font-black text-sm text-[#1F170D]">Club 61 Cafe &amp; Lounge</h4>
                                <p class="text-xs text-[#7A643E] mt-1.5 leading-relaxed">
                                    Artisan protein smoothie bar, specialty single-origin espresso, menu brunch sehat, serta panoramic viewing deck menghadap ke court arena.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#DFC387]/40 flex items-center justify-between text-[11px] font-bold text-[#7A5818]">
                            <span>F&amp;B &bull; Social Lounge</span>
                            <span class="text-emerald-700">Buka 07:00 - 22:30</span>
                        </div>
                    </div>

                    <!-- Facility 4 -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex flex-col justify-between hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase">LOCKER</span>
                            </div>
                            <div>
                                <h4 class="font-serif font-black text-sm text-[#1F170D]">Smart Locker &amp; Rain Shower</h4>
                                <p class="text-xs text-[#7A643E] mt-1.5 leading-relaxed">
                                    Locker digital berteknologi RFID dengan charging port, kamar bilas mewah dengan rain shower bertekanan tinggi, hair dryer Dyson, dan amenities Le Labo.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#DFC387]/40 flex items-center justify-between text-[11px] font-bold text-[#7A5818]">
                            <span>Private Locker Room</span>
                            <span class="text-emerald-700">RFID Security</span>
                        </div>
                    </div>

                    <!-- Facility 5 -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex flex-col justify-between hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase">SHOP</span>
                            </div>
                            <div>
                                <h4 class="font-serif font-black text-sm text-[#1F170D]">Pro Shop &amp; Custom Gear</h4>
                                <p class="text-xs text-[#7A643E] mt-1.5 leading-relaxed">
                                    Penyediaan raket padel edisi terbatas (Babolat, Bullpadel, Nox), demo raket uji coba gratis, serta apparel dan grip original berstandar pro tour.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#DFC387]/40 flex items-center justify-between text-[11px] font-bold text-[#7A5818]">
                            <span>Padel Gear &amp; Rental</span>
                            <span class="text-emerald-700">Demo Rackets</span>
                        </div>
                    </div>

                    <!-- Facility 6 -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex flex-col justify-between hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#8C6418] uppercase">VALET</span>
                            </div>
                            <div>
                                <h4 class="font-serif font-black text-sm text-[#1F170D]">Dedicated Valet &amp; EV Charger</h4>
                                <p class="text-xs text-[#7A643E] mt-1.5 leading-relaxed">
                                    Area parkir privat khusus member dengan layanan valet gratis tanpa antri, dilengkapi fast charging station untuk mobil listrik.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#DFC387]/40 flex items-center justify-between text-[11px] font-bold text-[#7A5818]">
                            <span>Valet Service</span>
                            <span class="text-emerald-700">Gratis untuk Member</span>
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
                            <h3 class="font-serif font-black text-base text-[#1F170D]">Etiket &amp; Peraturan Club</h3>
                            <p class="text-[11px] text-[#7A643E]">Demi menjaga standar kenyamanan bersama antar seluruh member</p>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs text-[#3B2B11]">
                        <div class="flex items-start gap-3 p-3 rounded-2xl bg-[#FAF8F2] border border-[#DFC387]/60">
                            <span class="font-black text-[#8C6418] shrink-0">01.</span>
                            <div>
                                <strong class="text-[#1F170D]">Alas Kaki Khusus Padel:</strong>
                                <span class="text-[#7A643E] block mt-0.5">Pemain diwajibkan mengenakan sepatu olahraga khusus court / padel dengan sol non-marking untuk menjaga kelestarian rumput karpet.</span>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-3 rounded-2xl bg-[#FAF8F2] border border-[#DFC387]/60">
                            <span class="font-black text-[#8C6418] shrink-0">02.</span>
                            <div>
                                <strong class="text-[#1F170D]">Check-In Turnstile Tepat Waktu:</strong>
                                <span class="text-[#7A643E] block mt-0.5">Tunjukkan QR Code E-Tiket Anda pada turnstile gate minimal 10 menit sebelum jam sesi dimulai untuk validasi otomatis.</span>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-3 rounded-2xl bg-[#FAF8F2] border border-[#DFC387]/60">
                            <span class="font-black text-[#8C6418] shrink-0">03.</span>
                            <div>
                                <strong class="text-[#1F170D]">Prosedur Sauna &amp; Cold Plunge:</strong>
                                <span class="text-[#7A643E] block mt-0.5">Wajib membilas badan (shower) terlebih dahulu sebelum memasuki cold plunge demi higienitas dan kenyamanan member lain.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Privilege Summary & CTA (Col 5) -->
                <div class="lg:col-span-5 space-y-6">
                    <div class="bg-gradient-to-br from-[#1C2E24] to-[#0E1A14] text-white rounded-3xl p-6 border border-[#DFC387]/70 shadow-lg space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-[#DFC387]/20 text-[#F5E6BE] border border-[#DFC387]/40">
                                Privilese Anda
                            </span>
                            <span class="text-xs text-[#E5C378] font-bold">VIP Platinum</span>
                        </div>

                        <h4 class="font-serif font-black text-lg text-white">Keistimewaan Status Member</h4>
                        
                        <ul class="space-y-2.5 text-xs text-emerald-100/85 font-medium">
                            <li class="flex items-center gap-2">
                                <span class="text-[#E5C378] font-bold">&bull;</span>
                                <span>Prioritas booking lapangan hingga H-7</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-[#E5C378] font-bold">&bull;</span>
                                <span>Diskon 25% sewa lapangan &amp; rental raket</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-[#E5C378] font-bold">&bull;</span>
                                <span>Akses tak terbatas ke Finnish Sauna &amp; Ice Bath</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-[#E5C378] font-bold">&bull;</span>
                                <span>Undangan eksklusif turnamen internal member</span>
                            </li>
                        </ul>

                        <div class="pt-3 border-t border-white/15">
                            <a href="{{ route('customer.booking') }}" 
                               class="w-full py-3.5 px-4 rounded-2xl bg-gradient-to-b from-[#F5DE9B] to-[#D4AF37] text-[#1E160A] text-xs font-black uppercase tracking-wider flex items-center justify-center gap-2 shadow-md hover:brightness-105 active:scale-95 transition-all">
                                <span>Pesan Lapangan Sekarang &rarr;</span>
                            </a>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
