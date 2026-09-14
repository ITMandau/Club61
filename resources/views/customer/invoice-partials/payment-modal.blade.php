<!-- Payment Method Selection Modal (Identik dengan Gambar 3 & Checkout) -->
<div x-show="showPaymentModal" 
     style="display: none;"
     class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-3xl border-2 border-[#DFC387] shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-[#DFC387]/50 pb-3">
            <h3 class="font-serif font-black text-base text-[#1F170D]">Pilih Metode Pembayaran</h3>
            <button type="button" @click="showPaymentModal = false" class="text-xs text-[#8C7A58] hover:text-[#1F170D] font-bold cursor-pointer">Tutup</button>
        </div>

        <div class="space-y-2.5 max-h-96 overflow-y-auto pr-1">
            <template x-for="m in paymentMethods" :key="m.id">
                <button type="button" 
                        @click="selectPaymentMethod(m)"
                        :class="selectedMethod.id === m.id ? 'border-[#D4AF37] bg-[#FAF6EC]' : 'border-[#E8DCC0] hover:bg-gray-50'"
                        class="w-full p-3.5 rounded-2xl border text-left flex items-center justify-between transition-all cursor-pointer">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-white border border-[#DFC387] flex items-center justify-center font-bold text-[10px] text-[#8C6418] font-mono" x-text="m.badge"></span>
                        <div>
                            <div class="font-bold text-xs text-[#1F170D]" x-text="m.name"></div>
                            <div class="text-[10px] text-[#7A643E]" x-text="m.note"></div>
                        </div>
                    </div>
                    <span class="text-xs font-mono font-bold text-[#1F170D]" x-text="m.fee > 0 ? '+ Rp ' + formatNumber(m.fee) : 'Free'"></span>
                </button>
            </template>
        </div>
    </div>
</div>
