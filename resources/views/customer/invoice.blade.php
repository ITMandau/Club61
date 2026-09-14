<x-app-layout>
    <div x-data="invoiceApp()" x-init="init()" class="py-6 sm:py-8 text-[#1F170D]">
        <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16 space-y-6">

            <!-- Top Header & Breadcrumb -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white/95 backdrop-blur-xl p-4 sm:p-5 rounded-3xl border border-[#DFC387] shadow-sm">
                <div class="flex items-center gap-3.5">
                    <a href="{{ route('dashboard') }}" class="p-2.5 rounded-2xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] transition-colors shrink-0" title="Kembali ke Beranda">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="font-serif font-black text-xl sm:text-2xl text-[#1F170D]">Invoice &amp; E-Tiket Digital</h1>
                            <span :class="ticket && (ticket.status === 'PAID' || ticket.status === 'CONFIRMED') ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-amber-100 text-amber-800 border-amber-300'"
                                  class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border"
                                  x-text="ticket ? ticket.status : 'MEMUAT...'">
                            </span>
                        </div>
                        <p class="text-xs text-[#7A643E] mt-0.5">Tunjukkan QR Code e-tiket ini pada turnstile gate venue untuk check-in lapangan langsung</p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 sm:gap-3 w-full sm:w-auto">
                    <button onclick="window.print()" class="flex-1 sm:flex-none justify-center px-4 py-2.5 rounded-2xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] text-xs font-bold transition-all shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span>Cetak E-Tiket</span>
                    </button>
                    <a href="{{ route('customer.booking') }}" 
                       class="flex-1 sm:flex-none text-center px-5 py-2.5 rounded-2xl bg-gradient-to-b from-[#F5DE9B] to-[#D4AF37] text-[#1E160A] text-xs font-black uppercase tracking-wider shadow-md hover:brightness-105 active:scale-95 transition-all">
                        + Booking Baru
                    </a>
                </div>
            </div>

            <!-- QA DEFENSE 2: Auto-Polling Banner saat Status masih Pending (Race Condition Guard) -->
            <div x-show="isPolling" 
                 style="display: none;"
                 class="p-4 rounded-2xl bg-amber-500/15 border border-amber-400 text-amber-900 flex items-center justify-between gap-4 shadow-sm animate-pulse">
                <div class="flex items-center gap-3">
                    <div class="w-5 h-5 border-2 border-amber-600 border-t-transparent rounded-full animate-spin shrink-0"></div>
                    <div>
                        <div class="text-xs font-black uppercase tracking-wider">Menunggu Konfirmasi Pembayaran Midtrans...</div>
                        <div class="text-[11px] text-amber-800 mt-0.5">Sistem sedang memverifikasi settlement pembayaran Anda secara otomatis setiap 3 detik. Halaman tidak perlu direfresh.</div>
                    </div>
                </div>
                <span class="text-xs font-mono font-bold text-amber-900 bg-amber-100 px-3 py-1 rounded-full border border-amber-300 shrink-0" x-text="'Polling ' + pollCount + '/10'"></span>
            </div>

            <!-- Loading State -->
            <div x-show="isLoading" class="py-16 text-center space-y-3">
                <div class="w-10 h-10 border-3 border-[#D4AF37] border-t-transparent rounded-full animate-spin mx-auto"></div>
                <div class="text-xs font-bold text-[#8C6418]">Memuat data e-tiket dari database...</div>
            </div>

            <!-- Multi-Column Desktop Layout (Col 7 / Col 5) -->
            <template x-if="!isLoading && currentTicket">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- Left Column: Active E-Ticket Card (Col 7) -->
                <div class="lg:col-span-7 flex flex-col gap-4">

                    <!-- Session Switcher Tabs if Order has Multiple Sesi (e.g. Non-Contiguous Jadwal Bolong) -->
                    <template x-if="ticket.order_bookings && ticket.order_bookings.length > 1">
                        <div class="bg-white/95 backdrop-blur-xl p-3.5 rounded-2xl border border-[#DFC387] shadow-sm flex items-center gap-2 overflow-x-auto">
                            <span class="text-[11px] font-bold text-[#7A5818] uppercase tracking-wider shrink-0">Tiket Sesi Main:</span>
                            <div class="flex items-center gap-2">
                                <template x-for="(sBooking, sIdx) in ticket.order_bookings" :key="sBooking.id">
                                    <button type="button"
                                            @click="switchSession(sBooking)"
                                            :class="currentTicket.id === sBooking.id ? 'bg-[#183428] text-[#FAF5E6] border-[#183428] shadow-sm' : 'bg-[#FAF2DE] text-[#7A5818] border-[#DFC387] hover:bg-[#F3DFAD]'"
                                            class="px-3.5 py-1.5 rounded-xl border text-xs font-bold transition-all shrink-0 flex items-center gap-1.5">
                                        <span x-text="'Sesi ' + (sIdx + 1) + ':'"></span>
                                        <span class="font-mono" x-text="formatTime(sBooking.start_time) + ' - ' + formatTime(sBooking.end_time)"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="bg-white/95 backdrop-blur-xl rounded-3xl border-2 border-[#D4AF37] shadow-[0_20px_50px_rgba(160,120,30,0.22)] overflow-hidden">
                        
                        <!-- Ticket Header Banner -->
                        <div class="p-4 sm:p-6 bg-gradient-to-r from-[#183428] via-[#10241B] to-[#0A1812] text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
                            <div>
                                <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-[#DFC387]/20 text-[#F5E6BE] border border-[#DFC387]/40 inline-block mb-1.5">
                                    Official Boarding Pass &bull; Padel Court
                                </span>
                                <h2 class="font-serif font-black text-xl sm:text-2xl text-white" x-text="currentTicket.court ? currentTicket.court.name : 'Court Arena'"></h2>
                                <p class="text-xs text-emerald-100/80 mt-1 font-mono">
                                    <span x-text="formatDate(currentTicket.booking_date)"></span> &bull; <span x-text="formatTime(currentTicket.start_time) + ' - ' + formatTime(currentTicket.end_time)"></span> WIB 
                                    <span class="text-amber-300 font-bold" x-text="'(' + calculateDuration(currentTicket.start_time, currentTicket.end_time) + ' Jam)'"></span>
                                </p>
                            </div>

                            <div class="text-left sm:text-right">
                                <span :class="currentTicket.status === 'PAID' || currentTicket.status === 'CONFIRMED' || currentTicket.status === 'CHECKED_IN' ? 'bg-emerald-500 text-white' : 'bg-amber-400 text-[#1E160A]'"
                                      class="px-3.5 py-1.5 rounded-full text-xs font-black shadow-sm uppercase tracking-wider inline-block"
                                      x-text="currentTicket.status">
                                </span>
                                <div class="text-[11px] text-emerald-200 mt-1 font-mono" x-text="'Kode: #' + (currentTicket.booking_code || currentTicket.id.substring(0, 10))"></div>
                            </div>
                        </div>

                        <!-- Ticket Perforated Divider Bar -->
                        <div class="relative py-2.5 bg-[#FAF6EC] border-t border-b border-dashed border-[#DFC387] px-4 sm:px-6 flex items-center justify-between text-xs text-[#7A5818] font-bold">
                            <div class="flex items-center gap-2">
                                <span x-text="currentTicket.status === 'PAID' || currentTicket.status === 'CHECKED_IN' ? 'STATUS: VALID ENTRY PASS' : 'STATUS: MENUNGGU PEMBAYARAN'"></span>
                                <span class="text-[#DFC387]">&bull;</span>
                                <span>GATE: FRONTDESK VENUE</span>
                            </div>
                            <span class="font-mono text-[11px]" x-text="'DURASI ' + calculateDuration(currentTicket.start_time, currentTicket.end_time) + ' JAM'"></span>
                        </div>

                        <!-- QR Code Body for Check-in -->
                        <div class="p-4 sm:p-6 lg:p-8 text-center flex flex-col gap-5 items-stretch">
                            
                            <!-- Dynamic QR Turnstile: Hanya aktif jika status PAID atau CHECKED_IN -->
                            <template x-if="currentTicket.status === 'PAID' || currentTicket.status === 'CHECKED_IN'">
                                <div class="w-full">
                                    <div class="p-4 bg-white rounded-3xl border-2 border-dashed border-[#DFC387] inline-block shadow-inner">
                                        <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(currentTicket.qr_code_hash || 'CLUB61-DEMO')" 
                                             alt="QR Check-in" 
                                             class="w-44 h-44 sm:w-48 sm:h-48 mx-auto rounded-xl" />
                                        <div class="mt-3 font-mono font-black text-xs text-[#8C6418] tracking-widest break-all" x-text="currentTicket.qr_code_hash || currentTicket.booking_code"></div>
                                    </div>

                                    <div class="max-w-md mx-auto mt-4">
                                        <h4 class="font-serif font-black text-base text-[#1F170D]">Tunjukkan Pada Kasir Frontdesk</h4>
                                        <p class="text-xs text-[#7A643E] mt-1 leading-relaxed">
                                            Tunjukkan QR Code ini kepada kasir saat tiba di venue untuk check-in lapangan sekaligus mengambil peralatan sewa (raket &amp; bola).
                                        </p>
                                    </div>
                                </div>
                            </template>

                            <!-- Placeholder Edukatif Jika Belum Lunas (PENDING_PAYMENT / Belum Bayar) -->
                            <template x-if="currentTicket.status !== 'PAID' && currentTicket.status !== 'CHECKED_IN'">
                                <div class="max-w-md mx-auto p-5 sm:p-6 rounded-3xl border-2 border-dashed border-amber-300 bg-amber-50/70 text-center space-y-3">
                                    <div class="w-14 h-14 rounded-2xl bg-amber-100 border border-amber-300 flex items-center justify-center mx-auto text-amber-700">
                                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-4a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-serif font-black text-base text-[#1F170D]">QR Tiket Terkunci</h4>
                                        <p class="text-xs text-[#7A643E] mt-1 leading-relaxed">
                                            Selesaikan pembayaran terlebih dahulu untuk membuka QR Code pass turnstile lapangan. QR Code akan aktif otomatis setelah status pembayaran terverifikasi lunas.
                                        </p>
                                    </div>
                                    <div class="inline-block px-3 py-1 rounded-full text-[11px] font-mono font-bold bg-amber-200/80 text-amber-900 border border-amber-300">
                                        Status: <span x-text="currentTicket.status"></span>
                                    </div>
                                </div>
                            </template>

                            <!-- Breakdown Details: Fully Responsive on Mobile & Desktop -->
                            <div class="bg-[#FAF8F2] p-4 sm:p-5 rounded-2xl border border-[#DFC387]/70 text-left text-xs space-y-2.5 sm:space-y-2">
                                <!-- Nama Pemegang Tiket -->
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-4 pb-2 sm:pb-1.5 border-b border-[#DFC387]/30 text-[#5C410F]">
                                    <span class="text-[11px] sm:text-xs font-semibold text-[#8C6418] uppercase tracking-wider shrink-0">Nama Pemegang Tiket:</span>
                                    <span class="font-bold text-xs sm:text-sm text-[#1F170D] sm:text-right break-words leading-snug">{{ Auth::user()->name }} (VIP Platinum)</span>
                                </div>

                                <!-- Waktu Booking -->
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-4 pb-2 sm:pb-1.5 border-b border-[#DFC387]/30 text-[#5C410F]">
                                    <span class="text-[11px] sm:text-xs font-semibold text-[#8C6418] uppercase tracking-wider shrink-0">Waktu Booking:</span>
                                    <span class="font-mono font-bold text-xs sm:text-sm text-[#1F170D] sm:text-right" x-text="formatTime(currentTicket.start_time) + ' - ' + formatTime(currentTicket.end_time) + ' WIB (' + calculateDuration(currentTicket.start_time, currentTicket.end_time) + ' Jam)'"></span>
                                </div>

                                <!-- Lokasi Lapangan -->
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-4 pb-2 sm:pb-1.5 border-b border-[#DFC387]/30 text-[#5C410F]">
                                    <span class="text-[11px] sm:text-xs font-semibold text-[#8C6418] uppercase tracking-wider shrink-0">Lokasi Lapangan:</span>
                                    <span class="font-bold text-xs sm:text-sm text-[#1F170D] sm:text-right leading-snug" x-text="(currentTicket.court ? currentTicket.court.name : 'Court 1') + ' &bull; Indoor Central AC'"></span>
                                </div>

                                <!-- Biaya Sesi Ini -->
                                <div class="flex justify-between items-center gap-3 pb-2 sm:pb-1.5 border-b border-[#DFC387]/30 text-[#5C410F]">
                                    <span class="text-[11px] sm:text-xs font-semibold text-[#8C6418] uppercase tracking-wider shrink-0">Biaya Sesi Ini:</span>
                                    <span class="font-mono font-bold text-xs sm:text-sm text-[#1F170D] whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(currentTicket.court_fee)"></span>
                                </div>

                                <!-- Sewa Alat (Add-ons) jika ada -->
                                <div class="flex justify-between items-center gap-3 pb-2 sm:pb-1.5 border-b border-[#DFC387]/30 text-[#5C410F]" x-show="currentTicket.equipment_fee > 0">
                                    <span class="text-[11px] sm:text-xs font-semibold text-[#8C6418] uppercase tracking-wider shrink-0">Sewa Alat (Add-ons):</span>
                                    <span class="font-mono font-bold text-xs sm:text-sm text-[#1F170D] whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(currentTicket.equipment_fee)"></span>
                                </div>

                                <!-- Total Pembayaran Tiket -->
                                <div class="pt-2.5 sm:pt-3 border-t border-[#DFC387]/60 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1.5 sm:gap-4">
                                    <span class="font-serif font-black text-xs sm:text-sm text-[#1F170D] uppercase tracking-wider shrink-0">Total Pembayaran Tiket:</span>
                                    <span class="font-mono font-black text-base sm:text-lg text-[#1F170D] whitespace-nowrap sm:text-right" x-text="'Rp ' + formatNumber(currentTicket.total_amount)"></span>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- Right Column: Invoice Details & Past History (Col 5) -->
                <div class="lg:col-span-5 flex flex-col gap-6">

                    <!-- Official Tax Invoice Card -->
                    <div class="bg-white/95 backdrop-blur-xl rounded-3xl border border-[#DFC387] shadow-[0_12px_35px_rgba(160,120,30,0.15)] p-4 sm:p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-[#DFC387]/50 pb-3">
                            <div>
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-[#7A5818]">Bukti Bayar Resmi</span>
                                <h3 class="font-serif font-black text-base text-[#1F170D]">Ringkasan Transaksi</h3>
                            </div>
                            <span class="font-mono text-xs font-bold text-[#8C6418]" x-text="'#' + (ticket.order_id || ticket.booking_code || ticket.id.substring(0, 10))"></span>
                        </div>

                        <div class="space-y-2.5 text-xs text-[#5C410F]">
                            <div class="flex justify-between items-center gap-3">
                                <span>Tanggal Reservasi:</span>
                                <span class="font-mono text-[#1F170D] font-bold whitespace-nowrap text-right" x-text="formatDate(ticket.booking_date)"></span>
                            </div>
                            <div class="flex justify-between items-center gap-3">
                                <span>Total Sewa Lapangan:</span>
                                <span class="font-mono text-[#1F170D] whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(displayCourtFee)"></span>
                            </div>
                            <div class="flex justify-between items-center gap-3">
                                <span>Peralatan Sewa (Flat):</span>
                                <span class="font-mono text-[#1F170D] whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(displayEquipmentFee)"></span>
                            </div>
                            <div class="flex justify-between items-center gap-3">
                                <span>Status Settlement:</span>
                                <span class="font-mono text-emerald-700 font-bold whitespace-nowrap text-right" x-text="ticket.status"></span>
                            </div>

                            <div class="pt-3 border-t border-[#DFC387]/60 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1.5 sm:gap-4 text-sm">
                                <span class="font-serif font-black text-xs sm:text-sm text-[#1F170D] shrink-0">Total Transaksi (Invoice):</span>
                                <span class="font-mono font-black text-base sm:text-lg text-[#1F170D] whitespace-nowrap sm:text-right" x-text="'Rp ' + formatNumber(displayGrandTotal)"></span>
                            </div>
                        </div>

                        <div class="pt-2">
                            <div class="p-3 rounded-2xl bg-[#FAF8F2] border border-[#DFC387]/70 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-[#1F170D]">Midtrans Payment Gateway</span>
                                </div>
                                <span :class="ticket.status === 'PAID' || ticket.status === 'CHECKED_IN' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                      class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full"
                                      x-text="ticket.status">
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Past Booking History (Real Database, Excludes Current Order) -->
                    <div class="bg-white/95 backdrop-blur-xl rounded-3xl border border-[#DFC387] shadow-[0_12px_35px_rgba(160,120,30,0.15)] p-4 sm:p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-[#DFC387]/50 pb-3">
                            <h3 class="font-serif font-black text-base text-[#1F170D]">Riwayat Booking Lainnya</h3>
                            <span class="text-[11px] text-[#7A643E] font-medium" x-text="pastBookings.length + ' Riwayat'"></span>
                        </div>

                        <div x-show="pastBookings.length === 0" class="text-xs text-[#8C7A58] italic py-2 text-center">
                            Belum ada riwayat booking sebelumnya.
                        </div>

                        <div x-show="pastBookings.length > 0" class="space-y-2.5 text-xs">
                            <template x-for="item in pastBookings" :key="item.id">
                                <a :href="'{{ route('customer.invoice') }}?booking_id=' + item.id"
                                   class="p-3.5 rounded-2xl bg-[#FAF8F2] hover:bg-[#FAF2DE] border border-[#DFC387]/70 flex items-center justify-between transition-colors block">
                                    <div>
                                        <div class="font-bold text-[#1F170D]" x-text="item.court ? item.court.name : 'Court Arena'"></div>
                                        <div class="text-[10px] text-[#7A643E]" x-text="formatDate(item.booking_date) + ' &bull; #' + (item.booking_code || item.id.substring(0, 8))"></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-mono font-bold text-[#1F170D] whitespace-nowrap" x-text="'Rp ' + formatNumber(item.total_amount)"></div>
                                        <span :class="item.status === 'PAID' || item.status === 'CHECKED_IN' ? 'text-emerald-700' : 'text-amber-700'"
                                              class="text-[9px] font-bold uppercase"
                                              x-text="item.status">
                                        </span>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </div>

                    <!-- Need Help & Support -->
                    <div class="p-4 sm:p-5 rounded-3xl bg-gradient-to-r from-[#FAF2DE] via-[#F5E6BE] to-[#FAF2DE] border border-[#DFC387] flex items-center justify-between gap-4">
                        <div>
                            <h4 class="font-serif font-black text-sm text-[#1F170D]">Butuh Bantuan Check-In?</h4>
                            <p class="text-xs text-[#7A643E]">Frontdesk concierge kami siap membantu 24/7 di turnstile gate.</p>
                        </div>
                        <a href="https://wa.me/6281261617233" target="_blank" 
                            class="px-4 py-2 rounded-xl bg-[#1E3327] text-[#FAF5E6] text-xs font-bold shrink-0 hover:bg-[#15241B] transition-colors">
                            Bantuan
                        </a>
                    </div>

                </div>

            </div>
        </template>

        </div>
    </div>

    <script>
        function invoiceApp() {
            return {
                isLoading: true,
                isPolling: false,
                pollCount: 0,
                maxPolls: 10,
                pollingInterval: null,
                ticket: null,
                currentTicket: null,
                pastBookings: [],

                get displayCourtFee() {
                    if (this.ticket && this.ticket.order_court_fee) return this.ticket.order_court_fee;
                    return this.ticket ? this.ticket.court_fee : 0;
                },

                get displayEquipmentFee() {
                    if (this.ticket && this.ticket.order_equipment_fee !== undefined) return this.ticket.order_equipment_fee;
                    return this.ticket ? (this.ticket.equipment_fee || 0) : 0;
                },

                get displayGrandTotal() {
                    if (this.ticket && this.ticket.order_grand_total) return this.ticket.order_grand_total;
                    return this.ticket ? this.ticket.total_amount : 0;
                },

                async init() {
                    const urlParams = new URLSearchParams(window.location.search);
                    const bookingId = urlParams.get('booking_id');
                    const orderId = urlParams.get('order_id');

                    const lookupKey = bookingId || orderId;

                    if (lookupKey) {
                        await this.loadTicket(lookupKey);
                    } else {
                        await this.loadLatestBooking();
                    }

                    await this.loadMyBookings();
                    this.isLoading = false;
                },

                async loadTicket(id) {
                    try {
                        const res = await fetch(`/api/v1/padel/bookings/${id}/ticket`);
                        const json = await res.json();
                        if (json.success && json.data) {
                            this.ticket = json.data;
                            this.currentTicket = json.data;

                            // QA DEFENSE 2: Jika status masih PENDING / PENDING_PAYMENT, lakukan Auto-Polling
                            if (this.ticket.status === 'PENDING' || this.ticket.status === 'PENDING_PAYMENT') {
                                this.startAutoPolling(id);
                            }
                        }
                    } catch(e) {
                        console.error('Gagal mengambil tiket:', e);
                    }
                },

                switchSession(sBooking) {
                    this.currentTicket = sBooking;
                },

                async loadLatestBooking() {
                    try {
                        const res = await fetch('/api/v1/padel/my-bookings');
                        const json = await res.json();
                        if (json.success && json.data && json.data.length > 0) {
                            const latest = json.data[0];
                            await this.loadTicket(latest.id);
                        }
                    } catch(e) {
                        console.error('Gagal mengambil booking terbaru:', e);
                    }
                },

                async loadMyBookings() {
                    try {
                        const res = await fetch('/api/v1/padel/my-bookings');
                        const json = await res.json();
                        if (json.success && json.data) {
                            // Filter keluar tiket saat ini dan tiket lain yang berada dalam order yang sama
                            this.pastBookings = json.data.filter(b => {
                                if (!this.ticket) return true;
                                if (b.id === this.ticket.id) return false;
                                if (this.ticket.order_id && b.order_id === this.ticket.order_id) return false;
                                return true;
                            });
                        }
                    } catch(e) {
                        console.error('Gagal mengambil riwayat booking:', e);
                    }
                },

                calculateDuration(start, end) {
                    if (!start || !end) return 1;
                    try {
                        const s = new Date(start).getTime();
                        const e = new Date(end).getTime();
                        if (!isNaN(s) && !isNaN(e)) {
                            return Math.max(1, Math.round((e - s) / (1000 * 60 * 60)));
                        }
                    } catch(e) {}
                    return 1;
                },

                /**
                 * QA DEFENSE 2: Auto-Polling anti race condition Webhook vs Redirect
                 */
                startAutoPolling(id) {
                    this.isPolling = true;
                    this.pollCount = 0;

                    this.pollingInterval = setInterval(async () => {
                        this.pollCount++;
                        try {
                            const res = await fetch(`/api/v1/padel/bookings/${id}/ticket`);
                            const json = await res.json();
                            if (json.success && json.data) {
                                this.ticket = json.data;
                                this.currentTicket = json.data;

                                if (this.ticket.status === 'PAID' || this.ticket.status === 'CONFIRMED' || this.ticket.status === 'CHECKED_IN') {
                                    clearInterval(this.pollingInterval);
                                    this.isPolling = false;
                                }
                            }
                        } catch(e) {}

                        if (this.pollCount >= this.maxPolls) {
                            clearInterval(this.pollingInterval);
                            this.isPolling = false;
                        }
                    }, 3000);
                },

                formatNumber(val) {
                    if (!val) return '0';
                    return Math.round(val).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                },

                formatDate(val) {
                    if (!val) return '-';
                    try {
                        const d = new Date(val);
                        if (!isNaN(d.getTime())) {
                            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                            return `${String(d.getDate()).padStart(2, '0')} ${months[d.getMonth()]} ${d.getFullYear()}`;
                        }
                    } catch(e) {}
                    return String(val).substring(0, 10);
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
