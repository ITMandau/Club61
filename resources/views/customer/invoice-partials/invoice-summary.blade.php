<!-- Official Tax Invoice Card -->
<div class="bg-white/95 backdrop-blur-xl rounded-3xl border border-[#DFC387] shadow-[0_12px_35px_rgba(160,120,30,0.15)] p-4 sm:p-6 space-y-4">
    <div class="flex items-center justify-between border-b border-[#DFC387]/50 pb-3">
        <div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-[#7A5818]">Bukti Bayar Resmi</span>
            <h3 class="font-serif font-black text-base text-[#1F170D]">Ringkasan Transaksi</h3>
        </div>
        <span class="font-mono text-xs font-bold text-[#8C6418]" x-text="'#' + ((ticket.order && ticket.order.order_number) ? ticket.order.order_number : (ticket.booking_code || (ticket.order_id ? ticket.order_id.substring(0, 12) : ticket.id.substring(0, 10))))"></span>
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
            <span :class="(ticket.status === 'PAID' || ticket.status === 'CHECKED_IN') ? 'text-emerald-700' : ((ticket.status === 'EXPIRED' || ticket.status === 'CANCELLED' || ticket.status === 'REFUNDED') ? 'text-rose-700' : 'text-amber-700')"
                  class="font-mono font-bold whitespace-nowrap text-right" 
                  x-text="ticket.status"></span>
        </div>

        <div class="pt-3 border-t border-[#DFC387]/60 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1.5 sm:gap-4 text-sm">
            <span class="font-serif font-black text-xs sm:text-sm text-[#1F170D] shrink-0">Total Transaksi (Invoice):</span>
            <span class="font-mono font-black text-base sm:text-lg text-[#1F170D] whitespace-nowrap sm:text-right" x-text="'Rp ' + formatNumber(displayGrandTotal)"></span>
        </div>

        <!-- Rincian Terbayar & Sisa Reschedule jika Ada Tagihan Sisa -->
        <template x-if="ticket.has_pending_delta">
            <div class="space-y-1.5 pt-2 border-t border-dashed border-[#DFC387]/60 text-xs">
                <div class="flex justify-between items-center gap-3 text-emerald-800">
                    <span>Terbayar Awal:</span>
                    <span class="font-mono font-bold whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(ticket.total_paid)"></span>
                </div>
                <div class="flex justify-between items-center gap-3 text-amber-900 font-bold">
                    <span>Sisa Kurang Bayar:</span>
                    <span class="font-mono text-red-700 font-black whitespace-nowrap text-right" x-text="'Rp ' + formatNumber(ticket.unpaid_delta)"></span>
                </div>
            </div>
        </template>
    </div>

    <div class="pt-2">
        <div class="p-3 rounded-2xl bg-[#FAF8F2] border border-[#DFC387]/70 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 rounded-md bg-white border border-[#DFC387] flex items-center justify-center font-bold text-[9px] text-[#8C6418] font-mono shrink-0" x-text="selectedMethod.badge"></span>
                <span class="text-xs font-bold text-[#1F170D]" x-text="selectedMethod.code === 'CASH' ? 'Metode: Bayar Tunai (Kasir)' : 'Metode: ' + selectedMethod.name"></span>
            </div>
            <span :class="(ticket.status === 'PAID' || ticket.status === 'CHECKED_IN') ? 'bg-emerald-100 text-emerald-800' : ((ticket.status === 'EXPIRED' || ticket.status === 'CANCELLED' || ticket.status === 'REFUNDED') ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800')"
                  class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full"
                  x-text="ticket.status">
            </span>
        </div>
    </div>
</div>
