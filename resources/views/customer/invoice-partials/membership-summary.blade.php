<!-- Official Tax Invoice Card (Membership Purchase) -->
<div class="bg-white/95 backdrop-blur-xl rounded-3xl border border-[#DFC387] shadow-[0_12px_35px_rgba(160,120,30,0.15)] p-4 sm:p-6 space-y-4">
    <div class="flex items-center justify-between border-b border-[#DFC387]/50 pb-3">
        <div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-[#7A5818]">Official Payment Receipt</span>
            <h3 class="font-serif font-black text-base text-[#1F170D]">Membership Transaction Summary</h3>
        </div>
        <span class="font-mono text-xs font-bold text-[#8C6418]" x-text="'#' + (ticket.order_number || ticket.membership_code)"></span>
    </div>

    <div class="space-y-2.5 text-xs text-[#5C410F]">
        <div class="flex justify-between items-center gap-3">
            <span>Package:</span>
            <span class="font-mono text-[#1F170D] font-bold whitespace-nowrap text-right" x-text="ticket.plan_name"></span>
        </div>
        <div class="flex justify-between items-center gap-3">
            <span>Package Price:</span>
            <span class="font-mono text-[#1F170D] whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(ticket.subtotal)"></span>
        </div>
        <template x-if="ticket.discount_amount > 0">
            <div class="flex justify-between items-center gap-3">
                <span>Discount:</span>
                <span class="font-mono text-emerald-700 whitespace-nowrap text-right" x-text="'- Rp ' + formatNumber(ticket.discount_amount)"></span>
            </div>
        </template>
        <template x-if="ticket.tax_amount > 0">
            <div class="flex justify-between items-center gap-3">
                <span>Pajak (PB1 / PPh):</span>
                <span class="font-mono text-[#1F170D] whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(ticket.tax_amount)"></span>
            </div>
        </template>
        <template x-if="ticket.service_charge > 0">
            <div class="flex justify-between items-center gap-3">
                <span>Biaya Layanan & Admin:</span>
                <span class="font-mono text-[#1F170D] whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(ticket.service_charge)"></span>
            </div>
        </template>
        <div class="flex justify-between items-center gap-3">
            <span>Settlement Status:</span>
            <span :class="ticket.payment_status === 'PAID' ? 'text-emerald-700' : (ticket.payment_status === 'CANCELLED' ? 'text-rose-700' : 'text-amber-700')"
                  class="font-mono font-bold whitespace-nowrap text-right"
                  x-text="ticket.payment_status || '-'"></span>
        </div>

        <div class="pt-3 border-t border-[#DFC387]/60 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1.5 sm:gap-4 text-sm">
            <span class="font-serif font-black text-xs sm:text-sm text-[#1F170D] shrink-0">Total Amount (Invoice):</span>
            <span class="font-mono font-black text-base sm:text-lg text-[#1F170D] whitespace-nowrap sm:text-right" x-text="'Rp ' + formatNumber(ticket.grand_total)"></span>
        </div>
    </div>

    <div class="pt-2">
        <div class="p-3 rounded-2xl bg-[#FAF8F2] border border-[#DFC387]/70 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 rounded-md bg-white border border-[#DFC387] flex items-center justify-center font-bold text-[9px] text-[#8C6418] font-mono shrink-0" x-text="getPaymentMethodObject(ticket.payment_method) ? getPaymentMethodObject(ticket.payment_method).badge : 'PAY'"></span>
                <span class="text-xs font-bold text-[#1F170D]" x-text="'Method: ' + (getPaymentMethodObject(ticket.payment_method) ? getPaymentMethodObject(ticket.payment_method).name : (ticket.payment_method || '-'))"></span>
            </div>
            <span :class="ticket.payment_status === 'PAID' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                  class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full"
                  x-text="ticket.payment_status || '-'">
            </span>
        </div>
    </div>
</div>
