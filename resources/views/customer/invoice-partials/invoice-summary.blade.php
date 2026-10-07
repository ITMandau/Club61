<!-- Ringkasan transaksi (bukti pembayaran) -->
<div class="bg-white/90 backdrop-blur-xl rounded-3xl border border-[#EADBB5] shadow-[0_8px_30px_rgba(160,120,30,0.08)] p-4 sm:p-5 space-y-4">
    <div class="pb-3 border-b border-[#F0E4C8]">
        <div class="text-[11px] font-extrabold uppercase tracking-wider text-[#8C6418]">Payment receipt</div>
        <h3 class="font-bold text-base leading-tight mt-0.5">Transaction summary</h3>
        <div class="mt-1 text-[11px] font-mono font-bold text-[#8C6418] truncate"
             x-text="'#' + ((ticket.order && ticket.order.order_number) ? ticket.order.order_number : (ticket.booking_code || (ticket.order_id ? ticket.order_id.substring(0, 12) : ticket.id.substring(0, 10))))"></div>
    </div>

    <div class="space-y-2.5 text-xs text-[#5C410F]">
        <div class="flex items-start justify-between gap-3">
            <span class="min-w-0">Booking date</span>
            <span class="shrink-0 font-bold text-[#1F170D] whitespace-nowrap" x-text="formatDate(ticket.booking_date)"></span>
        </div>
        <div class="flex items-start justify-between gap-3">
            <span class="min-w-0">Court rental</span>
            <span class="shrink-0 font-bold text-[#1F170D] tabular-nums whitespace-nowrap" x-text="'Rp ' + formatNumber(displayCourtFee + totalMemberDiscount + totalSponsorDiscount)"></span>
        </div>
        <template x-if="totalMemberDiscount > 0">
            <div class="flex items-start justify-between gap-3 text-[#8C6418] font-bold">
                <span class="min-w-0">Membership discount</span>
                <span class="shrink-0 tabular-nums whitespace-nowrap" x-text="'- Rp ' + formatNumber(totalMemberDiscount)"></span>
            </div>
        </template>
        <template x-if="totalSponsorDiscount > 0">
            <div class="flex items-start justify-between gap-3 text-[#1E3327] font-bold">
                <span class="min-w-0">Corporate voucher <span class="font-semibold" x-text="'(' + totalSponsorHours + ' hrs)'"></span></span>
                <span class="shrink-0 tabular-nums whitespace-nowrap" x-text="'- Rp ' + formatNumber(totalSponsorDiscount)"></span>
            </div>
        </template>
        <div class="flex items-start justify-between gap-3">
            <span class="min-w-0">Equipment</span>
            <span class="shrink-0 font-bold text-[#1F170D] tabular-nums whitespace-nowrap" x-text="'Rp ' + formatNumber(displayEquipmentFee)"></span>
        </div>
        <template x-if="ticket.order && ticket.order.tax_amount > 0">
            <div class="flex items-start justify-between gap-3">
                <span class="min-w-0">Tax</span>
                <span class="shrink-0 font-bold text-[#1F170D] tabular-nums whitespace-nowrap" x-text="'Rp ' + formatNumber(ticket.order.tax_amount)"></span>
            </div>
        </template>
        <template x-if="ticket.order && ticket.order.service_charge > 0">
            <div class="flex items-start justify-between gap-3">
                <span class="min-w-0">Service fee</span>
                <span class="shrink-0 font-bold text-[#1F170D] tabular-nums whitespace-nowrap" x-text="'Rp ' + formatNumber(ticket.order.service_charge)"></span>
            </div>
        </template>
        <div class="flex items-center justify-between gap-3">
            <span class="min-w-0">Status</span>
            <span class="shrink-0 px-2 py-0.5 rounded-full text-[11px] font-bold border whitespace-nowrap" :class="statusPillClass(ticket.status)" x-text="statusLabel(ticket.status)"></span>
        </div>

        <div class="pt-3 border-t border-[#F0E4C8] flex items-center justify-between gap-3">
            <span class="font-bold text-sm text-[#1F170D]">Total</span>
            <span class="shrink-0 font-black text-lg tabular-nums whitespace-nowrap text-[#1F170D]" x-text="'Rp ' + formatNumber(displayGrandTotal)"></span>
        </div>

        <!-- Reschedule: selisih ke jam lebih mahal (sudah termasuk total di atas) & selisih yang hangus -->
        <template x-if="ticket.reschedule_charges && ticket.reschedule_charges.length > 0 && !ticket.has_pending_delta">
            <div class="space-y-1.5 pt-2 border-t border-dashed border-[#EADBB5]">
                <template x-for="(rc, idx) in ticket.reschedule_charges" :key="idx">
                    <div class="flex items-start justify-between gap-3">
                        <span class="min-w-0" x-text="'Reschedule difference (paid' + (rc.method_label ? ' · ' + rc.method_label : '') + ')'"></span>
                        <span class="shrink-0 font-bold tabular-nums whitespace-nowrap" x-text="'Rp ' + formatNumber(rc.amount)"></span>
                    </div>
                </template>
                <div class="flex items-start justify-between gap-3 text-emerald-800 font-bold">
                    <span class="min-w-0">Total paid</span>
                    <span class="shrink-0 tabular-nums whitespace-nowrap" x-text="'Rp ' + formatNumber(ticket.total_paid)"></span>
                </div>
            </div>
        </template>
        <template x-if="ticket.order_reschedule_forfeited > 0">
            <div class="flex items-start justify-between gap-3 pt-2 border-t border-dashed border-[#EADBB5] text-amber-900">
                <span class="min-w-0">Reschedule difference forfeited (not refunded)</span>
                <span class="shrink-0 font-bold tabular-nums whitespace-nowrap" x-text="'Rp ' + formatNumber(ticket.order_reschedule_forfeited)"></span>
            </div>
        </template>

        <!-- Sudah dibayar & sisa tagihan selisih reschedule -->
        <template x-if="ticket.has_pending_delta">
            <div class="space-y-1.5 pt-2 border-t border-dashed border-[#EADBB5]">
                <div class="flex items-start justify-between gap-3 text-emerald-800">
                    <span class="min-w-0">Already paid</span>
                    <span class="shrink-0 font-bold tabular-nums whitespace-nowrap" x-text="'Rp ' + formatNumber(ticket.total_paid)"></span>
                </div>
                <div class="flex items-start justify-between gap-3 text-amber-900 font-bold">
                    <span class="min-w-0">Reschedule balance due</span>
                    <span class="shrink-0 font-black tabular-nums text-red-700 whitespace-nowrap" x-text="'Rp ' + formatNumber(ticket.unpaid_delta)"></span>
                </div>
                <p class="text-[11px] leading-snug text-amber-900"
                   x-text="ticket.pending_delta_channel === 'CASHIER'
                        ? 'As requested, you can pay the difference at the cashier when you arrive — or settle it online now.'
                        : 'Please pay the difference online below. Your QR ticket activates automatically once it is paid.'"></p>
            </div>
        </template>
    </div>

    <div class="p-3 rounded-2xl bg-[#FBF7EE] border border-[#F0E4C8] flex items-center gap-2.5">
        <span class="w-10 h-7 shrink-0 rounded-md bg-white border border-[#EADBB5] flex items-center justify-center font-black text-[9px] text-[#8C6418]" x-text="selectedMethod.badge"></span>
        <span class="flex-1 min-w-0 text-xs font-bold text-[#1F170D] truncate" x-text="selectedMethod.code === 'CASH' ? 'Cash at the front desk' : selectedMethod.name"></span>
        <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold border whitespace-nowrap" :class="statusPillClass(ticket.status)" x-text="statusLabel(ticket.status)"></span>
    </div>
</div>
