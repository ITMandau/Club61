@php
    $myCorporateMemberForDashboard = \App\Models\Sponsor\SponsorOrganizationMember::where('user_id', Auth::id())
        ->where('status', 'ACTIVE')
        // Organisasi Ditangguhkan → voucher tidak bisa dipakai, jadi tidak dipromosikan di Home.
        ->whereHas('organization', fn ($q) => $q->where('status', \App\Models\Sponsor\SponsorOrganization::STATUS_ACTIVE))
        ->with(['organization.userMembership.plan', 'vouchers' => fn ($q) => $q->whereNull('acknowledged_at')->where('expires_at', '>', now())->orderBy('expires_at')])
        ->first();
    $unacknowledgedVouchers = $myCorporateMemberForDashboard ? $myCorporateMemberForDashboard->vouchers : collect();
    $activeCourtCount = \App\Models\Padel\PadelCourt::where('is_active', true)->count();
    $activeUserMembership = \App\Models\Membership\UserMembership::with(['plan', 'balances'])
        ->where('user_id', Auth::id())
        ->where('status', 'ACTIVE')
        ->where(function ($q) {
            $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
        })
        ->latest('start_date')
        ->first();
@endphp
<x-app-layout>
    @include('customer.partials.bk-style')

    <div x-data="dashboardApp(@js($unacknowledgedVouchers->map(fn ($v) => [
            'id' => $v->id,
            'hours' => (float) $v->remainingHours(),
            'expires_at' => $v->expires_at->translatedFormat('d M Y'),
            'organization' => $myCorporateMemberForDashboard->organization->name ?? __('dashboard.organization_fallback'),
            'plan_name' => $myCorporateMemberForDashboard->organization->userMembership->plan->name ?? __('dashboard.plan_fallback'),
        ])->values()))" x-init="init()" class="text-[#4F2F2A] pb-8">
        <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16 pt-4 sm:pt-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-5 items-start">

                {{-- KONTEN UTAMA --}}
                <div class="lg:col-span-8 space-y-4 sm:space-y-5">

                    {{-- 1. Hero --}}
                    <div class="relative overflow-hidden rounded-xl p-5 sm:p-8 text-white shadow-[0_15px_40px_rgba(20,40,30,0.25)] border border-[#E6DAC0]/60"
                         style="background: #662721;">
                        <div class="absolute -right-12 -bottom-12 w-80 h-80 rounded-full bg-[#662721]/20 blur-3xl pointer-events-none"></div>

                        <div class="relative z-10 flex flex-col xl:flex-row xl:items-center justify-between gap-5">
                            <div class="max-w-2xl min-w-0">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-[#E6DAC0]/20 text-[#F7F0DB] border border-[#E6DAC0]/40 mb-3">{{ __('dashboard.member_portal') }}</span>
                                <h1 class="font-display text-3xl sm:text-4xl font-extrabold tracking-tight leading-none text-[#F7F0DB]">
                                    CLUB 61 <span class="text-[#E6DAC0] font-alex font-normal text-4xl sm:text-5xl block">Padel &amp; Wellness</span>
                                </h1>
                                <p class="text-xs sm:text-sm text-emerald-100/85 mt-3 leading-relaxed">
                                    {{ __('dashboard.welcome_before') }} <span class="font-bold text-white">{{ Auth::user()->name }}</span>. {{ __('dashboard.welcome_after', ['address' => \App\Models\Setting\CompanyProfileSetting::receiptAddress()]) }}
                                </p>

                                <div class="mt-5 grid grid-cols-1 sm:flex sm:flex-wrap items-center gap-2.5">
                                    <a href="{{ route('customer.booking') }}"
                                       class="bk-tap inline-flex items-center justify-center gap-2 h-12 px-6 rounded-lg text-xs font-black uppercase tracking-wider whitespace-nowrap bg-[#F7F0DB] hover:bg-white text-[#4F2F2A] shadow-lg active:scale-95 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        {{ __('dashboard.book_court') }}
                                    </a>
                                    <a href="{{ route('customer.my-club') }}"
                                       class="bk-tap inline-flex items-center justify-center gap-1.5 h-12 px-5 rounded-lg text-xs font-bold whitespace-nowrap text-[#F7F0DB] bg-white/10 hover:bg-white/20 border border-white/20 transition-colors">
                                        {{ __('dashboard.explore') }}
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                                    </a>
                                </div>
                            </div>

                            <div class="hidden sm:grid grid-cols-2 xl:grid-cols-1 gap-3 shrink-0 xl:w-56">
                                <div class="p-3.5 rounded-lg bg-white/10 border border-white/15">
                                    <div class="text-[10px] uppercase font-bold text-[#F7F0DB]/70">{{ __('dashboard.courts') }}</div>
                                    <div class="text-sm font-black text-white mt-0.5">{{ trans_choice('dashboard.courts_open', $activeCourtCount, ['count' => $activeCourtCount]) }}</div>
                                    <div class="text-[10px] text-[#F7F0DB]/60 mt-0.5 line-clamp-2">{{ \App\Models\Setting\CompanyProfileSetting::receiptAddress() }}</div>
                                </div>
                                <div class="p-3.5 rounded-lg bg-[#662721]/20 border border-[#662721]/40">
                                    <div class="text-[10px] uppercase font-bold text-[#F7F0DB]">{{ __('dashboard.wellness') }}</div>
                                    <div class="text-sm font-black text-[#F7F0DB] mt-0.5">{{ __('dashboard.wellness_room') }}</div>
                                    <div class="text-[10px] text-[#E6DAC0] mt-0.5">{{ __('dashboard.after_match') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 2. Akses cepat --}}
                    <section>
                        <div class="px-1 mb-2.5 text-[11px] font-extrabold uppercase tracking-wider text-[#662721]">{{ __('dashboard.quick_access') }}</div>
                        <div class="grid grid-cols-2 {{ $myCorporateMemberForDashboard ? 'sm:grid-cols-5' : 'sm:grid-cols-4' }} gap-2.5 sm:gap-3">
                            @php
                                $tileClass = 'bk-tap group relative flex items-center sm:flex-col sm:justify-center gap-3 sm:gap-2 p-3 sm:p-4 rounded-lg bg-white/90 border border-[#E6DAC0] hover:border-[#662721] hover:shadow-[0_10px_25px_rgba(102,39,33,0.2)] transition-all active:scale-[0.97] text-left sm:text-center';
                                $iconClass = 'w-10 h-10 sm:w-11 sm:h-11 shrink-0 rounded-lg flex items-center justify-center bg-gradient-to-br from-[#F7F0DB] to-[#F7F0DB] border border-[#E6DAC0] text-[#662721] transition-transform group-hover:scale-105';
                            @endphp
                            @if($myCorporateMemberForDashboard)
                                <a href="{{ route('profile.vouchers') }}" class="{{ $tileClass }}">
                                    @if($unacknowledgedVouchers->isNotEmpty())
                                        <span class="absolute top-2 right-2 w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                                    @endif
                                    <span class="{{ $iconClass }}"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2zM9 7h1" /></svg></span>
                                    <span class="min-w-0">
                                        <span class="block text-xs font-extrabold text-[#4F2F2A] truncate">{{ __('nav.my_vouchers') }}</span>
                                        <span class="block text-[10px] text-[#7A5A52] truncate">{{ __('dashboard.hours_active', ['hours' => number_format($myCorporateMemberForDashboard->totalRemainingHours(), 1)]) }}</span>
                                    </span>
                                </a>
                            @endif
                            <a href="{{ route('customer.booking') }}" class="{{ $tileClass }}">
                                <span class="{{ $iconClass }}"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg></span>
                                <span class="min-w-0">
                                    <span class="block text-xs font-extrabold text-[#4F2F2A] truncate">{{ __('nav.book_court') }}</span>
                                    <span class="block text-[10px] text-[#7A5A52] truncate">{{ trans_choice('dashboard.court_count', $activeCourtCount, ['count' => $activeCourtCount]) }}</span>
                                </span>
                            </a>
                            <a href="{{ route('customer.my-club') }}" class="{{ $tileClass }}">
                                <span class="{{ $iconClass }}"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg></span>
                                <span class="min-w-0">
                                    <span class="block text-xs font-extrabold text-[#4F2F2A] truncate">{{ __('nav.my_club') }}</span>
                                    <span class="block text-[10px] text-[#7A5A52] truncate">{{ __('dashboard.my_club_sub') }}</span>
                                </span>
                            </a>
                            {{-- Value Pack & Tournaments belum ada modulnya — dulu popup promo/turnamen karangan, sekarang "Coming soon". --}}
                            <button type="button" data-coming-soon="value-pack"
                                    @click="showNotice(@js(__('dashboard.value_pack_notice_title'), JSON_UNESCAPED_UNICODE), @js(__('dashboard.value_pack_notice_body'), JSON_UNESCAPED_UNICODE), 'info')"
                                    class="{{ $tileClass }}">
                                <span class="{{ $iconClass }}"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></span>
                                <span class="min-w-0">
                                    <span class="block text-xs font-extrabold text-[#4F2F2A] truncate">{{ __('dashboard.value_pack') }}</span>
                                    <span class="inline-block mt-0.5 px-1.5 py-px rounded-full text-[9px] font-extrabold uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-200 whitespace-nowrap">{{ __('dashboard.coming_soon') }}</span>
                                </span>
                            </button>
                            <button type="button" data-coming-soon="tournaments"
                                    @click="showNotice(@js(__('dashboard.tournaments_notice_title'), JSON_UNESCAPED_UNICODE), @js(__('dashboard.tournaments_notice_body'), JSON_UNESCAPED_UNICODE), 'info')"
                                    class="{{ $tileClass }}">
                                <span class="{{ $iconClass }}"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg></span>
                                <span class="min-w-0">
                                    <span class="block text-xs font-extrabold text-[#4F2F2A] truncate">{{ __('dashboard.tournaments') }}</span>
                                    <span class="inline-block mt-0.5 px-1.5 py-px rounded-full text-[9px] font-extrabold uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-200 whitespace-nowrap">{{ __('dashboard.coming_soon') }}</span>
                                </span>
                            </button>
                        </div>
                    </section>

                    {{-- 3. Jadwal main berikutnya (data asli) --}}
                    <template x-if="isLoadingBookings">
                        <div class="bk-skeleton h-28 rounded-xl"></div>
                    </template>
                    <template x-if="activeMatch">
                        <section class="bk-fade-up rounded-xl bg-white/95 border border-[#662721] shadow-[0_12px_35px_rgba(79,47,42,0.16)] p-4 sm:p-5">
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <div class="text-[11px] font-extrabold uppercase tracking-wider text-[#662721]">{{ __('dashboard.next_match') }}</div>
                                <span class="shrink-0 px-2.5 py-0.5 rounded-full text-[11px] font-bold border whitespace-nowrap" :class="statusPillClass(activeMatch.status)" x-text="statusLabel(activeMatch.status, activeMatch)"></span>
                            </div>
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <div class="w-14 h-14 shrink-0 rounded-lg bg-[#662721] text-[#F7F0DB] flex flex-col items-center justify-center leading-none">
                                        <span class="text-[10px] font-bold uppercase opacity-80" x-text="formatMonth(activeMatch.start_time)"></span>
                                        <span class="text-xl font-black mt-0.5" x-text="formatDay(activeMatch.start_time)"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-extrabold text-sm sm:text-base leading-snug line-clamp-2" x-text="activeMatch.court ? activeMatch.court.name : i18n.court"></div>
                                        <div class="mt-0.5 text-xs text-[#7A5A52] font-semibold tabular-nums whitespace-nowrap" x-text="formatTime(activeMatch.start_time) + '–' + formatTime(activeMatch.end_time) + ' WIB'"></div>
                                        <div class="text-[11px] font-mono font-bold text-[#662721] truncate" x-text="'#' + (activeMatch.booking_code || activeMatch.id.substring(0, 10))"></div>
                                    </div>
                                </div>
                                <a :href="'{{ route('customer.invoice') }}?booking_id=' + activeMatch.id"
                                   class="bk-tap shrink-0 h-11 px-5 rounded-lg flex items-center justify-center gap-1.5 text-xs font-bold whitespace-nowrap text-[#662721] bg-[#F7F0DB] hover:bg-[#F7F0DB] border border-[#E6DAC0] transition-colors">
                                    {{ __('dashboard.view_eticket') }}
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                                </a>
                            </div>
                        </section>
                    </template>
                    <template x-if="!activeMatch && !isLoadingBookings">
                        <section class="rounded-xl bg-white/90 border border-[#E6DAC0] p-4 flex items-center gap-3">
                            <div class="w-11 h-11 shrink-0 rounded-lg bg-[#F7F0DB] border border-[#E6DAC0] flex items-center justify-center text-[#662721]">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-sm leading-tight">{{ __('dashboard.no_match_title') }}</h4>
                                <p class="text-[11px] text-[#7A5A52] leading-snug mt-0.5">{{ __('dashboard.no_match_body') }}</p>
                            </div>
                            <a href="{{ route('customer.booking') }}" class="bk-tap shrink-0 h-10 px-4 rounded-xl bg-[#662721] hover:bg-[#4F2F2A] text-[#F7F0DB] text-xs font-bold whitespace-nowrap flex items-center transition-colors">
                                {{ __('dashboard.book_now') }}
                            </a>
                        </section>
                    </template>

                    {{-- 4. Riwayat booking terbaru (data asli) --}}
                    <section class="rounded-xl bg-white/90 border border-[#E6DAC0] shadow-[0_8px_30px_rgba(79,47,42,0.08)] p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <div class="min-w-0">
                                <div class="text-[11px] font-extrabold uppercase tracking-wider text-[#662721]">{{ __('dashboard.recent_bookings') }}</div>
                                <div class="text-[11px] text-[#7A5A52] truncate">{{ __('dashboard.recent_sub') }}</div>
                            </div>
                            <a href="{{ route('customer.invoice') }}" class="shrink-0 whitespace-nowrap text-xs font-bold text-[#662721] hover:underline flex items-center gap-1">
                                {{ __('dashboard.see_all') }}
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
                            </a>
                        </div>

                        <template x-if="isLoadingBookings">
                            <div class="space-y-2 pt-1"><template x-for="i in 3" :key="i"><div class="bk-skeleton h-14 rounded-lg"></div></template></div>
                        </template>

                        <div x-show="bookings.length === 0 && !isLoadingBookings" class="text-xs text-[#7A5A52] py-6 text-center">
                            {{ __('dashboard.no_bookings') }}
                        </div>

                        {{-- HP & tablet: daftar kartu --}}
                        <div x-show="bookings.length > 0" class="md:hidden divide-y divide-[#E6DAC0]">
                            <template x-for="item in recentBookings" :key="item.id">
                                <a :href="'{{ route('customer.invoice') }}?booking_id=' + item.id" class="bk-tap flex items-center gap-3 py-3">
                                    <div class="w-11 h-11 shrink-0 rounded-lg bg-[#F7F0DB] border border-[#E6DAC0] flex flex-col items-center justify-center leading-none text-[#662721]">
                                        <span class="text-[9px] font-bold uppercase" x-text="formatMonth(item.start_time)"></span>
                                        <span class="text-base font-black mt-0.5" x-text="formatDay(item.start_time)"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-bold truncate" x-text="item.court ? item.court.name : i18n.court"></div>
                                        <div class="text-[11px] text-[#7A5A52] tabular-nums whitespace-nowrap truncate" x-text="formatTime(item.start_time) + '–' + formatTime(item.end_time) + ' · #' + (item.booking_code || item.id.substring(0, 8))"></div>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div class="text-xs font-bold tabular-nums whitespace-nowrap" x-text="'Rp ' + formatNumber(item.total_amount)"></div>
                                        <div class="text-[10px] font-bold whitespace-nowrap"
                                             :class="statusTone(item.status) === 'ok' ? 'text-emerald-700' : (statusTone(item.status) === 'bad' ? 'text-rose-600' : 'text-amber-700')"
                                             x-text="statusLabel(item.status, item)"></div>
                                    </div>
                                </a>
                            </template>
                        </div>

                        {{-- Desktop: tabel --}}
                        <div x-show="bookings.length > 0" class="hidden md:block">
                            <table class="w-full text-left text-xs text-[#4F2F2A]">
                                <thead class="text-[10px] uppercase tracking-wider text-[#7A5A52] border-b border-[#E6DAC0]">
                                    <tr>
                                        <th class="py-2.5 pr-3 font-bold">{{ __('dashboard.col_booking') }}</th>
                                        <th class="py-2.5 pr-3 font-bold">{{ __('dashboard.col_court') }}</th>
                                        <th class="py-2.5 pr-3 font-bold">{{ __('dashboard.col_schedule') }}</th>
                                        <th class="py-2.5 pr-3 font-bold text-right">{{ __('dashboard.col_amount') }}</th>
                                        <th class="py-2.5 font-bold text-right">{{ __('dashboard.col_status') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E6DAC0]">
                                    <template x-for="item in recentBookings" :key="item.id">
                                        <tr class="hover:bg-[#F7F0DB] transition-colors">
                                            <td class="py-3 pr-3 font-mono font-bold text-[#662721] whitespace-nowrap">
                                                <a :href="'{{ route('customer.invoice') }}?booking_id=' + item.id" class="hover:underline" x-text="'#' + (item.booking_code || item.id.substring(0, 8))"></a>
                                            </td>
                                            <td class="py-3 pr-3 font-bold text-[#4F2F2A]" x-text="item.court ? item.court.name : i18n.court"></td>
                                            <td class="py-3 pr-3 text-[#7A5A52] whitespace-nowrap tabular-nums" x-text="formatDate(item.start_time) + ' · ' + formatTime(item.start_time) + '–' + formatTime(item.end_time)"></td>
                                            <td class="py-3 pr-3 font-bold text-right whitespace-nowrap tabular-nums" x-text="'Rp ' + formatNumber(item.total_amount)"></td>
                                            <td class="py-3 text-right">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border whitespace-nowrap" :class="statusPillClass(item.status)" x-text="statusLabel(item.status, item)"></span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                {{-- SAMPING --}}
                <div class="lg:col-span-4 space-y-4 sm:space-y-5">

                    {{-- Profil & membership --}}
                    <section class="relative overflow-hidden rounded-xl bg-white/95 border border-[#662721] shadow-[0_12px_35px_rgba(79,47,42,0.14)] p-5 text-center">
                        <div class="absolute -right-8 -top-8 w-28 h-28 bg-[#662721]/15 rounded-full blur-xl pointer-events-none"></div>
                        <div class="w-16 h-16 mx-auto rounded-full p-1 bg-[#662721] hover:bg-[#511D18] shadow-md">
                            <div class="w-full h-full rounded-full bg-[#F7F0DB] flex items-center justify-center text-xl font-black text-[#662721] font-display border border-white">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                        </div>
                        <h2 class="mt-2.5 font-bold text-base leading-tight break-words">{{ Auth::user()->name }}</h2>

                        @if($activeUserMembership)
                            <div class="mt-1.5 inline-flex items-center gap-1.5 max-w-full px-3 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-[#F7F0DB] text-[#662721] border border-[#E6DAC0]">
                                <span class="w-2 h-2 shrink-0 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="truncate">{{ $activeUserMembership->plan?->name ?? __('dashboard.member_fallback') }}</span>
                            </div>
                            <div class="text-[11px] font-mono font-bold text-[#662721] mt-1 truncate">{{ $activeUserMembership->membership_code }}</div>

                            @if($activeUserMembership->balances->isNotEmpty())
                                <div class="grid grid-cols-3 gap-2 mt-4">
                                    @foreach($activeUserMembership->balances as $bal)
                                        <div class="p-2 rounded-xl bg-[#F7F0DB] border border-[#E6DAC0] min-w-0">
                                            <span class="block text-[9px] uppercase font-bold text-[#7A5A52] truncate">{{ $bal->facility }}</span>
                                            <span class="block text-xs font-black text-[#4F2F2A] truncate">
                                                @if($bal->quota_type === 'HOURS')
                                                    {{ __('dashboard.quota_hours', ['n' => (float) $bal->remaining_quota]) }}
                                                @elseif($bal->quota_type === 'VISITS')
                                                    {{ $bal->initial_quota ? __('dashboard.quota_visits', ['n' => (float) $bal->remaining_quota]) : __('dashboard.unlimited') }}
                                                @else
                                                    {{ __('dashboard.discount_off', ['n' => $bal->discount_percent]) }}
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="grid grid-cols-2 gap-2 mt-2 text-left">
                                <div class="p-2.5 rounded-xl bg-[#F7F0DB] border border-[#E6DAC0]">
                                    <span class="block text-[9px] uppercase font-bold text-[#7A5A52]">{{ __('dashboard.status') }}</span>
                                    <span class="block text-xs font-black text-emerald-700">{{ __('dashboard.active') }}</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-[#F7F0DB] border border-[#E6DAC0]">
                                    <span class="block text-[9px] uppercase font-bold text-[#7A5A52]">{{ __('dashboard.valid_until') }}</span>
                                    <span class="block text-xs font-black text-[#4F2F2A] whitespace-nowrap">{{ $activeUserMembership->end_date ? \Carbon\Carbon::parse($activeUserMembership->end_date)->translatedFormat('d M Y') : __('dashboard.lifetime') }}</span>
                                </div>
                            </div>
                        @else
                            <div class="mt-1.5 inline-flex px-3 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-gray-100 text-gray-700 border border-gray-300">{{ __('dashboard.regular_member') }}</div>
                            <p class="mt-3 p-3 rounded-xl bg-[#F7F0DB] border border-[#E6DAC0] text-xs text-[#7A5A52] leading-relaxed">
                                {{ __('dashboard.non_member_pitch') }}
                            </p>
                        @endif

                        <a href="{{ route('profile.edit') }}" class="mt-4 pt-3 border-t border-[#E6DAC0] flex items-center justify-center gap-1 text-xs font-bold text-[#662721] hover:text-[#662721] transition-colors">
                            {{ __('dashboard.manage_profile') }}
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
                        </a>
                    </section>

                    {{-- Promo membership --}}
                    <section class="relative overflow-hidden rounded-xl p-5 text-white shadow-lg border border-[#E6DAC0]/70"
                             style="background: #511D18;">
                        <div class="relative z-10 space-y-2.5">
                            {{-- Dulu "Upgrade to Diamond Club" + priority booking (paket & fitur itu tidak ada). Isi mengikuti paket asli. --}}
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-[#F7F0DB]/20 text-[#F7F0DB] border border-[#F7F0DB]/40">{{ __('dashboard.promo_badge') }}</span>
                            <h3 class="font-display text-lg font-black leading-tight">{{ __('dashboard.promo_title') }}</h3>
                            <p class="text-xs text-emerald-100/85 leading-relaxed">{{ __('dashboard.promo_body') }}</p>
                            {{-- Dulu popup + WhatsApp (nomor tertulis di kode). Modul 23: langsung ke halaman Membership. --}}
                            <a href="{{ route('customer.membership') }}"
                               class="bk-tap w-full h-11 rounded-xl flex items-center justify-center text-xs font-black uppercase tracking-wider whitespace-nowrap text-[#4F2F2A] bg-[#F7F0DB] hover:bg-white shadow-md active:scale-[0.98] transition-transform">
                                {{ __('dashboard.join_membership') }}
                            </a>
                        </div>
                    </section>

                    {{-- Info venue --}}
                    <section class="rounded-xl bg-white/90 border border-[#E6DAC0] p-4 sm:p-5 text-xs">
                        <div class="font-bold text-sm mb-2.5">Club 61 Padel &amp; Wellness Medan</div>
                        <dl class="space-y-2 text-[#7A5A52] leading-relaxed">
                            {{-- Alamat & jam dari Konten Website → Fakta Venue (sama dengan halaman depan). --}}
                            @php($venue = \App\Models\Setting\CompanyProfileSetting::venueInfo())
                            {{-- venueInfo() mendahulukan teks jam versi Inggris; Bahasa Indonesia pakai teks ID bila diisi. --}}
                            @php($venueHoursId = trim((string) rescue(fn () => \App\Models\Setting\CompanyProfileSetting::current()->operating_hours_text, '', false)))
                            @if(app()->getLocale() === 'id' && $venueHoursId !== '')
                                @php($venue['hours'] = $venueHoursId)
                            @endif
                            <div class="flex gap-3"><dt class="w-20 shrink-0 font-bold text-[#4F2F2A]">{{ __('dashboard.address') }}</dt><dd class="min-w-0">{{ $venue['address'] }}</dd></div>
                            @if($venue['hours'] !== '')
                                <div class="flex gap-3"><dt class="w-20 shrink-0 font-bold text-[#4F2F2A]">{{ __('dashboard.hours') }}</dt><dd class="min-w-0">{{ $venue['hours'] }}</dd></div>
                            @endif
                            @php($venueWa = trim((string) rescue(fn () => \App\Models\Setting\CompanyProfileSetting::current()->whatsapp_number, '', false)))
                            @if($venueWa !== '')
                                <div class="flex gap-3"><dt class="w-20 shrink-0 font-bold text-[#4F2F2A]">{{ __('dashboard.whatsapp') }}</dt><dd class="min-w-0">{{ $venueWa }}</dd></div>
                            @endif
                        </dl>
                    </section>
                </div>
            </div>
        </div>

        {{-- Pemberitahuan --}}
        <div x-show="noticeModal.show" style="display: none; z-index: 99999 !important;" class="fixed inset-0 flex items-end sm:items-center justify-center sm:p-4">
            <div x-show="noticeModal.show" x-transition.opacity.duration.200ms @click="handleNoticeClose()" class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div x-show="noticeModal.show" x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-y-8 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
                 class="relative w-full sm:max-w-md bg-white rounded-t-xl sm:rounded-xl border-t-2 sm:border-2 border-[#662721] shadow-2xl p-6 pb-[calc(1.5rem+env(safe-area-inset-bottom,0px))] sm:pb-6 text-center space-y-4">
                <div class="w-14 h-14 mx-auto rounded-lg flex items-center justify-center bg-[#F7F0DB] border border-[#E6DAC0] text-[#662721]">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div class="space-y-1.5">
                    <h3 class="font-display font-black text-xl" x-text="noticeModal.title"></h3>
                    <p class="text-xs text-[#7A5A52] leading-relaxed" x-text="noticeModal.message"></p>
                </div>
                <button type="button" @click="handleNoticeClose()"
                        class="bk-tap w-full h-12 rounded-lg text-xs font-black uppercase tracking-wider bg-[#662721] hover:bg-[#511D18] text-[#F7F0DB] shadow-md active:scale-[0.98] transition-transform"
                        x-text="noticeModal.buttonText"></button>
            </div>
        </div>

        {{-- Klaim voucher jam corporate (bentuk kupon) --}}
        <div x-show="voucherModalOpen" style="display: none; z-index: 99999 !important;" class="fixed inset-0 flex items-end sm:items-center justify-center sm:p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
            <!-- Sengaja TANPA klik-di-luar untuk menutup — popup ini cuma boleh hilang lewat tombol "Claim & Continue",
                 biar karyawan gak kelewat klaim tanpa sadar. -->
            <div x-show="voucherModalOpen" x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-y-8 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
                 class="relative w-full sm:max-w-md bg-white rounded-t-xl sm:rounded-xl border-t-2 sm:border-2 border-[#662721] shadow-2xl p-6 pb-[calc(1.5rem+env(safe-area-inset-bottom,0px))] sm:pb-6">
                <div class="text-center mb-4">
                    <span class="inline-flex px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-[#F7F0DB] text-[#662721] border border-[#E6DAC0] mb-2">
                        {{ __('dashboard.new_voucher') }}<span x-show="unclaimedVouchers.length > 1">{{ __('dashboard.plural_suffix') }}</span>
                    </span>
                    <h3 class="font-display font-black text-xl leading-tight">{{ __('dashboard.free_title_before') }}<span x-show="unclaimedVouchers.length > 1">{{ __('dashboard.plural_suffix') }}</span> {{ __('dashboard.free_title_after') }}</h3>
                    <p class="text-xs text-[#7A5A52] mt-1">{{ __('dashboard.already_active') }}</p>
                </div>

                <div class="space-y-3 max-h-[45vh] overflow-y-auto">
                    <template x-for="v in unclaimedVouchers" :key="v.id">
                        <div class="relative flex rounded-lg overflow-hidden shadow-md border border-[#662721]/70">
                            <div class="w-24 shrink-0 flex flex-col items-center justify-center text-center py-4" style="background: linear-gradient(160deg, #4F2F2A 0%, #4F2F2A 100%);">
                                <div class="font-display font-black text-3xl text-[#F7F0DB]" x-text="v.hours"></div>
                                <div class="text-[8px] uppercase tracking-widest text-[#F7F0DB]/70 font-bold mt-0.5">{{ __('dashboard.hours_free') }}</div>
                            </div>
                            <div class="relative w-0 border-l-2 border-dashed border-[#662721]/50">
                                <div class="absolute -top-1.5 -left-2 w-3 h-3 rounded-full bg-white"></div>
                                <div class="absolute -bottom-1.5 -left-2 w-3 h-3 rounded-full bg-white"></div>
                            </div>
                            <div class="flex-1 min-w-0 bg-[#FCF8EE] p-3.5 text-left">
                                <div class="text-[9px] uppercase font-extrabold text-[#662721] tracking-wider truncate" x-text="v.organization"></div>
                                <div class="font-bold text-sm mt-0.5 line-clamp-2" x-text="v.plan_name"></div>
                                <div class="text-[10px] text-[#7A5A52] mt-1.5">{{ __('dashboard.expires') }} <span x-text="v.expires_at"></span></div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="pt-4 space-y-2">
                    <button type="button" @click="claimVouchers()"
                            class="bk-tap w-full h-12 rounded-lg text-xs font-black uppercase tracking-wider bg-[#662721] hover:bg-[#511D18] text-[#F7F0DB] shadow-md active:scale-[0.98] transition-transform">
                        {{ __('dashboard.claim_continue') }}
                    </button>
                    <a href="{{ route('profile.vouchers') }}" class="block w-full text-center py-2 text-xs font-bold text-[#662721] hover:text-[#662721] transition-colors">
                        {{ __('dashboard.view_all_vouchers') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function dashboardApp(unclaimedVouchers = []) {
            return {
                bookings: [],
                activeMatch: null,
                isLoadingBookings: true,
                unclaimedVouchers,
                voucherModalOpen: false,
                appTimezone: @js(config('app.timezone')),
                // Teks & format tanggal mengikuti bahasa situs (ID / EN).
                dateLocale: @js(app()->getLocale() === 'id' ? 'id-ID' : 'en-GB'),
                i18n: @js(['court' => __('dashboard.court_fallback'), 'got_it' => __('dashboard.got_it'), 'status' => __('dashboard.status_labels')]),
                noticeModal: {
                    show: false,
                    title: '',
                    message: '',
                    type: 'info',
                    buttonText: '',
                    onClose: null
                },

                showNotice(title, message, type = 'info', buttonText = null, onClose = null) {
                    this.noticeModal = {
                        show: true,
                        title,
                        message,
                        type,
                        buttonText: buttonText || this.i18n.got_it,
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
                    if (this.unclaimedVouchers.length) {
                        this.voucherModalOpen = true;
                    }
                },

                async claimVouchers() {
                    if (!this.voucherModalOpen) return; // sudah diklaim (mis. klik ganda tombol "Claim & Continue")
                    this.voucherModalOpen = false;

                    const ids = this.unclaimedVouchers.map(v => v.id);
                    this.unclaimedVouchers = [];
                    for (const id of ids) {
                        try {
                            await fetch('/api/v1/sponsor/my-vouchers/' + id + '/acknowledge', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                    'Accept': 'application/json',
                                },
                            });
                        } catch (e) {
                            console.error('Failed to acknowledge voucher', id, e);
                        }
                    }
                },

                async loadMyBookings() {
                    this.isLoadingBookings = true;
                    try {
                        const res = await fetch('/api/v1/padel/my-bookings');
                        const json = await res.json();
                        if (json.success && json.data) {
                            this.bookings = json.data;

                            // Jadwal main berikutnya: booking lunas terdekat yang belum selesai. API mengurutkan dari jadwal
                            // paling jauh, jadi dulu yang tampil justru jadwal terjauh (atau jadwal yang sudah lewat).
                            const now = Date.now();
                            this.activeMatch = this.bookings
                                .filter(b => ['PAID', 'CONFIRMED'].includes(b.status) && new Date(b.end_time).getTime() > now)
                                .sort((a, b) => new Date(a.start_time) - new Date(b.start_time))[0] || null;
                        }
                    } catch(e) {
                        console.error('Failed to load dashboard data:', e);
                    } finally {
                        this.isLoadingBookings = false;
                    }
                },

                get recentBookings() {
                    return this.bookings.slice(0, 5);
                },

                get totalMatchCount() {
                    return this.bookings.filter(b => b.status === 'PAID' || b.status === 'CONFIRMED' || b.status === 'COMPLETED').length;
                },

                // Booking yang dipindah jadwal dan selisihnya belum lunas tetap LOCKED di database → "Balance due", bukan "In checkout".
                statusLabel(status, booking = null) {
                    if (status === 'LOCKED' && booking && Number(booking.reschedule_count) > 0) {
                        return this.i18n.status.balance_due;
                    }
                    const map = this.i18n.status;
                    return map[status] || (status ? String(status).replace(/_/g, ' ').toLowerCase().replace(/^\w/, c => c.toUpperCase()) : '-');
                },

                statusTone(status) {
                    if (['PAID', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED'].includes(status)) return 'ok';
                    if (['EXPIRED', 'CANCELLED', 'REFUNDED', 'REFUND_PENDING'].includes(status)) return 'bad';
                    return 'wait';
                },

                statusPillClass(status) {
                    const tone = this.statusTone(status);
                    return tone === 'ok' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : (tone === 'bad' ? 'bg-rose-100 text-rose-800 border-rose-200' : 'bg-amber-100 text-amber-900 border-amber-200');
                },

                formatNumber(val) {
                    if (!val) return '0';
                    return Math.round(val).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                },

                // Tanggal & jam dalam zona waktu venue, bukan zona waktu HP.
                dateParts(val) {
                    const d = new Date(val);
                    if (!val || isNaN(d.getTime())) return null;
                    return Object.fromEntries(new Intl.DateTimeFormat(this.dateLocale, { timeZone: this.appTimezone, day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
                        .formatToParts(d).map(p => [p.type, p.value]));
                },

                formatTime(isoString) {
                    const p = this.dateParts(isoString);
                    return p ? `${p.hour}:${p.minute}` : '--:--';
                },

                formatDate(val) {
                    const p = this.dateParts(val);
                    return p ? `${p.day} ${p.month} ${p.year}` : (val ? String(val).substring(0, 10) : '-');
                },

                formatDay(val) {
                    const p = this.dateParts(val);
                    return p ? p.day : '--';
                },

                formatMonth(val) {
                    const p = this.dateParts(val);
                    return p ? p.month : '';
                }
            }
        }
    </script>
</x-app-layout>
