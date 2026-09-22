<x-app-layout>
    <div x-data="dashboardApp()" x-init="init()" class="py-6 sm:py-8 text-[#1F170D]">
        <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16 space-y-6">

            <!-- Desktop & Mobile Responsive Multi-Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- MAIN CONTENT (Cols 1 to 8 on Desktop) -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- 1. Hero Promo Banner: Executive Luxury Card -->
                    <div class="relative overflow-hidden rounded-3xl p-6 sm:p-8 text-white shadow-[0_15px_40px_rgba(20,40,30,0.25)] border border-[#DFC387]/60"
                         style="background: linear-gradient(135deg, #162E24 0%, #0F2019 60%, #0A1611 100%);">
                        <!-- Background Glow Accents -->
                        <div class="absolute -right-12 -bottom-12 w-96 h-96 rounded-full bg-[#D4AF37]/20 blur-3xl pointer-events-none"></div>
                        <div class="absolute top-0 right-1/4 w-72 h-72 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>

                        <div class="relative z-10 flex flex-col xl:flex-row xl:items-center justify-between gap-6">
                            <div class="max-w-2xl">
                                <div class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-[#DFC387]/20 text-[#F5E6BE] border border-[#DFC387]/40 mb-3 backdrop-blur-md shadow-sm">
                                    <span>Member Portal &bull; Club 61 Experience</span>
                                </div>
                                <h1 class="font-serif text-2xl sm:text-4xl font-extrabold tracking-tight leading-tight text-[#FAF5E6]">
                                    CLUB 61 <span class="text-[#E5C378] font-alex font-normal text-3xl sm:text-5xl block sm:inline">Padel Court</span>
                                </h1>
                                <p class="text-xs sm:text-sm text-emerald-100/80 mt-2.5 font-medium leading-relaxed">
                                    Welcome back, <span class="font-bold text-white">{{ Auth::user()->name }}</span>. Book WPT-standard panoramic padel courts at Indosat Building Medan, enjoy cedarwood sauna &amp; 4°C ice bath, and specialty cafe lounge.
                                </p>

                                <div class="mt-6 flex flex-wrap items-center gap-3">
                                    <a href="{{ route('customer.booking') }}" 
                                       class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl text-xs font-black uppercase tracking-wider transition-all duration-150 transform active:scale-95 shadow-lg"
                                       style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 50%, #A87D18 100%); color: #1E160A; border: 1.5px solid #FFF3CD;">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        + Book Padel Court
                                    </a>
                                    <a href="{{ route('customer.my-club') }}" 
                                       class="inline-flex items-center gap-1.5 px-5 py-3 rounded-2xl text-xs font-bold text-[#F5E6BE] bg-white/10 hover:bg-white/20 border border-white/20 backdrop-blur-md transition-colors">
                                        Explore Facilities &rarr;
                                    </a>
                                </div>
                            </div>

                            <!-- Quick Stats Highlight Box on Wide Screens -->
                            <div class="hidden sm:grid grid-cols-2 xl:grid-cols-1 gap-3 shrink-0 xl:w-56">
                                <div class="p-3.5 rounded-2xl bg-white/10 border border-white/15 backdrop-blur-md">
                                    <div class="text-[10px] uppercase font-bold text-emerald-200">Today's Availability</div>
                                    <div class="text-sm font-black text-white mt-0.5 font-serif">3 Active Courts</div>
                                    <div class="text-[10px] text-emerald-300/80 font-mono mt-0.5">Central AC &bull; 1000 Lux</div>
                                </div>
                                <div class="p-3.5 rounded-2xl bg-[#D4AF37]/20 border border-[#D4AF37]/40 backdrop-blur-md">
                                    <div class="text-[10px] uppercase font-bold text-[#F5E6BE]">Wellness Suite</div>
                                    <div class="text-sm font-black text-[#FAF5E6] mt-0.5 font-serif">Sauna &amp; Ice Bath 4°C</div>
                                    <div class="text-[10px] text-[#E5C378] font-mono mt-0.5">Complimentary for VIP Platinum</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Quick Action Cards -->
                    <div>
                        <div class="flex items-center justify-between mb-3 px-1">
                            <h3 class="font-serif font-extrabold text-sm text-[#5C410F] uppercase tracking-wider">Quick Facility Access</h3>
                            <span class="text-[11px] text-[#8C7A58] font-medium">Club 61 Concierge</span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                            <!-- Action 1: Booking -->
                            <a href="{{ route('customer.booking') }}" 
                               class="flex flex-col items-center justify-center p-4 sm:p-5 rounded-2xl bg-white/95 backdrop-blur-xl border border-[#DFC387]/80 hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.25)] transition-all group active:scale-95">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-sm transition-transform group-hover:scale-110"
                                     style="background: linear-gradient(135deg, #FAF2DE 0%, #F3DFAD 100%); border: 1.5px solid #DFC387;">
                                    <svg class="w-6 h-6 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <span class="text-xs font-extrabold text-[#3B2B11] mt-2.5 group-hover:text-[#8C6418]">Book Court</span>
                                <span class="text-[10px] text-[#8C7A58] mt-0.5">3 Pro Courts</span>
                            </a>

                            <!-- Action 2: Club -->
                            <a href="{{ route('customer.my-club') }}" 
                               class="flex flex-col items-center justify-center p-4 sm:p-5 rounded-2xl bg-white/95 backdrop-blur-xl border border-[#DFC387]/80 hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.25)] transition-all group active:scale-95">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-sm transition-transform group-hover:scale-110"
                                     style="background: linear-gradient(135deg, #FAF2DE 0%, #F3DFAD 100%); border: 1.5px solid #DFC387;">
                                    <svg class="w-6 h-6 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <span class="text-xs font-extrabold text-[#3B2B11] mt-2.5 group-hover:text-[#8C6418]">My Club</span>
                                <span class="text-[10px] text-[#8C7A58] mt-0.5">Venue &amp; Location</span>
                            </a>

                            <!-- Action 3: Value Pack -->
                            <button type="button"
                                 @click="showNotice('Value Pack Passes', 'Buy 10 Hours of Padel and receive 2 Hours complimentary Sauna & Ice Bath! Contact Concierge on WhatsApp at 0812-6161-PADEL.', 'info', 'Contact WhatsApp', () => window.open('https://wa.me/6281261617233?text=Hello%20Club%2061,%20I%20am%20interested%20in%20Value%20Pack', '_blank'))"
                                 class="flex flex-col items-center justify-center p-4 sm:p-5 rounded-2xl bg-white/95 backdrop-blur-xl border border-[#DFC387]/80 hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.25)] transition-all group cursor-pointer active:scale-95">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-sm transition-transform group-hover:scale-110"
                                     style="background: linear-gradient(135deg, #FAF2DE 0%, #F3DFAD 100%); border: 1.5px solid #DFC387;">
                                    <svg class="w-6 h-6 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <span class="text-xs font-extrabold text-[#3B2B11] mt-2.5 group-hover:text-[#8C6418]">Value Pack</span>
                                <span class="text-[10px] text-[#8C7A58] mt-0.5">Hourly Pass</span>
                            </button>

                            <!-- Action 4: Turnamen -->
                            <button type="button"
                                 @click="showNotice('Upcoming Tournament', 'CLUB 61 Padel Championship 2026! Prize pool Rp 50.000.000. Registration open for members.', 'info', 'Inquire Concierge', () => window.open('https://wa.me/6281261617233?text=Hello%20Club%2061,%20I%20want%20to%20register%20for%20Tournament', '_blank'))"
                                 class="flex flex-col items-center justify-center p-4 sm:p-5 rounded-2xl bg-white/95 backdrop-blur-xl border border-[#DFC387]/80 hover:border-[#D4AF37] hover:shadow-[0_10px_25px_rgba(212,175,55,0.25)] transition-all group cursor-pointer active:scale-95">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-sm transition-transform group-hover:scale-110"
                                     style="background: linear-gradient(135deg, #FAF2DE 0%, #F3DFAD 100%); border: 1.5px solid #DFC387;">
                                    <svg class="w-6 h-6 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                    </svg>
                                </div>
                                <span class="text-xs font-extrabold text-[#3B2B11] mt-2.5 group-hover:text-[#8C6418]">Tournaments</span>
                                <span class="text-[10px] text-[#8C7A58] mt-0.5">Club Competitions</span>
                            </button>
                        </div>
                    </div>

                    <!-- 3. Active Upcoming Match Card (Connected to Real Database) -->
                    <template x-if="activeMatch">
                        <div class="p-5 sm:p-6 rounded-3xl bg-white/95 backdrop-blur-xl border-2 border-[#D4AF37] shadow-[0_12px_35px_rgba(160,120,30,0.18)] flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <div class="w-14 h-14 rounded-2xl flex items-center justify-center p-2 shadow-inner shrink-0"
                                     style="background: linear-gradient(145deg, #FBF6EB 0%, #EBD5A4 60%, #D4AF37 100%); border: 1.5px solid #BD923E;">
                                    <img src="{{ asset('images/club61-logo.png') }}" alt="Club 61" class="w-full h-full object-contain">
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-serif font-black text-base sm:text-lg text-[#1F170D]" x-text="activeMatch.court ? activeMatch.court.name : 'Court Arena'"></span>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300 uppercase" x-text="activeMatch.status">CONFIRMED</span>
                                    </div>
                                    <p class="text-xs text-[#7A643E] font-medium mt-1">
                                        <span x-text="formatDate(activeMatch.booking_date)"></span> &bull; <span x-text="formatTime(activeMatch.start_time) + ' - ' + formatTime(activeMatch.end_time)"></span> WIB &bull; Code: <span class="font-mono font-bold text-[#8C6418]" x-text="'#' + (activeMatch.booking_code || activeMatch.id.substring(0, 10))"></span>
                                    </p>
                                </div>
                            </div>

                            <a :href="'{{ route('customer.invoice') }}?booking_id=' + activeMatch.id" 
                               class="w-full sm:w-auto px-6 py-3 rounded-2xl text-center text-xs font-extrabold text-[#7A5818] bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] transition-colors shrink-0 shadow-sm">
                                View E-Ticket &rarr;
                            </a>
                        </div>
                    </template>

                    <!-- Empty State for Active Match -->
                    <template x-if="!activeMatch && !isLoadingBookings">
                        <div class="p-5 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3.5">
                                <div class="w-12 h-12 rounded-2xl bg-[#FAF8F2] border border-[#DFC387] flex items-center justify-center shrink-0">
                                    <svg class="w-6 h-6 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-serif font-black text-sm text-[#1F170D]">No Active Match Tickets</h4>
                                    <p class="text-xs text-[#7A643E]">Book your favorite padel court today before slots fill up.</p>
                                </div>
                            </div>
                            <a href="{{ route('customer.booking') }}" 
                               class="px-4 py-2 rounded-xl bg-[#1E3327] hover:bg-[#15241B] text-[#FAF5E6] text-xs font-bold shrink-0 transition-colors">
                                Book Now
                            </a>
                        </div>
                    </template>

                    <!-- 4. Riwayat Booking Terkini Table Card (Connected to Real Database) -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-serif font-black text-base text-[#1F170D]">Match &amp; Session Activity History</h3>
                                <p class="text-xs text-[#7A643E]">Your official padel court and wellness booking records.</p>
                            </div>
                            <a href="{{ route('customer.invoice') }}" class="text-xs font-bold text-[#8C6418] hover:underline">
                                All History &rarr;
                            </a>
                        </div>

                        <!-- Empty State Table -->
                        <div x-show="bookings.length === 0 && !isLoadingBookings" class="text-xs text-[#8C7A58] italic py-8 text-center">
                            No booking history yet. Reserve your first court session today!
                        </div>

                        <div x-show="bookings.length > 0" class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-[#3B2B11]">
                                <thead class="text-[#5C410F] uppercase text-[10px] tracking-wider"
                                       style="background: linear-gradient(90deg, #FBF6EB 0%, #EEDBB0 100%); border-bottom: 1.5px solid #DFC387;">
                                    <tr>
                                        <th class="p-3.5 rounded-l-xl">Booking ID</th>
                                        <th class="p-3.5">Facility / Court</th>
                                        <th class="p-3.5">Session Schedule</th>
                                        <th class="p-3.5">Fee</th>
                                        <th class="p-3.5 rounded-r-xl">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#EEDBB0]/60">
                                    <template x-for="item in bookings" :key="item.id">
                                        <tr class="hover:bg-amber-50/40 transition-colors">
                                            <td class="p-3.5 font-mono font-bold text-[#8C6418]">
                                                <a :href="'{{ route('customer.invoice') }}?booking_id=' + item.id" class="hover:underline" x-text="'#' + (item.booking_code || item.id.substring(0, 8))"></a>
                                            </td>
                                            <td class="p-3.5 font-bold text-[#1F170D]" x-text="item.court ? item.court.name : 'Court Arena'"></td>
                                            <td class="p-3.5 text-[#523F1C]" x-text="formatDate(item.booking_date) + ' • ' + formatTime(item.start_time) + ' - ' + formatTime(item.end_time) + ' WIB'"></td>
                                            <td class="p-3.5 font-mono font-bold text-[#1F170D]" x-text="'Rp ' + formatNumber(item.total_amount)"></td>
                                            <td class="p-3.5">
                                                <span :class="item.status === 'PAID' || item.status === 'CONFIRMED' ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-amber-100 text-amber-800 border-amber-300'"
                                                      class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold uppercase border"
                                                      x-text="item.status">
                                                </span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- SIDEBAR COLUMN (Cols 9 to 12 on Desktop) -->
                <div class="lg:col-span-4 space-y-6">

                    <!-- Member Profile & VIP Card -->
                    <div class="p-6 rounded-3xl bg-white/95 backdrop-blur-xl border-2 border-[#D4AF37] shadow-[0_15px_40px_rgba(160,120,30,0.18)] text-center relative overflow-hidden">
                        <div class="absolute -right-8 -top-8 w-28 h-28 bg-[#D4AF37]/15 rounded-full blur-xl pointer-events-none"></div>

                        <div class="w-20 h-20 mx-auto rounded-full p-1 bg-gradient-to-tr from-[#D4AF37] via-[#FFF3CD] to-[#8C6418] shadow-md">
                            <div class="w-full h-full rounded-full bg-[#FAF2DE] flex items-center justify-center text-2xl font-black text-[#7A5818] font-serif border border-white">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </div>
                        </div>

                        @php
                            $activeUserMembership = \App\Models\Membership\UserMembership::with(['plan', 'balances'])
                                ->where('user_id', Auth::id())
                                ->where('status', 'ACTIVE')
                                ->where(function ($q) {
                                    $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
                                })
                                ->latest('start_date')
                                ->first();
                        @endphp

                        <h2 class="font-serif font-black text-lg text-[#1F170D] mt-3">{{ Auth::user()->name }}</h2>

                        @if($activeUserMembership)
                            <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-[#FAF2DE] text-[#7A5818] border border-[#DFC387] mt-1 shadow-sm">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>{{ $activeUserMembership->plan->name }}</span>
                            </div>
                            <div class="text-[11px] font-mono font-bold text-[#8C6418] mt-1">{{ $activeUserMembership->membership_code }}</div>

                            <!-- Facility Quotas -->
                            <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                                @foreach($activeUserMembership->balances as $bal)
                                    <div class="p-2.5 rounded-xl bg-[#FAF8F2] border border-[#E8DCC0]">
                                        <span class="text-[9px] uppercase font-bold text-[#8C7A58] block">{{ $bal->facility }}</span>
                                        <span class="font-serif font-black text-xs text-[#1F170D]">
                                            @if($bal->quota_type === 'HOURS')
                                                {{ (float)$bal->remaining_quota }} Jam
                                            @elseif($bal->quota_type === 'VISITS')
                                                {{ $bal->initial_quota ? ((float)$bal->remaining_quota . ' Sesi') : 'Unlimited' }}
                                            @else
                                                Diskon {{ $bal->discount_percent }}%
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>

                            <div class="grid grid-cols-2 gap-2 mt-2 text-left">
                                <div class="p-2.5 rounded-xl bg-[#FAF8F2] border border-[#E8DCC0]">
                                    <span class="text-[9px] uppercase font-bold text-[#8C7A58] block">Status</span>
                                    <span class="font-serif font-black text-xs text-emerald-700">Aktif</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-[#FAF8F2] border border-[#E8DCC0]">
                                    <span class="text-[9px] uppercase font-bold text-[#8C7A58] block">Valid Until</span>
                                    <span class="font-serif font-black text-xs text-[#1F170D]">{{ $activeUserMembership->end_date ? \Carbon\Carbon::parse($activeUserMembership->end_date)->format('d M Y') : 'Lifetime' }}</span>
                                </div>
                            </div>
                        @else
                            <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-gray-100 text-gray-700 border border-gray-300 mt-1 shadow-sm">
                                <span>Regular Member</span>
                            </div>
                            <div class="p-3 rounded-xl bg-[#FAF8F2] border border-[#E8DCC0] mt-4 text-xs text-[#8C7A58]">
                                Dapatkan akses VIP, kuota jam bermain padel, gym harian, dan sauna eksklusif dengan paket membership Club 61.
                            </div>
                        @endif

                        <div class="mt-5 pt-4 border-t border-[#DFC387]/60">
                            <a href="{{ route('profile.edit') }}" class="text-xs font-bold text-[#8C6418] hover:text-[#5C410F] transition-colors flex items-center justify-center gap-1">
                                <span>Manage Profile Settings</span>
                                <span>&rarr;</span>
                            </a>
                        </div>
                    </div>

                    <!-- Membership Promo Card -->
                    <div class="relative overflow-hidden rounded-3xl p-6 text-white shadow-lg border border-[#DFC387]/70"
                         style="background: linear-gradient(135deg, #1F382B 0%, #15271E 100%);">
                        <div class="relative z-10 space-y-3">
                            <div class="inline-block px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-[#E5FF44]/20 text-[#E5FF44] border border-[#E5FF44]/40">
                                VIP Privilege
                            </div>
                            <h3 class="font-serif text-lg font-black text-white leading-tight">
                                Upgrade to Diamond Club
                            </h3>
                            <p class="text-xs text-emerald-100/80 leading-relaxed font-medium">
                                Enjoy 7-day advance booking priority, 25% court rental discount, and unlimited weekly sauna &amp; cold plunge access!
                            </p>
                            <button type="button" 
                                    @click="showNotice('VIP Membership Upgrade', 'To upgrade to Diamond Club VIP Membership, please visit our Frontdesk Concierge or contact via WhatsApp at 0812-6161-PADEL.', 'info', 'Inquire Concierge', () => window.open('https://wa.me/6281261617233?text=Hello%20Club%2061,%20I%20want%20to%20upgrade%20to%20Diamond%20Club', '_blank'))"
                                    class="w-full py-3 rounded-xl text-xs font-black uppercase tracking-wider text-[#1E160A] shadow-md hover:brightness-105 active:scale-95 transition-all cursor-pointer"
                                    style="background: linear-gradient(180deg, #FBF0CE 0%, #D4AF37 60%, #B38622 100%); border: 1px solid #FFF3CD;">
                                Join Membership Now
                            </button>
                        </div>
                    </div>

                    <!-- Venue Operating Info & Concierge -->
                    <div class="p-5 rounded-3xl bg-white/95 backdrop-blur-xl border border-[#DFC387] shadow-sm space-y-3 text-xs">
                        <div class="font-serif font-black text-sm text-[#1F170D] flex items-center gap-2">
                            <span>Club 61 Padel Court Medan</span>
                        </div>
                        <div class="text-[#7A643E] space-y-1.5 leading-relaxed">
                            <div><strong class="text-[#3B2B11]">Address:</strong> Gedung Indosat, Jl. Perintis Kemerdekaan No. 39, Medan, North Sumatra</div>
                            <div><strong class="text-[#3B2B11]">Tagline:</strong> Play. Compete. Connect.</div>
                            <div><strong class="text-[#3B2B11]">Hours:</strong> Daily &bull; 06:00 - 23:00 WIB</div>
                            <div><strong class="text-[#3B2B11]">WhatsApp Concierge:</strong> 0812-6161-PADEL</div>
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <!-- CUSTOM LUXURY NOTICE MODAL -->
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
                
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto shadow-sm bg-[#FAF2DE] border border-[#DFC387] text-[#8C6418]">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
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
        function dashboardApp() {
            return {
                bookings: [],
                activeMatch: null,
                isLoadingBookings: true,
                noticeModal: {
                    show: false,
                    title: '',
                    message: '',
                    type: 'info',
                    buttonText: 'Got It',
                    onClose: null
                },

                showNotice(title, message, type = 'info', buttonText = 'Got It', onClose = null) {
                    this.noticeModal = {
                        show: true,
                        title,
                        message,
                        type,
                        buttonText,
                        onClose
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

                async init() {
                    await this.loadMyBookings();
                },

                async loadMyBookings() {
                    this.isLoadingBookings = true;
                    try {
                        const res = await fetch('/api/v1/padel/my-bookings');
                        const json = await res.json();
                        if (json.success && json.data) {
                            this.bookings = json.data;

                            // Find upcoming active match with PAID or CONFIRMED status
                            this.activeMatch = this.bookings.find(b => b.status === 'PAID' || b.status === 'CONFIRMED');
                        }
                    } catch(e) {
                        console.error('Failed to load dashboard data:', e);
                    } finally {
                        this.isLoadingBookings = false;
                    }
                },

                get totalMatchCount() {
                    return this.bookings.filter(b => b.status === 'PAID' || b.status === 'CONFIRMED' || b.status === 'COMPLETED').length;
                },

                formatNumber(val) {
                    if (!val) return '0';
                    return Math.round(val).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                },

                formatTime(isoString) {
                    if (!isoString) return '--:--';
                    try {
                        const d = new Date(isoString);
                        if (!isNaN(d.getTime())) {
                            return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
                        }
                    } catch(e) {}
                    return isoString.substring(11, 16) || isoString;
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
                }
            }
        }
    </script>
</x-app-layout>