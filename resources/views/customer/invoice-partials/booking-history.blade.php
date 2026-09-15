<!-- Past Booking History (Real Database, Excludes Current Order) -->
<div class="bg-white/95 backdrop-blur-xl rounded-3xl border border-[#DFC387] shadow-[0_12px_35px_rgba(160,120,30,0.15)] p-4 sm:p-6 space-y-4">
    <div class="flex items-center justify-between border-b border-[#DFC387]/50 pb-3">
        <div>
            <h3 class="font-serif font-black text-base text-[#1F170D]">Riwayat Booking Lainnya</h3>
            <p class="text-[11px] text-[#7A643E]">Riwayat reservasi sesi Anda sebelumnya</p>
        </div>
        <span class="text-[11px] text-[#7A643E] font-medium bg-[#FAF8F2] px-2.5 py-1 rounded-full border border-[#DFC387]/60" 
              x-text="(searchQuery || searchDate) ? (filteredPastBookings.length + ' dari ' + pastBookings.length + ' Riwayat') : (pastBookings.length + ' Riwayat')"></span>
    </div>

    <!-- Filter & Pencarian Kompak (Search by Lapangan & Date) -->
    <div class="space-y-2" x-show="pastBookings.length > 0">
        <!-- Row 1: Full-width Search Bar for Court Name & Booking Code -->
        <div class="relative w-full">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#8C7A58]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input type="text" 
                   x-model="searchQuery" 
                   @input="currentPage = 1"
                   placeholder="Cari nama lapangan (Court 1, 2) / kode..." 
                   class="w-full pl-10 pr-8 py-2.5 rounded-xl border border-[#DFC387] text-xs focus:ring-1 focus:ring-[#D4AF37] focus:outline-none bg-[#FAF8F2] text-[#1F170D] placeholder-[#8C7A58]/70 shadow-sm">
            <button type="button" 
                    x-show="searchQuery" 
                    @click="searchQuery = ''; currentPage = 1" 
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#8C7A58] hover:text-rose-600 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- Row 2: Date Picker Filter & Reset Button -->
        <div class="flex items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <span class="text-[11px] font-bold text-[#7A5818] uppercase tracking-wider shrink-0 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>Tgl:</span>
                </span>
                <input type="date" 
                       x-model="searchDate" 
                       @change="currentPage = 1"
                       title="Filter tanggal reservasi"
                       class="flex-1 min-w-0 px-2.5 py-1.5 rounded-xl border border-[#DFC387] text-xs focus:ring-1 focus:ring-[#D4AF37] focus:outline-none bg-[#FAF8F2] text-[#1F170D] shadow-sm">
            </div>

            <button type="button" 
                    x-show="searchQuery || searchDate" 
                    @click="resetFilters()" 
                    class="px-2.5 py-1.5 rounded-xl border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 text-[11px] font-bold transition-all shrink-0 flex items-center gap-1 shadow-sm">
                <span>Reset</span>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    </div>

    <!-- Empty State: Belum Ada Riwayat -->
    <div x-show="pastBookings.length === 0" class="text-xs text-[#8C7A58] italic py-3 text-center">
        Belum ada riwayat booking sebelumnya.
    </div>

    <!-- Empty State: Hasil Pencarian Tidak Ditemukan -->
    <div x-show="pastBookings.length > 0 && filteredPastBookings.length === 0" class="text-center py-6 space-y-2 bg-[#FAF8F2] rounded-2xl border border-dashed border-[#DFC387]">
        <div class="text-xs text-[#7A643E]">Tidak ditemukan riwayat booking yang sesuai filter.</div>
        <button type="button" @click="resetFilters()" class="px-3 py-1 text-xs font-bold bg-white border border-[#DFC387] rounded-xl text-[#7A5818] hover:bg-[#FAF2DE] transition-colors">
            Reset Pencarian
        </button>
    </div>

    <!-- Static Height Paginated Booking List (Tanpa Scroll Panjang, Maksimal 3 Item Per Halaman) -->
    <div x-show="paginatedBookings.length > 0" class="space-y-2.5 text-xs">
        <template x-for="item in paginatedBookings" :key="item.id">
            <a :href="'{{ route('customer.invoice') }}?booking_id=' + item.id"
               class="p-3 rounded-2xl bg-[#FAF8F2] hover:bg-[#FAF2DE] border border-[#DFC387]/70 flex items-center justify-between transition-colors block group">
                <div>
                    <div class="font-bold text-[#1F170D] group-hover:text-[#8C6418] transition-colors" x-text="item.court ? item.court.name : 'Court Arena'"></div>
                    <div class="text-[10px] text-[#7A643E]" x-text="formatDate(item.booking_date) + ' &bull; #' + (item.booking_code || item.id.substring(0, 8))"></div>
                </div>
                <div class="text-right">
                    <div class="font-mono font-bold text-[#1F170D] whitespace-nowrap" x-text="'Rp ' + formatNumber(item.total_amount)"></div>
                    <span :class="item.status === 'PAID' || item.status === 'CHECKED_IN' ? 'text-emerald-700' : 'text-amber-700'"
                          class="text-[9px] font-bold uppercase"
                          x-text="item.status">
                    </span>
                </div>
            </a>
        </template>
    </div>

    <!-- Pagination Footer Navigation -->
    <div x-show="totalPages > 1 && paginatedBookings.length > 0" 
         class="flex items-center justify-between pt-2 border-t border-[#DFC387]/40 text-xs">
        <button type="button" 
                @click="prevPage()" 
                :disabled="currentPage === 1"
                class="px-3 py-1.5 rounded-xl border border-[#DFC387] text-[#7A5818] font-bold text-[11px] hover:bg-[#FAF2DE] disabled:opacity-40 disabled:cursor-not-allowed transition-all flex items-center gap-1 bg-white">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span>Prev</span>
        </button>

        <div class="flex items-center gap-1 font-mono text-[11px] text-[#7A643E]">
            <span>Hal</span>
            <span class="font-bold text-[#1F170D]" x-text="currentPage"></span>
            <span>/</span>
            <span class="font-bold text-[#1F170D]" x-text="totalPages"></span>
        </div>

        <button type="button" 
                @click="nextPage()" 
                :disabled="currentPage === totalPages"
                class="px-3 py-1.5 rounded-xl border border-[#DFC387] text-[#7A5818] font-bold text-[11px] hover:bg-[#FAF2DE] disabled:opacity-40 disabled:cursor-not-allowed transition-all flex items-center gap-1 bg-white">
            <span>Next</span>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>
</div>

<!-- Need Help & Support -->
<div class="p-4 sm:p-5 rounded-3xl bg-gradient-to-r from-[#FAF2DE] via-[#F5E6BE] to-[#FAF2DE] border border-[#DFC387] flex items-center justify-between gap-4">
    <div>
        <h4 class="font-serif font-black text-sm text-[#1F170D]">Butuh Bantuan Check-In?</h4>
        <p class="text-xs text-[#7A643E]">Frontdesk concierge kami siap membantu 24/7 di turnstile gate.</p>
    </div>
    <a href="https://wa.me/6281261617233" target="_blank" 
        class="px-4 py-2 rounded-xl bg-[#1E3327] text-[#FAF5E6] text-xs font-bold shrink-0 hover:bg-[#15241B] transition-colors">
        Bantuan
    </a>
</div>
