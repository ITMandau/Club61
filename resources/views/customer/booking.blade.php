<x-app-layout>
    <style>
        .bk-scroll { scrollbar-width: none; -webkit-overflow-scrolling: touch; scroll-snap-type: x proximity; }
        .bk-scroll::-webkit-scrollbar { display: none; }
        .bk-snap { scroll-snap-align: center; }
        .bk-tap { -webkit-tap-highlight-color: transparent; touch-action: manipulation; user-select: none; }
        @keyframes bk-pop { 0% { transform: scale(.92); } 60% { transform: scale(1.04); } 100% { transform: scale(1); } }
        .bk-pop { animation: bk-pop .22s ease-out; }
        @keyframes bk-fade-up { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        .bk-fade-up { animation: bk-fade-up .28s ease-out both; }
        @keyframes bk-shimmer { 0% { background-position: -200px 0; } 100% { background-position: 200px 0; } }
        .bk-skeleton { background: linear-gradient(90deg, #F3ECDD 0px, #FBF6EA 80px, #F3ECDD 160px); background-size: 400px 100%; animation: bk-shimmer 1.2s linear infinite; }
        .bk-bar { bottom: calc(62px + env(safe-area-inset-bottom, 0px) + 10px); }
        @media (min-width: 768px) { .bk-bar { bottom: 24px; } }
        /* Ruang bawah = setinggi bar ringkasan saja (<main> layout sudah menyisakan tempat untuk navigasi bawah). */
        .bk-page { padding-bottom: calc(var(--bk-bar-h, 84px) + 4px + env(safe-area-inset-bottom, 0px)); }
        @media (min-width: 768px) { .bk-page { padding-bottom: calc(var(--bk-bar-h, 96px) + 8px); } }
        @media (prefers-reduced-motion: reduce) { .bk-pop, .bk-fade-up, .bk-skeleton { animation: none; } }
    </style>

    <div x-data="bookingCourtApp()" x-init="init()" class="bk-page text-[#1F170D]">
        <div class="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8 pt-4 sm:pt-6 space-y-4 sm:space-y-5">

            {{-- Header --}}
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <a href="{{ route('dashboard') }}" title="Back to Home"
                       class="bk-tap hidden sm:flex w-10 h-10 shrink-0 items-center justify-center rounded-2xl bg-white/90 border border-[#EADBB5] text-[#7A5818] hover:bg-[#FAF2DE] transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" /></svg>
                    </a>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h1 class="font-serif font-black text-xl sm:text-2xl">Book Court</h1>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 motion-safe:animate-pulse"></span>Live
                            </span>
                        </div>
                        <p class="hidden sm:block text-xs text-[#7A643E] mt-0.5">Pick a date, duration and start time — consecutive hours are reserved automatically.</p>
                    </div>
                </div>
            </div>

            {{-- 1. Tanggal --}}
            <section class="bg-white/90 backdrop-blur-xl rounded-3xl border border-[#EADBB5] shadow-[0_8px_30px_rgba(160,120,30,0.08)] p-3 sm:p-4">
                <div class="flex items-center justify-between px-1 mb-2.5">
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-[#8C6418]">Date</div>
                    <button type="button" @click="openCalendarPicker()" title="Pick any date (up to 2 months ahead)"
                            class="bk-tap relative flex items-center gap-1.5 text-xs font-bold text-[#7A5818] px-2.5 py-1.5 rounded-xl hover:bg-[#FAF2DE] transition-colors">
                        <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        <span class="pointer-events-none">Calendar</span>
                        <input type="date" x-ref="calendarInput" :min="minDate" :max="maxDate" :value="activeDate"
                               @change="onCalendarSelect($event.target.value)" class="absolute inset-0 opacity-0 pointer-events-none w-full h-full">
                    </button>
                </div>
                <div id="date-tabs-slider" class="bk-scroll flex gap-2 overflow-x-auto pb-0.5">
                    <template x-for="tab in dateTabs" :key="tab.date">
                        <button type="button" :id="'date-tab-' + tab.date" @click="selectDate(tab.date)"
                                :class="activeDate === tab.date
                                    ? 'bg-[#183428] text-[#F5E6BE] border-[#183428] shadow-[0_6px_16px_rgba(24,52,40,0.28)]'
                                    : 'bg-[#FBF7EE] text-[#5C4A2E] border-transparent hover:bg-[#F6EEDB]'"
                                class="bk-tap bk-snap shrink-0 w-[62px] sm:w-[70px] py-2 rounded-2xl border text-center transition-all duration-200 active:scale-95">
                            <span class="block text-[10px] font-bold uppercase tracking-wide opacity-80" x-text="tab.day"></span>
                            <span class="block text-lg font-black leading-tight" x-text="tab.dayNum"></span>
                            <span class="block text-[10px] font-semibold opacity-70" x-text="tab.month"></span>
                        </button>
                    </template>
                </div>
            </section>

            {{-- 2. Durasi --}}
            <section class="bg-white/90 backdrop-blur-xl rounded-3xl border border-[#EADBB5] shadow-[0_8px_30px_rgba(160,120,30,0.08)] p-3 sm:p-4">
                <div class="flex items-center justify-between px-1 mb-2.5">
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-[#8C6418]">Duration</div>
                    <div class="hidden sm:block text-[11px] text-[#7A643E]">Tap a start time — the next hours follow</div>
                </div>
                <div class="grid grid-cols-4 gap-1.5 p-1 rounded-2xl bg-[#FBF7EE]">
                    <template x-for="d in [1, 2, 3, 4]" :key="d">
                        <button type="button" @click="setDuration(d)"
                                :class="selectedDuration === d ? 'bg-white text-[#183428] shadow-[0_4px_12px_rgba(160,120,30,0.18)] font-black' : 'text-[#7A643E] font-bold hover:text-[#1F170D]'"
                                class="bk-tap relative py-2.5 rounded-xl text-sm transition-all duration-200 active:scale-95">
                            <span x-text="d + (d === 1 ? ' hr' : ' hrs')"></span>
                            <span x-show="d === 2" class="absolute -top-1.5 right-1 px-1.5 rounded-full text-[8px] font-black uppercase bg-[#D4AF37] text-[#1E160A]">Popular</span>
                        </button>
                    </template>
                </div>
            </section>

            {{-- 3. Lapangan + tampilan --}}
            <section class="space-y-3">
                <div class="flex items-center justify-between px-1">
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-[#8C6418]">Court</div>
                    <div class="hidden md:flex p-1 rounded-xl bg-white/90 border border-[#EADBB5] text-xs font-bold">
                        <button type="button" @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-[#183428] text-[#F5E6BE]' : 'text-[#7A643E] hover:text-[#1F170D]'" class="bk-tap px-3 py-1.5 rounded-lg transition-colors">All courts</button>
                        <button type="button" @click="viewMode = 'court'" :class="viewMode === 'court' ? 'bg-[#183428] text-[#F5E6BE]' : 'text-[#7A643E] hover:text-[#1F170D]'" class="bk-tap px-3 py-1.5 rounded-lg transition-colors">One court</button>
                    </div>
                </div>

                {{-- Kartu lapangan (tampilan satu lapangan) --}}
                <div x-show="viewMode === 'court'" class="bk-scroll flex gap-2.5 overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0 pb-1">
                    <template x-if="isLoading && courts.length === 0">
                        <div class="flex gap-2.5">
                            <template x-for="i in 3" :key="i"><div class="bk-skeleton shrink-0 w-[168px] h-[74px] rounded-2xl"></div></template>
                        </div>
                    </template>
                    <template x-for="(court, ci) in courts" :key="court.court_id">
                        <button type="button" @click="activeCourtIndex = ci"
                                :class="activeCourtIndex === ci
                                    ? 'bg-[#183428] border-[#183428] text-[#F5E6BE] shadow-[0_8px_20px_rgba(24,52,40,0.28)]'
                                    : 'bg-white/90 border-[#EADBB5] text-[#1F170D] hover:border-[#D4AF37]'"
                                class="bk-tap bk-snap shrink-0 w-[188px] sm:w-[220px] text-left p-3 rounded-2xl border transition-all duration-200 active:scale-[0.97]">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-extrabold text-sm truncate" x-text="court.court_name"></span>
                                <span x-show="selectedCountForCourt(court.court_id) > 0" class="w-5 h-5 rounded-full bg-[#D4AF37] text-[#1E160A] text-[10px] font-black flex items-center justify-center shrink-0" x-text="selectedCountForCourt(court.court_id)"></span>
                            </div>
                            <div class="text-[11px] mt-0.5 truncate" :class="activeCourtIndex === ci ? 'text-[#D9C99A]' : 'text-[#7A643E]'" x-text="court.description"></div>
                            <div class="mt-1.5 text-[11px] font-bold" :class="availableCount(court) > 0 ? (activeCourtIndex === ci ? 'text-emerald-300' : 'text-emerald-700') : (activeCourtIndex === ci ? 'text-rose-300' : 'text-rose-600')"
                                 x-text="availableCount(court) > 0 ? availableCount(court) + ' slots open' : 'Fully booked'"></div>
                        </button>
                    </template>
                </div>
            </section>

            {{-- Legenda --}}
            <div class="hidden sm:flex flex-wrap items-center gap-x-4 gap-y-1.5 px-1 text-[11px] text-[#7A643E]">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-md bg-white border border-[#DCC690]"></span>Available</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-md bg-[#183428]"></span>Selected</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-md bg-amber-100 border border-amber-300"></span>In checkout</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-md bg-[#E9E3D6] border border-[#D9CFBC]"></span>Booked / closed</span>
                <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-[#D4AF37]"></span>Prime time</span>
            </div>

            {{-- Loading --}}
            <div x-show="isLoading" class="bg-white/90 rounded-3xl border border-[#EADBB5] p-4">
                <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                    <template x-for="i in 12" :key="i"><div class="bk-skeleton h-[58px] rounded-2xl"></div></template>
                </div>
            </div>

            {{-- Kosong --}}
            <div x-show="!isLoading && courts.length === 0" class="bg-white/90 rounded-3xl border border-[#EADBB5] p-8 text-center text-sm text-[#7A643E]">
                No courts are available for booking right now.
            </div>

            {{-- Tampilan satu lapangan: jam dikelompokkan pagi / siang / malam --}}
            <section x-show="!isLoading && viewMode === 'court' && activeCourt" class="bg-white/90 backdrop-blur-xl rounded-3xl border border-[#EADBB5] shadow-[0_12px_36px_rgba(160,120,30,0.10)] p-3 sm:p-5 space-y-4">
                <div x-show="pastNote" class="flex items-center gap-2 px-3 py-2 rounded-xl bg-[#FBF7EE] text-[11px] text-[#7A643E]">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span x-text="pastNote"></span>
                </div>
                <template x-for="group in activeCourtGroups" :key="activeCourtIndex + '-' + activeDate + '-' + group.label">
                    <div class="bk-fade-up">
                        <div class="flex items-center gap-2 px-1 mb-2">
                            <span class="text-xs font-extrabold text-[#1F170D]" x-text="group.label"></span>
                            <span class="text-[11px] text-[#A08C66]" x-text="group.range"></span>
                            <span class="flex-1 h-px bg-[#F0E4C8]"></span>
                        </div>
                        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                            <template x-for="item in group.items" :key="item.slot.local_start">
                                <div>
                                    <template x-if="item.slot.status === 'AVAILABLE'">
                                        <button type="button"
                                                @click="handleSlotClick(activeCourtIndex, item.index)"
                                                @mouseenter="previewSlots(activeCourtIndex, item.index)" @mouseleave="clearPreview()"
                                                :class="slotButtonClass(activeCourt.court_id, item.slot.local_start)"
                                                class="bk-tap relative w-full h-[58px] rounded-2xl border transition-all duration-150 active:scale-95 flex flex-col items-center justify-center">
                                            <span x-show="item.slot.is_prime_time" class="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-[#D4AF37]"></span>
                                            <span class="text-sm font-extrabold tabular-nums" x-text="item.slot.local_start"></span>
                                            <span class="text-[10px] font-semibold tabular-nums"
                                                  :class="isSlotSelected(activeCourt.court_id, item.slot.local_start) ? 'text-[#D9C99A]' : 'text-[#8C6418]'"
                                                  x-text="'Rp ' + formatNumber(item.slot.price)"></span>
                                        </button>
                                    </template>
                                    <template x-if="item.slot.status !== 'AVAILABLE'">
                                        <div :class="item.slot.status === 'LOCKED' ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-[#F4F1EA] border-transparent text-[#B3A78F]'"
                                             class="w-full h-[58px] rounded-2xl border flex flex-col items-center justify-center cursor-not-allowed select-none">
                                            <span class="text-sm font-bold tabular-nums" :class="item.slot.status === 'LOCKED' ? '' : 'line-through decoration-1'" x-text="item.slot.local_start"></span>
                                            <span class="text-[10px] font-semibold" x-text="statusLabel(item.slot.status)"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                <div x-show="activeCourtGroups.length === 0" class="py-6 text-center text-sm text-[#7A643E]">This court has no open hours on this date.</div>
            </section>

            {{-- Tampilan semua lapangan (tablet & desktop) --}}
            <section x-show="!isLoading && viewMode === 'grid' && courts.length > 0" class="bg-white/90 backdrop-blur-xl rounded-3xl border border-[#EADBB5] shadow-[0_12px_36px_rgba(160,120,30,0.10)] overflow-hidden">
                <div class="overflow-x-auto bk-scroll" style="scroll-snap-type: none;">
                    <div :style="'min-width: ' + (72 + courts.length * 132) + 'px'">
                        <div class="grid gap-2 px-3 py-3 bg-[#FBF5E6] border-b border-[#EADBB5] sticky top-0 z-10" :style="gridColumns">
                            <div class="sticky left-0 z-10 bg-[#FBF5E6] text-[10px] font-extrabold uppercase tracking-wider text-[#8C6418] flex items-center justify-center">Time</div>
                            <template x-for="court in courts" :key="court.court_id">
                                <div class="text-center min-w-0 px-1">
                                    <div class="text-xs font-extrabold truncate" x-text="court.court_name" :title="court.court_name"></div>
                                    <div class="text-[10px] font-bold" :class="availableCount(court) > 0 ? 'text-emerald-700' : 'text-rose-600'" x-text="availableCount(court) > 0 ? availableCount(court) + ' open' : 'Full'"></div>
                                </div>
                            </template>
                        </div>
                        <div x-show="pastNote" class="flex items-center gap-2 px-4 py-2 bg-[#FFFCF5] border-b border-[#F3E9D2] text-[11px] text-[#7A643E]">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span x-text="pastNote"></span>
                        </div>
                        <div class="divide-y divide-[#F3E9D2]">
                            <template x-for="row in visibleMatrixRows" :key="activeDate + '-' + row.time">
                                <div class="grid gap-2 px-3 py-1.5 items-center hover:bg-[#FFFCF5] transition-colors" :style="gridColumns">
                                    <div class="sticky left-0 z-[1] bg-white/95 text-center text-xs font-bold tabular-nums text-[#5C4A2E] py-2" x-text="row.time"></div>
                                    <template x-for="(cell, ci) in row.cells" :key="ci">
                                        <div>
                                            <template x-if="cell && cell.status === 'AVAILABLE'">
                                                <button type="button"
                                                        @click="handleSlotClick(ci, row.index)"
                                                        @mouseenter="previewSlots(ci, row.index)" @mouseleave="clearPreview()"
                                                        :class="slotButtonClass(cell.court_id, cell.local_start)"
                                                        class="bk-tap relative w-full h-10 rounded-xl border text-xs font-bold tabular-nums transition-all duration-150 active:scale-95">
                                                    <span x-show="cell.is_prime_time" class="absolute top-1 right-1.5 w-1.5 h-1.5 rounded-full bg-[#D4AF37]"></span>
                                                    <span x-text="'Rp ' + formatNumber(cell.price)"></span>
                                                </button>
                                            </template>
                                            <template x-if="cell && cell.status !== 'AVAILABLE'">
                                                <div :class="cell.status === 'LOCKED' ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-[#F4F1EA] border-transparent text-[#B3A78F]'"
                                                     class="w-full h-10 rounded-xl border text-[11px] font-semibold flex items-center justify-center cursor-not-allowed select-none"
                                                     x-text="statusLabel(cell.status)"></div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- Ringkasan & lanjut (menempel di atas navigasi bawah) --}}
        <div class="bk-bar fixed inset-x-0 z-30 px-3 sm:px-6 pointer-events-none">
            <div x-ref="bar" class="mx-auto max-w-6xl pointer-events-auto bg-white/95 backdrop-blur-xl rounded-3xl border border-[#E3CF9C] shadow-[0_18px_44px_rgba(90,64,12,0.22)] p-3 sm:p-4 flex items-center gap-3">
                <div class="flex-1 min-w-0">
                    <template x-if="selectedSlots.length === 0">
                        <div>
                            <div class="text-sm font-bold text-[#1F170D]">Choose a start time</div>
                            <div class="text-[11px] text-[#7A643E] truncate" x-text="dateLabel(activeDate) + ' · ' + selectedDuration + (selectedDuration === 1 ? ' hour' : ' hours')"></div>
                        </div>
                    </template>
                    <template x-if="selectedSlots.length > 0">
                        <div class="bk-fade-up min-w-0">
                            <div class="text-[11px] font-bold text-[#1F170D] truncate" x-text="selectionHeadline"></div>
                            <div class="text-[11px] font-semibold text-[#8C6418] truncate" x-text="selectionDetail"></div>
                            <div class="font-black text-lg sm:text-xl leading-tight tabular-nums text-[#1F170D]" x-text="'Rp ' + formatNumber(totalPrice)"></div>
                        </div>
                    </template>
                </div>
                <button type="button" x-show="selectedSlots.length > 0" @click="selectedSlots = []" title="Clear selection"
                        class="bk-tap shrink-0 w-10 h-10 rounded-2xl border border-[#EADBB5] text-[#9A3412] hover:bg-rose-50 flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
                <button type="button" :disabled="selectedSlots.length === 0 || isHolding" @click="proceedToHoldAndCart()"
                        :class="selectedSlots.length > 0
                            ? 'bg-gradient-to-b from-[#F5DE9B] via-[#D4AF37] to-[#A87D18] text-[#1E160A] shadow-[0_8px_20px_rgba(168,125,24,0.35)] hover:brightness-105 active:scale-95'
                            : 'bg-[#EFEAE0] text-[#A89F8F] cursor-not-allowed'"
                        class="bk-tap shrink-0 h-12 px-5 sm:px-7 rounded-2xl text-xs sm:text-sm font-black uppercase tracking-wider transition-all flex items-center gap-2">
                    <svg x-show="isHolding" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"></circle><path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="isHolding ? 'Reserving…' : 'Continue'"></span>
                    <svg x-show="!isHolding" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                </button>
            </div>
        </div>

        {{-- Pemberitahuan: bottom sheet di HP, dialog di layar lebar --}}
        <div x-show="noticeModal.show" style="display: none; z-index: 99999 !important;" class="fixed inset-0 flex items-end sm:items-center justify-center sm:p-4">
            <div x-show="noticeModal.show" x-transition.opacity.duration.200ms @click="handleNoticeClose()" class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div x-show="noticeModal.show"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0" x-transition:enter-end="translate-y-0 sm:opacity-100"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0 sm:opacity-100" x-transition:leave-end="translate-y-full sm:translate-y-4 sm:opacity-0"
                 class="relative w-full sm:max-w-md bg-white rounded-t-3xl sm:rounded-3xl border-t-2 sm:border-2 border-[#D4AF37] shadow-2xl p-6 pb-[calc(1.5rem+env(safe-area-inset-bottom,0px))] sm:pb-6 text-center space-y-4">
                <div class="sm:hidden mx-auto w-10 h-1.5 rounded-full bg-[#E8DCC0]"></div>
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto"
                     :class="{
                         'bg-[#FAF2DE] text-[#8C6418]': noticeModal.type === 'info' || noticeModal.type === 'gold',
                         'bg-rose-50 text-rose-600': noticeModal.type === 'error' || noticeModal.type === 'danger',
                         'bg-emerald-50 text-emerald-600': noticeModal.type === 'success'
                     }">
                    <svg x-show="noticeModal.type === 'error' || noticeModal.type === 'danger'" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    <svg x-show="noticeModal.type === 'success'" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <svg x-show="noticeModal.type === 'info' || noticeModal.type === 'gold'" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div class="space-y-1.5">
                    <h3 class="font-serif font-black text-lg" x-text="noticeModal.title"></h3>
                    <p class="text-sm text-[#7A643E] leading-relaxed" x-text="noticeModal.message"></p>
                </div>
                <button type="button" @click="handleNoticeClose()" x-text="noticeModal.buttonText"
                        class="bk-tap w-full h-12 rounded-2xl text-sm font-black uppercase tracking-wider text-[#1E160A] bg-gradient-to-b from-[#F5DE9B] via-[#D4AF37] to-[#A87D18] active:scale-[0.98] transition-transform"></button>
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
                // HP: satu lapangan (kartu + kotak jam); tablet & desktop: grid semua lapangan (bisa diganti).
                viewMode: window.matchMedia('(min-width: 768px)').matches ? 'grid' : 'court',
                activeCourtIndex: 0,

                noticeModal: { show: false, title: '', message: '', type: 'info', buttonText: 'Got It', onClose: null },

                showNotice(title, message, type = 'info', buttonText = 'Got It', onClose = null) {
                    this.noticeModal = { show: true, title, message, type, buttonText, onClose };
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
                    // Layar HP selalu tampilan satu lapangan (grid semua lapangan terlalu sempit).
                    window.matchMedia('(min-width: 768px)').addEventListener('change', (e) => { if (!e.matches) this.viewMode = 'court'; });
                    // Bar ringkasan berubah tinggi (1 → 3 baris saat ada pilihan); ruang bawah halaman ikut tingginya.
                    if (window.ResizeObserver && this.$refs.bar) {
                        new ResizeObserver(() => {
                            this.$root.style.setProperty('--bk-bar-h', this.$refs.bar.offsetHeight + 'px');
                        }).observe(this.$refs.bar);
                    }
                },

                get activeCourt() {
                    return this.courts[this.activeCourtIndex] || null;
                },

                get gridColumns() {
                    return `grid-template-columns: 64px repeat(${Math.max(1, this.courts.length)}, minmax(120px, 1fr));`;
                },

                /** Jam lapangan aktif dikelompokkan Pagi / Siang / Malam (jam di luar jam buka lapangan disembunyikan). */
                get activeCourtGroups() {
                    const court = this.activeCourt;
                    if (!court) return [];
                    const groups = [
                        { label: 'Morning', range: 'before 12:00', items: [] },
                        { label: 'Afternoon', range: '12:00 – 17:00', items: [] },
                        { label: 'Evening', range: 'from 17:00', items: [] },
                    ];
                    court.slots.forEach((slot, index) => {
                        if (slot.status === 'CLOSED' || slot.status === 'PAST') return;
                        const hour = parseInt(slot.local_start, 10);
                        groups[hour < 12 ? 0 : (hour < 17 ? 1 : 2)].items.push({ slot, index });
                    });
                    return groups.filter(g => g.items.length > 0);
                },

                /** Baris grid yang semua lapangannya sudah lewat jam disembunyikan (diganti satu catatan). */
                get visibleMatrixRows() {
                    return this.matrixRows.filter(row => row.cells.some(cell => cell && cell.status !== 'PAST'));
                },

                get pastNote() {
                    if (!this.matrixRows.some(row => row.cells.some(cell => cell && cell.status === 'PAST'))) return '';
                    const firstOpen = this.visibleMatrixRows[0];
                    return firstOpen ? `Hours before ${firstOpen.time} today have passed` : 'All hours today have passed — pick another date';
                },

                availableCount(court) {
                    return court && court.slots ? court.slots.filter(s => s.status === 'AVAILABLE').length : 0;
                },

                selectedCountForCourt(courtId) {
                    return this.selectedSlots.filter(s => s.court_id === courtId).length;
                },

                statusLabel(status) {
                    return { BOOKED: 'Booked', LOCKED: 'In checkout', CLOSED: 'Closed', PAST: 'Passed' }[status] || status;
                },

                slotButtonClass(courtId, startTime) {
                    if (this.isSlotSelected(courtId, startTime)) {
                        return 'bk-pop bg-[#183428] border-[#183428] text-[#F5E6BE] shadow-[0_6px_16px_rgba(24,52,40,0.32)]';
                    }
                    if (this.isSlotHovered(courtId, startTime)) {
                        return 'bg-[#FAF2DE] border-[#D4AF37] text-[#1F170D]';
                    }
                    return 'bg-white border-[#E8D9B2] text-[#1F170D] hover:border-[#D4AF37] hover:bg-[#FFFBF0]';
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
                    for (let i = 0; i < 14; i++) {
                        const d = new Date();
                        d.setDate(today.getDate() + i);
                        this.dateTabs.push({
                            day: i === 0 ? 'Today' : days[d.getDay()],
                            dayNum: d.getDate(),
                            month: months[d.getMonth()],
                            date: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`,
                        });
                    }
                },

                dateLabel(dateStr) {
                    const tab = this.dateTabs.find(t => t.date === dateStr);
                    return tab ? `${tab.day}, ${tab.dayNum} ${tab.month}` : dateStr;
                },

                openCalendarPicker() {
                    const input = this.$refs.calendarInput;
                    if (!input) return;
                    if (typeof input.showPicker === 'function') {
                        try { input.showPicker(); return; } catch (e) { console.warn('showPicker error:', e); }
                    }
                    input.focus();
                },

                onCalendarSelect(val) {
                    if (!val) return;
                    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    if (!this.dateTabs.some(t => t.date === val)) {
                        const d = new Date(val + 'T00:00:00');
                        this.dateTabs = this.dateTabs.filter(t => !t.isCustom);
                        this.dateTabs.push({ day: days[d.getDay()], dayNum: d.getDate(), month: months[d.getMonth()], date: val, isCustom: true });
                    }
                    this.selectDate(val);
                },

                selectDate(dateStr) {
                    this.activeDate = dateStr;
                    this.selectedSlots = [];
                    this.hoveredSlots = [];
                    this.fetchSchedule();
                    this.$nextTick(() => {
                        const el = document.getElementById('date-tab-' + dateStr);
                        if (el) el.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                    });
                },

                async fetchSchedule() {
                    this.isLoading = true;
                    try {
                        const res = await fetch(`/api/v1/padel/schedule?date=${this.activeDate}`, { headers: { 'Accept': 'application/json' } });
                        const json = await res.json();
                        if (json.success) {
                            this.courts = json.data.courts || [];
                            this.buildMatrixRows(this.courts);
                            if (this.activeCourtIndex >= this.courts.length) this.activeCourtIndex = 0;
                        }
                    } catch (e) {
                        console.error('Failed to fetch court schedule:', e);
                    } finally {
                        this.isLoading = false;
                    }
                },

                /** Baris grid = jam; kolom = semua lapangan aktif (tidak lagi terbatas 3 lapangan). */
                buildMatrixRows(courts) {
                    const slotCount = courts.length ? Math.max(...courts.map(c => c.slots.length)) : 0;
                    this.matrixRows = [];
                    for (let i = 0; i < slotCount; i++) {
                        const first = courts.map(c => c.slots[i]).find(Boolean);
                        this.matrixRows.push({
                            index: i,
                            time: first ? first.local_start : '',
                            cells: courts.map(c => c.slots[i] ? { court_id: c.court_id, court_name: c.court_name, ...c.slots[i] } : null),
                        });
                    }
                },

                isSlotSelected(courtId, startTime) {
                    return this.selectedSlots.some(s => s.court_id === courtId && startTime >= s.start_time && startTime < s.end_time);
                },

                isSlotHovered(courtId, startTime) {
                    return this.hoveredSlots.some(s => s.court_id === courtId && s.start_time === startTime);
                },

                previewSlots(courtIndex, slotIndex) {
                    if (this.selectedDuration <= 1) return;
                    const court = this.courts[courtIndex];
                    if (!court) return;
                    this.hoveredSlots = [];
                    for (let i = 0; i < this.selectedDuration; i++) {
                        const slot = court.slots[slotIndex + i];
                        if (slot) this.hoveredSlots.push({ court_id: court.court_id, start_time: slot.local_start });
                    }
                },

                clearPreview() {
                    this.hoveredSlots = [];
                },

                /** Durasi > 1 jam: pilih blok jam berurutan di lapangan yang sama (dicek dari daftar slot lapangan itu sendiri). */
                handleSlotClick(courtIndex, slotIndex) {
                    const court = this.courts[courtIndex];
                    const slot = court ? court.slots[slotIndex] : null;
                    if (!slot) return;
                    if (navigator.vibrate) { try { navigator.vibrate(8); } catch (e) {} }

                    if (this.selectedDuration === 1) {
                        this.toggleSlot(court.court_id, court.court_name, slot.local_start, slot.local_end, slot.price);
                        return;
                    }

                    const needed = this.selectedDuration;
                    let totalBlockPrice = 0;
                    for (let i = 0; i < needed; i++) {
                        const target = court.slots[slotIndex + i];
                        if (!target) {
                            this.showNotice('Operating Hours Exceeded', `Not enough remaining operating hours for a ${needed}-hour booking block.`, 'error', 'Choose Another Time');
                            return;
                        }
                        if (target.status !== 'AVAILABLE') {
                            this.showNotice('Slot Unavailable', `Cannot reserve ${needed} consecutive hours starting at ${slot.local_start} because the ${target.local_start} slot is ${this.statusLabel(target.status).toLowerCase()}. Please select another start time.`, 'error', 'Choose Another Time');
                            return;
                        }
                        totalBlockPrice += target.price;
                    }

                    const endHour = court.slots[slotIndex + needed - 1].local_end;
                    this.selectedSlots = [{
                        court_id: court.court_id,
                        court: court.court_name,
                        booking_date: this.activeDate,
                        start_time: slot.local_start,
                        end_time: endHour,
                        duration_hours: needed,
                        time: `${slot.local_start} - ${endHour} (${needed} Hours)`,
                        price: totalBlockPrice,
                    }];
                    this.hoveredSlots = [];
                },

                toggleSlot(courtId, courtName, startTime, endTime, price) {
                    const idx = this.selectedSlots.findIndex(s => s.court_id === courtId && s.start_time === startTime && s.end_time === endTime);
                    if (idx >= 0) {
                        this.selectedSlots.splice(idx, 1);
                        return;
                    }
                    // Jam yang menimpa pilihan sebelumnya di lapangan yang sama: lepas pilihan itu.
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
                        price: price,
                    });
                },

                /** Jam berurutan di lapangan yang sama digabung jadi satu e-tiket; yang terpisah jadi booking sendiri. */
                consolidateContiguousSlots(slots) {
                    if (!slots || slots.length === 0) return [];
                    const courtGroups = {};
                    slots.forEach(s => {
                        if (!courtGroups[s.court_id]) courtGroups[s.court_id] = [];
                        courtGroups[s.court_id].push({ ...s });
                    });

                    const consolidated = [];
                    Object.keys(courtGroups).forEach(courtId => {
                        const list = courtGroups[courtId].sort((a, b) => a.start_time.localeCompare(b.start_time));
                        let current = null;
                        list.forEach(slot => {
                            if (!current) {
                                current = { ...slot, duration_hours: slot.duration_hours || 1 };
                                return;
                            }
                            if (slot.start_time === current.end_time) {
                                current.end_time = slot.end_time;
                                current.price += slot.price;
                                current.duration_hours += (slot.duration_hours || 1);
                                current.time = `${current.start_time} - ${current.end_time} (${current.duration_hours} ${current.duration_hours === 1 ? 'Hour' : 'Hours'})`;
                            } else {
                                consolidated.push(current);
                                current = { ...slot, duration_hours: slot.duration_hours || 1 };
                            }
                        });
                        if (current) consolidated.push(current);
                    });
                    return consolidated;
                },

                get selectedSlotsSummary() {
                    if (this.selectedSlots.length === 0) return '0 Slots Selected';
                    const consolidated = this.consolidateContiguousSlots(this.selectedSlots);
                    if (consolidated.length === 1) {
                        const first = consolidated[0];
                        return `${first.court} · ${this.dateLabel(this.activeDate)} · ${first.start_time}–${first.end_time}`;
                    }
                    const totalHours = consolidated.reduce((sum, s) => sum + s.duration_hours, 0);
                    return `${consolidated.length} sessions · ${totalHours} hours · ${this.dateLabel(this.activeDate)}`;
                },

                /** Ringkasan di bar bawah: baris 1 lapangan (atau jumlah sesi), baris 2 tanggal + jam. */
                get selectionHeadline() {
                    const consolidated = this.consolidateContiguousSlots(this.selectedSlots);
                    if (consolidated.length === 1) return consolidated[0].court;
                    const totalHours = consolidated.reduce((sum, s) => sum + s.duration_hours, 0);
                    return `${consolidated.length} sessions · ${totalHours} hours`;
                },

                get selectionDetail() {
                    const consolidated = this.consolidateContiguousSlots(this.selectedSlots);
                    if (consolidated.length === 1) {
                        const s = consolidated[0];
                        return `${this.dateLabel(this.activeDate)} · ${s.start_time}–${s.end_time}`;
                    }
                    return this.dateLabel(this.activeDate) + ' · ' + [...new Set(consolidated.map(s => s.court))].join(', ');
                },

                get totalPrice() {
                    return this.selectedSlots.reduce((sum, s) => sum + s.price, 0);
                },

                formatNumber(val) {
                    if (!val) return '0';
                    return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                },

                async proceedToHoldAndCart() {
                    if (this.selectedSlots.length === 0) return;
                    this.isHolding = true;

                    try {
                        const consolidatedSlots = this.consolidateContiguousSlots(this.selectedSlots);
                        const payload = {
                            booking_date: this.activeDate,
                            slots: consolidatedSlots.map(s => ({ court_id: s.court_id, start_time: s.start_time, end_time: s.end_time })),
                        };

                        const res = await fetch('/api/v1/padel/hold-slot', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            },
                            body: JSON.stringify(payload),
                        });
                        const json = await res.json();

                        if (res.status === 201 && json.success) {
                            localStorage.setItem('club61_cart', JSON.stringify(consolidatedSlots));
                            localStorage.setItem('club61_hold_data', JSON.stringify(json.data));
                            sessionStorage.setItem('club61_cart', JSON.stringify(consolidatedSlots));
                            sessionStorage.setItem('club61_hold_data', JSON.stringify(json.data));
                            window.dispatchEvent(new CustomEvent('cart-updated'));
                            window.location.href = "{{ route('customer.cart') }}";
                        } else {
                            this.showNotice('Slot Reservation Failed', json.message || 'Failed to hold selected court slots. Please try another time.', 'error', 'Close');
                            this.fetchSchedule();
                        }
                    } catch (e) {
                        this.showNotice('Network Error', 'A network error occurred while holding court slots. Please check your connection.', 'error', 'Close');
                    } finally {
                        this.isHolding = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>
