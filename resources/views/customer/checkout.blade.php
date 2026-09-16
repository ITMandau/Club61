<x-app-layout>
    <div x-data="checkoutApp()" x-init="init()" class="py-6 sm:py-8 text-[#1F170D]">
        <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16 space-y-6">

            <!-- Top Header & Breadcrumb -->
            <div
                class="flex items-center justify-between bg-white/95 backdrop-blur-xl p-5 rounded-3xl border border-[#DFC387] shadow-sm">
                <div class="flex items-center gap-3.5">
                    <a href="{{ route('customer.cart') }}"
                        class="p-2.5 rounded-2xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] transition-colors"
                        title="Back to Cart">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <h1 class="font-serif font-black text-xl sm:text-2xl text-[#1F170D]">Payment Details &amp; Checkout</h1>
                        <p class="text-xs text-[#7A643E]">Select add-on equipment, apply promo codes, and choose payment method</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 bg-white/80 px-3.5 py-1.5 rounded-2xl border border-[#DFC387]">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-xs font-mono font-bold text-[#8C6418]"
                        x-text="'Time Left: ' + timerDisplay">10:00</span>
                </div>
            </div>

            <!-- Multi-Column Desktop Checkout Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- Left Column: Details, Add-ons, Methods (Col 8) -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- 1. Booking Items Breakdown Card -->
                    <div
                        class="bg-white/95 backdrop-blur-xl rounded-3xl border border-[#DFC387] shadow-[0_15px_40px_rgba(160,120,30,0.15)] p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-[#DFC387]/50 pb-3">
                            <div>
                                <h2 class="font-serif font-black text-base text-[#1F170D]">Selected Padel Court Schedule</h2>
                                <p class="text-xs text-[#7A643E]" x-text="bookingDateFormatted"></p>
                            </div>
                            <span
                                class="px-3 py-1 rounded-full text-xs font-bold bg-[#FAF2DE] text-[#7A5818] border border-[#DFC387]"
                                x-text="bookingItems.length + (bookingItems.length > 1 ? ' Match Sessions' : ' Match Session')"></span>
                        </div>

                        <div class="divide-y divide-[#EEDBB0]/60 text-xs">
                            <template x-for="(item, idx) in bookingItems" :key="idx">
                                <div class="py-3.5 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-8 h-8 rounded-xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center text-[10px] font-black text-[#7A5818]">
                                            COURT
                                        </div>
                                        <div>
                                            <div class="font-bold text-sm text-[#1F170D]"
                                                x-text="item.court || 'Court Arena'"></div>
                                            <div class="text-[11px] text-[#7A643E] font-mono" x-text="item.time"></div>
                                        </div>
                                    </div>
                                    <span class="font-mono font-black text-sm text-[#1F170D]"
                                        x-text="'Rp ' + formatNumber(item.price)"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- 2. Equipment Add-Ons (Real Catalog) -->
                    <div
                        class="bg-white/95 backdrop-blur-xl rounded-3xl border border-[#DFC387] shadow-[0_15px_40px_rgba(160,120,30,0.15)] p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-[#DFC387]/50 pb-3">
                            <div>
                                <h2 class="font-serif font-black text-base text-[#1F170D]">Equipment Rental &amp; Add-ons</h2>
                                <p class="text-xs text-[#7A643E]">Official tournament-standard equipment and gear</p>
                            </div>
                            <button type="button" @click="showAddOnsModal = true"
                                class="text-xs font-bold text-[#8C6418] hover:text-[#5C410F] flex items-center gap-1 cursor-pointer">
                                <span>+ Add Equipment</span>
                            </button>
                        </div>

                        <!-- Selected Add-ons List -->
                        <div x-show="selectedAddOns.length === 0"
                            class="text-xs text-[#8C7A58] italic py-2 text-center">
                            No add-on equipment selected yet. Click "+ Add Equipment" to rent rackets or balls.
                        </div>

                        <div x-show="selectedAddOns.length > 0" class="divide-y divide-[#EEDBB0]/60 text-xs">
                            <template x-for="(addon, idx) in selectedAddOns" :key="idx">
                                <div class="py-3 flex items-center justify-between">
                                    <div>
                                        <div class="font-bold text-[#1F170D]" x-text="addon.name"></div>
                                        <div class="text-[10px] text-[#7A643E]" x-text="addon.desc"></div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="font-mono font-bold text-[#1F170D]"
                                            x-text="'Rp ' + formatNumber(addon.price * (addon.quantity || 1))"></span>
                                        <button type="button" @click="removeAddon(idx)"
                                            class="text-rose-500 hover:text-rose-700 text-xs font-bold cursor-pointer">Remove</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- 3. Payment Method Selection (Midtrans Gateway) -->
                    <div
                        class="bg-white/95 backdrop-blur-xl rounded-3xl border border-[#DFC387] shadow-[0_15px_40px_rgba(160,120,30,0.15)] p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-[#DFC387]/50 pb-3">
                            <div>
                                <h2 class="font-serif font-black text-base text-[#1F170D]">Payment Method (Midtrans Gateway)</h2>
                                <p class="text-xs text-[#7A643E]">Instant QRIS and automated bank Virtual Account verification</p>
                            </div>
                            <button type="button" @click="showPaymentModal = true"
                                class="text-xs font-bold text-[#8C6418] hover:text-[#5C410F] cursor-pointer">
                                Change Method &rarr;
                            </button>
                        </div>

                        <!-- Selected Method Display Card -->
                        <div
                            class="p-4 rounded-2xl bg-[#FAF8F2] border border-[#DFC387] flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-white border border-[#DFC387] flex items-center justify-center font-bold text-xs font-mono text-[#8C6418]"
                                    x-text="selectedMethod.badge">
                                    QRIS
                                </div>
                                <div>
                                    <div class="font-bold text-sm text-[#1F170D]" x-text="selectedMethod.name"></div>
                                    <div class="text-[11px] text-[#7A643E]" x-text="selectedMethod.note"></div>
                                </div>
                            </div>

                            <span class="text-xs font-mono font-bold text-[#1F170D]"
                                x-text="selectedMethod.fee > 0 ? '+ Rp ' + formatNumber(selectedMethod.fee) : 'No Fee'"></span>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Payment Summary & Pay CTA (Col 4) -->
                <div class="lg:col-span-4 space-y-4">
                    <div
                        class="bg-white/95 backdrop-blur-xl rounded-3xl border-2 border-[#D4AF37] shadow-[0_15px_40px_rgba(160,120,30,0.18)] p-6 space-y-5 sticky top-24">

                        <h3 class="font-serif font-black text-base text-[#1F170D] border-b border-[#DFC387]/50 pb-3">
                            Payment Summary</h3>

                        <!-- Promo Code Input -->
                        <div class="space-y-2">
                            <label class="text-[11px] font-bold text-[#7A5818] uppercase tracking-wider block">Discount Promo Code</label>
                            <div class="flex gap-2">
                                <input type="text" x-model="promoCode" :disabled="promoApplied"
                                    placeholder="HEMAT10 / CLUB61"
                                    class="flex-1 px-3.5 py-2.5 rounded-xl border border-[#DFC387] text-xs font-mono uppercase focus:ring-1 focus:ring-[#D4AF37] focus:outline-none bg-white">
                                <button type="button" x-show="!promoApplied" @click="applyPromo()"
                                    class="px-4 py-2.5 rounded-xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] text-xs font-bold transition-colors cursor-pointer">
                                    Apply
                                </button>
                                <button type="button" x-show="promoApplied" @click="removePromo()"
                                    class="px-3 py-2.5 rounded-xl bg-rose-50 text-rose-600 border border-rose-200 text-xs font-bold cursor-pointer">
                                    Remove
                                </button>
                            </div>
                            <div x-show="promoApplied" class="text-[10px] text-emerald-700 font-bold">
                                Promo Code Applied: Saved Rp 40,000
                            </div>
                        </div>

                        <!-- Price Breakdown List -->
                        <div class="space-y-2.5 text-xs text-[#5C410F] border-t border-[#DFC387]/50 pt-3">
                            <div class="flex justify-between">
                                <span>Court Rental Subtotal:</span>
                                <span class="font-mono font-bold" x-text="'Rp ' + formatNumber(subtotal)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Equipment Add-ons:</span>
                                <span class="font-mono font-bold" x-text="'Rp ' + formatNumber(addonsTotal)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Gateway Processing Fee:</span>
                                <span class="font-mono font-bold"
                                    x-text="'Rp ' + formatNumber(selectedMethod.fee)"></span>
                            </div>
                            <div x-show="promoApplied" class="flex justify-between text-emerald-700 font-bold">
                                <span>Voucher Discount:</span>
                                <span class="font-mono" x-text="'- Rp ' + formatNumber(promoDiscount)"></span>
                            </div>
                            <div class="flex justify-between text-[10px] text-[#8C7A58]">
                                <span>11% VAT (Included):</span>
                                <span class="font-mono"
                                    x-text="'Rp ' + formatNumber(Math.round(grandTotal * 0.11 / 1.11))"></span>
                            </div>

                            <div class="pt-3 border-t border-[#DFC387]/60 flex justify-between items-center text-sm">
                                <span class="font-serif font-black text-[#1F170D]">Grand Total:</span>
                                <span class="font-mono font-black text-xl text-[#1F170D]"
                                    x-text="'Rp ' + formatNumber(grandTotal)"></span>
                            </div>
                        </div>

                        <!-- Pay Button with Loading & Expiry State -->
                        <div class="pt-2">
                            <button type="button" :disabled="isSubmitting || isExpired" @click="executePayment()"
                                class="w-full py-4 rounded-2xl text-xs font-black uppercase tracking-widest transition-all duration-150 transform shadow-lg flex items-center justify-center gap-2 cursor-pointer"
                                :class="(isSubmitting || isExpired) ? 'opacity-60 cursor-not-allowed bg-gray-400 text-white' :
                                'hover:brightness-105 active:scale-95 text-[#1E160A]'"
                                style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 50%, #A87D18 100%); border: 1.5px solid #FFF3CD;">

                                <!-- Spinner loading -->
                                <template x-if="isSubmitting">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="w-4 h-4 border-2 border-[#1E160A] border-t-transparent rounded-full animate-spin">
                                        </div>
                                        <span>Processing Secure Payment...</span>
                                    </div>
                                </template>

                                <!-- Normal Pay Label -->
                                <template x-if="!isSubmitting">
                                    <div class="flex items-center gap-2">
                                        <span>Pay Now</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                </template>
                            </button>

                            <template x-if="canCancelBooking">
                                <button type="button" @click="cancelCheckout()"
                                    class="w-full mt-2 py-2.5 px-4 rounded-2xl text-rose-700 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 border border-rose-200 text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                                    <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span>Cancel &amp; Select Another Schedule</span>
                                </button>
                            </template>
                        </div>

                        <div class="text-[10px] text-center text-[#7A643E]">
                            Transactions secured with 256-bit SSL encryption and Midtrans 3D-Secure.
                        </div>

                    </div>
                </div>

            </div>

        </div>

        <!-- Session Expired Modal -->
        <div x-show="showExpiredModal" style="display: none; z-index: 99999 !important;"
            class="fixed inset-0 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div
                class="bg-white rounded-3xl border-2 border-[#D4AF37] max-w-md w-full p-6 text-center space-y-4 shadow-2xl animate-scaleIn">
                <div
                    class="w-16 h-16 rounded-full bg-rose-100 border border-rose-300 text-rose-600 flex items-center justify-center mx-auto text-xs font-black tracking-wider">
                    EXPIRED
                </div>
                <h3 class="font-serif font-black text-xl text-[#1F170D]">Session Expired!</h3>
                <p class="text-xs text-[#7A643E] leading-relaxed">
                    The 10-minute reservation window has ended and your held court slots have been released back to the public schedule.
                </p>
                <div class="pt-2">
                    <a href="{{ route('customer.booking') }}"
                        class="w-full py-3.5 px-4 rounded-2xl text-[#1E160A] text-xs font-black uppercase tracking-wider block shadow-md hover:brightness-105 transition-all"
                        style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 100%); color: #1E160A; border: 1px solid #FFF3CD;">
                        Select Schedule Again &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Payment Method Selection Modal -->
        <div x-show="showPaymentModal" style="display: none; z-index: 99999 !important;"
            class="fixed inset-0 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="w-full max-w-md bg-white rounded-3xl border-2 border-[#DFC387] shadow-2xl p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-[#DFC387]/50 pb-3">
                    <h3 class="font-serif font-black text-base text-[#1F170D]">Select Payment Method</h3>
                    <button type="button" @click="showPaymentModal = false"
                        class="text-xs text-[#8C7A58] hover:text-[#1F170D] font-bold cursor-pointer">Close</button>
                </div>

                <div class="space-y-2.5 max-h-96 overflow-y-auto pr-1">
                    <template x-for="m in paymentMethods" :key="m.id">
                        <button type="button" @click="selectPaymentMethod(m)"
                            :class="selectedMethod.id === m.id ? 'border-[#D4AF37] bg-[#FAF6EC]' :
                                'border-[#E8DCC0] hover:bg-gray-50'"
                            class="w-full p-3.5 rounded-2xl border text-left flex items-center justify-between transition-all cursor-pointer">
                            <div class="flex items-center gap-3">
                                <span
                                    class="w-8 h-8 rounded-lg bg-white border border-[#DFC387] flex items-center justify-center font-bold text-[10px] text-[#8C6418]"
                                    x-text="m.badge"></span>
                                <div>
                                    <div class="font-bold text-xs text-[#1F170D]" x-text="m.name"></div>
                                    <div class="text-[10px] text-[#7A643E]" x-text="m.note"></div>
                                </div>
                            </div>
                            <span class="text-xs font-mono font-bold text-[#1F170D]"
                                x-text="m.fee > 0 ? '+ Rp ' + formatNumber(m.fee) : 'No Fee'"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- Add-Ons Selection Modal -->
        <div x-show="showAddOnsModal" style="display: none; z-index: 99999 !important;"
            class="fixed inset-0 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="w-full max-w-md bg-white rounded-3xl border-2 border-[#DFC387] shadow-2xl p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-[#DFC387]/50 pb-3">
                    <h3 class="font-serif font-black text-base text-[#1F170D]">Equipment &amp; Add-ons Catalog</h3>
                    <button type="button" @click="showAddOnsModal = false"
                        class="text-xs text-[#8C7A58] hover:text-[#1F170D] font-bold cursor-pointer">Done</button>
                </div>

                <div class="space-y-2.5 max-h-96 overflow-y-auto pr-1">
                    <template x-for="addon in availableAddOns" :key="addon.id">
                        <div
                            class="p-3.5 rounded-2xl border border-[#DFC387]/70 bg-[#FAF8F2] flex items-center justify-between">
                            <div>
                                <div class="font-bold text-xs text-[#1F170D]" x-text="addon.name"></div>
                                <div class="text-[10px] text-[#7A643E]" x-text="addon.desc"></div>
                                <div class="font-mono font-bold text-xs text-[#8C6418] mt-1"
                                    x-text="'Rp ' + formatNumber(addon.price) + ' / session'"></div>
                            </div>

                            <button type="button" @click="toggleAddOn(addon)"
                                :class="isAddOnSelected(addon.id) ? 'bg-rose-100 text-rose-700 border-rose-300' :
                                    'bg-[#FAF2DE] text-[#7A5818] border-[#DFC387]'"
                                class="px-3.5 py-1.5 rounded-xl text-xs font-bold border transition-colors cursor-pointer">
                                <span x-text="isAddOnSelected(addon.id) ? 'Remove' : '+ Add'"></span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Success QRIS Simulation Modal (Sandbox Mode) -->
        <div x-show="showPaymentSuccessModal" style="display: none; z-index: 99999 !important;"
            class="fixed inset-0 overflow-y-auto bg-black/75 backdrop-blur-md flex items-center justify-center p-4 sm:p-6">

            <div
                class="w-full max-w-2xl bg-white rounded-3xl border-2 border-[#D4AF37] shadow-2xl p-6 sm:p-8 space-y-6 animate-scaleIn">

                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-[#DFC387]/50 pb-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center font-serif font-black text-xs text-[#7A5818] shadow-sm">
                            61
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-serif font-black text-base sm:text-lg text-[#1F170D]">Complete Payment</h3>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    Midtrans Sandbox
                                </span>
                            </div>
                            <p class="text-xs text-[#7A643E]">Scan QRIS or complete the simulated payment for your order</p>
                        </div>
                    </div>
                    <button type="button" @click="showPaymentSuccessModal = false"
                        class="p-2 text-[#8C7A58] hover:text-[#1F170D] rounded-xl hover:bg-[#FAF2DE] transition-colors cursor-pointer"
                        title="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body: 2-Column Responsive Layout -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">

                    <!-- Left Column: Bill Summary & Action (Col 7) -->
                    <div class="md:col-span-7 space-y-4">
                        <div class="p-4 rounded-2xl bg-[#FAF8F2] border border-[#E8DCC0] space-y-3">
                            <div class="flex justify-between items-center text-xs text-[#5C410F]">
                                <span>Selected Method:</span>
                                <span class="font-bold text-[#1F170D]"
                                    x-text="selectedMethod.name || 'QRIS Instant'"></span>
                            </div>
                            <div class="flex justify-between items-center text-xs text-[#5C410F]">
                                <span>System Status:</span>
                                <span
                                    class="font-mono text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Awaiting Payment</span>
                            </div>
                            <div class="pt-2 border-t border-[#DFC387]/60 flex justify-between items-baseline">
                                <span class="text-xs font-serif font-black text-[#1F170D]">Grand Total:</span>
                                <span class="font-mono font-black text-xl text-[#8C6418]"
                                    x-text="'Rp ' + formatNumber(grandTotal)"></span>
                            </div>
                        </div>

                        <div class="space-y-1 text-[11px] text-[#7A643E]">
                            <div class="flex items-center gap-1.5 font-medium">
                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Supports BCA, Mandiri, GoPay, OVO, Dana, ShopeePay.</span>
                            </div>
                            <div class="flex items-center gap-1.5 font-medium">
                                <svg class="w-4 h-4 text-[#8C6418] shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                <span>Protected by SHA-512 Signatures &amp; Idempotency.</span>
                            </div>
                        </div>

                        <div class="space-y-2 pt-2">
                            <button type="button" @click="completePaymentAndRedirect()"
                                class="w-full py-3.5 px-4 rounded-2xl text-xs font-black uppercase tracking-wider shadow-lg active:scale-95 transition-all cursor-pointer flex items-center justify-center gap-2"
                                style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 50%, #A87D18 100%); color: #1E160A; border: 1.5px solid #FFF3CD;">
                                <span>Confirm Paid &amp; View E-Ticket</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                        d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                            <button type="button" @click="showPaymentSuccessModal = false"
                                class="w-full py-2 text-xs font-bold text-[#8C7A58] hover:text-[#1F170D] cursor-pointer text-center">
                                Close Window
                            </button>
                        </div>
                    </div>

                    <!-- Right Column: Big QRIS Display (Col 5) -->
                    <div
                        class="md:col-span-5 flex flex-col items-center justify-center p-4 bg-[#FAF8F2] rounded-2xl border border-[#DFC387]/70">
                        <div
                            class="p-3 bg-white rounded-2xl border-2 border-dashed border-[#DFC387] shadow-md inline-block">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=CLUB61-MIDTRANS-DEMO"
                                alt="QRIS Payment Club 61"
                                class="w-48 h-48 sm:w-52 sm:h-52 mx-auto rounded-lg object-contain" />
                        </div>
                        <div class="mt-3 text-center">
                            <div class="text-[10px] font-mono font-bold tracking-widest text-[#8C6418] uppercase">QRIS NATIONAL STANDARD</div>
                            <div class="text-[10px] text-[#7A643E] mt-0.5">NMID: ID102061617233 &bull; Club 61</div>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <!-- Cancel Checkout Confirmation Modal (Club 61 Luxury Theme) -->
        <div x-show="showCancelModal" style="display: none; z-index: 99999 !important;"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 overflow-y-auto bg-black/75 backdrop-blur-md flex items-center justify-center p-4">

            <div
                class="w-full max-w-md bg-white rounded-3xl border-2 border-[#D4AF37] shadow-2xl p-6 sm:p-7 space-y-5 animate-scaleIn text-center relative">

                <!-- Header Alert Icon -->
                <div
                    class="w-16 h-16 rounded-2xl bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center mx-auto shadow-sm">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <!-- Content -->
                <div class="space-y-2">
                    <h3 class="font-serif font-black text-xl text-[#1F170D]">Cancel Checkout Session?</h3>
                    <p class="text-xs text-[#7A643E] leading-relaxed">
                        Your held court slots will be immediately released back to the public schedule for other players.
                    </p>
                </div>

                <!-- Booking Details Preview in Checkout -->
                <template x-if="bookingItems && bookingItems.length > 0">
                    <div class="p-3.5 rounded-2xl bg-[#FAF8F2] border border-[#E8DCC0] text-left text-xs space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-[#7A643E]">Court:</span>
                            <span class="font-bold text-[#1F170D]"
                                x-text="bookingItems[0].court || 'Court Arena'"></span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-[#7A643E]">Match Schedule:</span>
                            <span class="font-bold text-[#1F170D]"
                                x-text="(formatDate(bookingItems[0].booking_date) || bookingDateFormatted || '') + ' • ' + (bookingItems[0].time || '')"></span>
                        </div>
                        <template x-if="bookingItems.length > 1">
                            <div class="flex justify-between items-center text-xs pt-1 border-t border-[#DFC387]/40">
                                <span class="text-[#7A643E]">Total Sessions:</span>
                                <span class="font-mono font-bold text-[#8C6418]"
                                    x-text="bookingItems.length + (bookingItems.length > 1 ? ' Court Sessions' : ' Court Session')"></span>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Action Buttons -->
                <div class="space-y-2.5 pt-2">
                    <button type="button" @click="confirmCancelCheckout()" :disabled="isCancellingCheckout"
                        class="w-full py-3.5 px-4 rounded-2xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 hover:brightness-105 active:scale-98"
                        style="background: linear-gradient(180deg, #E11D48 0%, #BE123C 100%); color: #FFFFFF !important; border: 1px solid #FDA4AF; box-shadow: 0 4px 14px rgba(225, 29, 72, 0.35);">
                        <template x-if="!isCancellingCheckout">
                            <span class="flex items-center gap-1.5" style="color: #FFFFFF;">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    style="color: #FFFFFF;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span style="color: #FFFFFF;">Yes, Cancel &amp; Pick Another Schedule</span>
                            </span>
                        </template>
                        <template x-if="isCancellingCheckout">
                            <span class="flex items-center gap-2" style="color: #FFFFFF;">
                                <div class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"
                                    style="border-color: #FFFFFF; border-top-color: transparent;"></div>
                                <span style="color: #FFFFFF;">Releasing Slots...</span>
                            </span>
                        </template>
                    </button>

                    <button type="button" @click="showCancelModal = false" :disabled="isCancellingCheckout"
                        class="w-full py-3 px-4 rounded-2xl border text-xs font-bold transition-all cursor-pointer hover:brightness-95 disabled:opacity-50"
                        style="border: 1px solid #DFC387; background-color: #FAF8F2; color: #7A5818;">
                        Continue Payment
                    </button>
                </div>

            </div>
        </div>

        <!-- Custom Luxury Notice Modal -->
        <div x-show="noticeModal.show" style="display: none; z-index: 99999 !important;"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 overflow-y-auto bg-black/75 backdrop-blur-md flex items-center justify-center p-4">

            <div class="w-full max-w-md bg-white rounded-3xl border-2 border-[#D4AF37] shadow-2xl p-6 sm:p-7 space-y-5 animate-scaleIn text-center relative"
                @click.away="handleNoticeClose()">

                <!-- Icon Header -->
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto shadow-sm"
                    :class="{
                        'bg-[#FAF2DE] border border-[#DFC387] text-[#8C6418]': noticeModal.type === 'info' ||
                            noticeModal.type === 'gold',
                        'bg-rose-50 border border-rose-200 text-rose-600': noticeModal.type === 'error' ||
                            noticeModal.type === 'danger',
                        'bg-emerald-50 border border-emerald-200 text-emerald-600': noticeModal.type === 'success'
                    }">
                    <template x-if="noticeModal.type === 'info' || noticeModal.type === 'gold'">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                    <template x-if="noticeModal.type === 'error' || noticeModal.type === 'danger'">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </template>
                    <template x-if="noticeModal.type === 'success'">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                </div>

                <!-- Content -->
                <div class="space-y-2">
                    <h3 class="font-serif font-black text-xl text-[#1F170D]" x-text="noticeModal.title"></h3>
                    <p class="text-xs text-[#7A643E] leading-relaxed" x-text="noticeModal.message"></p>
                </div>

                <!-- Action Button -->
                <div class="pt-2">
                    <button type="button" @click="handleNoticeClose()"
                        class="w-full py-3.5 px-4 rounded-2xl text-[#1E160A] text-xs font-black uppercase tracking-wider block shadow-md hover:brightness-105 transition-all cursor-pointer active:scale-98"
                        style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 50%, #A87D18 100%); color: #1E160A; border: 1px solid #FFF3CD;">
                        <span x-text="noticeModal.buttonText || 'OK, Understood'"></span>
                    </button>
                </div>

            </div>
        </div>

    </div>

    <script>
        function checkoutApp() {
            return {
                canCancelBooking: @json(Auth::check() && Auth::user()->canCancelBooking()),
                noticeModal: {
                    show: false,
                    title: '',
                    message: '',
                    type: 'info',
                    buttonText: 'OK, Understood',
                    onClose: null,
                },

                showNotice(title, message, type = 'info', buttonText = 'OK, Understood', onClose = null) {
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

                bookingItems: [],
                bookingDateFormatted: 'Today',
                holdData: null,
                expiresAtTime: null,
                timerDisplay: '10:00',
                timerInterval: null,
                isExpired: false,
                showExpiredModal: false,
                showCancelModal: false,
                isCancellingCheckout: false,
                isSubmitting: false,
                createdBookingId: null,

                showPaymentModal: false,
                showAddOnsModal: false,
                showPaymentSuccessModal: false,
                promoCode: '',
                promoApplied: false,
                promoDiscount: 0,
                selectedAddOns: [],
                availableAddOns: [{
                        id: 'racket-01',
                        name: 'Bullpadel Hack 03 Pro',
                        desc: 'Carbon 12K Official WPT',
                        price: 50000,
                        quantity: 1
                    },
                    {
                        id: 'balls-01',
                        name: 'Can of Bullpadel Next Balls (3 Pcs)',
                        desc: 'Official Match Balls',
                        price: 75000,
                        quantity: 1
                    },
                    {
                        id: 'shoes-01',
                        name: 'Non-Marking Padel Shoes',
                        desc: 'Special Turf Court Outsole',
                        price: 35000,
                        quantity: 1
                    },
                ],
                selectedMethod: {
                    id: 'qris',
                    code: 'QRIS',
                    name: 'QRIS Instant (GoPay/Shopee/BCA)',
                    badge: 'QRIS',
                    fee: 2800,
                    note: ''
                },
                paymentMethods: [{
                        id: 'qris',
                        code: 'QRIS',
                        name: 'QRIS Instant (GoPay/OVO/BCA)',
                        badge: 'QRIS',
                        fee: 2800,
                        note: ''
                    },
                    {
                        id: 'bca',
                        code: 'BCA_VA',
                        name: 'BCA Virtual Account',
                        badge: 'BCA',
                        fee: 4440,
                        note: ''
                    },
                    {
                        id: 'mandiri',
                        code: 'MANDIRI_VA',
                        name: 'Mandiri Virtual Account',
                        badge: 'MDR',
                        fee: 4440,
                        note: ''
                    },
                    {
                        id: 'bri',
                        code: 'BRI_VA',
                        name: 'BRI Virtual Account',
                        badge: 'BRI',
                        fee: 4440,
                        note: ''
                    },
                    {
                        id: 'bni',
                        code: 'BNI_VA',
                        name: 'BNI Virtual Account',
                        badge: 'BNI',
                        fee: 4440,
                        note: ''
                    },
                    {
                        id: 'cimb',
                        code: 'CIMB_VA',
                        name: 'CIMB Virtual Account',
                        badge: 'CIMB',
                        fee: 4440,
                        note: ''
                    },
                    {
                        id: 'bsi',
                        code: 'BSI_VA',
                        name: 'BSI Virtual Account',
                        badge: 'BSI',
                        fee: 4440,
                        note: ''
                    },
                    {
                        id: 'cash',
                        code: 'CASH',
                        name: 'Cash on Arrival (Walk-in)',
                        badge: 'CASH',
                        fee: 0,
                        note: 'Pay at Venue Frontdesk'
                    },
                ],

                init() {
                    const saved = localStorage.getItem('club61_cart') || sessionStorage.getItem('club61_cart') ||
                        sessionStorage.getItem('vantage_cart');
                    const holdSaved = localStorage.getItem('club61_hold_data') || sessionStorage.getItem(
                        'club61_hold_data') || sessionStorage.getItem('vantage_hold_data');

                    if (saved) {
                        try {
                            const parsed = JSON.parse(saved);
                            if (parsed && parsed.length > 0) {
                                this.bookingItems = parsed;
                                if (parsed[0].booking_date) {
                                    this.bookingDateFormatted = this.formatDate(parsed[0].booking_date);
                                }
                            }
                        } catch (e) {}
                    }

                    if (holdSaved) {
                        try {
                            this.holdData = JSON.parse(holdSaved);
                            if (this.holdData && this.holdData.expires_at) {
                                this.expiresAtTime = new Date(this.holdData.expires_at).getTime();
                            }
                        } catch (e) {}
                    }

                    // Fallback if no expires_at
                    if (!this.expiresAtTime && this.bookingItems.length > 0) {
                        this.expiresAtTime = Date.now() + (10 * 60 * 1000);
                    }

                    // Countdown Timer & Visibility Listener
                    if (this.bookingItems.length > 0) {
                        this.startCountdown();

                        document.addEventListener('visibilitychange', () => {
                            if (document.visibilityState === 'visible') {
                                this.checkExpiry();
                            }
                        });
                    }

                    // Load catalog from database
                    this.fetchEquipments();
                },

                async fetchEquipments() {
                    try {
                        const res = await fetch('/api/v1/padel/equipments');
                        const json = await res.json();
                        if (json.success && json.data && json.data.length > 0) {
                            this.availableAddOns = json.data.map(eq => ({
                                id: eq.id,
                                name: eq.name,
                                desc: `In Stock: ${eq.stock_quantity}`,
                                price: parseFloat(eq.rental_price),
                                quantity: 1,
                            }));
                        }
                    } catch (e) {
                        console.log('Using default equipment list');
                    }
                },

                startCountdown() {
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

                get subtotal() {
                    return this.bookingItems.reduce((sum, item) => sum + item.price, 0);
                },

                get addonsTotal() {
                    return this.selectedAddOns.reduce((sum, item) => sum + (item.price * (item.quantity || 1)), 0);
                },

                get grandTotal() {
                    const total = this.subtotal + this.addonsTotal + this.selectedMethod.fee - this.promoDiscount;
                    return total > 0 ? total : 0;
                },

                selectPaymentMethod(method) {
                    this.selectedMethod = method;
                    this.showPaymentModal = false;
                },

                isAddOnSelected(id) {
                    return this.selectedAddOns.some(a => a.id === id);
                },

                toggleAddOn(addon) {
                    const idx = this.selectedAddOns.findIndex(a => a.id === addon.id);
                    if (idx >= 0) {
                        this.selectedAddOns.splice(idx, 1);
                    } else {
                        this.selectedAddOns.push({
                            ...addon,
                            quantity: 1
                        });
                    }
                },

                removeAddon(idx) {
                    this.selectedAddOns.splice(idx, 1);
                },

                applyPromo() {
                    const code = this.promoCode.trim().toUpperCase();
                    if (code === 'HEMAT10' || code === 'VANTAGE20' || code === 'CLUB61' || code === 'GOLDVIP') {
                        this.promoApplied = true;
                        this.promoDiscount = 40000;
                    } else {
                        this.showNotice('Invalid Promo Code',
                            'The promo code entered is invalid. Try: HEMAT10 or CLUB61', 'error', 'Close');
                    }
                },

                removePromo() {
                    this.promoApplied = false;
                    this.promoDiscount = 0;
                    this.promoCode = '';
                },

                formatNumber(val) {
                    if (!val) return '0';
                    return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                },

                formatDate(val) {
                    if (!val) return '-';
                    try {
                        const d = new Date(val);
                        if (!isNaN(d.getTime())) {
                            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                            return `${String(d.getDate()).padStart(2, '0')} ${months[d.getMonth()]} ${d.getFullYear()}`;
                        }
                    } catch(e) {}
                    return String(val).substring(0, 10);
                },

                /**
                 * Request Snap Token & Execute Payment
                 */
                async executePayment() {
                    if (this.isSubmitting || this.isExpired) return;

                    this.isSubmitting = true;

                    try {
                        let bookingIds = [];
                        if (this.holdData && this.holdData.bookings && this.holdData.bookings.length > 0) {
                            bookingIds = this.holdData.bookings.map(b => b.id);
                        }

                        if (bookingIds.length === 0) {
                            this.showNotice(
                                'Slot Reservation Not Found',
                                'Court slot reservation data was not found. Please reselect your match schedule.',
                                'error',
                                'Select Schedule',
                                () => {
                                    window.location.href = "{{ route('customer.booking') }}";
                                }
                            );
                            return;
                        }

                        const payload = {
                            booking_ids: bookingIds,
                            equipments: this.selectedAddOns.map(a => ({
                                equipment_id: a.id,
                                quantity: a.quantity || 1
                            })),
                            voucher_code: this.promoApplied ? this.promoCode : null,
                            payment_method: this.selectedMethod.code
                        };

                        const idempotencyKey = (crypto && crypto.randomUUID) ?
                            crypto.randomUUID() :
                            ('IDEM-' + Date.now() + '-' + Math.random().toString(36).substring(2, 9));

                        const res = await fetch('/api/v1/padel/checkout', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Idempotency-Key': idempotencyKey,
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                    'content'),
                            },
                            body: JSON.stringify(payload)
                        });

                        const json = await res.json();

                        this.isSubmitting = false;

                        if (res.status === 200 && json.success) {
                            const data = json.data;
                            this.createdBookingId = data.booking_id || (data.bookings && data.bookings[0] ? data
                                .bookings[0].booking_id : '');
                            this.createdOrderId = data.order_id || '';

                            if (data.driver === 'midtrans' && window.snap && typeof window.snap.pay === 'function' && !
                                data.is_mock && data.snap_token) {
                                // Midtrans Snap Pop-Up
                                window.snap.pay(data.snap_token, {
                                    onSuccess: (result) => {
                                        this.clearSessionAndRedirect(this.createdBookingId, this
                                            .createdOrderId);
                                    },
                                    onPending: (result) => {
                                        this.clearSessionAndRedirect(this.createdBookingId, this
                                            .createdOrderId);
                                    },
                                    onError: (result) => {
                                        this.showNotice(
                                            'Payment Declined',
                                            'Payment was declined or failed to process. Please try again.',
                                            'error',
                                            'Try Again'
                                        );
                                    },
                                    onClose: () => {
                                        this.showNotice(
                                            'Payment Window Closed',
                                            'The payment session window was closed. You can view transaction status or complete payment on the Invoice page.',
                                            'info',
                                            'View Invoice Page',
                                            () => {
                                                this.clearSessionAndRedirect(this.createdBookingId, this
                                                    .createdOrderId);
                                            }
                                        );
                                    }
                                });
                            } else {
                                // Sandbox Mock Simulator / Cash
                                this.showPaymentSuccessModal = true;
                            }
                        } else {
                            this.showNotice('Payment Failed', json.message || 'Failed to process payment.',
                                'error', 'Close');
                        }
                    } catch (e) {
                        this.isSubmitting = false;
                        this.showNotice('System Error', 'An unexpected error occurred while processing checkout.', 'error',
                            'Close');
                    }
                },

                clearSessionAndRedirect(bookingId, orderId) {
                    localStorage.removeItem('club61_cart');
                    localStorage.removeItem('club61_hold_data');
                    sessionStorage.removeItem('club61_cart');
                    sessionStorage.removeItem('club61_hold_data');
                    sessionStorage.removeItem('vantage_cart');
                    sessionStorage.removeItem('vantage_hold_data');
                    window.dispatchEvent(new CustomEvent('cart-updated'));
                    let target = "{{ route('customer.invoice') }}";
                    const params = [];
                    if (bookingId) params.push(`booking_id=${bookingId}`);
                    if (orderId) params.push(`order_id=${orderId}`);
                    if (params.length > 0) target += '?' + params.join('&');
                    window.location.href = target;
                },

                completePaymentAndRedirect() {
                    this.clearSessionAndRedirect(this.createdBookingId, this.createdOrderId);
                },

                cancelCheckout() {
                    if (!this.canCancelBooking) {
                        this.showNotice('Access Denied', 'You do not have permission to cancel this booking.', 'error',
                            'Close');
                        return;
                    }
                    this.showCancelModal = true;
                },

                async confirmCancelCheckout() {
                    if (!this.canCancelBooking) {
                        this.showNotice('Access Denied', 'You do not have permission to cancel this booking.',
                            'error', 'Close');
                        return;
                    }
                    this.isCancellingCheckout = true;

                    let bookingIds = [];
                    if (this.holdData && this.holdData.bookings && this.holdData.bookings.length > 0) {
                        bookingIds = this.holdData.bookings.map(b => b.id);
                    }

                    if (bookingIds.length > 0) {
                        try {
                            await fetch('/api/v1/padel/release-slot', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                        .getAttribute('content'),
                                },
                                body: JSON.stringify({
                                    booking_ids: bookingIds
                                })
                            });
                        } catch (e) {
                            console.error('Error releasing slots on cancel:', e);
                        }
                    }

                    localStorage.removeItem('club61_cart');
                    localStorage.removeItem('club61_hold_data');
                    sessionStorage.removeItem('club61_cart');
                    sessionStorage.removeItem('club61_hold_data');
                    sessionStorage.removeItem('vantage_cart');
                    sessionStorage.removeItem('vantage_hold_data');
                    window.dispatchEvent(new CustomEvent('cart-updated'));

                    window.location.href = "{{ route('customer.booking') }}";
                }
            }
        }
    </script>

    @push('scripts')
        @if (config('services.payment.driver', 'midtrans') === 'midtrans')
            <script
                src="{{ config('services.midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}"
                data-client-key="{{ config('services.midtrans.client_key', 'SB-Mid-client-demo-61') }}"></script>
        @endif
    @endpush
</x-app-layout>
