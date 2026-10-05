<!-- Official Tax Invoice Card -->
<div class="bg-white/95 backdrop-blur-xl rounded-3xl border border-[#DFC387] shadow-[0_12px_35px_rgba(160,120,30,0.15)] p-4 sm:p-6 space-y-4">
    <div class="flex items-center justify-between border-b border-[#DFC387]/50 pb-3">
        <div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-[#7A5818]">Official Payment Receipt</span>
            <h3 class="font-serif font-black text-base text-[#1F170D]">Transaction Summary</h3>
        </div>
        <span class="font-mono text-xs font-bold text-[#8C6418]" x-text="'#' + ((ticket.order && ticket.order.order_number) ? ticket.order.order_number : (ticket.booking_code || (ticket.order_id ? ticket.order_id.substring(0, 12) : ticket.id.substring(0, 10))))"></span>
    </div>

    <div class="space-y-2.5 text-xs text-[#5C410F]">
        <div class="flex justify-between items-center gap-3">
            <span>Reservation Date:</span>
            <span class="font-mono text-[#1F170D] font-bold whitespace-nowrap text-right" x-text="formatDate(ticket.booking_date)"></span>
        </div>
        <div class="flex justify-between items-center gap-3">
            <span>Court Rental Total:</span>
            <span class="font-mono text-[#1F170D] whitespace-nowrap text-right"
                  x-text="'Rp ' + formatNumber(displayCourtFee + totalMemberDiscount + totalSponsorDiscount)"></span>
        </div>
        <template x-if="totalMemberDiscount > 0">
            <div class="flex justify-between items-center gap-3 text-[#8C6418] font-bold">
                <span>Membership Benefit Discount:</span>
                <span class="font-mono whitespace-nowrap text-right" x-text="'- Rp ' + formatNumber(totalMemberDiscount)"></span>
            </div>
        </template>
        <template x-if="totalSponsorDiscount > 0">
            <div class="flex justify-between items-center gap-3 text-[#1E3327] font-bold">
                <span>🎟️ Corporate Voucher Discount <span x-text="'(' + totalSponsorHours + ' hrs)'"></span>:</span>
                <span class="font-mono whitespace-nowrap text-right" x-text="'- Rp ' + formatNumber(totalSponsorDiscount)"></span>
            </div>
        </template>
        <div class="flex justify-between items-center gap-3">
            <span>Rental Equipment (Flat):</span>
            <span class="font-mono text-[#1F170D] whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(displayEquipmentFee)"></span>
        </div>
        <template x-if="ticket.order && ticket.order.tax_amount > 0">
            <div class="flex justify-between items-center gap-3">
                <span>Pajak (PB1 / PPh):</span>
                <span class="font-mono text-[#1F170D] whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(ticket.order.tax_amount)"></span>
            </div>
        </template>
        <template x-if="ticket.order && ticket.order.service_charge > 0">
            <div class="flex justify-between items-center gap-3">
                <span>Biaya Layanan & Admin:</span>
                <span class="font-mono text-[#1F170D] whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(ticket.order.service_charge)"></span>
            </div>
        </template>
        <div class="flex justify-between items-center gap-3">
            <span>Settlement Status:</span>
            <span :class="(ticket.status === 'PAID' || ticket.status === 'CHECKED_IN') ? 'text-emerald-700' : ((ticket.status === 'EXPIRED' || ticket.status === 'CANCELLED' || ticket.status === 'REFUNDED' || ticket.status === 'REFUND_PENDING') ? 'text-rose-700' : 'text-amber-700')"
                  class="font-mono font-bold whitespace-nowrap text-right" 
                  x-text="ticket.status"></span>
        </div>

        <div class="pt-3 border-t border-[#DFC387]/60 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1.5 sm:gap-4 text-sm">
            <span class="font-serif font-black text-xs sm:text-sm text-[#1F170D] shrink-0">Total Amount (Invoice):</span>
            <span class="font-mono font-black text-base sm:text-lg text-[#1F170D] whitespace-nowrap sm:text-right" x-text="'Rp ' + formatNumber(displayGrandTotal)"></span>
        </div>

        <!-- Reschedule: selisih ke jam lebih mahal (sudah termasuk total di atas) & selisih yang hangus -->
        <template x-if="ticket.reschedule_charges && ticket.reschedule_charges.length > 0 && !ticket.has_pending_delta">
            <div class="space-y-1.5 pt-2 border-t border-dashed border-[#DFC387]/60 text-xs">
                <template x-for="(rc, idx) in ticket.reschedule_charges" :key="idx">
                    <div class="flex justify-between items-center gap-3 text-[#5C410F]">
                        <span x-text="'Selisih Reschedule (Lunas' + (rc.method_label ? ' • ' + rc.method_label : '') + '):'"></span>
                        <span class="font-mono font-bold whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(rc.amount)"></span>
                    </div>
                </template>
                <div class="flex justify-between items-center gap-3 text-emerald-800 font-bold">
                    <span>Total Dibayar:</span>
                    <span class="font-mono whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(ticket.total_paid)"></span>
                </div>
            </div>
        </template>
        <template x-if="ticket.order_reschedule_forfeited > 0">
            <div class="flex justify-between items-center gap-3 pt-2 border-t border-dashed border-[#DFC387]/60 text-xs text-amber-900">
                <span>Selisih Reschedule Hangus (tidak dikembalikan):</span>
                <span class="font-mono font-bold whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(ticket.order_reschedule_forfeited)"></span>
            </div>
        </template>

        <!-- Paid & Remaining Breakdown for Reschedule -->
        <template x-if="ticket.has_pending_delta">
            <div class="space-y-1.5 pt-2 border-t border-dashed border-[#DFC387]/60 text-xs">
                <div class="flex justify-between items-center gap-3 text-emerald-800">
                    <span>Sudah Dibayar:</span>
                    <span class="font-mono font-bold whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(ticket.total_paid)"></span>
                </div>
                <div class="flex justify-between items-center gap-3 text-amber-900 font-bold">
                    <span>Sisa Tagihan Selisih Reschedule:</span>
                    <span class="font-mono text-red-700 font-black whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(ticket.unpaid_delta)"></span>
                </div>
                <p class="text-[11px] leading-snug text-amber-900"
                    x-text="ticket.pending_delta_channel === 'CASHIER'
                        ? 'Sesuai permintaan Anda, selisih dibayar di kasir saat datang. Anda juga bisa melunasinya sekarang secara online.'
                        : 'Silakan lunasi selisih secara online di bawah ini. QR tiket aktif otomatis setelah lunas.'"></p>
            </div>
        </template>
    </div>

    <div class="pt-2">
        <div class="p-3 rounded-2xl bg-[#FAF8F2] border border-[#DFC387]/70 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 rounded-md bg-white border border-[#DFC387] flex items-center justify-center font-bold text-[9px] text-[#8C6418] font-mono shrink-0" x-text="selectedMethod.badge"></span>
                <span class="text-xs font-bold text-[#1F170D]" x-text="selectedMethod.code === 'CASH' ? 'Method: Cash on Arrival (Frontdesk)' : 'Method: ' + selectedMethod.name"></span>
            </div>
            <span :class="(ticket.status === 'PAID' || ticket.status === 'CHECKED_IN') ? 'bg-emerald-100 text-emerald-800' : ((ticket.status === 'EXPIRED' || ticket.status === 'CANCELLED' || ticket.status === 'REFUNDED' || ticket.status === 'REFUND_PENDING') ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800')"
                  class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full"
                  x-text="ticket.status">
            </span>
        </div>
    </div>
</div>
