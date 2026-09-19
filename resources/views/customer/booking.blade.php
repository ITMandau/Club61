<x-app-layout>
    <div x-data="bookingCourtApp()" x-init="init()" class="py-6 sm:py-8 text-[#1F170D]">
        <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16 space-y-6">

            <!-- Top Header & Breadcrumb (Screen 2 & 3 Mockup) -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white/95 backdrop-blur-xl p-5 rounded-3xl border border-[#DFC387] shadow-sm">
                <div class="flex items-center gap-3.5">
                    <a href="{{ route('dashboard') }}" class="p-2.5 rounded-2xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] transition-colors cursor-pointer" title="Back to Home">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="font-serif font-black text-xl sm:text-2xl text-[#1F170D]">Book Court</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-emerald-100 text-emerald-800 border border-emerald-300">Live Schedule</span>
                        </div>
                        <p class="text-xs text-[#7A643E] mt-0.5">Select your preferred date and time slot for our panoramic padel courts</p>
                    </div>
                </div>

                <!-- Shopping Cart Shortcut with Counter -->
                <div class="flex items-center gap-3">
                    <a href="{{ route('customer.cart') }}" 
                       class="flex items-center gap-2.5 px-4 py-2.5 rounded-2xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] transition-colors relative shadow-sm cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span class="font-bold text-xs">Booking Cart</span>
                        <span x-text="selectedSlots.length" class="min-w-[20px] h-[20px] px-1.5 bg-[#D4AF37] text-[#1E160A] text-[10px] font-black rounded-full flex items-center justify-center shadow-sm"></span>
                    </a>
                </div>
            </div>

            <!-- Date Picker Bar (10 Quick Days + Calendar Picker up to 2 Months Ahead) -->
            <div class="bg-white/95 backdrop-blur-xl p-3.5 sm:p-4 rounded-3xl border border-[#DFC387] shadow-sm flex items-center gap-2 sm:gap-3">
                
                <!-- Direct Date Picker Calendar Button (Select any date up to 2 months ahead) -->
                <button type="button" 
                        @click="openCalendarPicker()"
                        class="px-3.5 py-2.5 rounded-2xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] shrink-0 cursor-pointer transition-colors relative flex items-center gap-2 shadow-sm active:scale-95" 
                        title="Pick any date from calendar (Up to 2 months ahead)">
                    <svg class="w-4 h-4 text-[#8C6418] pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-xs font-bold text-[#7A5818] hidden sm:inline pointer-events-none">Pick Date</span>
                    <input type="date" 
                           x-ref="calendarInput"
                           :min="minDate" 
                           :max="maxDate" 
                           :value="activeDate"
                           @change="onCalendarSelect($event.target.value)" 
                           class="absolute inset-0 opacity-0 pointer-events-none w-full h-full">
                </button>

                <!-- 10 Quick Date Tabs -->
                <div id="date-tabs-slider" class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none w-full">
                    <template x-for="(tab, index) in dateTabs" :key="tab.date">
                        <button type="button" 
                                :id="'date-tab-' + tab.date"
                                @click="selectDate(tab.date)"
                                :class="activeDate === tab.date 
                                    ? 'bg-[#183428] text-[#F5E6BE] border-[#D4AF37] shadow-md scale-[1.02]' 
                                    : 'bg-[#FAF6EC] text-[#6B5738] border-[#DFC387]/70 hover:bg-white'"
                                class="px-4 sm:px-5 py-2 rounded-2xl text-center border font-sans shrink-0 transition-all text-xs cursor-pointer">
                            <span class="block text-[9px] uppercase font-extrabold opacity-80" x-text="tab.day"></span>
                            <span class="block font-black text-xs mt-0.5" x-text="tab.formatted"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Court Selector Badges (3 Courts Pro Ecosystem) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <template x-for="court in courts" :key="court.court_id">
                    <div class="p-4 rounded-2xl bg-white/95 border border-[#DFC387] shadow-sm flex items-center justify-between">
                        <div>
                            <div class="font-extrabold text-xs sm:text-sm text-[#1F170D] flex items-center gap-1.5">
                                <span x-text="court.court_name"></span>
                                <span class="text-[#8C6418] cursor-help" title="Specification: 12mm WPT Tempered Glass & Mondo Supercourt Turf">ⓘ</span>
                            </div>
                            <div class="text-[10px] text-[#7A643E] mt-0.5" x-text="court.description || (court.type === 'INDOOR' ? 'Indoor • Central AC' : 'Outdoor • Open Air Court')"></div>
                        </div>
                        <span class="w-3 h-3 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
                    </div>
                </template>
            </div>

            <!-- Duration Preset Selector (Consecutive Duration: 1 Hour, 2 Hours, 3 Hours, 4 Hours) -->
            <div class="bg-white/95 backdrop-blur-xl p-4 sm:p-5 rounded-3xl border border-[#DFC387] shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center text-[11px] font-black shrink-0 text-[#7A5818] shadow-sm tracking-wider">
                        HOURS
                    </div>
                    <div>
                        <div class="text-xs font-black text-[#1F170D] uppercase tracking-wider flex items-center gap-2">
                            <span>Consecutive Duration:</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-[#FAF2DE] text-[#7A5818] border border-[#DFC387]" x-text="selectedDuration + (selectedDuration === 1 ? ' Hour' : ' Hours')"></span>
                        </div>
                        <p class="text-[11px] text-[#7A643E] mt-0.5">Select duration, then click starting time. The system automatically reserves consecutive slots without manual selection.</p>
                    </div>
                </div>

                <!-- Duration Pills -->
                <div class="flex items-center gap-1.5 sm:gap-2 bg-[#FAF8F2] p-1.5 rounded-2xl border border-[#DFC387]/70 shrink-0">
                    <template x-for="d in [1, 2, 3, 4]" :key="d">
                        <button type="button" 
                                @click="setDuration(d)"
                                :class="selectedDuration === d 
                                    ? 'bg-[#183428] text-[#F5E6BE] border-[#D4AF37] shadow-md font-black scale-[1.02]' 
                                    : 'bg-white text-[#5C410F] hover:bg-[#FAF2DE] border-transparent font-bold'"
                                class="px-3.5 sm:px-4 py-2 rounded-xl text-xs border transition-all cursor-pointer flex items-center gap-1.5">
                            <span x-text="d + (d === 1 ? ' Hour' : ' Hours')"></span>
                            <span x-show="d === 2" class="hidden sm:inline text-[9px] text-amber-500 font-bold">Popular</span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Loading State -->
            <div x-show="isLoading" class="py-12 text-center">
                <div class="inline-block w-8 h-8 border-3 border-[#D4AF37] border-t-transparent rounded-full animate-spin"></div>
                <div class="text-xs font-bold text-[#8C6418] mt-2">Loading court schedule matrix...</div>
            </div>

            <!-- Schedule Matrix Grid (Displays all 3 Courts side-by-side with Time) -->
            <div x-show="!isLoading" class="bg-white/95 backdrop-blur-xl rounded-3xl border border-[#DFC387] shadow-[0_15px_40px_rgba(160,120,30,0.15)] overflow-hidden">
                <div class="overflow-x-auto">
                    <div class="min-w-[760px]">
                        
                        <!-- Table Headers (Time + 3 Courts) -->
                        <div class="items-center bg-gradient-to-r from-[#FAF2DE] via-[#F5E6BE] to-[#FAF2DE] border-b border-[#DFC387] text-[11px] font-black uppercase text-[#5C410F] py-3.5 px-4"
                             style="display: grid; grid-template-columns: 110px 1fr 1fr 1fr; gap: 14px;">
                            <div class="text-center font-mono font-bold">Match Time</div>
                            <div class="text-center" x-text="courts[0] ? courts[0].court_name : 'Court 1 (Panoramic Indoor)'">Court 1</div>
                            <div class="text-center" x-text="courts[1] ? courts[1].court_name : 'Court 2 (Panoramic Indoor)'">Court 2</div>
                            <div class="text-center" x-text="courts[2] ? courts[2].court_name : 'Court 3 (Open Air Outdoor)'">Court 3</div>
                        </div>

                        <!-- Time Slot Rows -->
                        <div class="divide-y divide-[#EEDBB0]/60 text-xs">
                            <template x-for="(row, rowIndex) in matrixRows" :key="row.time">
                                <div class="items-center p-3 sm:p-3.5 hover:bg-[#FDFBF7] transition-colors"
                                     style="display: grid; grid-template-columns: 110px 1fr 1fr 1fr; gap: 14px;">
                                    
                                    <!-- Time Label -->
                                    <div class="text-center font-mono font-bold text-[#5C410F] text-xs sm:text-sm" x-text="row.time"></div>

                                    <!-- Court 1 Slot -->
                                    <div>
                                        <template x-if="row.c1 && row.c1.status === 'BOOKED'">
                                            <div class="w-full py-2.5 px-3 rounded-xl bg-[#F0ECE1] text-[#9E907B] text-center font-bold text-xs border border-[#E0D8C8] cursor-not-allowed">
                                                Booked
                                            </div>
                                        </template>
                                        <template x-if="row.c1 && row.c1.status === 'LOCKED'">
                                            <div class="w-full py-2.5 px-3 rounded-xl bg-amber-100/80 text-amber-800 text-center font-bold text-xs border border-amber-300">
                                                In Checkout
                                            </div>
                                        </template>
                                        <template x-if="row.c1 && row.c1.status === 'AVAILABLE'">
                                            <button type="button" 
                                                    @click="handleSlotClick(row.c1.court_id, row.c1.court_name, row.c1.local_start, row.c1.local_end, row.c1.price, rowIndex, 'c1')"
                                                    @mouseenter="previewSlots(row.c1.court_id, rowIndex, 'c1')"
                                                    @mouseleave="clearPreview()"
                                                    :class="isSlotSelected(row.c1.court_id, row.c1.local_start) 
                                                        ? 'bg-[#183428] text-white border-2 border-[#D4AF37] shadow-[0_4px_15px_rgba(24,52,40,0.4)] scale-[1.01]' 
                                                        : (isSlotHovered(row.c1.court_id, row.c1.local_start) 
                                                            ? 'bg-[#FAF2DE] text-[#1F170D] border-2 border-[#D4AF37] shadow-md' 
                                                            : 'bg-white hover:bg-[#FAF5E6] text-[#1F170D] border border-[#DFC387]')"
                                                    class="w-full py-2.5 px-3 rounded-xl text-center font-medium transition-all active:scale-95 group cursor-pointer relative">
                                                <div class="font-extrabold text-xs sm:text-sm text-[#8C6418]" 
                                                     :class="isSlotSelected(row.c1.court_id, row.c1.local_start) ? 'text-[#F5E6BE]' : ''"
                                                     x-text="'Rp ' + formatNumber(row.c1.price)"></div>
                                            </button>
                                        </template>
                                    </div>

                                    <!-- Court 2 Slot -->
                                    <div>
                                        <template x-if="row.c2 && row.c2.status === 'BOOKED'">
                                            <div class="w-full py-2.5 px-3 rounded-xl bg-[#F0ECE1] text-[#9E907B] text-center font-bold text-xs border border-[#E0D8C8] cursor-not-allowed">
                                                Booked
                                            </div>
                                        </template>
                                        <template x-if="row.c2 && row.c2.status === 'LOCKED'">
                                            <div class="w-full py-2.5 px-3 rounded-xl bg-amber-100/80 text-amber-800 text-center font-bold text-xs border border-amber-300">
                                                In Checkout
                                            </div>
                                        </template>
                                        <template x-if="row.c2 && row.c2.status === 'AVAILABLE'">
                                            <button type="button" 
                                                    @click="handleSlotClick(row.c2.court_id, row.c2.court_name, row.c2.local_start, row.c2.local_end, row.c2.price, rowIndex, 'c2')"
                                                    @mouseenter="previewSlots(row.c2.court_id, rowIndex, 'c2')"
                                                    @mouseleave="clearPreview()"
                                                    :class="isSlotSelected(row.c2.court_id, row.c2.local_start) 
                                                        ? 'bg-[#183428] text-white border-2 border-[#D4AF37] shadow-[0_4px_15px_rgba(24,52,40,0.4)] scale-[1.01]' 
                                                        : (isSlotHovered(row.c2.court_id, row.c2.local_start) 
                                                            ? 'bg-[#FAF2DE] text-[#1F170D] border-2 border-[#D4AF37] shadow-md' 
                                                            : 'bg-white hover:bg-[#FAF5E6] text-[#1F170D] border border-[#DFC387]')"
                                                    class="w-full py-2.5 px-3 rounded-xl text-center font-medium transition-all active:scale-95 group cursor-pointer relative">
                                                <div class="font-extrabold text-xs sm:text-sm text-[#8C6418]" 
                                                     :class="isSlotSelected(row.c2.court_id, row.c2.local_start) ? 'text-[#F5E6BE]' : ''"
                                                     x-text="'Rp ' + formatNumber(row.c2.price)"></div>
                                            </button>
                                        </template>
                                    </div>

                                    <!-- Court 3 Slot -->
                                    <div>
                                        <template x-if="row.c3 && row.c3.status === 'BOOKED'">
                                            <div class="w-full py-2.5 px-3 rounded-xl bg-[#F0ECE1] text-[#9E907B] text-center font-bold text-xs border border-[#E0D8C8] cursor-not-allowed">
                                                Booked
                                            </div>
                                        </template>
                                        <template x-if="row.c3 && row.c3.status === 'LOCKED'">
                                            <div class="w-full py-2.5 px-3 rounded-xl bg-amber-100/80 text-amber-800 text-center font-bold text-xs border border-amber-300">
                                                In Checkout
                                            </div>
                                        </template>
                                        <template x-if="row.c3 && row.c3.status === 'AVAILABLE'">
                                            <button type="button" 
                                                    @click="handleSlotClick(row.c3.court_id, row.c3.court_name, row.c3.local_start, row.c3.local_end, row.c3.price, rowIndex, 'c3')"
                                                    @mouseenter="previewSlots(row.c3.court_id, rowIndex, 'c3')"
                                                    @mouseleave="clearPreview()"
                                                    :class="isSlotSelected(row.c3.court_id, row.c3.local_start) 
                                                        ? 'bg-[#183428] text-white border-2 border-[#D4AF37] shadow-[0_4px_15px_rgba(24,52,40,0.4)] scale-[1.01]' 
                                                        : (isSlotHovered(row.c3.court_id, row.c3.local_start) 
                                                            ? 'bg-[#FAF2DE] text-[#1F170D] border-2 border-[#D4AF37] shadow-md' 
                                                            : 'bg-white hover:bg-[#FAF5E6] text-[#1F170D] border border-[#DFC387]')"
                                                    class="w-full py-2.5 px-3 rounded-xl text-center font-medium transition-all active:scale-95 group cursor-pointer relative">
                                                <div class="font-extrabold text-xs sm:text-sm text-[#8C6418]" 
                                                     :class="isSlotSelected(row.c3.court_id, row.c3.local_start) ? 'text-[#F5E6BE]' : ''"
                                                     x-text="'Rp ' + formatNumber(row.c3.price)"></div>
                                            </button>
                                        </template>
                                    </div>

                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Bottom Selection Action Bar -->
            <div class="sticky bottom-16 md:bottom-4 z-30 bg-white/95 backdrop-blur-xl p-4 sm:p-5 rounded-2xl border-2 border-[#D4AF37] shadow-[0_15px_40px_rgba(160,120,30,0.3)] flex items-center justify-between gap-4">
                <div>
                    <div class="text-[11px] text-[#7A643E] font-medium">Selected Subtotal:</div>
                    <div class="font-mono font-black text-lg sm:text-2xl text-[#1F170D]" x-text="'Rp ' + formatNumber(totalPrice)"></div>
                    <div class="text-[10px] font-bold text-[#8C6418]" x-text="selectedSlotsSummary"></div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" 
                            x-show="selectedSlots.length > 0"
                            @click="selectedSlots = []" 
                            class="px-4 py-2.5 rounded-xl text-xs font-bold text-rose-600 hover:bg-rose-50 border border-rose-200 transition-colors cursor-pointer">
                        Reset
                    </button>
                    
                    <button type="button" 
                            :disabled="selectedSlots.length === 0 || isHolding"
                            @click="proceedToHoldAndCart()"
                            :class="selectedSlots.length > 0 
                                ? 'bg-gradient-to-b from-[#F5DE9B] via-[#D4AF37] to-[#A87D18] text-[#1E160A] shadow-lg hover:brightness-105 active:scale-95 cursor-pointer' 
                                : 'bg-[#E5DFD3] text-[#A89F8F] cursor-not-allowed'"
                            class="px-6 sm:px-8 py-3.5 rounded-2xl text-xs font-black uppercase tracking-wider transition-all border border-[#FFF3CD]/80 flex items-center gap-2">
                        <span x-text="isHolding ? 'Reserving Slots...' : (selectedSlots.length > 0 ? 'Proceed to Cart &rarr;' : 'Select Schedule')"></span>
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
                    <h3 class="font-serif font-black text-lg sm:text-xl text-[#1F170D]" x-text="noticeModal.title"></h3>
                    <p class="text-xs sm:text-sm text-[#7A643E] leading-relaxed font-medium" x-text="noticeModal.message"></p>
                </div>

                <div class="pt-2">
                    <button type="button" 
                            @click="handleNoticeClose()"
                            class="w-full py-3.5 px-6 rounded-2xl text-xs font-black uppercase tracking-wider text-[#1E160A] transition-all transform active:scale-95 shadow-md cursor-pointer"
                            style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 50%, #A87D18 100%); border: 1.5px solid #FFF3CD;"
                            x-text="noticeModal.buttonText">
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function bookingCourtApp() {
            return {
                courts: [],
                dateTabs: [],
                activeDate: '{{ now()->format("Y-m-d") }}',
                minDate: '{{ now()->format("Y-m-d") }}',
                maxDate: '{{ now()->addDays(59)->format("Y-m-d") }}',
                selectedDuration: 1, // Durasi: 1, 2, 3, atau 4 jam
                selectedSlots: [],
                hoveredSlots: [],
                matrixRows: [],
                isLoading: true,
                isHolding: false,

                // Luxury Notice Modal State
                noticeModal: {
                    show: false,
                    title: '',
                    message: '',
                    type: 'info',
                    buttonText: 'Got It',
                    onClose: null,
                },

                showNotice(title, message, type = 'info', buttonText = 'Got It', onClose = null) {
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
                
                init() {
                    this.generateDateTabs();
                    this.fetchSchedule();
                },

                setDuration(hours) {
                    this.selectedDuration = hours;
                    this.selectedSlots = [];
                    this.hoveredSlots = [];
                },

                generateDateTabs() {
                    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    const today = new Date();

                    this.dateTabs = [];
                    // Display 10 Days in Quick Tabs
                    for (let i = 0; i < 10; i++) {
                        const d = new Date();
                        d.setDate(today.getDate() + i);

                        const yyyy = d.getFullYear();
                        const mm = String(d.getMonth() + 1).padStart(2, '0');
                        const dd = String(d.getDate()).padStart(2, '0');
                        const dateStr = `${yyyy}-${mm}-${dd}`;

                        this.dateTabs.push({
                            day: i === 0 ? 'Today' : days[d.getDay()],
                            formatted: `${d.getDate()} ${months[d.getMonth()]}`,
                            date: dateStr,
                        });
                    }
                },

                openCalendarPicker() {
                    const input = this.$refs.calendarInput;
                    if (!input) return;
                    if (typeof input.showPicker === 'function') {
                        try {
                            input.showPicker();
                            return;
                        } catch (e) {
                            console.warn('showPicker error:', e);
                        }
                    }
                    input.focus();
                },

                onCalendarSelect(val) {
                    if (!val) return;
                    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

                    // If chosen date is beyond the 10 quick tabs, add/replace custom tab so it is active and clearly visible
                    const exists = this.dateTabs.some(t => t.date === val);
                    if (!exists) {
                        const d = new Date(val + 'T00:00:00');
                        this.dateTabs = this.dateTabs.filter(t => !t.isCustom);
                        this.dateTabs.push({
                            day: days[d.getDay()],
                            formatted: `${d.getDate()} ${months[d.getMonth()]}`,
                            date: val,
                            isCustom: true
                        });
                    }
                    this.selectDate(val);
                },

                selectDate(dateStr) {
                    this.activeDate = dateStr;
                    this.selectedSlots = [];
                    this.fetchSchedule();
                },

                async fetchSchedule() {
                    this.isLoading = true;
                    try {
                        const res = await fetch(`/api/v1/padel/schedule?date=${this.activeDate}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        const json = await res.json();
                        if (json.success && json.data.courts.length > 0) {
                            this.courts = json.data.courts;
                            this.buildMatrixRows(json.data.courts);
                        }
                    } catch (e) {
                        console.error('Failed to fetch court schedule:', e);
                    } finally {
                        this.isLoading = false;
                    }
                },

                buildMatrixRows(courts) {
                    const c1 = courts[0];
                    const c2 = courts[1];
                    const c3 = courts[2];

                    this.matrixRows = [];
                    const slotCount = c1 ? c1.slots.length : 0;

                    for (let i = 0; i < slotCount; i++) {
                        const slot1 = c1 ? c1.slots[i] : null;
                        const slot2 = c2 ? c2.slots[i] : null;
                        const slot3 = c3 ? c3.slots[i] : null;

                        this.matrixRows.push({
                            time: slot1 ? slot1.local_start : '',
                            c1: c1 && slot1 ? { court_id: c1.court_id, court_name: c1.court_name, ...slot1 } : null,
                            c2: c2 && slot2 ? { court_id: c2.court_id, court_name: c2.court_name, ...slot2 } : null,
                            c3: c3 && slot3 ? { court_id: c3.court_id, court_name: c3.court_name, ...slot3 } : null,
                        });
                    }
                },

                isSlotSelected(courtId, startTime) {
                    return this.selectedSlots.some(s => {
                        if (s.court_id !== courtId) return false;
                        return startTime >= s.start_time && startTime < s.end_time;
                    });
                },

                isSlotHovered(courtId, startTime) {
                    return this.hoveredSlots.some(s => s.court_id === courtId && s.start_time === startTime);
                },

                previewSlots(courtId, rowIndex, courtKey) {
                    if (this.selectedDuration <= 1) return;
                    this.hoveredSlots = [];
                    for (let i = 0; i < this.selectedDuration; i++) {
                        const targetRow = this.matrixRows[rowIndex + i];
                        if (targetRow && targetRow[courtKey]) {
                            this.hoveredSlots.push({
                                court_id: courtId,
                                start_time: targetRow[courtKey].local_start
                            });
                        }
                    }
                },

                clearPreview() {
                    this.hoveredSlots = [];
                },

                /**
                 * Multi-Hour Consecutive Slot Selection
                 */
                handleSlotClick(courtId, courtName, startTime, endTime, price, rowIndex, courtKey) {
                    // If 1-hour duration, use standard toggle
                    if (this.selectedDuration === 1) {
                        this.toggleSlot(courtId, courtName, startTime, endTime, price);
                        return;
                    }

                    // If duration > 1 hour, check consecutive availability
                    const needed = this.selectedDuration;
                    let totalBlockPrice = 0;

                    for (let i = 0; i < needed; i++) {
                        const targetRow = this.matrixRows[rowIndex + i];
                        if (!targetRow) {
                            this.showNotice('Operating Hours Exceeded', `Not enough remaining operating hours for a ${needed}-hour booking block.`, 'error', 'Choose Another Time');
                            return;
                        }

                        const slotObj = targetRow[courtKey];
                        if (!slotObj || slotObj.status !== 'AVAILABLE') {
                            this.showNotice('Slot Unavailable', `Cannot reserve ${needed} consecutive hours starting at ${startTime} because the ${targetRow.time} slot is occupied (${slotObj ? slotObj.status : 'Closed'}). Please select another start time.`, 'error', 'Choose Another Time');
                            return;
                        }

                        totalBlockPrice += slotObj.price;
                    }

                    const endRow = this.matrixRows[rowIndex + needed - 1];
                    const endHour = endRow[courtKey].local_end;

                    // Automatically apply 1 consolidated master booking slot
                    this.selectedSlots = [{
                        court_id: courtId,
                        court: courtName,
                        booking_date: this.activeDate,
                        start_time: startTime,
                        end_time: endHour,
                        duration_hours: needed,
                        time: `${startTime} - ${endHour} (${needed} ${needed === 1 ? 'Hour' : 'Hours'})`,
                        price: totalBlockPrice
                    }];

                    this.hoveredSlots = [];
                },

                toggleSlot(courtId, courtName, startTime, endTime, price) {
                    const idx = this.selectedSlots.findIndex(s => s.court_id === courtId && s.start_time === startTime && s.end_time === endTime);
                    if (idx >= 0) {
                        this.selectedSlots.splice(idx, 1);
                    } else {
                        // If any existing slot overlaps with this hour, remove it first
                        const overlapIdx = this.selectedSlots.findIndex(s => s.court_id === courtId && startTime >= s.start_time && startTime < s.end_time);
                        if (overlapIdx >= 0) {
                            this.selectedSlots.splice(overlapIdx, 1);
                            return;
                        }

                        this.selectedSlots.push({
                            court_id: courtId,
                            court: courtName,
                            booking_date: this.activeDate,
                            start_time: startTime,
                            end_time: endTime,
                            duration_hours: 1,
                            time: `${startTime} - ${endTime}`,
                            price: price
                        });
                    }
                },

                /**
                 * Contiguous Grouping Algorithm
                 * Groups consecutive hours into single E-Ticket, separates gaps into distinct bookings
                 */
                consolidateContiguousSlots(slots) {
                    if (!slots || slots.length === 0) return [];

                    const courtGroups = {};
                    slots.forEach(s => {
                        if (!courtGroups[s.court_id]) courtGroups[s.court_id] = [];
                        courtGroups[s.court_id].push({ ...s });
                    });

                    const consolidated = [];

                    Object.keys(courtGroups).forEach(courtId => {
                        const list = courtGroups[courtId];
                        list.sort((a, b) => a.start_time.localeCompare(b.start_time));

                        let current = null;

                        list.forEach(slot => {
                            if (!current) {
                                current = { ...slot };
                                current.duration_hours = current.duration_hours || 1;
                                return;
                            }

                            // Consecutive without gap?
                            if (slot.start_time === current.end_time) {
                                current.end_time = slot.end_time;
                                current.price += slot.price;
                                current.duration_hours += (slot.duration_hours || 1);
                                current.time = `${current.start_time} - ${current.end_time} (${current.duration_hours} ${current.duration_hours === 1 ? 'Hour' : 'Hours'})`;
                            } else {
                                // Gap detected: push current as standalone session
                                consolidated.push(current);
                                current = { ...slot };
                                current.duration_hours = current.duration_hours || 1;
                            }
                        });

                        if (current) {
                            consolidated.push(current);
                        }
                    });

                    return consolidated;
                },

                get selectedSlotsSummary() {
                    if (this.selectedSlots.length === 0) return '0 Slots Selected';
                    const consolidated = this.consolidateContiguousSlots(this.selectedSlots);
                    if (consolidated.length === 1) {
                        const first = consolidated[0];
                        return `${first.duration_hours} ${first.duration_hours === 1 ? 'Hour' : 'Hours'} (${first.start_time} - ${first.end_time} WIB • ${first.court})`;
                    }
                    const totalHours = consolidated.reduce((sum, s) => sum + s.duration_hours, 0);
                    return `${consolidated.length} Sessions (${totalHours} Hours Total)`;
                },

                get totalPrice() {
                    return this.selectedSlots.reduce((sum, s) => sum + s.price, 0);
                },

                formatNumber(val) {
                    if (!val) return '0';
                    return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                },

                async proceedToHoldAndCart() {
                    if (this.selectedSlots.length === 0) return;
                    this.isHolding = true;

                    try {
                        // Consolidate contiguous slots prior to holding
                        const consolidatedSlots = this.consolidateContiguousSlots(this.selectedSlots);

                        const payload = {
                            booking_date: this.activeDate,
                            slots: consolidatedSlots.map(s => ({
                                court_id: s.court_id,
                                start_time: s.start_time,
                                end_time: s.end_time,
                            })),
                        };

                        const res = await fetch('/api/v1/padel/hold-slot', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            },
                            body: JSON.stringify(payload)
                        });

                        const json = await res.json();

                        if (res.status === 201 && json.success) {
                            // Store consolidated booking details for multi-tab sync
                            localStorage.setItem('club61_cart', JSON.stringify(consolidatedSlots));
                            localStorage.setItem('club61_hold_data', JSON.stringify(json.data));
                            sessionStorage.setItem('club61_cart', JSON.stringify(consolidatedSlots));
                            sessionStorage.setItem('club61_hold_data', JSON.stringify(json.data));
                            window.dispatchEvent(new CustomEvent('cart-updated'));
                            window.location.href = "{{ route('customer.cart') }}";
                        } else {
                            this.showNotice('Slot Reservation Failed', json.message || 'Failed to hold selected court slots. Please try another time.', 'error', 'Close');
                            this.fetchSchedule(); // Refresh data
                        }
                    } catch (e) {
                        this.showNotice('Network Error', 'A network error occurred while holding court slots. Please check your connection.', 'error', 'Close');
                    } finally {
                        this.isHolding = false;
                    }
                }
            }
        }
    </script>
</x-app-layout>
