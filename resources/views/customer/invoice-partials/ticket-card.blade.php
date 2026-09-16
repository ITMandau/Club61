<!-- Session Switcher Tabs if Order has Multiple Sesi (e.g. Non-Contiguous Jadwal Bolong) -->
<template x-if="ticket.order_bookings && ticket.order_bookings.length > 1">
    <div
        class="bg-white/95 backdrop-blur-xl p-3.5 rounded-2xl border border-[#DFC387] shadow-sm flex items-center gap-2 overflow-x-auto">
        <span class="text-[11px] font-bold text-[#7A5818] uppercase tracking-wider shrink-0">Tiket Sesi Main:</span>
        <div class="flex items-center gap-2">
            <template x-for="(sBooking, sIdx) in ticket.order_bookings" :key="sBooking.id">
                <button type="button" @click="switchSession(sBooking)"
                    :class="currentTicket.id === sBooking.id ? 'bg-[#183428] text-[#FAF5E6] border-[#183428] shadow-sm' :
                        'bg-[#FAF2DE] text-[#7A5818] border-[#DFC387] hover:bg-[#F3DFAD]'"
                    class="px-3.5 py-1.5 rounded-xl border text-xs font-bold transition-all shrink-0 flex items-center gap-1.5">
                    <span x-text="'Sesi ' + (sIdx + 1) + ':'"></span>
                    <span class="font-mono"
                        x-text="formatTime(sBooking.start_time) + ' - ' + formatTime(sBooking.end_time)"></span>
                </button>
            </template>
        </div>
    </div>
</template>

