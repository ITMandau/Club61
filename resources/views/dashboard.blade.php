<x-app-layout>
    <div x-data="dashboardApp()" x-init="init()" class="py-6 sm:py-8 text-[#1F170D]">
        <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16 space-y-6">

            <!-- Desktop & Mobile Responsive Multi-Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- MAIN CONTENT (Cols 1 to 8 on Desktop) -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- 1. Hero Promo Banner: Executive Luxury Card -->
                    <div class="relative overflow-hidden rounded-3xl p-6 sm:p-8 text-white shadow-[0_15px_40px_rgba(20,40,30,0.25)] border border-[#DFC387]/60"
                         style="background: linear-gradient(135deg, #162E24 0%, #0F2019 60%, #0A1611 100%);">
                        <!-- Background Glow Accents -->
                        <div class="absolute -right-12 -bottom-12 w-96 h-96 rounded-full bg-[#D4AF37]/20 blur-3xl pointer-events-none"></div>
                        <div class="absolute top-0 right-1/4 w-72 h-72 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>

                        <div class="relative z-10 flex flex-col xl:flex-row xl:items-center justify-between gap-6">
                            <div class="max-w-2xl">
                                <div class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-[#DFC387]/20 text-[#F5E6BE] border border-[#DFC387]/40 mb-3 backdrop-blur-md shadow-sm">
                                    <span>✨ Member Portal &bull; VIP Platinum Experience</span>
                                </div>
                                <h1 class="font-serif text-2xl sm:text-4xl font-extrabold tracking-tight leading-tight text-[#FAF5E6]">
                                    VANTAGE <span class="text-[#E5C378] font-alex font-normal text-3xl sm:text-5xl block sm:inline">Sports &amp; Social</span>
                                </h1>
                                <p class="text-xs sm:text-sm text-emerald-100/80 mt-2.5 font-medium leading-relaxed">
                                    Selamat datang kembali, <span class="font-bold text-white">{{ Auth::user()->name }}</span>. Pesan lapangan padel panoramic standar WPT, nikmati sauna kayu cedar &amp; ice bath 4°C, serta specialty cafe lounge.
                                </p>

                                <div class="mt-6 flex flex-wrap items-center gap-3">
                                    <a href="{{ route('customer.booking') }}" 
                                       class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl text-xs font-black uppercase tracking-wider transition-all duration-150 transform active:scale-95 shadow-lg"
                                       style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 50%, #A87D18 100%); color: #1E160A; border: 1.5px solid #FFF3CD;">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        + Pesan Lapangan Padel
                                    </a>
                                    <a href="{{ route('customer.my-club') }}" 
                                       class="inline-flex items-center gap-1.5 px-5 py-3 rounded-2xl text-xs font-bold text-[#F5E6BE] bg-white/10 hover:bg-white/20 border border-white/20 backdrop-blur-md transition-colors">
                                        Lihat Fasilitas Club &rarr;
                                    </a>
                                </div>
                            </div>

                            <!-- Quick Stats Highlight Box on Wide Screens -->
                            <div class="hidden sm:grid grid-cols-2 xl:grid-cols-1 gap-3 shrink-0 xl:w-56">
                                <div class="p-3.5 rounded-2xl bg-white/10 border border-white/15 backdrop-blur-md">
                                    <div class="text-[10px] uppercase font-bold text-emerald-200">Ketersediaan Hari Ini</div>
                                    <div class="text-sm font-black text-white mt-0.5 font-serif">4 Lapangan Aktif</div>
                                    <div class="text-[10px] text-emerald-300/80 font-mono mt-0.5">AC Central &bull; 1000 Lux</div>
                                </div>
                                <div class="p-3.5 rounded-2xl bg-[#D4AF37]/20 border border-[#D4AF37]/40 backdrop-blur-md">
                                    <div class="text-[10px] uppercase font-bold text-[#F5E6BE]">Wellness Suite</div>
                                    <div class="text-sm font-black text-[#FAF5E6] mt-0.5 font-serif">Sauna &amp; Ice Bath 4°C</div>
                                    <div class="text-[10px] text-[#E5C378] font-mono mt-0.5">Free Access VIP Platinum</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. 4 Quick Action Cards (Screen 1 Mockup) -->
                    <div>
                        <div class="flex items-center justify-between mb-3 px-1">
                            <h3 class="font-serif font-extrabold text-sm text-[#5C410F] uppercase tracking-wider">Akses Cepat Fasilitas</h3>
                            <span class="text-[11px] text-[#8C7A58] font-medium">Club 61 Concierge</span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                            <!-- Action 1: Booking -->
                            <a href="{{ route('customer.booking') }}" 
                               class="flex flex-col items-center justify-center p-4 sm:p-5 rounded-2xl bg-white/95 backdrop-blur-xl border border-[#DFC387]/80 hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.25)] transition-all group active:scale-95">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-sm transition-transform group-hover:scale-110"
                                     style="background: linear-gradient(135deg, #FAF2DE 0%, #F3DFAD 100%); border: 1.5px solid #DFC387;">
                                    <svg class="w-6 h-6 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <span class="text-xs font-extrabold text-[#3B2B11] mt-2.5 group-hover:text-[#8C6418]">Booking Court</span>
                                <span class="text-[10px] text-[#8C7A58] mt-0.5">4 Lapangan Pro</span>
                            </a>

                            <!-- Action 2: Club -->
                            <a href="{{ route('customer.my-club') }}" 
                               class="flex flex-col items-center justify-center p-4 sm:p-5 rounded-2xl bg-white/95 backdrop-blur-xl border border-[#DFC387]/80 hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.25)] transition-all group active:scale-95">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-sm transition-transform group-hover:scale-110"
                                     style="background: linear-gradient(135deg, #FAF2DE 0%, #F3DFAD 100%); border: 1.5px solid #DFC387;">
                                    <svg class="w-6 h-6 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <span class="text-xs font-extrabold text-[#3B2B11] mt-2.5 group-hover:text-[#8C6418]">My Club</span>
                                <span class="text-[10px] text-[#8C7A58] mt-0.5">Venue &amp; Lokasi</span>
                            </a>

                            <!-- Action 3: Value Pack -->
                            <div onclick="alert('Paket Value Pack: Beli 10 Jam Padel Gratis 2 Jam Sauna & Ice Bath! Hubungi Concierge di WhatsApp 0812-6161-PADEL.')"
                                 class="flex flex-col items-center justify-center p-4 sm:p-5 rounded-2xl bg-white/95 backdrop-blur-xl border border-[#DFC387]/80 hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.25)] transition-all group cursor-pointer active:scale-95">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-sm transition-transform group-hover:scale-110"
                                     style="background: linear-gradient(135deg, #FAF2DE 0%, #F3DFAD 100%); border: 1.5px solid #DFC387;">
                                    <svg class="w-6 h-6 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <span class="text-xs font-extrabold text-[#3B2B11] mt-2.5 group-hover:text-[#8C6418]">Value Pack</span>
                                <span class="text-[10px] text-[#8C7A58] mt-0.5">Paket Hemat Jam</span>
                            </div>

                            <!-- Action 4: Turnamen -->
                            <div onclick="alert('Turnamen Mendatang: VANTAGE Padel Open 2026! Total Hadiah Rp 50.000.000. Pendaftaran dibuka untuk member.')"
                                 class="flex flex-col items-center justify-center p-4 sm:p-5 rounded-2xl bg-white/95 backdrop-blur-xl border border-[#DFC387]/80 hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.25)] transition-all group cursor-pointer active:scale-95">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-sm transition-transform group-hover:scale-110"
                                     style="background: linear-gradient(135deg, #FAF2DE 0%, #F3DFAD 100%); border: 1.5px solid #DFC387;">
                                    <svg class="w-6 h-6 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                    </svg>
                                </div>
                                <span class="text-xs font-extrabold text-[#3B2B11] mt-2.5 group-hover:text-[#8C6418]">Turnamen</span>
                                <span class="text-[10px] text-[#8C7A58] mt-0.5">Kompetisi Club</span>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Active Upcoming Match Card (Connected to Real Database) -->
                    <template x-if="activeMatch">
                        <div class="p-5 sm:p-6 rounded-3xl bg-white/95 backdrop-blur-xl border-2 border-[#D4AF37] shadow-[0_12px_35px_rgba(160,120,30,0.18)] flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl shadow-inner shrink-0"
                                     style="background: linear-gradient(145deg, #FBF6EB 0%, #EBD5A4 60%, #D4AF37 100%); border: 1.5px solid #BD923E;">
                                    🎾
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-serif font-black text-base sm:text-lg text-[#1F170D]" x-text="activeMatch.court ? activeMatch.court.name : 'Court Arena'"></span>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300 uppercase" x-text="activeMatch.status">CONFIRMED</span>
                                    </div>
                                    <p class="text-xs text-[#7A643E] font-medium mt-1">
                                        <span x-text="activeMatch.booking_date"></span> &bull; <span x-text="formatTime(activeMatch.start_time) + ' - ' + formatTime(activeMatch.end_time)"></span> WIB &bull; Kode: <span class="font-mono font-bold text-[#8C6418]" x-text="'#' + (activeMatch.booking_code || activeMatch.id.substring(0, 10))"></span>
                                    </p>
                                </div>
                            </div>

                            <a :href="'{{ route('customer.invoice') }}?booking_id=' + activeMatch.id" 
                               class="w-full sm:w-auto px-6 py-3 rounded-2xl text-center text-xs font-extrabold text-[#7A5818] bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] transition-colors shrink-0 shadow-sm">
                                Lihat E-Tiket &rarr;
                            </a>
                        </div>
                    </template>

                    <!-- Empty State for Active Match -->
                    <template x-if="!activeMatch && !isLoadingBookings">
                        <div class="p-5 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3.5">
                                <div class="w-12 h-12 rounded-2xl bg-[#FAF8F2] border border-[#DFC387] flex items-center justify-center text-xl shrink-0">
                                    📅
                                </div>
                                <div>
                                    <h4 class="font-serif font-black text-sm text-[#1F170D]">Belum Ada Tiket Pertandingan Aktif</h4>
                                    <p class="text-xs text-[#7A643E]">Pesan lapangan padel favorit Anda hari ini sebelum slot penuh.</p>
                                </div>
                            </div>
                            <a href="{{ route('customer.booking') }}" 
                               class="px-4 py-2 rounded-xl bg-[#1E3327] hover:bg-[#15241B] text-[#FAF5E6] text-xs font-bold shrink-0 transition-colors">
                                Booking Sekarang
                            </a>
                        </div>
                    </template>

                    <!-- 4. Riwayat Booking Terkini Table Card (Connected to Real Database) -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-serif font-black text-base text-[#1F170D]">Riwayat Aktivitas &amp; Sesi Lapangan</h3>
                                <p class="text-xs text-[#7A643E]">Daftar pemesanan lapangan padel dan wellness resmi Anda.</p>
                            </div>
                            <a href="{{ route('customer.invoice') }}" class="text-xs font-bold text-[#8C6418] hover:underline">
                                Semua Riwayat &rarr;
                            </a>
                        </div>

                        <!-- Empty State Table -->
                        <div x-show="bookings.length === 0 && !isLoadingBookings" class="text-xs text-[#8C7A58] italic py-8 text-center">
                            Belum ada riwayat pemesanan. Selesaikan booking lapangan pertama Anda!
                        </div>

                        <div x-show="bookings.length > 0" class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-[#3B2B11]">
                                <thead class="text-[#5C410F] uppercase text-[10px] tracking-wider"
                                       style="background: linear-gradient(90deg, #FBF6EB 0%, #EEDBB0 100%); border-bottom: 1.5px solid #DFC387;">
                                    <tr>
                                        <th class="p-3.5 rounded-l-xl">ID Booking</th>
                                        <th class="p-3.5">Fasilitas / Lapangan</th>
                                        <th class="p-3.5">Jadwal Sesi</th>
                                        <th class="p-3.5">Biaya</th>
                                        <th class="p-3.5 rounded-r-xl">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#EEDBB0]/60">
                                    <template x-for="item in bookings" :key="item.id">
                                        <tr class="hover:bg-amber-50/40 transition-colors">
                                            <td class="p-3.5 font-mono font-bold text-[#8C6418]">
                                                <a :href="'{{ route('customer.invoice') }}?booking_id=' + item.id" class="hover:underline" x-text="'#' + (item.booking_code || item.id.substring(0, 8))"></a>
                                            </td>
                                            <td class="p-3.5 font-bold text-[#1F170D]" x-text="item.court ? item.court.name : 'Court Arena'"></td>
                                            <td class="p-3.5 text-[#523F1C]" x-text="item.booking_date + ', ' + formatTime(item.start_time) + ' - ' + formatTime(item.end_time)"></td>
                                            <td class="p-3.5 font-mono font-bold text-[#1F170D]" x-text="'Rp ' + formatNumber(item.total_amount)"></td>
                                            <td class="p-3.5">
                                                <span :class="item.status === 'PAID' || item.status === 'CONFIRMED' ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-amber-100 text-amber-800 border-amber-300'"
                                                      class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold uppercase border"
                                                      x-text="item.status">
                                                </span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- SIDEBAR COLUMN (Cols 9 to 12 on Desktop) -->
                <div class="lg:col-span-4 space-y-6">

                    <!-- Member Profile & VIP Card -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border-2 border-[#D4AF37] shadow-[0_15px_40px_rgba(160,120,30,0.18)] text-center relative overflow-hidden">
                        <div class="absolute -right-8 -top-8 w-28 h-28 bg-[#D4AF37]/15 rounded-full blur-xl pointer-events-none"></div>

                        <div class="w-20 h-20 mx-auto rounded-full p-1 bg-gradient-to-tr from-[#D4AF37] via-[#FFF3CD] to-[#8C6418] shadow-md">
                            <div class="w-full h-full rounded-full bg-[#FAF2DE] flex items-center justify-center text-2xl font-black text-[#7A5818] font-serif border border-white">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </div>
                        </div>

                        <h2 class="font-serif font-black text-lg text-[#1F170D] mt-3">{{ Auth::user()->name }}</h2>
                        <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-[#FAF2DE] text-[#7A5818] border border-[#DFC387] mt-1 shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>VIP Platinum Active</span>
                        </div>

                        <!-- 4 Stats Micro Grid -->
                        <div class="grid grid-cols-2 gap-2.5 mt-5 text-left">
                            <div class="p-3 rounded-xl bg-[#FAF8F2] border border-[#E8DCC0]">
                                <span class="text-[9px] uppercase font-bold text-[#8C7A58] block">Total Match</span>
                                <span class="font-serif font-black text-base text-[#1F170D]" x-text="totalMatchCount + ' Sesi'">0 Sesi</span>
                            </div>
                            <div class="p-3 rounded-xl bg-[#FAF8F2] border border-[#E8DCC0]">
                                <span class="text-[9px] uppercase font-bold text-[#8C7A58] block">Peringkat</span>
                                <span class="font-serif font-black text-base text-[#B8860B]">Tier Gold III</span>
                            </div>
                            <div class="p-3 rounded-xl bg-[#FAF8F2] border border-[#E8DCC0]">
                                <span class="text-[9px] uppercase font-bold text-[#8C7A58] block">Status Akun</span>
                                <span class="font-serif font-black text-base text-emerald-700">Aktif</span>
                            </div>
                            <div class="p-3 rounded-xl bg-[#FAF8F2] border border-[#E8DCC0]">
                                <span class="text-[9px] uppercase font-bold text-[#8C7A58] block">Masa Berlaku</span>
                                <span class="font-serif font-black text-xs text-[#1F170D]">31 Des 2026</span>
                            </div>
                        </div>

                        <div class="mt-5 pt-4 border-t border-[#DFC387]/60">
                            <a href="{{ route('profile.edit') }}" class="text-xs font-bold text-[#8C6418] hover:text-[#5C410F] transition-colors flex items-center justify-center gap-1">
                                <span>Kelola Pengaturan Profil</span>
                                <span>&rarr;</span>
                            </a>
                        </div>
                    </div>

                    <!-- Membership Promo Card -->
                    <div class="relative overflow-hidden rounded-3xl p-6 text-white shadow-lg border border-[#DFC387]/70"
                         style="background: linear-gradient(135deg, #1F382B 0%, #15271E 100%);">
                        <div class="relative z-10 space-y-3">
                            <div class="inline-block px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-[#E5FF44]/20 text-[#E5FF44] border border-[#E5FF44]/40">
                                Privilege VIP
                            </div>
                            <h3 class="font-serif text-lg font-black text-white leading-tight">
                                Upgrade Membership Diamond Club 🎾
                            </h3>
                            <p class="text-xs text-emerald-100/80 leading-relaxed font-medium">
                                Dapatkan prioritas booking H-7, diskon 25% sewa lapangan, dan akses tak terbatas sauna &amp; cold plunge setiap pekan!
                            </p>
                            <button type="button" 
                                    onclick="alert('Pendaftaran Membership VIP: Kunjungi Frontdesk atau WhatsApp concierge di 0812-6161-PADEL.')"
                                    class="w-full py-3 rounded-xl text-xs font-black uppercase tracking-wider text-[#1E160A] shadow-md hover:brightness-105 active:scale-95 transition-all"
                                    style="background: linear-gradient(180deg, #FBF0CE 0%, #D4AF37 60%, #B38622 100%); border: 1px solid #FFF3CD;">
                                Gabung Membership Sekarang
                            </button>
                        </div>
                    </div>

                    <!-- Venue Operating Info & Concierge -->
                    <div class="p-5 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm space-y-3 text-xs">
                        <div class="font-serif font-black text-sm text-[#1F170D] flex items-center gap-2">
                            <span>🏛️</span>
                            <span>VANTAGE Club 61 Senopati</span>
                        </div>
                        <div class="text-[#7A643E] space-y-1.5 leading-relaxed">
                            <div><strong class="text-[#3B2B11]">Alamat:</strong> Jl. Senopati No. 61, Kebayoran Baru, Jakarta Selatan</div>
                            <div><strong class="text-[#3B2B11]">Jam Buka:</strong> Setiap Hari &bull; 06:00 - 23:00 WIB</div>
                            <div><strong class="text-[#3B2B11]">WhatsApp Concierge:</strong> 0812-6161-PADEL</div>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </div>

    <script>
        function dashboardApp() {
            return {
                bookings: [],
                activeMatch: null,
                isLoadingBookings: true,

                async init() {
                    await this.loadMyBookings();
                },

                async loadMyBookings() {
                    this.isLoadingBookings = true;
                    try {
                        const res = await fetch('/api/v1/padel/my-bookings');
                        const json = await res.json();
                        if (json.success && json.data) {
                            this.bookings = json.data;

                            // Temukan pertandingan aktif mendatang yang berstatus PAID / CONFIRMED
                            this.activeMatch = this.bookings.find(b => b.status === 'PAID' || b.status === 'CONFIRMED');
                        }
                    } catch(e) {
                        console.error('Gagal mengambil data dashboard:', e);
                    } finally {
                        this.isLoadingBookings = false;
                    }
                },

                get totalMatchCount() {
                    return this.bookings.filter(b => b.status === 'PAID' || b.status === 'CONFIRMED' || b.status === 'COMPLETED').length;
                },

                formatNumber(val) {
                    if (!val) return '0';
                    return Math.round(val).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                },

                formatTime(isoString) {
                    if (!isoString) return '--:--';
                    try {
                        const d = new Date(isoString);
                        if (!isNaN(d.getTime())) {
                            return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
                        }
                    } catch(e) {}
                    return isoString.substring(11, 16) || isoString;
                }
            }
        }
    </script>
</x-app-layout>