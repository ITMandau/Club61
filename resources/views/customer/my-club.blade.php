<x-app-layout>
    <div class="py-6 sm:py-8 text-[#4F2F2A]">
        <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16 space-y-6">

            <!-- Top Header & Navigation -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white/95 p-5 rounded-xl border border-[#E6DAC0] shadow-sm">
                <div class="flex items-center gap-3.5">
                    <a href="{{ route('dashboard') }}" class="p-2.5 rounded-lg bg-[#F7F0DB] hover:bg-[#F7F0DB] border border-[#E6DAC0] text-[#662721] transition-colors" title="{{ __('myclub.back_home') }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="font-display font-black text-xl sm:text-2xl text-[#4F2F2A]">My Club &bull; CLUB 61 Padel Court</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-[#F7F0DB] text-[#662721] border border-[#E6DAC0]">{{ __('myclub.venue_badge') }}</span>
                        </div>
                        <p class="text-xs text-[#7A5A52] mt-0.5">{{ __('myclub.subtitle') }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="px-4 py-2 rounded-lg text-xs font-black bg-[#F7F0DB] text-[#662721] border border-[#E6DAC0] shadow-sm">
                        Club 61 Member
                    </span>
                    <a href="{{ route('customer.booking') }}" 
                       class="px-5 py-2.5 rounded-lg bg-[#662721] hover:bg-[#511D18] text-[#F7F0DB] text-xs font-black uppercase tracking-wider shadow-md  active:scale-95 transition-all">
                        {{ __('myclub.book_court') }}
                    </a>
                </div>
            </div>

            <!-- Club Hero Visual Banner -->
            <div class="relative overflow-hidden rounded-xl p-6 sm:p-10 text-white shadow-[0_15px_40px_rgba(20,40,30,0.25)] border border-[#E6DAC0]/70"
                 style="background: #662721;">
                <!-- Glowing Orb Accents -->
                <div class="absolute -right-10 -bottom-10 w-96 h-96 rounded-full bg-[#662721]/20 blur-3xl pointer-events-none"></div>

                <div class="relative z-10 max-w-2xl">
                    <div class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-[#E6DAC0]/20 text-[#F7F0DB] border border-[#E6DAC0]/40 mb-3 shadow-sm">
                        <span>{{ __('myclub.profile_badge') }}</span>
                    </div>
                    <h2 class="font-display text-2xl sm:text-4xl font-extrabold tracking-tight text-[#F7F0DB] leading-tight">
                        Club 61 Padel Court Medan
                    </h2>
                    <p class="text-xs sm:text-sm text-emerald-100/80 mt-3 font-medium leading-relaxed">
                        {{ __('myclub.hero_desc') }}
                    </p>

                    {{-- Alamat & jam dari Konten Website → Fakta Venue (sama dengan halaman depan). --}}
                    {{-- Sengaja bentuk blok (bukan versi satu baris dengan kurung): versi satu baris yang muncul sebelum blok PHP lain merusak kompilasi Blade. --}}
                    @php
                        $venue = \App\Models\Setting\CompanyProfileSetting::venueInfo();
                    @endphp
                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        @if($venue['hours'] !== '')
                            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/10 border border-white/20 text-xs font-bold text-white">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                {{ $venue['hours'] }}
                            </div>
                        @endif
                        {{-- Tombol WhatsApp dihapus — kontak lewat tombol WhatsApp melayang (satu tombol untuk semua). --}}
                    </div>
                </div>
            </div>

            @php
                // Modul 23: kartu member lengkap & voucher pindah ke Profile / Voucher Saya. Di sini cukup satu baris status.
                $activeMbr = app(\App\Services\Customer\CustomerAccountService::class)->activeMembership(Auth::user());
                $allPlans = \App\Models\Membership\MembershipPlan::with('benefits')->where('is_active', true)->get();
                $facilityService = app(\App\Services\Membership\MembershipFacilityService::class);
            @endphp

            @if($activeMbr)
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 hover:border-emerald-400 transition-colors">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
                    <span class="flex-1 min-w-0 text-sm text-emerald-900">
                        {!! __('myclub.member_status', ['plan' => '<strong>'.e($activeMbr->plan?->name ?? 'Club 61').'</strong>']) !!}
                        @if($activeMbr->end_date) {{ __('myclub.active_until', ['date' => \Carbon\Carbon::parse($activeMbr->end_date)->translatedFormat('d M Y')]) }} @endif
                    </span>
                    <span class="shrink-0 text-xs font-black text-emerald-800 whitespace-nowrap">{{ __('myclub.view_in_profile') }}</span>
                </a>
            @endif

            <!-- Membership Plans Catalog -->
            <div class="space-y-4">
                <div class="flex items-center justify-between px-1">
                    <div>
                        <h3 class="font-display font-extrabold text-lg text-[#4F2F2A]">{{ __('myclub.plans_heading') }}</h3>
                        <p class="text-xs text-[#7A5A52]">{{ __('myclub.plans_sub') }}</p>
                    </div>
                    <span class="text-xs font-bold text-[#662721]">{{ __('myclub.plan_count', ['count' => $allPlans->count()]) }}</span>
                </div>

                <div class="flex flex-wrap justify-center gap-4 [&>*]:w-full md:[&>*]:w-[calc(50%-8px)] lg:[&>*]:w-[calc(25%-12px)]">
                    @foreach($allPlans as $p)
                        @php
                            $cards = $facilityService->presentPlan($p);
                            $perks = array_values(array_filter((array) ($p->perks ?? []), fn ($perk) => is_string($perk) && trim($perk) !== ''));
                        @endphp
                        <div onclick="window.location.href='{{ route('customer.membership', ['plan' => $p->id]) }}'"
                             class="p-5 rounded-xl bg-white/95 border border-[#E6DAC0] shadow-sm hover:border-[#662721] hover:shadow-xl hover:-translate-y-1 transition-all flex flex-col justify-between group cursor-pointer">
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-[10px] font-extrabold uppercase text-[#662721]">{{ __('membership.ownership')[$p->ownership_type] ?? $p->ownership_type }}</span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#F7F0DB] text-[#662721]">{{ __('myclub.days', ['count' => $p->duration_days]) }}</span>
                                </div>
                                <div class="font-display font-black text-lg text-[#4F2F2A] group-hover:text-[#662721] transition-colors">{{ $p->name }}</div>
                                <div class="font-black text-base text-[#662721] mt-1">Rp {{ number_format($p->price, 0, ',', '.') }}</div>

                                <div class="mt-4 border-t border-[#F7F0DB] pt-3 space-y-2 text-xs text-[#7A5A52]">
                                    @foreach ($cards as $card)
                                        <div class="flex items-start gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#662721] hover:bg-[#511D18] mt-1 shrink-0"></span>
                                            <span>{{ $card['title'] }}@if ($card['details']) <span class="text-[#7A5A52]">({{ implode(', ', $card['details']) }})</span>@endif</span>
                                        </div>
                                    @endforeach
                                    @foreach ($perks as $perk)
                                        <div class="flex items-start gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#662721] hover:bg-[#511D18] mt-1 shrink-0"></span>
                                            <span>{{ $perk }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mt-5 pt-3 border-t border-[#F7F0DB] space-y-2">
                                <a href="{{ route('customer.membership', ['plan' => $p->id]) }}" 
                                   class="block w-full text-center py-2.5 px-3 rounded-xl bg-[#662721] hover:bg-[#511D18]  text-[#F7F0DB] font-extrabold text-xs shadow-md transition-all active:scale-95 cursor-pointer">
                                    {{ __('myclub.view_detail_buy') }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Key Info Bar (3 Cards) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-5 rounded-xl bg-white/95 border border-[#E6DAC0] shadow-sm flex items-start gap-4">
                    <div class="w-12 h-12 rounded-lg bg-[#F7F0DB] border border-[#E6DAC0] flex items-center justify-center shrink-0 text-[#662721]">
                        <svg class="w-6 h-6 text-[#662721]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase font-extrabold tracking-wider text-[#662721]">{{ __('myclub.hours_label') }}</div>
                        <div class="font-display font-black text-base text-[#4F2F2A] mt-0.5">{{ $venue['hours'] !== '' ? $venue['hours'] : __('myclub.hours_fallback') }}</div>
                    </div>
                </div>

                <div class="p-5 rounded-xl bg-white/95 border border-[#E6DAC0] shadow-sm flex items-start gap-4">
                    <div class="w-12 h-12 rounded-lg bg-[#F7F0DB] border border-[#E6DAC0] flex items-center justify-center shrink-0 text-[#662721]">
                        <svg class="w-6 h-6 text-[#662721]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase font-extrabold tracking-wider text-[#662721]">{{ __('myclub.location_label') }}</div>
                        <div class="font-display font-black text-base text-[#4F2F2A] mt-0.5">Club 61 Padel Court Medan</div>
                        <div class="text-xs text-[#7A5A52] mt-1">{{ $venue['address'] }}</div>
                    </div>
                </div>

                <div class="p-5 rounded-xl bg-white/95 border border-[#E6DAC0] shadow-sm flex items-start gap-4">
                    <div class="w-12 h-12 rounded-lg bg-[#F7F0DB] border border-[#E6DAC0] flex items-center justify-center shrink-0 text-[#662721]">
                        <svg class="w-6 h-6 text-[#662721]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase font-extrabold tracking-wider text-[#662721]">{{ __('myclub.concierge_label') }}</div>
                        <div class="font-display font-black text-base text-[#4F2F2A] mt-0.5">{{ __('myclub.concierge_title') }}</div>
                        @php($venueWa = trim((string) rescue(fn () => \App\Models\Setting\CompanyProfileSetting::current()->whatsapp_number, '', false)))
                        <div class="text-xs text-[#7A5A52] mt-1 font-mono">@if($venueWa !== ''){{ $venueWa }} &bull; @endif {{ __('myclub.free_valet') }}</div>
                    </div>
                </div>
            </div>

            <!-- Facilities Showcase: 6 Premium Cards -->
            <div class="space-y-4">
                <div class="flex items-center justify-between px-1">
                    <div>
                        <h3 class="font-display font-extrabold text-lg text-[#4F2F2A]">{{ __('myclub.facilities_heading') }}</h3>
                        <p class="text-xs text-[#7A5A52]">{{ __('myclub.facilities_sub') }}</p>
                    </div>
                    <span class="text-xs font-bold text-[#662721]">{{ __('myclub.facilities_count') }}</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    
                    <!-- Facility 1: Courts -->
                    <div class="p-6 rounded-xl bg-white/95 border border-[#E6DAC0] shadow-sm flex flex-col justify-between hover:border-[#662721] hover:shadow-[0_10px_25px_rgba(102,39,33,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-[#F7F0DB] border border-[#E6DAC0] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#662721] uppercase">COURT</span>
                            </div>
                            <div>
                                <h4 class="font-display font-black text-sm text-[#4F2F2A]">{{ __('myclub.court_title') }}</h4>
                                <p class="text-xs text-[#7A5A52] mt-1.5 leading-relaxed">
                                    {{ __('myclub.court_desc') }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#E6DAC0]/40 flex items-center justify-between text-[11px] font-bold text-[#662721]">
                            <span>{{ __('myclub.court_tag') }}</span>
                            <span class="text-emerald-700">{{ __('myclub.available_daily') }}</span>
                        </div>
                    </div>

                    <!-- Facility 2: Wellness -->
                    <div class="p-6 rounded-xl bg-white/95 border border-[#E6DAC0] shadow-sm flex flex-col justify-between hover:border-[#662721] hover:shadow-[0_10px_25px_rgba(102,39,33,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-[#F7F0DB] border border-[#E6DAC0] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#662721] uppercase">WELLNESS</span>
                            </div>
                            <div>
                                <h4 class="font-display font-black text-sm text-[#4F2F2A]">{{ __('myclub.wellness_title') }}</h4>
                                <p class="text-xs text-[#7A5A52] mt-1.5 leading-relaxed">
                                    {{ __('myclub.wellness_desc') }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#E6DAC0]/40 flex items-center justify-between text-[11px] font-bold text-[#662721]">
                            <span>{{ __('myclub.muscle_recovery') }}</span>
                            <span class="text-emerald-700">{{ __('myclub.member_quota_access') }}</span>
                        </div>
                    </div>

                    <!-- Facility 3: Cafe & Lounge -->
                    <div class="p-6 rounded-xl bg-white/95 border border-[#E6DAC0] shadow-sm flex flex-col justify-between hover:border-[#662721] hover:shadow-[0_10px_25px_rgba(102,39,33,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-[#F7F0DB] border border-[#E6DAC0] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#662721] uppercase">CAFE</span>
                            </div>
                            <div>
                                <h4 class="font-display font-black text-sm text-[#4F2F2A]">Club 61 Cafe &amp; Lounge</h4>
                                <p class="text-xs text-[#7A5A52] mt-1.5 leading-relaxed">
                                    {{ __('myclub.cafe_desc') }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#E6DAC0]/40 flex items-center justify-between text-[11px] font-bold text-[#662721]">
                            <span>F&amp;B &bull; Social Lounge</span>
                            <span class="text-[#662721] font-mono">{{ __('myclub.cafe_open') }}</span>
                        </div>
                    </div>

                    <!-- Facility 4: Smart Locker -->
                    <div class="p-6 rounded-xl bg-white/95 border border-[#E6DAC0] shadow-sm flex flex-col justify-between hover:border-[#662721] hover:shadow-[0_10px_25px_rgba(102,39,33,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-[#F7F0DB] border border-[#E6DAC0] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#662721] uppercase">LOCKER</span>
                            </div>
                            <div>
                                <h4 class="font-display font-black text-sm text-[#4F2F2A]">Smart Locker &amp; Rain Shower</h4>
                                <p class="text-xs text-[#7A5A52] mt-1.5 leading-relaxed">
                                    {{ __('myclub.locker_desc') }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#E6DAC0]/40 flex items-center justify-between text-[11px] font-bold text-[#662721]">
                            <span>{{ __('myclub.private_locker') }}</span>
                            <span class="text-emerald-700">{{ __('myclub.rfid_security') }}</span>
                        </div>
                    </div>

                    <!-- Facility 5: Pro Shop -->
                    <div class="p-6 rounded-xl bg-white/95 border border-[#E6DAC0] shadow-sm flex flex-col justify-between hover:border-[#662721] hover:shadow-[0_10px_25px_rgba(102,39,33,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-[#F7F0DB] border border-[#E6DAC0] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#662721] uppercase">SHOP</span>
                            </div>
                            <div>
                                <h4 class="font-display font-black text-sm text-[#4F2F2A]">{{ __('myclub.shop_title') }}</h4>
                                <p class="text-xs text-[#7A5A52] mt-1.5 leading-relaxed">
                                    {{ __('myclub.shop_desc') }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#E6DAC0]/40 flex items-center justify-between text-[11px] font-bold text-[#662721]">
                            <span>{{ __('myclub.gear_rental') }}</span>
                            <span class="text-emerald-700">{{ __('myclub.demo_rackets') }}</span>
                        </div>
                    </div>

                    <!-- Facility 6: Valet & EV -->
                    <div class="p-6 rounded-xl bg-white/95 border border-[#E6DAC0] shadow-sm flex flex-col justify-between hover:border-[#662721] hover:shadow-[0_10px_25px_rgba(102,39,33,0.2)] transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-[#F7F0DB] border border-[#E6DAC0] flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                <span class="text-[10px] font-black tracking-widest text-[#662721] uppercase">VALET</span>
                            </div>
                            <div>
                                <h4 class="font-display font-black text-sm text-[#4F2F2A]">{{ __('myclub.valet_title') }}</h4>
                                <p class="text-xs text-[#7A5A52] mt-1.5 leading-relaxed">
                                    {{ __('myclub.valet_desc') }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#E6DAC0]/40 flex items-center justify-between text-[11px] font-bold text-[#662721]">
                            <span>{{ __('myclub.valet_service') }}</span>
                            <span class="text-emerald-700">{{ __('myclub.free_for_members') }}</span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Club Etiquette & Rules (Left) + Member Status Benefits (Right) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- Left: Etiquette Guidelines (Col 7) -->
                <div class="lg:col-span-7 bg-white/95 rounded-xl p-6 sm:p-8 border border-[#E6DAC0] shadow-sm space-y-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-[#F7F0DB] border border-[#E6DAC0] flex items-center justify-center shrink-0 text-[#662721]">
                            <svg class="w-5 h-5 text-[#662721]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-display font-black text-base sm:text-lg text-[#4F2F2A]">{{ __('myclub.etiquette_title') }}</h3>
                            <p class="text-xs text-[#7A5A52]">{{ __('myclub.etiquette_sub') }}</p>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="flex items-start gap-3 p-3 rounded-lg bg-[#FCF8EE] border border-[#E6DAC0]/60">
                            <span class="font-black text-[#662721] shrink-0">01.</span>
                            <div>
                                <strong class="text-[#4F2F2A]">{{ __('myclub.rule1_title') }}</strong>
                                <span class="text-[#7A5A52] block mt-0.5">{{ __('myclub.rule1_desc') }}</span>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-3 rounded-lg bg-[#FCF8EE] border border-[#E6DAC0]/60">
                            <span class="font-black text-[#662721] shrink-0">02.</span>
                            <div>
                                <strong class="text-[#4F2F2A]">{{ __('myclub.rule2_title') }}</strong>
                                <span class="text-[#7A5A52] block mt-0.5">{{ __('myclub.rule2_desc') }}</span>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-3 rounded-lg bg-[#FCF8EE] border border-[#E6DAC0]/60">
                            <span class="font-black text-[#662721] shrink-0">03.</span>
                            <div>
                                <strong class="text-[#4F2F2A]">{{ __('myclub.rule3_title') }}</strong>
                                <span class="text-[#7A5A52] block mt-0.5">{{ __('myclub.rule3_desc') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Official Member Privileges (Col 5) -->
                <div class="lg:col-span-5 space-y-6">
                    <div class="bg-[#511D18] text-white rounded-xl p-6 border border-transparent shadow-lg space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-[#E6DAC0]/20 text-[#F7F0DB] border border-[#E6DAC0]/40">
                                {{ __('myclub.privileges_badge') }}
                            </span>
                            <span class="text-xs text-[#E6DAC0] font-bold">Club 61 Medan</span>
                        </div>

                        <div>
                            <h4 class="font-display font-black text-lg text-white">{{ __('myclub.privileges_title') }}</h4>
                            <p class="text-xs text-emerald-100/70 mt-1">{{ __('myclub.privileges_sub') }}</p>
                        </div>
                        
                        <ul class="space-y-3 text-xs text-emerald-100/85 font-medium">
                            <li class="flex items-start gap-2.5">
                                <span class="text-[#E6DAC0] font-bold mt-0.5">&bull;</span>
                                <div>
                                    <strong class="text-white block font-bold">{{ __('myclub.priv1_title') }}</strong>
                                    <span class="text-[11px] text-emerald-100/70">{{ __('myclub.priv1_desc') }}</span>
                                </div>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="text-[#E6DAC0] font-bold mt-0.5">&bull;</span>
                                <div>
                                    <strong class="text-white block font-bold">{{ __('myclub.priv2_title') }}</strong>
                                    <span class="text-[11px] text-emerald-100/70">{{ __('myclub.priv2_desc') }}</span>
                                </div>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="text-[#E6DAC0] font-bold mt-0.5">&bull;</span>
                                <div>
                                    <strong class="text-white block font-bold">{{ __('myclub.priv3_title') }}</strong>
                                    <span class="text-[11px] text-emerald-100/70">{{ __('myclub.priv3_desc') }}</span>
                                </div>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="text-[#E6DAC0] font-bold mt-0.5">&bull;</span>
                                <div>
                                    <strong class="text-white block font-bold">{{ __('myclub.priv4_title') }}</strong>
                                    <span class="text-[11px] text-emerald-100/70">{{ __('myclub.priv4_desc') }}</span>
                                </div>
                            </li>
                        </ul>

                        <div class="pt-3 border-t border-white/15 space-y-2">
                            <a href="{{ route('customer.booking') }}" 
                               class="w-full py-3.5 px-4 rounded-lg bg-[#F7F0DB] hover:bg-white text-[#4F2F2A] text-xs font-black uppercase tracking-wider flex items-center justify-center gap-2 shadow-md  active:scale-95 transition-all">
                                <span>{{ __('myclub.book_now') }}</span>
                            </a>
                            <a href="{{ route('customer.membership') }}"
                               class="block w-full text-center py-2 text-[11px] font-bold text-[#E6DAC0] hover:text-[#F7F0DB] transition-colors">
                                {{ __('myclub.view_buy_plans') }}
                            </a>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>


