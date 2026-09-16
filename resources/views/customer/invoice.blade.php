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
                            <span :class="ticket && (ticket.status === 'PAID' || ticket.status === 'CONFIRMED' || ticket.status === 'CHECKED_IN') ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : (ticket && (ticket.status === 'EXPIRED' || ticket.status === 'CANCELLED' || ticket.status === 'REFUNDED') ? 'bg-rose-100 text-rose-800 border-rose-300' : 'bg-amber-100 text-amber-800 border-amber-300')"
                                  class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border"
                                  x-text="ticket ? ticket.status : 'MEMUAT...'">
                            </span>
                        </div>
                        <p class="text-xs text-[#7A643E] mt-0.5">Tunjukkan QR Code e-tiket ini pada turnstile gate venue untuk check-in lapangan langsung</p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 sm:gap-3 w-full sm:w-auto">
                    <button type="button" 
                            @click="downloadTicketPng()" 
                            :disabled="isDownloadingPng || !currentTicket || currentTicket.status === 'EXPIRED' || currentTicket.status === 'CANCELLED' || currentTicket.status === 'REFUNDED'"
                            class="flex-1 sm:flex-none justify-center px-4 py-2.5 rounded-2xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] text-xs font-bold transition-all shadow-sm flex items-center gap-2 disabled:opacity-50 cursor-pointer">
                        <template x-if="!isDownloadingPng">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span>Download E-Tiket (PNG)</span>
                            </div>
                        </template>
                        <template x-if="isDownloadingPng">
                            <div class="flex items-center gap-2">
                                <div class="w-3.5 h-3.5 border-2 border-[#8C6418] border-t-transparent rounded-full animate-spin"></div>
                                <span>Membuat PNG...</span>
                            </div>
                        </template>
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
                        @include('customer.invoice-partials.ticket-card')
                    </div>

                    <!-- Right Column: Invoice Details & Past History (Col 5) -->
                    <div class="lg:col-span-5 flex flex-col gap-6">
                        @include('customer.invoice-partials.invoice-summary')
                        @include('customer.invoice-partials.booking-history')
                    </div>

                </div>
            </template>

            <!-- Payment Method Selection Modal -->
            @include('customer.invoice-partials.payment-modal')

            <!-- Cancel Booking Confirmation Modal -->
            @include('customer.invoice-partials.cancel-modal')
        </div>
    </div>

    <!-- Scripts: Alpine.js Controller & High-Res PNG Ticket Canvas Generator -->
    @include('customer.invoice-partials.invoice-scripts')
</x-app-layout>