<div
    class="bg-white/95 backdrop-blur-xl rounded-3xl border-2 border-[#D4AF37] shadow-[0_20px_50px_rgba(160,120,30,0.22)] overflow-hidden">

    <!-- Ticket Header Banner -->
    <div
        class="p-4 sm:p-6 bg-gradient-to-r from-[#183428] via-[#10241B] to-[#0A1812] text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
        <div>
            <span
                class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-[#DFC387]/20 text-[#F5E6BE] border border-[#DFC387]/40 inline-block mb-1.5">
                Official Boarding Pass &bull; Padel Court
            </span>
            <h2 class="font-serif font-black text-xl sm:text-2xl text-white"
                x-text="currentTicket.court ? currentTicket.court.name : 'Court Arena'"></h2>
            <p class="text-xs text-emerald-100/80 mt-1 font-mono">
                <span x-text="formatDate(currentTicket.booking_date)"></span> &bull; <span
                    x-text="formatTime(currentTicket.start_time) + ' - ' + formatTime(currentTicket.end_time)"></span>
                WIB
                <span class="text-amber-300 font-bold"
                    x-text="'(' + calculateDuration(currentTicket.start_time, currentTicket.end_time) + ' Jam)'"></span>
            </p>
        </div>

        <div class="text-left sm:text-right">
            <span
                :class="currentTicket.status === 'PAID' || currentTicket.status === 'CONFIRMED' || currentTicket
                    .status === 'CHECKED_IN' ? 'bg-emerald-500 text-white' : (currentTicket.status === 'EXPIRED' ||
                        currentTicket.status === 'CANCELLED' ? 'bg-rose-500 text-white' : 'bg-amber-400 text-[#1E160A]')"
                class="px-3.5 py-1.5 rounded-full text-xs font-black shadow-sm uppercase tracking-wider inline-block"
                x-text="currentTicket.status">
            </span>
            <div class="text-[11px] text-emerald-200 mt-1 font-mono"
                x-text="'Kode: #' + (currentTicket.booking_code || currentTicket.id.substring(0, 10))"></div>
        </div>
    </div>

    <!-- Ticket Perforated Divider Bar -->
    <div
        class="relative py-2.5 bg-[#FAF6EC] border-t border-b border-dashed border-[#DFC387] px-4 sm:px-6 flex items-center justify-between text-xs text-[#7A5818] font-bold">
        <div class="flex items-center gap-2">
            <span
                x-text="currentTicket.status === 'PAID' || currentTicket.status === 'CHECKED_IN' ? 'STATUS: VALID ENTRY PASS' : (currentTicket.status === 'EXPIRED' ? 'STATUS: KEDALUWARSA (EXPIRED)' : (currentTicket.status === 'CANCELLED' ? 'STATUS: DIBATALKAN' : 'STATUS: MENUNGGU PEMBAYARAN'))"></span>
            <span class="text-[#DFC387]">&bull;</span>
            <span
                x-text="currentTicket.status === 'EXPIRED' || currentTicket.status === 'CANCELLED' ? 'TIDAK DAPAT DIGUNAKAN' : 'GATE: FRONTDESK VENUE'"></span>
        </div>
        <span class="font-mono text-[11px]"
            x-text="'DURASI ' + calculateDuration(currentTicket.start_time, currentTicket.end_time) + ' JAM'"></span>
    </div>

    <!-- QR Code Body for Check-in -->
    <div class="p-4 sm:p-6 lg:p-8 text-center flex flex-col gap-5 items-stretch">

        <!-- Dynamic QR Turnstile: Hanya aktif jika status PAID atau CHECKED_IN -->
        <template x-if="currentTicket.status === 'PAID' || currentTicket.status === 'CHECKED_IN'">
            <div class="w-full">
                <div class="p-4 bg-white rounded-3xl border-2 border-dashed border-[#DFC387] inline-block shadow-inner">
                    <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(currentTicket
                        .qr_code_hash || 'CLUB61-DEMO')"
                        alt="QR Check-in" class="w-44 h-44 sm:w-48 sm:h-48 mx-auto rounded-xl" />
                    <div class="mt-3 font-mono font-black text-xs text-[#8C6418] tracking-widest break-all"
                        x-text="currentTicket.qr_code_hash || currentTicket.booking_code"></div>
                </div>

                <div class="max-w-md mx-auto mt-4">
                    <h4 class="font-serif font-black text-base text-[#1F170D]">Tunjukkan Pada Kasir Frontdesk</h4>
                    <p class="text-xs text-[#7A643E] mt-1 leading-relaxed">
                        Tunjukkan QR Code ini kepada kasir saat tiba di venue untuk check-in lapangan sekaligus
                        mengambil peralatan sewa (raket &amp; bola).
                    </p>
                    <div class="mt-3">
                        <button type="button" @click="downloadTicketPng()" :disabled="isDownloadingPng"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] text-xs font-bold transition-all shadow-sm cursor-pointer disabled:opacity-50">
                            <template x-if="!isDownloadingPng">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-[#8C6418]" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <span>Simpan Gambar E-Tiket (PNG)</span>
                                </div>
                            </template>
                            <template x-if="isDownloadingPng">
                                <div class="flex items-center gap-1.5">
                                    <div
                                        class="w-3.5 h-3.5 border-2 border-[#8C6418] border-t-transparent rounded-full animate-spin">
                                    </div>
                                    <span>Memproses...</span>
                                </div>
                            </template>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Tampilan Khusus Jika Tiket Kedaluwarsa (EXPIRED), Dibatalkan (CANCELLED), atau Direfund -->
        <template
            x-if="currentTicket.status === 'EXPIRED' || currentTicket.status === 'CANCELLED' || currentTicket.status === 'REFUNDED'">
            <div
                class="max-w-md mx-auto p-6 sm:p-7 rounded-3xl border-2 border-dashed border-rose-300 bg-rose-50/70 text-center space-y-4">
                <div
                    class="w-14 h-14 rounded-2xl bg-rose-100 border border-rose-300 flex items-center justify-center mx-auto text-rose-600 shadow-sm">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h4 class="font-serif font-black text-base text-[#1F170D]"
                        x-text="currentTicket.status === 'CANCELLED' ? 'Reservasi Dibatalkan' : (currentTicket.status === 'REFUNDED' ? 'Reservasi Telah Direfund' : 'Reservasi Kedaluwarsa')">
                    </h4>
                    <p class="text-xs text-[#7A643E] mt-1.5 leading-relaxed">
                        Batas waktu pembayaran untuk sesi reservasi ini telah habis dan slot lapangan telah dirilis
                        kembali. Tiket ini sudah tidak dapat dibayar atau digunakan. Silakan lakukan reservasi ulang
                        untuk jadwal baru.
                    </p>
                </div>
                <div
                    class="inline-block px-3 py-1 rounded-full text-[11px] font-mono font-bold bg-rose-200/80 text-rose-900 border border-rose-300 uppercase">
                    Status: <span x-text="currentTicket.status"></span>
                </div>

                <div class="pt-2">
                    <a href="{{ route('customer.booking') }}"
                        class="w-full py-3.5 px-4 rounded-2xl text-[#1E160A] text-xs font-black uppercase tracking-wider shadow-md hover:brightness-105 active:scale-95 transition-all flex items-center justify-center gap-2 block text-center"
                        style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 50%, #A87D18 100%); border: 1.5px solid #FFF3CD;">
                        <span>+ Booking Ulang Lapangan</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </template>

        <!-- Placeholder Edukatif Jika Belum Lunas (PENDING_PAYMENT / LOCKED / Belum Bayar) -->
        <template
            x-if="currentTicket.status !== 'PAID' && currentTicket.status !== 'CHECKED_IN' && currentTicket.status !== 'EXPIRED' && currentTicket.status !== 'CANCELLED' && currentTicket.status !== 'REFUNDED'">
            <div
                class="max-w-md mx-auto p-5 sm:p-6 rounded-3xl border-2 border-dashed border-amber-300 bg-amber-50/70 text-center space-y-4">
                <div
                    class="w-14 h-14 rounded-2xl bg-amber-100 border border-amber-300 flex items-center justify-center mx-auto text-amber-700">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-4a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <div>
                    <h4 class="font-serif font-black text-base text-[#1F170D]">QR Tiket Terkunci</h4>
                    <p class="text-xs text-[#7A643E] mt-1 leading-relaxed">
                        Selesaikan pembayaran terlebih dahulu untuk membuka QR Code pass turnstile lapangan. QR Code
                        akan aktif otomatis setelah status pembayaran terverifikasi lunas.
                    </p>
                </div>
                <div
                    class="inline-block px-3 py-1 rounded-full text-[11px] font-mono font-bold bg-amber-200/80 text-amber-900 border border-amber-300">
                    Status: <span x-text="currentTicket.status"></span>
                </div>

                <!-- Selected Payment Method Display Card (Identik dengan Desain Checkout) -->
                <div
                    class="p-3.5 rounded-2xl bg-white border border-[#DFC387] flex items-center justify-between text-left shadow-sm">
                    <div class="flex items-center gap-3">
                        <span
                            class="w-8 h-8 rounded-lg bg-[#FAF8F2] border border-[#DFC387] flex items-center justify-center font-bold text-[10px] text-[#8C6418] font-mono shrink-0"
                            x-text="selectedMethod.badge"></span>
                        <div>
                            <div class="font-bold text-xs text-[#1F170D]" x-text="selectedMethod.name"></div>
                            <div class="text-[10px] text-[#7A643E]" x-text="selectedMethod.note"></div>
                        </div>
                    </div>
                    <button type="button" @click="showPaymentModal = true"
                        class="text-xs font-bold text-[#8C6418] hover:text-[#5C410F] px-3 py-1.5 rounded-xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] transition-all shrink-0">
                        Ubah
                    </button>
                </div>

                <!-- Tagihan Sisa Kurang Bayar Reschedule (Delta Banner) -->
                <template x-if="currentTicket.has_pending_delta">
                    <div
                        class="p-3.5 rounded-2xl bg-amber-100/90 border border-amber-300 text-left space-y-1 shadow-sm">
                        <div class="flex items-center justify-between text-xs font-bold text-amber-900">
                            <span>Sisa Kurang Bayar Reschedule:</span>
                            <span class="font-mono text-sm font-black text-red-700"
                                x-text="'Rp ' + formatNumber(currentTicket.unpaid_delta)"></span>
                        </div>
                        <p class="text-[11px] text-amber-800 leading-snug">
                            Selesaikan pembayaran sisa <strong class="font-mono"
                                x-text="'Rp ' + formatNumber(currentTicket.unpaid_delta)"></strong> (Tunai di Kasir
                            atau Online VA/QRIS) untuk mengaktifkan QR Code tiket masuk turnstile gate Club61.
                        </p>
                    </div>
                </template>

                <!-- Instruksi Tunai di Kasir jika metode CASH dipilih -->
                <div x-show="selectedMethod.code === 'CASH' || isCashNotice || (currentTicket.order && currentTicket.order.payment_method === 'CASH')"
                    class="p-3.5 rounded-2xl bg-gradient-to-r from-[#FAF2DE] to-[#F5E6BE] border border-[#DFC387] text-left space-y-1.5 shadow-sm">
                    <div class="flex items-center gap-2 text-[#7A5818] font-bold text-xs">
                        <svg class="w-4 h-4 text-[#8C6418]" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>Instruksi Bayar di Meja Kasir:</span>
                    </div>
                    <p class="text-[11px] text-[#5C410F] leading-snug">
                        Tunjukkan Kode Booking <strong class="font-mono text-[#1F170D]"
                            x-text="'#' + (currentTicket.booking_code || currentTicket.id.substring(0, 8))"></strong>
                        kepada kasir frontdesk venue Club61 untuk melunasi reservasi secara tunai dan mengaktifkan
                        e-tiket Anda.
                    </p>
                </div>

                <!-- Single Action Button (Bayar Sekarang - Konsisten dengan Tombol Checkout) -->
                <div class="pt-1">
                    <button type="button" @click="payNow()" :disabled="isSubmittingPayment"
                        class="w-full py-3.5 px-4 rounded-2xl text-[#1E160A] text-xs font-black uppercase tracking-wider shadow-md hover:brightness-105 active:scale-95 transition-all flex items-center justify-center gap-2 cursor-pointer"
                        :class="isSubmittingPayment ? 'opacity-60 cursor-not-allowed' : ''"
                        style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 50%, #A87D18 100%); border: 1.5px solid #FFF3CD;">
                        <template x-if="isSubmittingPayment">
                            <div class="flex items-center gap-2">
                                <div
                                    class="w-4 h-4 border-2 border-[#1E160A] border-t-transparent rounded-full animate-spin">
                                </div>
                                <span>Memproses Pembayaran...</span>
                            </div>
                        </template>
                        <template x-if="!isSubmittingPayment">
                            <div class="flex items-center gap-2">
                                <span
                                    x-text="selectedMethod.code === 'CASH' ? 'Lihat / Konfirmasi Kasir' : (currentTicket.has_pending_delta ? ('Lunasi Sisa Rp ' + formatNumber(currentTicket.unpaid_delta) + ' &rarr;') : 'Bayar Sekarang &rarr;')"></span>
                                <template x-if="selectedMethod.code !== 'CASH'">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </template>
                            </div>
                        </template>
                    </button>

                    <template x-if="canCancelBooking">
                        <button type="button" @click="openCancelModal()" :disabled="isCancellingBooking"
                            class="w-full mt-2.5 py-2.5 px-4 rounded-2xl text-rose-700 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 border border-rose-200 text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50">
                            <template x-if="isCancellingBooking">
                                <div class="flex items-center gap-2">
                                    <div class="w-3.5 h-3.5 border-2 border-t-transparent rounded-full animate-spin"
                                        style="border-color: #E11D48; border-top-color: transparent;"></div>
                                    <span>Membatalkan Pesanan...</span>
                                </div>
                            </template>
                            <template x-if="!isCancellingBooking">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span>Batalkan Pesanan &amp; Pilih Jadwal Lain</span>
                                </div>
                            </template>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        <!-- Breakdown Details: Fully Responsive on Mobile & Desktop -->
        <div
            class="bg-[#FAF8F2] p-4 sm:p-5 rounded-2xl border border-[#DFC387]/70 text-left text-xs space-y-2.5 sm:space-y-2">
            <!-- Nama Pemegang Tiket -->
            <div
                class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-4 pb-2 sm:pb-1.5 border-b border-[#DFC387]/30 text-[#5C410F]">
                <span
                    class="text-[11px] sm:text-xs font-semibold text-[#8C6418] uppercase tracking-wider shrink-0">Nama
                    Pemegang Tiket:</span>
                <span
                    class="font-bold text-xs sm:text-sm text-[#1F170D] sm:text-right break-words leading-snug">{{ Auth::user()->name }}
                    (VIP Platinum)</span>
            </div>

            <!-- Waktu Booking -->
            <div
                class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-4 pb-2 sm:pb-1.5 border-b border-[#DFC387]/30 text-[#5C410F]">
                <span
                    class="text-[11px] sm:text-xs font-semibold text-[#8C6418] uppercase tracking-wider shrink-0">Waktu
                    Booking:</span>
                <span class="font-mono font-bold text-xs sm:text-sm text-[#1F170D] sm:text-right"
                    x-text="formatTime(currentTicket.start_time) + ' - ' + formatTime(currentTicket.end_time) + ' WIB (' + calculateDuration(currentTicket.start_time, currentTicket.end_time) + ' Jam)'"></span>
            </div>

            <!-- Lokasi Lapangan -->
            <div
                class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-4 pb-2 sm:pb-1.5 border-b border-[#DFC387]/30 text-[#5C410F]">
                <span
                    class="text-[11px] sm:text-xs font-semibold text-[#8C6418] uppercase tracking-wider shrink-0">Lokasi
                    Lapangan:</span>
                <span class="font-bold text-xs sm:text-sm text-[#1F170D] sm:text-right leading-snug"
                    x-text="(currentTicket.court ? currentTicket.court.name : 'Court 1') + ' &bull; Indoor Central AC'"></span>
            </div>

            <!-- Biaya Sesi Ini -->
            <div
                class="flex justify-between items-center gap-3 pb-2 sm:pb-1.5 border-b border-[#DFC387]/30 text-[#5C410F]">
                <span
                    class="text-[11px] sm:text-xs font-semibold text-[#8C6418] uppercase tracking-wider shrink-0">Biaya
                    Sesi Ini:</span>
                <span class="font-mono font-bold text-xs sm:text-sm text-[#1F170D] whitespace-nowrap text-right"
                    x-text="'Rp ' + formatNumber(currentTicket.court_fee)"></span>
            </div>

            <!-- Sewa Alat (Add-ons) jika ada -->
            <div class="flex justify-between items-center gap-3 pb-2 sm:pb-1.5 border-b border-[#DFC387]/30 text-[#5C410F]"
                x-show="currentTicket.equipment_fee > 0">
                <span
                    class="text-[11px] sm:text-xs font-semibold text-[#8C6418] uppercase tracking-wider shrink-0">Sewa
                    Alat (Add-ons):</span>
                <span class="font-mono font-bold text-xs sm:text-sm text-[#1F170D] whitespace-nowrap text-right"
                    x-text="'Rp ' + formatNumber(currentTicket.equipment_fee)"></span>
            </div>

            <!-- Total Pembayaran Tiket -->
            <div
                class="pt-2.5 sm:pt-3 border-t border-[#DFC387]/60 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1.5 sm:gap-4">
                <span
                    class="font-serif font-black text-xs sm:text-sm text-[#1F170D] uppercase tracking-wider shrink-0">Total
                    Pembayaran Tiket:</span>
                <span class="font-mono font-black text-base sm:text-lg text-[#1F170D] whitespace-nowrap sm:text-right"
                    x-text="'Rp ' + formatNumber(currentTicket.total_amount)"></span>
            </div>
        </div>
    </div>

</div>
