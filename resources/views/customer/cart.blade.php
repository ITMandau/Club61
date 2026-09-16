<x-app-layout>
    <div x-data="cartApp()" x-init="init()" class="py-6 sm:py-8 text-[#1F170D]">
        <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16 space-y-6">

            <!-- Top Header & Breadcrumb -->
            <div class="flex items-center justify-between bg-white/95 backdrop-blur-xl p-5 rounded-3xl border border-[#DFC387] shadow-sm">
                <div class="flex items-center gap-3.5">
                    <a href="{{ route('customer.booking') }}" class="p-2.5 rounded-2xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] transition-colors" title="Kembali ke Booking">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <h1 class="font-serif font-black text-xl sm:text-2xl text-[#1F170D]">Keranjang Pemesanan</h1>
                        <p class="text-xs text-[#7A643E]">Periksa jadwal pertandingan dan slot yang telah Anda kunci (Hold 10 Menit)</p>
                    </div>
                </div>

                <a href="{{ route('customer.booking') }}" class="p-2 text-[#9E907B] hover:text-[#1F170D] rounded-2xl hover:bg-[#FAF2DE] transition-colors" title="Tutup">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>

            <!-- GUARDRAIL 1: Countdown Timer Banner (10 Menit Live Sync) -->
            <div x-show="items.length > 0 && !isExpired" 
                 class="p-4 rounded-2xl bg-gradient-to-r from-[#183428] via-[#12241C] to-[#0A1611] text-white border border-[#DFC387] shadow-md flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="w-3 h-3 rounded-full bg-amber-400 animate-ping"></span>
                    <div>
                        <div class="text-[10px] uppercase font-bold text-amber-300 tracking-wider">Slot Terkunci Khusus Anda (Two-Tier Lock)</div>
                        <div class="text-xs text-emerald-100/90 font-medium">Selesaikan pemesanan sebelum waktu habis agar slot tidak dilepas kembali ke publik.</div>
                    </div>
                </div>

                <div class="flex items-center gap-2 bg-white/10 px-4 py-2 rounded-xl border border-white/20 shrink-0">
                    <span class="text-xs text-[#DFC387] font-bold">Sisa Waktu:</span>
                    <span class="font-mono font-black text-base text-[#FAF5E6] tracking-wider" x-text="timerDisplay">10:00</span>
                </div>
            </div>

            <!-- Empty State -->
            <div x-show="items.length === 0" class="bg-white/95 backdrop-blur-xl rounded-3xl border border-[#DFC387] p-12 text-center space-y-4 shadow-sm">
                <div class="w-20 h-20 mx-auto rounded-full bg-[#FAF2DE] flex items-center justify-center text-xs font-black border border-[#DFC387] shadow-sm tracking-wider text-[#7A5818]">
                    KOSONG
                </div>
                <h3 class="font-serif font-black text-xl text-[#1F170D]">Keranjang Booking Anda Kosong</h3>
                <p class="text-xs text-[#7A643E] max-w-sm mx-auto">Anda belum memilih slot jadwal lapangan padel. Kunjungi halaman booking untuk memilih jam main.</p>
                <a href="{{ route('customer.booking') }}" 
                   class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-gradient-to-b from-[#F5DE9B] to-[#D4AF37] text-[#1E160A] text-xs font-black uppercase tracking-wider shadow-md hover:brightness-105 active:scale-95 transition-all">
                    <span>Cari Jadwal Lapangan &rarr;</span>
                </a>
            </div>

            <!-- Multi-Column Desktop Cart Layout -->
            <div x-show="items.length > 0" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- Left Column: Items List (Col 8) -->
                <div class="lg:col-span-8 space-y-4">
                    <div class="bg-white/95 backdrop-blur-xl rounded-3xl border border-[#DFC387] shadow-[0_15px_40px_rgba(160,120,30,0.15)] p-6 space-y-5">
                        
                        <div class="flex items-center justify-between border-b border-[#DFC387]/50 pb-4">
                            <div>
                                <span class="text-xs font-bold text-[#7A5818] uppercase tracking-wider">Slot Lapangan Terkunci</span>
                                <div class="font-serif font-black text-lg text-[#1F170D]" x-text="bookingDateFormatted"></div>
                            </div>
                            <span class="px-3.5 py-1.5 rounded-full text-xs font-black bg-[#FAF2DE] text-[#7A5818] border border-[#DFC387]" x-text="items.length + ' Slot Dipilih'"></span>
                        </div>

                        <!-- Items List -->
                        <div class="divide-y divide-[#EEDBB0]/60">
                            <template x-for="(item, index) in items" :key="index">
                                <div class="py-4 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3.5">
                                        <div class="w-12 h-12 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center text-xs font-black shrink-0 shadow-sm tracking-wider text-[#7A5818]">
                                            COURT
                                        </div>
                                        <div>
                                            <h4 class="font-serif font-black text-base text-[#1F170D]" x-text="item.court || 'Court Arena'"></h4>
                                            <div class="flex items-center gap-2 text-xs text-[#7A643E] mt-0.5 font-mono">
                                                <span x-text="item.time"></span>
                                                <span>&bull;</span>
                                                <span class="text-emerald-700 font-bold">Standard Pro WPT</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4">
                                        <div class="text-right">
                                            <div class="font-mono font-black text-base text-[#1F170D]" x-text="'Rp ' + formatNumber(item.price)"></div>
                                            <span class="text-[10px] text-emerald-700 font-bold uppercase">Locked</span>
                                        </div>

                                        <button type="button" 
                                                @click="removeItem(index)" 
                                                class="p-2 rounded-xl text-rose-500 hover:bg-rose-50 hover:text-rose-700 transition-colors" 
                                                title="Hapus Slot">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                    </div>
                </div>

                <!-- Right Column: Sticky Summary & CTA (Col 4) -->
                <div class="lg:col-span-4 space-y-4">
                    <div class="bg-white/95 backdrop-blur-xl rounded-3xl border-2 border-[#D4AF37] shadow-[0_15px_40px_rgba(160,120,30,0.18)] p-6 space-y-5 sticky top-24">
                        
                        <h3 class="font-serif font-black text-base text-[#1F170D] border-b border-[#DFC387]/50 pb-3">Ringkasan Pesanan</h3>

                        <div class="space-y-3 text-xs">
                            <div class="flex justify-between text-[#5C410F]">
                                <span>Subtotal Lapangan</span>
                                <span class="font-mono font-bold text-sm" x-text="'Rp ' + formatNumber(subtotal)"></span>
                            </div>
                            <div class="flex justify-between text-[#5C410F]">
                                <span>Pajak &amp; Biaya Layanan</span>
                                <span class="text-[11px] text-[#8C7A58]">Dihitung di checkout</span>
                            </div>
                            <div class="pt-3 border-t border-[#DFC387]/60 flex justify-between items-center text-sm">
                                <span class="font-serif font-black text-[#1F170D]">Total Sementara</span>
                                <span class="font-mono font-black text-lg text-[#1F170D]" x-text="'Rp ' + formatNumber(subtotal)"></span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="space-y-3 pt-2">
                            <button type="button" 
                                    :disabled="isExpired"
                                    @click="proceedToCheckout()"
                                    class="w-full py-4 rounded-2xl text-xs font-black uppercase tracking-widest transition-all duration-150 transform active:scale-95 shadow-lg flex items-center justify-center gap-2 cursor-pointer"
                                    :class="isExpired ? 'opacity-50 cursor-not-allowed bg-gray-400 text-white' : ''"
                                    style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 50%, #A87D18 100%); color: #1E160A; border: 1.5px solid #FFF3CD;">
                                <span>Lanjut ke Checkout</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>

                            <template x-if="canCancelBooking">
                                <button type="button" 
                                        @click="showClearCartModal = true"
                                        class="w-full py-2.5 text-center text-xs font-bold text-[#8A7A64] hover:text-rose-600 transition-colors cursor-pointer">
                                    Kosongkan Semua Keranjang
                                </button>
                            </template>
                        </div>

                        <!-- VIP Privilege Note -->
                        <div class="p-3.5 rounded-2xl bg-[#FAF8F2] border border-[#E8DCC0] text-[11px] text-[#7A643E] leading-relaxed">
                            <strong class="text-[#3B2B11]">Garansi VIP:</strong> Slot Anda terkunci otomatis selama 10 menit. Selesaikan checkout sebelum waktu berakhir untuk menghindari pelepasan slot otomatis.
                        </div>

                    </div>
                </div>

            </div>

        </div>

        <!-- GUARDRAIL 1 MODAL: Waktu Sesi Habis Pop-up -->
        <div x-show="showExpiredModal" 
             style="display: none; z-index: 99999 !important;"
             class="fixed inset-0 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl border-2 border-[#D4AF37] max-w-md w-full p-6 text-center space-y-4 shadow-2xl animate-scaleIn">
                <div class="w-16 h-16 rounded-full bg-rose-100 border border-rose-300 text-rose-600 flex items-center justify-center mx-auto text-xs font-black tracking-wider">
                    EXPIRED
                </div>
                <h3 class="font-serif font-black text-xl text-[#1F170D]">Waktu Sesi Habis!</h3>
                <p class="text-xs text-[#7A643E] leading-relaxed">
                    Batas waktu 10 menit telah berakhir dan slot yang Anda pilih telah dilepaskan kembali secara otomatis ke sistem publik untuk menghindari blokir jadwal.
                </p>
                <div class="pt-2">
                    <a href="{{ route('customer.booking') }}" 
                       @click="handleExpiredRedirect()"
                       class="w-full py-3.5 px-4 rounded-2xl text-[#1E160A] text-xs font-black uppercase tracking-wider block shadow-md hover:brightness-105 transition-all"
                       style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 100%); color: #1E160A; border: 1px solid #FFF3CD;">
                        Pilih Jadwal Kembali &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Clear Cart Confirmation Modal (Club 61 Luxury Theme) -->
        <div x-show="showClearCartModal" 
             style="display: none; z-index: 99999 !important;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 overflow-y-auto bg-black/75 backdrop-blur-md flex items-center justify-center p-4">

            <div class="w-full max-w-md bg-white rounded-3xl border-2 border-[#D4AF37] shadow-2xl p-6 sm:p-7 space-y-5 animate-scaleIn text-center relative">
                
                <!-- Header Alert Icon -->
                <div class="w-16 h-16 rounded-2xl bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center mx-auto shadow-sm">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>

                <!-- Content -->
                <div class="space-y-2">
                    <h3 class="font-serif font-black text-xl text-[#1F170D]">Kosongkan Keranjang Booking?</h3>
                    <p class="text-xs text-[#7A643E] leading-relaxed">
                        Semua slot lapangan yang telah Anda kunci akan dibatalkan dan langsung dilepaskan kembali ke sistem publik untuk pemain lain.
                    </p>
                </div>

                <!-- Items Preview in Cart -->
                <template x-if="items && items.length > 0">
                    <div class="p-3.5 rounded-2xl bg-[#FAF8F2] border border-[#E8DCC0] text-left text-xs space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-[#7A643E]">Total Slot:</span>
                            <span class="font-bold text-[#1F170D]" x-text="items.length + ' Sesi Lapangan'"></span>
                        </div>
                        <div class="flex justify-between items-center text-xs pt-1 border-t border-[#DFC387]/40">
                            <span class="text-[#7A643E]">Total Biaya:</span>
                            <span class="font-mono font-bold text-[#8C6418]" x-text="'Rp ' + formatNumber(subtotal)"></span>
                        </div>
                    </div>
                </template>

                <!-- Action Buttons -->
                <div class="space-y-2.5 pt-2">
                    <button type="button" 
                            @click="confirmClearAll()"
                            :disabled="isClearingCart"
                            class="w-full py-3.5 px-4 rounded-2xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 hover:brightness-105 active:scale-98"
                            style="background: linear-gradient(180deg, #E11D48 0%, #BE123C 100%); color: #FFFFFF !important; border: 1px solid #FDA4AF; box-shadow: 0 4px 14px rgba(225, 29, 72, 0.35);">
                        <template x-if="!isClearingCart">
                            <span class="flex items-center gap-1.5" style="color: #FFFFFF;">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #FFFFFF;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                <span style="color: #FFFFFF;">Ya, Kosongkan Keranjang</span>
                            </span>
                        </template>
                        <template x-if="isClearingCart">
                            <span class="flex items-center gap-2" style="color: #FFFFFF;">
                                <div class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" style="border-color: #FFFFFF; border-top-color: transparent;"></div>
                                <span style="color: #FFFFFF;">Melepaskan Seluruh Slot...</span>
                            </span>
                        </template>
                    </button>

                    <button type="button" 
                            @click="showClearCartModal = false"
                            :disabled="isClearingCart"
                            class="w-full py-3 px-4 rounded-2xl border text-xs font-bold transition-all cursor-pointer hover:brightness-95 disabled:opacity-50"
                            style="border: 1px solid #DFC387; background-color: #FAF8F2; color: #7A5818;">
                        Batal
                    </button>
                </div>

            </div>
        </div>

        <!-- CUSTOM LUXURY NOTICE / ALERT MODAL (Club 61 Theme) -->
        <div x-show="noticeModal.show" 
             style="display: none; z-index: 99999 !important;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 overflow-y-auto bg-black/75 backdrop-blur-md flex items-center justify-center p-4">

            <div class="w-full max-w-md bg-white rounded-3xl border-2 border-[#D4AF37] shadow-2xl p-6 sm:p-7 space-y-5 animate-scaleIn text-center relative"
                 @click.away="handleNoticeClose()">
                
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto shadow-sm"
                     :class="{
                         'bg-[#FAF2DE] border border-[#DFC387] text-[#8C6418]': noticeModal.type === 'info' || noticeModal.type === 'gold',
                         'bg-rose-50 border border-rose-200 text-rose-600': noticeModal.type === 'error' || noticeModal.type === 'danger',
                         'bg-emerald-50 border border-emerald-200 text-emerald-600': noticeModal.type === 'success'
                     }">
                    <template x-if="noticeModal.type === 'info' || noticeModal.type === 'gold'">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                    <template x-if="noticeModal.type === 'error' || noticeModal.type === 'danger'">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </template>
                    <template x-if="noticeModal.type === 'success'">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                </div>

                <div class="space-y-2">
                    <h3 class="font-serif font-black text-xl text-[#1F170D]" x-text="noticeModal.title"></h3>
                    <p class="text-xs text-[#7A643E] leading-relaxed" x-text="noticeModal.message"></p>
                </div>

                <div class="pt-2">
                    <button type="button" 
                            @click="handleNoticeClose()"
                            class="w-full py-3.5 px-4 rounded-2xl text-[#1E160A] text-xs font-black uppercase tracking-wider block shadow-md hover:brightness-105 transition-all cursor-pointer active:scale-98"
                            style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 50%, #A87D18 100%); color: #1E160A; border: 1px solid #FFF3CD;">
                        <span x-text="noticeModal.buttonText || 'OK, Mengerti'"></span>
                    </button>
                </div>

            </div>
        </div>

    </div>

    <script>
        function cartApp() {
            return {
                canCancelBooking: @json(Auth::check() && Auth::user()->canCancelBooking()),
                noticeModal: {
                    show: false,
                    title: '',
                    message: '',
                    type: 'info',
                    buttonText: 'OK, Mengerti',
                    onClose: null,
                },

                showNotice(title, message, type = 'info', buttonText = 'OK, Mengerti', onClose = null) {
                    this.noticeModal = {
                        show: true,
                        title,
                        message,
                        type,
                        buttonText,
                        onClose,
                    };
                },

                handleNoticeClose() {
                    this.noticeModal.show = false;
                    if (typeof this.noticeModal.onClose === 'function') {
                        const cb = this.noticeModal.onClose;
                        this.noticeModal.onClose = null;
                        cb();
                    }
                },

                items: [],
                bookingDateFormatted: 'Hari Ini',
                holdData: null,
                expiresAtTime: null,
                timerDisplay: '10:00',
                timerInterval: null,
                isExpired: false,
                showExpiredModal: false,
                showClearCartModal: false,
                isClearingCart: false,

                init() {
                    this.syncFromStorage();

                    // Multi-tab synchronizer: saat tab lain memutasi keranjang, tab ini otomatis sinkron
                    window.addEventListener('storage', (e) => {
                        if (!e.key || e.key.includes('cart') || e.key.includes('hold_data')) {
                            this.syncFromStorage();
                        }
                    });

                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible') {
                            this.syncFromStorage();
                            this.checkExpiry();
                        }
                    });
                },

                syncFromStorage() {
                    const saved = localStorage.getItem('club61_cart') || sessionStorage.getItem('club61_cart') || sessionStorage.getItem('vantage_cart');
                    const holdSaved = localStorage.getItem('club61_hold_data') || sessionStorage.getItem('club61_hold_data') || sessionStorage.getItem('vantage_hold_data');

                    if (saved) {
                        try {
                            const parsed = JSON.parse(saved);
                            if (Array.isArray(parsed) && parsed.length > 0) {
                                this.items = parsed;
                                if (parsed[0].booking_date) {
                                    this.bookingDateFormatted = parsed[0].booking_date;
                                }
                            } else {
                                this.items = [];
                            }
                        } catch(e) {
                            this.items = [];
                        }
                    } else {
                        this.items = [];
                    }

                    if (holdSaved) {
                        try {
                            this.holdData = JSON.parse(holdSaved);
                            if (this.holdData && this.holdData.expires_at) {
                                this.expiresAtTime = new Date(this.holdData.expires_at).getTime();
                            }
                        } catch(e) {}
                    }

                    // Fallback jika tidak ada expires_at, defaultkan 10 menit dari sekarang
                    if (!this.expiresAtTime && this.items.length > 0) {
                        this.expiresAtTime = Date.now() + (10 * 60 * 1000);
                    }

                    // GUARDRAIL 1: Jalankan Timer (Tab Switching Aware)
                    if (this.items.length > 0) {
                        this.startCountdown();
                    } else if (this.timerInterval) {
                        clearInterval(this.timerInterval);
                        this.timerInterval = null;
                    }
                },

                startCountdown() {
                    if (this.timerInterval) {
                        clearInterval(this.timerInterval);
                        this.timerInterval = null;
                    }
                    this.checkExpiry();
                    this.timerInterval = setInterval(() => {
                        this.checkExpiry();
                    }, 1000);
                },

                checkExpiry() {
                    if (!this.expiresAtTime) return;

                    const now = Date.now();
                    const diffMs = this.expiresAtTime - now;

                    if (diffMs <= 0) {
                        this.isExpired = true;
                        this.timerDisplay = '00:00';
                        this.showExpiredModal = true;
                        if (this.timerInterval) clearInterval(this.timerInterval);
                        return;
                    }

                    const totalSeconds = Math.floor(diffMs / 1000);
                    const minutes = Math.floor(totalSeconds / 60);
                    const seconds = totalSeconds % 60;

                    this.timerDisplay = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                },

                handleExpiredRedirect() {
                    localStorage.removeItem('club61_cart');
                    localStorage.removeItem('club61_hold_data');
                    sessionStorage.removeItem('club61_cart');
                    sessionStorage.removeItem('club61_hold_data');
                    sessionStorage.removeItem('vantage_cart');
                    sessionStorage.removeItem('vantage_hold_data');
                    window.dispatchEvent(new CustomEvent('cart-updated'));
                },

                get subtotal() {
                    return this.items.reduce((sum, item) => sum + item.price, 0);
                },

                async removeItem(idx) {
                    const removedItem = this.items[idx];
                    this.items.splice(idx, 1);
                    localStorage.setItem('club61_cart', JSON.stringify(this.items));
                    sessionStorage.setItem('club61_cart', JSON.stringify(this.items));
                    window.dispatchEvent(new CustomEvent('cart-updated'));

                    // Release slot di backend via API
                    if (this.holdData && this.holdData.bookings) {
                        const bookingMatch = this.holdData.bookings[idx];
                        if (bookingMatch && bookingMatch.id) {
                            try {
                                await fetch('/api/v1/padel/release-slot', {
                                    method: 'POST',
                                    headers: {
                                         'Content-Type': 'application/json',
                                         'Accept': 'application/json',
                                         'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                    },
                                    body: JSON.stringify({ booking_ids: [bookingMatch.id] })
                                });
                            } catch(e) {}
                        }
                    }
                },

                clearAll() {
                    if (!this.canCancelBooking) {
                        this.showNotice('Akses Ditolak', 'Peran akun Anda tidak memiliki izin untuk membatalkan pesanan ini.', 'error', 'Tutup');
                        return;
                    }
                    this.showClearCartModal = true;
                },

                async confirmClearAll() {
                    if (!this.canCancelBooking) {
                        this.showNotice('Akses Ditolak', 'Peran akun Anda tidak memiliki izin untuk membatalkan pesanan ini.', 'error', 'Tutup');
                        return;
                    }
                    this.isClearingCart = true;
                    const bookingIds = (this.holdData && this.holdData.bookings) 
                        ? this.holdData.bookings.map(b => b.id) 
                        : [];

                    if (bookingIds.length > 0) {
                        try {
                            await fetch('/api/v1/padel/release-slot', {
                                method: 'POST',
                                headers: {
                                     'Content-Type': 'application/json',
                                     'Accept': 'application/json',
                                     'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                },
                                body: JSON.stringify({ booking_ids: bookingIds })
                            });
                        } catch(e) {}
                    }

                    this.items = [];
                    this.holdData = null;
                    this.expiresAtTime = null;
                    if (this.timerInterval) {
                        clearInterval(this.timerInterval);
                        this.timerInterval = null;
                    }

                    localStorage.removeItem('club61_cart');
                    localStorage.removeItem('club61_hold_data');
                    sessionStorage.removeItem('club61_cart');
                    sessionStorage.removeItem('club61_hold_data');
                    sessionStorage.removeItem('vantage_cart');
                    sessionStorage.removeItem('vantage_hold_data');
                    window.dispatchEvent(new CustomEvent('cart-updated'));

                    this.isClearingCart = false;
                    this.showClearCartModal = false;
                },

                formatNumber(val) {
                    if (!val) return '0';
                    return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                },

                proceedToCheckout() {
                    if (this.isExpired) {
                        this.showExpiredModal = true;
                        return;
                    }
                    localStorage.setItem('club61_cart', JSON.stringify(this.items));
                    sessionStorage.setItem('club61_cart', JSON.stringify(this.items));
                    window.location.href = "{{ route('customer.checkout') }}";
                }
            }
        }
    </script>
</x-app-layout>
