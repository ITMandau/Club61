<div class="bg-white/95 backdrop-blur-xl rounded-3xl border border-[#E3CF9C] shadow-[0_14px_40px_rgba(160,120,30,0.16)] overflow-hidden">

    <!-- Kepala kartu -->
    <div class="p-4 sm:p-6 bg-gradient-to-br from-[#183428] via-[#10241B] to-[#0A1812] text-white">
        <div class="flex flex-wrap items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-[#DFC387]/20 text-[#F5E6BE] border border-[#DFC387]/40">
                Membership · <span x-text="currentTicket.owner_type === 'ORGANIZATIONAL' ? 'Corporate' : 'Individual'"></span>
            </span>
            <span :class="statusTone(currentTicket.status) === 'ok' ? 'bg-emerald-500 text-white' : (statusTone(currentTicket.status) === 'bad' ? 'bg-rose-500 text-white' : 'bg-amber-400 text-[#1E160A]')"
                  class="px-2.5 py-0.5 rounded-full text-[11px] font-black whitespace-nowrap shadow-sm"
                  x-text="statusLabel(currentTicket.status)"></span>
        </div>
        <h2 class="mt-2 font-serif font-black text-xl sm:text-2xl leading-tight break-words" x-text="currentTicket.plan_name"></h2>
        <div class="mt-1.5 text-[11px] text-emerald-200/90 font-mono truncate" x-text="'Code #' + currentTicket.membership_code"></div>
    </div>

    <!-- Strip info -->
    <div class="grid grid-cols-2 divide-x divide-[#EADBB5] bg-[#FAF6EC] border-y border-dashed border-[#DFC387] text-center">
        <div class="py-2.5 px-2 min-w-0">
            <div class="text-[9px] font-extrabold uppercase tracking-wider text-[#8C7A58]">Membership</div>
            <div class="text-xs font-bold text-[#1F170D] truncate" x-text="statusLabel(currentTicket.status)"></div>
        </div>
        <div class="py-2.5 px-2 min-w-0">
            <div class="text-[9px] font-extrabold uppercase tracking-wider text-[#8C7A58]">Payment</div>
            <div class="text-xs font-bold text-[#1F170D] truncate" x-text="statusLabel(currentTicket.payment_status)"></div>
        </div>
    </div>

    <div class="p-4 sm:p-6 grid grid-cols-1 2xl:grid-cols-2 gap-4 sm:gap-5 items-start">

        <!-- QR kartu member digital: hanya untuk membership ACTIVE -->
        <template x-if="currentTicket.status === 'ACTIVE' && currentTicket.qr_pass_hash">
            <div class="w-full text-center">
                <div class="inline-block p-3.5 bg-white rounded-3xl border-2 border-dashed border-[#DFC387] shadow-inner">
                    {{-- QR dibuat di browser (renderQr) — kode kartu member tidak pernah dikirim ke layanan QR luar. --}}
                    <div x-effect="renderQr($el, currentTicket.qr_pass_hash)" role="img" aria-label="Membership Card QR"
                         class="w-48 h-48 mx-auto rounded-xl overflow-hidden bg-white"></div>
                    <div class="mt-2.5 max-w-[12rem] mx-auto font-mono font-bold text-[10px] text-[#8C6418] tracking-wider break-all leading-snug" x-text="currentTicket.qr_pass_hash"></div>
                </div>
                <div class="max-w-sm mx-auto mt-3">
                    <h4 class="font-bold text-sm">Your digital membership card</h4>
                    <p class="text-xs text-[#7A643E] mt-1 leading-relaxed">Show this QR code at the front desk for verification and facility access.</p>
                </div>
            </div>
        </template>

        <!-- Belum dibayar -->
        <template x-if="currentTicket.status === 'PENDING_PAYMENT' || currentTicket.payment_status === 'UNPAID'">
            <div class="w-full max-w-md mx-auto p-4 sm:p-5 rounded-3xl border-2 border-dashed border-amber-300 bg-amber-50/60 space-y-3.5">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 shrink-0 rounded-2xl bg-amber-100 border border-amber-300 flex items-center justify-center text-amber-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-4a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                    </div>
                    <div class="min-w-0">
                        <h4 class="font-bold text-sm leading-tight">Payment not confirmed yet</h4>
                        <p class="text-[11px] text-[#7A643E] leading-snug mt-0.5"
                           x-text="currentTicket.can_pay_online
                                ? 'Finish payment to activate this membership. The package and price stay the same.'
                                : 'Not activated yet because payment hasn\'t been confirmed. Already paid? Contact our front desk with the order code below.'"></p>
                    </div>
                </div>
                <div class="text-[11px] font-mono font-bold text-amber-900 truncate">Order <span x-text="'#' + (currentTicket.order_number || currentTicket.membership_code)"></span></div>

                {{-- Pesanan online yang belum dibayar: lanjutkan bayar (order yang sama) atau batalkan untuk ganti paket. --}}
                <template x-if="currentTicket.can_pay_online">
                    <div class="space-y-2.5">
                        <div class="p-3 rounded-2xl bg-white border border-[#EADBB5] flex items-center gap-3">
                            <span class="w-11 h-8 shrink-0 rounded-lg bg-[#FBF7EE] border border-[#EADBB5] flex items-center justify-center font-black text-[10px] text-[#8C6418]" x-text="selectedMethod.badge"></span>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-xs leading-snug line-clamp-2" x-text="selectedMethod.name"></div>
                                <div class="text-[10px] text-[#7A643E] leading-snug line-clamp-2" x-text="selectedMethod.note"></div>
                            </div>
                            <button type="button" @click="showPaymentModal = true"
                                    class="bk-tap shrink-0 h-8 px-3 rounded-xl bg-[#FBF7EE] hover:bg-[#F3DFAD] border border-[#EADBB5] text-[#8C6418] text-xs font-bold whitespace-nowrap transition-colors">
                                Change
                            </button>
                        </div>

                        <button type="button" @click="payMembership()" :disabled="isSubmittingPayment"
                                class="bk-tap w-full h-12 rounded-2xl flex items-center justify-center gap-2 text-xs font-black uppercase tracking-wider bg-gradient-to-b from-[#F5DE9B] via-[#D4AF37] to-[#A87D18] text-[#1E160A] shadow-md active:scale-[0.98] transition-transform disabled:opacity-60 disabled:cursor-not-allowed">
                            <span class="whitespace-nowrap" x-text="isSubmittingPayment ? 'Processing…' : ('Pay Rp ' + formatNumber(currentTicket.grand_total))"></span>
                        </button>

                        <button type="button" @click="showCancelMembershipModal = true" :disabled="isSubmittingPayment || isCancellingMembership"
                                class="bk-tap w-full h-10 rounded-2xl text-xs font-bold text-[#9A3412] hover:bg-rose-50 transition-colors disabled:opacity-50">
                            Cancel this order
                        </button>
                    </div>
                </template>

                <template x-if="! currentTicket.can_pay_online">
                    <a href="https://wa.me/6281261617233" target="_blank" rel="noopener"
                       class="bk-tap w-full h-12 rounded-2xl flex items-center justify-center text-xs font-black uppercase tracking-wider bg-gradient-to-b from-[#F5DE9B] via-[#D4AF37] to-[#A87D18] text-[#1E160A] shadow-md active:scale-[0.98] transition-transform">
                        Contact the front desk
                    </a>
                </template>
            </div>
        </template>

        <!-- Kedaluwarsa / dibatalkan -->
        <template x-if="currentTicket.status === 'EXPIRED' || currentTicket.status === 'CANCELLED'">
            <div class="w-full max-w-md mx-auto p-5 sm:p-6 rounded-3xl border-2 border-dashed border-rose-300 bg-rose-50/70 text-center space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-rose-100 border border-rose-300 flex items-center justify-center mx-auto text-rose-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <h4 class="font-bold text-sm" x-text="currentTicket.status === 'CANCELLED' ? 'Membership cancelled' : 'Membership expired'"></h4>
                <a href="{{ route('customer.membership') }}"
                   class="bk-tap w-full h-12 rounded-2xl flex items-center justify-center text-xs font-black uppercase tracking-wider bg-gradient-to-b from-[#F5DE9B] via-[#D4AF37] to-[#A87D18] text-[#1E160A] shadow-md active:scale-[0.98] transition-transform">
                    Renew or buy a package
                </a>
            </div>
        </template>

        <!-- Rincian -->
        <div class="bg-[#FBF7EE] p-4 rounded-2xl border border-[#F0E4C8] text-xs space-y-2.5">
            <div class="flex items-start justify-between gap-3">
                <span class="shrink-0 text-[#7A643E]">Card holder</span>
                <span class="min-w-0 text-right font-bold text-[#1F170D] break-words leading-snug">{{ Auth::user()->name }}</span>
            </div>
            <div class="flex items-start justify-between gap-3">
                <span class="shrink-0 text-[#7A643E]">Valid</span>
                <span class="min-w-0 text-right font-bold text-[#1F170D]"
                      x-text="currentTicket.start_date ? (formatDate(currentTicket.start_date) + ' – ' + formatDate(currentTicket.end_date)) : 'Not activated yet'"></span>
            </div>
            <div class="pt-2.5 border-t border-[#DFC387] flex items-center justify-between gap-3">
                <span class="font-bold text-sm text-[#1F170D]">Package price</span>
                <span class="shrink-0 font-black text-lg tabular-nums whitespace-nowrap text-[#1F170D]" x-text="'Rp ' + formatNumber(currentTicket.grand_total)"></span>
            </div>
        </div>
    </div>
</div>
