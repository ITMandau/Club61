{{-- Klaim promo cara sebar C (Modul 22 tahap 3). Data: PromoClaimController. Publik: tamu memakai layout tamu + tombol
     "Log in to claim" (kembali ke sini setelah login / daftar); customer memakai layout portal. Lebar penuh seperti
     halaman customer lain; banner promo + kupon di atas, syarat & cara pakai di bawah. --}}
@php
    $guest = $viewer === 'GUEST';
    $claimed = session('promo_claimed') || $state === 'CLAIMED';
    $blocked = [
        'INACTIVE' => __('promo.blocked_inactive'),
        'ENDED' => __('promo.blocked_ended'),
        'NOT_STARTED' => __('promo.blocked_not_started', ['date' => $voucher->valid_from?->timezone('Asia/Jakarta')->translatedFormat('d M Y')]),
        'SOLD_OUT' => __('promo.blocked_sold_out'),
    ][$state] ?? null;
    $isPercent = $voucher->discount_type === \App\Models\Pos\Voucher::TYPE_PERCENT;
    $value = $isPercent
        ? rtrim(rtrim(number_format((float) $voucher->discount_value, 2, ',', '.'), '0'), ',').'%'
        : 'Rp'.number_format((float) $voucher->discount_value, 0, ',', '.');
    $left = $voucher->quota === null ? null : max(0, (int) $voucher->quota);
    $percentLeft = $totalClaims ? max(4, (int) round($left / max(1, $totalClaims) * 100)) : null;
    $until = $voucher->valid_until?->timezone('Asia/Jakarta')->translatedFormat('d M Y');
@endphp

