<div
    class="bg-white/95 backdrop-blur-xl rounded-3xl border-2 border-[#D4AF37] shadow-[0_20px_50px_rgba(160,120,30,0.22)] overflow-hidden">

    <!-- Card Header Banner -->
    <div
        class="p-4 sm:p-6 bg-gradient-to-r from-[#183428] via-[#10241B] to-[#0A1812] text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
        <div>
            <span
                class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-[#DFC387]/20 text-[#F5E6BE] border border-[#DFC387]/40 inline-block mb-1.5">
                Membership Purchase &bull; <span x-text="currentTicket.owner_type === 'ORGANIZATIONAL' ? 'Corporate' : 'Individual'"></span>
            </span>
            <h2 class="font-serif font-black text-xl sm:text-2xl text-white" x-text="currentTicket.plan_name"></h2>
            <p class="text-xs text-emerald-100/80 mt-1 font-mono">
                <span x-text="'Code: #' + currentTicket.membership_code"></span>
            </p>
        </div>

        <div class="text-left sm:text-right">
            <span
                :class="currentTicket.status === 'ACTIVE' ? 'bg-emerald-500 text-white' : (currentTicket.status === 'EXPIRED' || currentTicket.status === 'CANCELLED' ? 'bg-rose-500 text-white' : 'bg-amber-400 text-[#1E160A]')"
                class="px-3.5 py-1.5 rounded-full text-xs font-black shadow-sm uppercase tracking-wider inline-block"
                x-text="currentTicket.status">
            </span>
        </div>
    </div>

    <!-- Divider Bar -->
    <div
        class="relative py-2.5 bg-[#FAF6EC] border-t border-b border-dashed border-[#DFC387] px-4 sm:px-6 flex items-center justify-between text-xs text-[#7A5818] font-bold">
        <span
            x-text="currentTicket.status === 'ACTIVE' ? 'STATUS: MEMBERSHIP ACTIVE' : (currentTicket.status === 'PENDING_PAYMENT' ? 'STATUS: AWAITING PAYMENT' : 'STATUS: ' + currentTicket.status)"></span>
        <span class="font-mono text-[11px]" x-text="currentTicket.payment_status"></span>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 text-center flex flex-col gap-5 items-stretch">

        <!-- Digital Membership Card QR: only for ACTIVE membership -->
        <template x-if="currentTicket.status === 'ACTIVE' && currentTicket.qr_pass_hash">
            <div class="w-full">
                <div class="p-4 bg-white rounded-3xl border-2 border-dashed border-[#DFC387] inline-block shadow-inner">
                    <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(currentTicket.qr_pass_hash)"
                        alt="Membership Card QR" class="w-44 h-44 sm:w-48 sm:h-48 mx-auto rounded-xl" />
                    <div class="mt-3 font-mono font-black text-xs text-[#8C6418] tracking-widest break-all"
                        x-text="currentTicket.qr_pass_hash"></div>
                </div>
                <div class="max-w-md mx-auto mt-4">
                    <h4 class="font-serif font-black text-base text-[#1F170D]">Your Digital Membership Card</h4>
                    <p class="text-xs text-[#7A643E] mt-1 leading-relaxed">
                        Present this QR Code at the frontdesk for identity verification and facility access as needed.
                    </p>
                </div>
            </div>
        </template>

        <!-- Pending Payment State -->
        <template x-if="currentTicket.status === 'PENDING_PAYMENT' || currentTicket.payment_status === 'UNPAID'">
            <div
                class="max-w-md mx-auto p-5 sm:p-6 rounded-3xl border-2 border-dashed border-amber-300 bg-amber-50/70 text-center space-y-4">
                <div
                    class="w-14 h-14 rounded-2xl bg-amber-100 border border-amber-300 flex items-center justify-center mx-auto text-amber-700">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-4a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <div>
                    <h4 class="font-serif font-black text-base text-[#1F170D]">Payment Not Yet Confirmed</h4>
                    <p class="text-xs text-[#7A643E] mt-1 leading-relaxed"
                        x-text="currentTicket.can_pay_online
                            ? 'Complete payment to activate this membership. Your package and price stay the same; you can pick another payment method.'
                            : 'This membership package hasn\'t been activated yet because payment settlement hasn\'t been confirmed. If you\'ve already paid, please contact our concierge with your order code below.'"></p>
                </div>
                <div class="inline-block px-3 py-1 rounded-full text-[11px] font-mono font-bold bg-amber-200/80 text-amber-900 border border-amber-300">
                    Order: <span x-text="'#' + (currentTicket.order_number || currentTicket.membership_code)"></span>
                </div>

                {{-- Pesanan online yang belum dibayar: lanjutkan bayar (order yang sama) atau batalkan untuk ganti paket. --}}
                <template x-if="currentTicket.can_pay_online">
                    <div class="space-y-2.5">
                        <div class="p-3.5 rounded-2xl bg-white border border-[#DFC387] flex items-center justify-between text-left shadow-sm">
                            <div class="flex items-center gap-3">
                                <span class="w-8 h-8 rounded-lg bg-[#FAF8F2] border border-[#DFC387] flex items-center justify-center font-bold text-[10px] text-[#8C6418] font-mono shrink-0"
                                    x-text="selectedMethod.badge"></span>
                                <div>
                                    <div class="font-bold text-xs text-[#1F170D]" x-text="selectedMethod.name"></div>
                                    <div class="text-[10px] text-[#7A643E]" x-text="selectedMethod.note"></div>
                                </div>
                            </div>
                            <button type="button" @click="showPaymentModal = true"
                                class="text-xs font-bold text-[#8C6418] hover:text-[#5C410F] px-3 py-1.5 rounded-xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] transition-all shrink-0 cursor-pointer">
                                Change
                            </button>
                        </div>

                        <button type="button" @click="payMembership()" :disabled="isSubmittingPayment"
                            class="w-full py-3.5 px-4 rounded-2xl text-[#1E160A] text-xs font-black uppercase tracking-wider shadow-md hover:brightness-105 active:scale-95 transition-all flex items-center justify-center gap-2 cursor-pointer"
                            :class="isSubmittingPayment ? 'opacity-60 cursor-not-allowed' : ''"
                            style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 50%, #A87D18 100%); border: 1.5px solid #FFF3CD;">
                            <span x-text="isSubmittingPayment ? 'Processing Payment...' : ('Continue Payment Rp ' + formatNumber(currentTicket.grand_total))"></span>
                        </button>

                        <button type="button" @click="showCancelMembershipModal = true" :disabled="isSubmittingPayment || isCancellingMembership"
                            class="w-full py-2.5 px-4 rounded-2xl text-rose-700 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 border border-rose-200 text-xs font-bold transition-all cursor-pointer disabled:opacity-50">
                            Cancel This Order
                        </button>
                    </div>
                </template>

                <template x-if="! currentTicket.can_pay_online">
                    <div class="pt-1">
                        <a href="https://wa.me/6281261617233" target="_blank"
                            class="w-full py-3 px-4 rounded-2xl text-[#1E160A] text-xs font-black uppercase tracking-wider shadow-md hover:brightness-105 active:scale-95 transition-all flex items-center justify-center gap-2 block"
                            style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 50%, #A87D18 100%); border: 1.5px solid #FFF3CD;">
                            Contact Concierge Support
                        </a>
                    </div>
                </template>
            </div>
        </template>

        <!-- Expired / Cancelled State -->
        <template x-if="currentTicket.status === 'EXPIRED' || currentTicket.status === 'CANCELLED'">
            <div
                class="max-w-md mx-auto p-6 sm:p-7 rounded-3xl border-2 border-dashed border-rose-300 bg-rose-50/70 text-center space-y-4">
                <div class="w-14 h-14 rounded-2xl bg-rose-100 border border-rose-300 flex items-center justify-center mx-auto text-rose-600 shadow-sm">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h4 class="font-serif font-black text-base text-[#1F170D]"
                    x-text="currentTicket.status === 'CANCELLED' ? 'Membership Cancelled' : 'Membership Expired'"></h4>
                <div class="pt-1">
                    <a href="{{ route('customer.membership') }}"
                        class="w-full py-3 px-4 rounded-2xl text-[#1E160A] text-xs font-black uppercase tracking-wider shadow-md hover:brightness-105 active:scale-95 transition-all flex items-center justify-center gap-2 block"
                        style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 50%, #A87D18 100%); border: 1.5px solid #FFF3CD;">
                        Renew / Buy New Package
                    </a>
                </div>
            </div>
        </template>

        <!-- Breakdown Details -->
        <div class="bg-[#FAF8F2] p-4 sm:p-5 rounded-2xl border border-[#DFC387]/70 text-left text-xs space-y-2.5 sm:space-y-2">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-4 pb-2 sm:pb-1.5 border-b border-[#DFC387]/30 text-[#5C410F]">
                <span class="text-[11px] sm:text-xs font-semibold text-[#8C6418] uppercase tracking-wider shrink-0">Card Holder:</span>
                <span class="font-bold text-xs sm:text-sm text-[#1F170D] sm:text-right break-words leading-snug">{{ Auth::user()->name }}</span>
            </div>
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-4 pb-2 sm:pb-1.5 border-b border-[#DFC387]/30 text-[#5C410F]">
                <span class="text-[11px] sm:text-xs font-semibold text-[#8C6418] uppercase tracking-wider shrink-0">Validity Period:</span>
                <span class="font-mono font-bold text-xs sm:text-sm text-[#1F170D] sm:text-right"
                    x-text="currentTicket.start_date ? (formatDate(currentTicket.start_date) + ' - ' + formatDate(currentTicket.end_date)) : 'Not activated yet'"></span>
            </div>
            <div class="pt-2.5 sm:pt-3 border-t border-[#DFC387]/60 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1.5 sm:gap-4">
                <span class="font-serif font-black text-xs sm:text-sm text-[#1F170D] uppercase tracking-wider shrink-0">Total Package Price:</span>
                <span class="font-mono font-black text-base sm:text-lg text-[#1F170D] whitespace-nowrap sm:text-right"
                    x-text="'Rp ' + formatNumber(currentTicket.grand_total)"></span>
            </div>
        </div>
    </div>
</div>
