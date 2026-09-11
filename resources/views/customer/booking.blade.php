<x-app-layout>
    <div x-data="bookingCourtApp()" x-init="init()" class="py-6 sm:py-8 text-[#1F170D]">
        <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16 space-y-6">

            <!-- Top Header & Breadcrumb (Screen 2 & 3 Mockup) -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white/95 backdrop-blur-xl p-5 rounded-3xl border border-[#DFC387] shadow-sm">
                <div class="flex items-center gap-3.5">
                    <a href="{{ route('dashboard') }}" class="p-2.5 rounded-2xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] transition-colors" title="Kembali ke Beranda">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="font-serif font-black text-xl sm:text-2xl text-[#1F170D]">Booking Court</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-emerald-100 text-emerald-800 border border-emerald-300">Live Schedule</span>
                        </div>
                        <p class="text-xs text-[#7A643E] mt-0.5">Pilih tanggal dan slot jam lapangan padel panoramic favorit Anda</p>
                    </div>
                </div>

                <!-- Shopping Cart Shortcut with Counter -->
                <div class="flex items-center gap-3">
                    <a href="{{ route('customer.cart') }}" 
                       class="flex items-center gap-2.5 px-4 py-2.5 rounded-2xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] transition-colors relative shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span class="font-bold text-xs">Keranjang Booking</span>
                        <span x-text="selectedSlots.length" class="min-w-[20px] h-[20px] px-1.5 bg-[#D4AF37] text-[#1E160A] text-[10px] font-black rounded-full flex items-center justify-center shadow-sm"></span>
                    </a>
                </div>
            </div>

            <!-- Date Picker Slider / Tabs (Screen 2 Mockup) -->
            <div class="bg-white/95 backdrop-blur-xl p-4 rounded-3xl border border-[#DFC387] shadow-sm">
                <div class="flex items-center gap-2.5 overflow-x-auto pb-1 scrollbar-none">
                    <div class="p-3 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] text-[#7A5818] shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>

                    <template x-for="(tab, index) in dateTabs" :key="index">
                        <button type="button" 
                                @click="selectDate(tab.date)"
                                :class="activeDate === tab.date 
                                    ? 'bg-[#183428] text-[#F5E6BE] border-[#D4AF37] shadow-md scale-[1.02]' 
                                    : 'bg-[#FAF6EC] text-[#6B5738] border-[#DFC387]/70 hover:bg-white'"
                                class="px-5 py-2.5 rounded-2xl text-center border font-sans shrink-0 transition-all text-xs cursor-pointer">
                            <span class="block text-[10px] uppercase font-extrabold opacity-80" x-text="tab.day"></span>
                            <span class="block font-black text-xs sm:text-sm mt-0.5" x-text="tab.formatted"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Court Selector Badges -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <template x-for="court in courts" :key="court.court_id">
                    <div class="p-4 rounded-2xl bg-white/95 border border-[#DFC387] shadow-sm flex items-center justify-between">
                        <div>
                            <div class="font-extrabold text-xs sm:text-sm text-[#1F170D] flex items-center gap-1.5">
                                <span x-text="court.court_name"></span>
                                <span class="text-[#8C6418] cursor-help" title="Spesifikasi: Kaca Tempered 12mm WPT & Rumput Mondo Supercourt">ⓘ</span>
                            </div>
                            <div class="text-[10px] text-[#7A643E] mt-0.5" x-text="court.type + ' &bull; AC Indoor Central'"></div>
                        </div>
                        <span class="w-3 h-3 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
                    </div>
                </template>
            </div>

            <!-- Duration Preset Selector (Pilihan Durasi Cepat: 1 Jam, 2 Jam, 3 Jam, 4 Jam) -->
            <div class="bg-white/95 backdrop-blur-xl p-4 sm:p-5 rounded-3xl border border-[#DFC387] shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center text-xl shrink-0 text-[#7A5818] shadow-sm">
                        ⏱️
                    </div>
                    <div>
                        <div class="text-xs font-black text-[#1F170D] uppercase tracking-wider flex items-center gap-2">
                            <span>Durasi Main Sekaligus:</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-[#FAF2DE] text-[#7A5818] border border-[#DFC387]" x-text="selectedDuration + ' Jam'"></span>
                        </div>
                        <p class="text-[11px] text-[#7A643E] mt-0.5">Pilih durasi, lalu klik 1 jam mulai. Sistem otomatis mem-blok slot berturut-turut tanpa harus klik satu per satu.</p>
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
                            <span x-text="d + ' Jam'"></span>
                            <span x-show="d === 2" class="hidden sm:inline text-[9px] text-amber-400 font-bold">★ Populer</span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Loading State -->
            <div x-show="isLoading" class="py-12 text-center">
                <div class="inline-block w-8 h-8 border-3 border-[#D4AF37] border-t-transparent rounded-full animate-spin"></div>
                <div class="text-xs font-bold text-[#8C6418] mt-2">Memuat matriks jadwal lapangan...</div>
            </div>

            <!-- Schedule Matrix Grid (Matches Screen 2 & 3 Mockup) -->
            <div x-show="!isLoading" class="bg-white/95 backdrop-blur-xl rounded-3xl border border-[#DFC387] shadow-[0_15px_40px_rgba(160,120,30,0.15)] overflow-hidden">
                
                <!-- Table Headers -->
                <div class="grid grid-cols-12 bg-gradient-to-r from-[#FAF2DE] via-[#F5E6BE] to-[#FAF2DE] border-b border-[#DFC387] text-[11px] font-black uppercase text-[#5C410F] py-3.5 px-4">
                    <div class="col-span-3 sm:col-span-2 text-center">Waktu Main</div>
                    <div class="col-span-4 sm:col-span-5 text-center">Lapangan 1 (Panoramic Pro)</div>
                    <div class="col-span-5 sm:col-span-5 text-center">Lapangan 2 (Tournament Std)</div>
                </div>

                <!-- Time Slot Rows -->
                <div class="divide-y divide-[#EEDBB0]/60 text-xs">
                    <template x-for="(row, rowIndex) in matrixRows" :key="row.time">
                        <div class="grid grid-cols-12 items-center p-3 sm:p-3.5 hover:bg-[#FDFBF7] transition-colors gap-3">
                            
                            <!-- Time Label -->
                            <div class="col-span-3 sm:col-span-2 text-center font-mono font-bold text-[#5C410F] text-xs sm:text-sm" x-text="row.time"></div>

                            <!-- Court 1 Slot -->
                            <div class="col-span-4 sm:col-span-5">
                                <template x-if="row.c1.status === 'BOOKED'">
                                    <div class="w-full py-2.5 px-3 rounded-xl bg-[#F0ECE1] text-[#9E907B] text-center font-bold text-xs border border-[#E0D8C8] cursor-not-allowed">
                                        Booked
                                    </div>
                                </template>
                                <template x-if="row.c1.status === 'LOCKED'">
                                    <div class="w-full py-2.5 px-3 rounded-xl bg-amber-100/80 text-amber-800 text-center font-bold text-xs border border-amber-300">
                                        In Checkout
                                    </div>
                                </template>
                                <template x-if="row.c1.status === 'AVAILABLE'">
                                    <button type="button" 
                                            @click="handleSlotClick(row.c1.court_id, 'Lapangan 1', row.c1.local_start, row.c1.local_end, row.c1.price, rowIndex, 'c1')"
                                            @mouseenter="previewSlots(row.c1.court_id, rowIndex, 'c1')"
                                            @mouseleave="clearPreview()"
                                            :class="isSlotSelected(row.c1.court_id, row.c1.local_start) 
                                                ? 'bg-[#183428] text-white border-2 border-[#D4AF37] shadow-[0_4px_15px_rgba(24,52,40,0.4)] scale-[1.01]' 
                                                : (isSlotHovered(row.c1.court_id, row.c1.local_start) 
                                                    ? 'bg-[#FAF2DE] text-[#1F170D] border-2 border-[#D4AF37] shadow-md' 
                                                    : 'bg-white hover:bg-[#FAF5E6] text-[#1F170D] border border-[#DFC387]')"
                                            class="w-full py-2 px-3 rounded-xl text-center font-medium transition-all active:scale-95 group cursor-pointer relative">
                                        <div class="text-[9px] line-through text-[#9E907B]" 
                                             :class="isSlotSelected(row.c1.court_id, row.c1.local_start) ? 'text-emerald-200/70' : ''"
                                             x-text="'Rp ' + formatNumber(row.c1.original_price)"></div>
                                        <div class="font-extrabold text-xs sm:text-sm text-[#8C6418]" 
                                             :class="isSlotSelected(row.c1.court_id, row.c1.local_start) ? 'text-[#F5E6BE]' : ''"
                                             x-text="'Rp ' + formatNumber(row.c1.price)"></div>
                                    </button>
                                </template>
                            </div>

                            <!-- Court 2 Slot -->
                            <div class="col-span-5 sm:col-span-5">
                                <template x-if="row.c2.status === 'BOOKED'">
                                    <div class="w-full py-2.5 px-3 rounded-xl bg-[#F0ECE1] text-[#9E907B] text-center font-bold text-xs border border-[#E0D8C8] cursor-not-allowed">
                                        Booked
                                    </div>
                                </template>
                                <template x-if="row.c2.status === 'LOCKED'">
                                    <div class="w-full py-2.5 px-3 rounded-xl bg-amber-100/80 text-amber-800 text-center font-bold text-xs border border-amber-300">
                                        In Checkout
                                    </div>
                                </template>
                                <template x-if="row.c2.status === 'AVAILABLE'">
                                    <button type="button" 
                                            @click="handleSlotClick(row.c2.court_id, 'Lapangan 2', row.c2.local_start, row.c2.local_end, row.c2.price, rowIndex, 'c2')"
                                            @mouseenter="previewSlots(row.c2.court_id, rowIndex, 'c2')"
                                            @mouseleave="clearPreview()"
                                            :class="isSlotSelected(row.c2.court_id, row.c2.local_start) 
                                                ? 'bg-[#183428] text-white border-2 border-[#D4AF37] shadow-[0_4px_15px_rgba(24,52,40,0.4)] scale-[1.01]' 
                                                : (isSlotHovered(row.c2.court_id, row.c2.local_start) 
                                                    ? 'bg-[#FAF2DE] text-[#1F170D] border-2 border-[#D4AF37] shadow-md' 
                                                    : 'bg-white hover:bg-[#FAF5E6] text-[#1F170D] border border-[#DFC387]')"
                                            class="w-full py-2 px-3 rounded-xl text-center font-medium transition-all active:scale-95 group cursor-pointer relative">
                                        <div class="text-[9px] line-through text-[#9E907B]" 
                                             :class="isSlotSelected(row.c2.court_id, row.c2.local_start) ? 'text-emerald-200/70' : ''"
                                             x-text="'Rp ' + formatNumber(row.c2.original_price)"></div>
                                        <div class="font-extrabold text-xs sm:text-sm text-[#8C6418]" 
                                             :class="isSlotSelected(row.c2.court_id, row.c2.local_start) ? 'text-[#F5E6BE]' : ''"
                                             x-text="'Rp ' + formatNumber(row.c2.price)"></div>
                                    </button>
                                </template>
                            </div>

                        </div>
                    </template>
                </div>

            </div>

            <!-- Sticky Bottom Selection Action Bar -->
            <div class="sticky bottom-16 md:bottom-4 z-30 bg-white/95 backdrop-blur-xl p-4 sm:p-5 rounded-2xl border-2 border-[#D4AF37] shadow-[0_15px_40px_rgba(160,120,30,0.3)] flex items-center justify-between gap-4">
                <div>
                    <div class="text-[11px] text-[#7A643E] font-medium">Subtotal Terpilih:</div>
                    <div class="font-mono font-black text-lg sm:text-2xl text-[#1F170D]" x-text="'Rp ' + formatNumber(totalPrice)"></div>
                    <div class="text-[10px] font-bold text-[#8C6418]" x-text="selectedSlotsSummary"></div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" 
                            x-show="selectedSlots.length > 0"
                            @click="selectedSlots = []" 
                            class="px-4 py-2.5 rounded-xl text-xs font-bold text-rose-600 hover:bg-rose-50 border border-rose-200 transition-colors">
                        Reset
                    </button>
                    
                    <button type="button" 
                            :disabled="selectedSlots.length === 0 || isHolding"
                            @click="proceedToHoldAndCart()"
                            :class="selectedSlots.length > 0 
                                ? 'bg-gradient-to-b from-[#F5DE9B] via-[#D4AF37] to-[#A87D18] text-[#1E160A] shadow-lg hover:brightness-105 active:scale-95' 
                                : 'bg-[#E5DFD3] text-[#A89F8F] cursor-not-allowed'"
                            class="px-6 sm:px-8 py-3.5 rounded-2xl text-xs font-black uppercase tracking-wider transition-all border border-[#FFF3CD]/80 flex items-center gap-2">
                        <span x-text="isHolding ? 'Mengunci Slot...' : (selectedSlots.length > 0 ? 'Pilih Jadwal &rarr;' : 'Pilih Jadwal')"></span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <script>
        function bookingCourtApp() {
            return {
                activeDate: '{{ now()->format("Y-m-d") }}',
                isLoading: false,
                isHolding: false,
                courts: [],
                matrixRows: [],
                dateTabs: [],
                selectedSlots: [],
                selectedDuration: 1,
                hoveredSlots: [],
                
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
                    const days = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
                    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                    const today = new Date();

                    this.dateTabs = [];
                    for (let i = 0; i < 7; i++) {
                        const d = new Date();
                        d.setDate(today.getDate() + i);

                        const yyyy = d.getFullYear();
                        const mm = String(d.getMonth() + 1).padStart(2, '0');
                        const dd = String(d.getDate()).padStart(2, '0');
                        const dateStr = `${yyyy}-${mm}-${dd}`;

                        this.dateTabs.push({
                            day: i === 0 ? 'Hari Ini' : days[d.getDay()],
                            formatted: `${d.getDate()} ${months[d.getMonth()]}`,
                            date: dateStr,
                        });
                    }
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
                        console.error('Gagal mengambil jadwal:', e);
                    } finally {
                        this.isLoading = false;
                    }
                },

                buildMatrixRows(courts) {
                    const c1 = courts[0];
                    const c2 = courts[1] || courts[0];

                    this.matrixRows = [];
                    const slotCount = c1.slots.length;

                    for (let i = 0; i < slotCount; i++) {
                        const slot1 = c1.slots[i];
                        const slot2 = c2 ? c2.slots[i] : slot1;

                        this.matrixRows.push({
                            time: slot1.local_start,
                            c1: {
                                court_id: c1.court_id,
                                ...slot1,
                            },
                            c2: {
                                court_id: c2 ? c2.court_id : c1.court_id,
                                ...slot2,
                            },
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
                 * 🛡️ CELAH 1 QA: Konsolidasi Sesi Multi-Jam Bersambung
                 */
                handleSlotClick(courtId, courtName, startTime, endTime, price, rowIndex, courtKey) {
                    // Jika durasi 1 jam, gunakan toggle biasa
                    if (this.selectedDuration === 1) {
                        this.toggleSlot(courtId, courtName, startTime, endTime, price);
                        return;
                    }

                    // Jika durasi > 1 jam: pilih rentang jam berturut-turut sekaligus
                    const needed = this.selectedDuration;
                    let totalBlockPrice = 0;

                    for (let i = 0; i < needed; i++) {
                        const targetRow = this.matrixRows[rowIndex + i];
                        if (!targetRow) {
                            alert(`Slot waktu tidak mencukupi untuk durasi ${needed} jam hingga jam tutup operasional.`);
                            return;
                        }

                        const slotObj = targetRow[courtKey];
                        if (!slotObj || slotObj.status !== 'AVAILABLE') {
                            alert(`Tidak dapat memilih ${needed} jam berturut-turut mulai jam ${startTime} karena jam ${targetRow.time} sudah terisi (${slotObj ? slotObj.status : 'Tutup'}). Silakan pilih jam mulai yang lain.`);
                            return;
                        }

                        totalBlockPrice += slotObj.price;
                    }

                    const endRow = this.matrixRows[rowIndex + needed - 1];
                    const endHour = endRow[courtKey].local_end;

                    // Otomatis terapkan 1 Sesi Bersambung Utuh (1 Master Booking Slot)
                    this.selectedSlots = [{
                        court_id: courtId,
                        court: courtName,
                        booking_date: this.activeDate,
                        start_time: startTime,
                        end_time: endHour,
                        duration_hours: needed,
                        time: `${startTime} - ${endHour} (${needed} Jam)`,
                        price: totalBlockPrice
                    }];

                    this.hoveredSlots = [];
                },

                toggleSlot(courtId, courtName, startTime, endTime, price) {
                    const idx = this.selectedSlots.findIndex(s => s.court_id === courtId && s.start_time === startTime && s.end_time === endTime);
                    if (idx >= 0) {
                        this.selectedSlots.splice(idx, 1);
                    } else {
                        // Jika ada slot yang mencakup jam ini, hapus dulu
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
                 * 🛡️ CELAH 1 QA DEFENSE: Algoritma Contiguous Grouping
                 * Menggabungkan jam yang nempel tanpa putus menjadi 1 E-Tiket,
                 * dan memisahkan jadwal bolong (non-contiguous) menjadi E-Tiket terpisah.
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

                            // Bersambung tanpa celah?
                            if (slot.start_time === current.end_time) {
                                current.end_time = slot.end_time;
                                current.price += slot.price;
                                current.duration_hours += (slot.duration_hours || 1);
                                current.time = `${current.start_time} - ${current.end_time} (${current.duration_hours} Jam)`;
                            } else {
                                // JADWAL BOLONG! Simpan current sebagai tiket mandiri
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
                    if (this.selectedSlots.length === 0) return '0 Slot Jam Terpilih';
                    const consolidated = this.consolidateContiguousSlots(this.selectedSlots);
                    if (consolidated.length === 1) {
                        const first = consolidated[0];
                        return `${first.duration_hours} Jam (${first.start_time} - ${first.end_time} WIB • ${first.court})`;
                    }
                    const totalHours = consolidated.reduce((sum, s) => sum + s.duration_hours, 0);
                    return `${consolidated.length} Sesi Main (${totalHours} Jam Total)`;
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
                        // Konsolidasi slot bersambung sebelum hold
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
                            // Simpan detail booking yang telah dikonsolidasi ke session storage untuk cart
                            sessionStorage.setItem('vantage_cart', JSON.stringify(consolidatedSlots));
                            sessionStorage.setItem('vantage_hold_data', JSON.stringify(json.data));
                            window.location.href = "{{ route('customer.cart') }}";
                        } else {
                            alert(json.message || 'Gagal mengunci slot lapangan. Silakan coba jam lain.');
                            this.fetchSchedule(); // Refresh data
                        }
                    } catch (e) {
                        alert('Terjadi kesalahan jaringan saat hold slot.');
                    } finally {
                        this.isHolding = false;
                    }
                }
            }
        }
    </script>
</x-app-layout>