<x-dynamic-component :component="$guest ? 'guest-layout' : 'app-layout'" theme="terracotta">
    <div class="{{ $guest ? 'w-full self-start' : 'py-6 sm:py-8' }} text-[#4F2F2A]">
        <div class="w-full {{ $guest ? '' : 'px-4 sm:px-8 lg:px-12 2xl:px-16' }} space-y-5 sm:space-y-6">

            @if ($guest)
                {{-- Tamu: tanpa navbar portal — baris merek + tombol masuk. --}}
                <div class="flex items-center justify-between gap-3">
                    <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                        <img src="{{ asset('images/identity/monogram-terracotta.png').'?v=2' }}" alt="" class="h-9 w-auto">
                        <span class="font-display font-black text-lg tracking-wide text-[#662721]">Club 61</span>
                    </a>
                    <a href="{{ route('promo.claim.auth', [$voucher->code, 'masuk']) }}" class="px-4 py-2 rounded-lg bg-white border border-[#E6DAC0] text-[#662721] font-bold text-xs">{{ __('promo.log_in') }}</a>
                </div>
            @endif

            @if (session('promo_error'))
                <div role="alert" class="px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm font-medium text-rose-800">{{ session('promo_error') }}</div>
            @endif

            {{-- ====== Banner promo ====== --}}
            <section data-promo-claim class="relative overflow-hidden rounded-3xl bg-[#511D18] text-[#F7F0DB] shadow-[0_30px_70px_-40px_rgba(79,47,42,0.9)]">
                <img src="{{ asset('images/club-hero.jpg') }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-25">
                <div class="absolute inset-0" style="background: radial-gradient(120% 140% at 0% 0%, rgba(185,28,28,0.55) 0%, rgba(81,29,24,0.92) 55%, rgba(48,16,13,0.97) 100%);"></div>

                <div class="relative grid gap-5 md:gap-8 p-5 sm:p-6 lg:p-8 md:grid-cols-[minmax(0,1fr)_250px] lg:grid-cols-[minmax(0,1fr)_290px] items-center">
                    <div class="min-w-0 [overflow-wrap:anywhere]">
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#F7F0DB]/10 border border-[#F7F0DB]/25 text-[10px] font-extrabold uppercase tracking-[0.18em]">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-300"></span> {{ __('promo.badge') }}
                        </span>
                        <h1 class="mt-3 font-display font-black text-2xl sm:text-3xl xl:text-4xl leading-[1.1]">{{ $voucher->name }}</h1>
                        <p class="mt-2 text-sm sm:text-base font-bold text-[#F7F0DB]/85">{{ $terms['headline'] }}</p>

                        <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-xs text-[#F7F0DB]/80">
                            @if ($until)
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ __('promo.until', ['date' => $until]) }}@if ($daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 14)<b class="text-amber-300">&nbsp;· {{ $daysLeft === 0 ? __('promo.last_day') : __('promo.days_left', ['count' => $daysLeft]) }}</b>@endif
                                </span>
                            @endif
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                {{ __('promo.one_per_account') }}
                            </span>
                        </div>

                        @if ($percentLeft !== null && ! $claimed && ! $blocked)
                            <div class="mt-4 max-w-sm">
                                <div class="flex justify-between text-[11px] font-bold text-[#F7F0DB]/80 mb-1.5">
                                    <span>{{ __('promo.claims_left', ['left' => number_format($left, 0, ',', '.'), 'total' => number_format($totalClaims, 0, ',', '.')]) }}</span>
                                    @if ($percentLeft <= 25)<span class="text-amber-300">{{ __('promo.almost_gone') }}</span>@endif
                                </div>
                                <div class="h-2 rounded-full bg-[#F7F0DB]/15 overflow-hidden">
                                    <div class="h-full rounded-full bg-gradient-to-r from-amber-300 to-[#F7F0DB]" style="width: {{ $percentLeft }}%"></div>
                                </div>
                            </div>
                        @endif

                        {{-- Tombol sesuai status --}}
                        <div class="mt-5">
                            @if ($claimed)
                                <div data-promo-claimed class="space-y-3">
                                    <div class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-400/15 border border-emerald-300/40 text-emerald-100 text-sm font-bold">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        {{ session('promo_claimed') ? __('promo.claimed_now') : '' }}{{ __('promo.in_your_vouchers') }}
                                    </div>
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <a href="{{ route('customer.booking') }}" class="text-center px-5 py-3 rounded-xl bg-[#F7F0DB] hover:bg-white text-[#662721] font-black text-xs uppercase tracking-wider">{{ __('promo.book_and_use') }}</a>
                                        <a href="{{ route('profile.vouchers') }}" class="text-center px-5 py-3 rounded-xl border border-[#F7F0DB]/35 hover:bg-[#F7F0DB]/10 font-bold text-xs">{{ __('promo.my_vouchers') }}</a>
                                    </div>
                                </div>
                            @elseif ($blocked)
                                <div class="inline-block px-4 py-3 rounded-xl bg-[#F7F0DB]/10 border border-[#F7F0DB]/25 text-sm font-bold">{{ $blocked }}</div>
                            @elseif ($guest)
                                <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                                    <a href="{{ route('promo.claim.auth', [$voucher->code, 'masuk']) }}" data-promo-login
                                       class="text-center px-6 py-3 rounded-xl bg-[#F7F0DB] hover:bg-white text-[#662721] font-black text-xs uppercase tracking-wider shadow-lg">{{ __('promo.login_to_claim') }}</a>
                                    <a href="{{ route('promo.claim.auth', [$voucher->code, 'daftar']) }}" class="text-center px-5 py-3 rounded-xl border border-[#F7F0DB]/35 hover:bg-[#F7F0DB]/10 font-bold text-xs">{{ __('promo.create_account') }}</a>
                                </div>
                                <p class="mt-2 text-[11px] text-[#F7F0DB]/65">{{ __('promo.come_back_note') }}</p>
                            @elseif ($viewer === 'STAFF')
                                <div class="inline-block px-4 py-3 rounded-xl bg-[#F7F0DB]/10 border border-[#F7F0DB]/25 text-sm font-bold">{{ __('promo.staff_blocked') }}</div>
                            @else
                                <form method="POST" action="{{ route('promo.claim.store', $voucher->code) }}" x-data="{ busy: false }" @submit="busy = true">
                                    @csrf
                                    <button type="submit" :disabled="busy" data-promo-claim-button
                                            class="w-full sm:w-auto px-8 py-3 rounded-xl bg-[#F7F0DB] hover:bg-white text-[#662721] font-black text-xs uppercase tracking-wider shadow-lg transition-colors disabled:opacity-60">
                                        <span x-show="!busy">{{ __('promo.claim') }}</span><span x-show="busy" x-cloak>{{ __('promo.claiming') }}</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    {{-- Kupon --}}
                    <div class="relative w-full max-w-[290px] mx-auto md:mx-0 md:justify-self-end">
                        <div class="relative rounded-2xl bg-[#FCF8EE] text-[#4F2F2A] px-5 py-5 sm:py-6 text-center shadow-[0_20px_40px_-20px_rgba(0,0,0,0.6)] {{ $claimed ? '' : 'lg:rotate-1' }}">
                            {{-- Lubang sobekan kupon kiri-kanan --}}
                            <span class="absolute top-1/2 -left-2.5 -translate-y-1/2 w-5 h-5 rounded-full bg-[#3A1410]"></span>
                            <span class="absolute top-1/2 -right-2.5 -translate-y-1/2 w-5 h-5 rounded-full bg-[#3A1410]"></span>
                            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-[#B91C1C]">{{ $isPercent ? __('promo.discount') : __('promo.you_save') }}</div>
                            <div class="mt-2 font-display font-black leading-none whitespace-nowrap text-[#662721] {{ mb_strlen($value) > 7 ? 'text-3xl' : 'text-4xl' }}">{{ $value }}</div>
                            <div class="mt-2 text-xs font-bold text-[#7A5A52]">{{ __('promo.on_court_rental') }}</div>
                            <div class="my-4 border-t-2 border-dashed border-[#E6DAC0]"></div>
                            <div class="flex items-center justify-center gap-2">
                                <span class="px-3 py-1.5 rounded-lg bg-[#F7F0DB] border border-[#E6DAC0] font-mono font-bold text-sm tracking-wider">{{ $voucher->code }}</span>
                                @if ($claimed)
                                    <span class="px-2 py-1 rounded-md bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase">{{ __('promo.saved') }}</span>
                                @endif
                            </div>
                            <div class="mt-2 text-[10px] text-[#7A5A52]">{{ __('promo.no_typing') }}</div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ====== Syarat & cara pakai ====== --}}
            <div class="grid gap-5 md:grid-cols-2">
                <div class="bg-white rounded-2xl border border-[#E6DAC0] shadow-sm p-5">
                    <h2 class="font-display font-black text-base">{{ __('promo.terms') }}</h2>
                    <ul class="mt-3 space-y-2.5">
                        @foreach ($terms['rules'] as $rule)
                            <li class="flex items-start gap-2.5 text-sm">
                                <svg class="w-4 h-4 mt-0.5 shrink-0 text-[#B91C1C]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span class="min-w-0 [overflow-wrap:anywhere]">{{ $rule }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="bg-white rounded-2xl border border-[#E6DAC0] shadow-sm p-5">
                    <h2 class="font-display font-black text-base">{{ __('promo.how_it_works') }}</h2>
                    <ol class="mt-3 grid gap-3 sm:grid-cols-3 md:grid-cols-1 xl:grid-cols-3">
                        @foreach ([
                            [__('promo.step_claim_title'), __('promo.step_claim_text')],
                            [__('promo.step_book_title'), __('promo.step_book_text')],
                            [__('promo.step_save_title'), __('promo.step_save_text')],
                        ] as $i => [$title, $text])
                            <li class="flex sm:flex-col md:flex-row xl:flex-col gap-3 p-3 rounded-xl bg-[#FCF8EE] border border-[#EFE6D2]">
                                <span class="w-8 h-8 shrink-0 rounded-full bg-[#662721] text-[#F7F0DB] text-sm font-black flex items-center justify-center">{{ $i + 1 }}</span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-black">{{ $title }}</span>
                                    <span class="block text-xs text-[#7A5A52] mt-0.5">{{ $text }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
