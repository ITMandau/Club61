<!-- Cancel Booking Confirmation Modal (Club 61 Luxury Theme) -->
<div x-show="showCancelModal" 
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        <!-- Content -->
        <div class="space-y-2">
            <h3 class="font-serif font-black text-xl text-[#1F170D]">Batalkan Pesanan Lapangan?</h3>
            <p class="text-xs text-[#7A643E] leading-relaxed">
                Slot jam lapangan yang terkunci akan langsung dilepaskan kembali ke sistem publik untuk pemain lain. Anda dapat memilih jadwal baru setelah pembatalan.
            </p>
        </div>

        <!-- Booking Details Preview -->
        <template x-if="currentTicket">
            <div class="p-3.5 rounded-2xl bg-[#FAF8F2] border border-[#E8DCC0] text-left text-xs space-y-2">
                <div class="flex justify-between items-center text-xs">
                    <span class="text-[#7A643E]">Lapangan:</span>
                    <span class="font-bold text-[#1F170D]" x-text="currentTicket.court ? currentTicket.court.name : (currentTicket.court_name || 'Court Arena')"></span>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-[#7A643E]">Jadwal Main:</span>
                    <span class="font-bold text-[#1F170D]" x-text="formatDate(currentTicket.booking_date) + ' • ' + formatTime(currentTicket.start_time) + ' - ' + formatTime(currentTicket.end_time) + ' WIB'"></span>
                </div>
                <template x-if="ticket && ticket.order_bookings && ticket.order_bookings.length > 1">
                    <div class="flex justify-between items-center text-xs pt-1 border-t border-[#DFC387]/40">
                        <span class="text-[#7A643E]">Total Sesi:</span>
                        <span class="font-bold text-[#8C6418]" x-text="ticket.order_bookings.length + ' Sesi Lapangan Terkait'"></span>
                    </div>
                </template>
                <div class="flex justify-between items-center text-xs pt-1 border-t border-[#DFC387]/40">
                    <span class="text-[#7A643E]">Kode Booking:</span>
                    <span class="font-mono font-bold text-[#8C6418]" x-text="currentTicket.booking_code || currentTicket.order_number || currentTicket.id"></span>
                </div>
            </div>
        </template>

        <!-- Action Buttons -->
        <div class="space-y-2.5 pt-1">
            <button type="button" 
                    @click="confirmCancelBooking()"
                    :disabled="isCancellingBooking"
                    class="w-full py-3.5 px-4 rounded-2xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 hover:brightness-105 active:scale-98"
                    style="background: linear-gradient(180deg, #E11D48 0%, #BE123C 100%); color: #FFFFFF !important; border: 1px solid #FDA4AF; box-shadow: 0 4px 14px rgba(225, 29, 72, 0.35);">
                <template x-if="!isCancellingBooking">
                    <span class="flex items-center gap-1.5" style="color: #FFFFFF;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #FFFFFF;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        <span style="color: #FFFFFF;">Ya, Batalkan Pesanan</span>
                    </span>
                </template>
                <template x-if="isCancellingBooking">
                    <span class="flex items-center gap-2" style="color: #FFFFFF;">
                        <div class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" style="border-color: #FFFFFF; border-top-color: transparent;"></div>
                        <span style="color: #FFFFFF;">Membatalkan Slot Lapangan...</span>
                    </span>
                </template>
            </button>

            <button type="button" 
                    @click="showCancelModal = false"
                    :disabled="isCancellingBooking"
                    class="w-full py-3 px-4 rounded-2xl border text-xs font-bold transition-all cursor-pointer hover:brightness-95 disabled:opacity-50"
                    style="border: 1px solid #DFC387; background-color: #FAF8F2; color: #7A5818;">
                Kembali ke Tiket
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

