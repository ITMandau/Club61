{{-- Voucher Saya (Modul 23) — gaya kupon: tab Tersedia / Riwayat. Data dari CustomerAccountService::vouchers().
     Warna potongan kiri: hijau tua = voucher saldo, emas = jam corporate, (nanti) merah = promo marketing. --}}
<x-app-layout>
    @php
        $stubs = [
            'CREDIT' => ['bg' => '#14532D', 'label' => 'CREDIT'],
            'CORPORATE' => ['bg' => '#A16207', 'label' => 'CORPORATE'],
            'PROMO' => ['bg' => '#B91C1C', 'label' => 'PROMO'],
        ];
        $statusLabels = [
            \App\Services\Customer\CustomerAccountService::USED => 'Used up',
            \App\Services\Customer\CustomerAccountService::EXPIRED => 'Expired',
            \App\Services\Customer\CustomerAccountService::INACTIVE => 'Deactivated',
        ];
        $list = $tab === 'history' ? $history : $available;
        $tabClass = fn (bool $on) => 'flex-1 sm:flex-none inline-flex items-center justify-center gap-2 h-11 px-5 rounded-lg text-xs font-black uppercase tracking-wider transition-colors '
            .($on ? 'bg-[#662721] text-[#F7F0DB] shadow-md' : 'text-[#662721] hover:bg-[#F7F0DB]');
    @endphp

    <div class="py-6 sm:py-8 text-[#4F2F2A]" x-data="{ toast: '', copy(code) { navigator.clipboard && navigator.clipboard.writeText(code); this.toast = 'Code copied'; clearTimeout(this.t); this.t = setTimeout(() => this.toast = '', 1800); } }">
        <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16 space-y-5">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-xl bg-white/95 border border-[#E6DAC0] shadow-sm">
                <div class="flex items-center gap-3.5">
                    <a href="{{ route('profile.edit') }}" class="p-2.5 rounded-lg bg-[#F7F0DB] border border-[#E6DAC0] text-[#662721]" title="Back to Profile">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" /></svg>
                    </a>
                    <div>
                        <h1 class="font-display font-black text-xl sm:text-2xl">My Vouchers</h1>
                        <p class="text-xs text-[#7A5A52] mt-0.5">Your vouchers appear automatically at checkout — or show the code at the front desk.</p>
                    </div>
                </div>
                <div class="flex gap-1.5 p-1 rounded-xl bg-white border border-[#E6DAC0]" role="tablist">
                    <a href="{{ route('profile.vouchers') }}" role="tab" aria-selected="{{ $tab === 'available' ? 'true' : 'false' }}" class="{{ $tabClass($tab === 'available') }}">
                        Available
                        <span class="min-w-[20px] h-5 px-1.5 rounded-full text-[10px] flex items-center justify-center {{ $tab === 'available' ? 'bg-[#F7F0DB] text-[#662721]' : 'bg-[#F7F0DB] text-[#662721]' }}">{{ $available->count() }}</span>
                    </a>
                    <a href="{{ route('profile.vouchers', ['tab' => 'history']) }}" role="tab" aria-selected="{{ $tab === 'history' ? 'true' : 'false' }}" class="{{ $tabClass($tab === 'history') }}">
                        History
                        <span class="min-w-[20px] h-5 px-1.5 rounded-full text-[10px] flex items-center justify-center bg-[#F7F0DB] text-[#662721]">{{ $history->count() }}</span>
                    </a>
                </div>
            </div>

            @if($list->isEmpty())
                <div class="flex flex-col items-center text-center gap-3 py-14 px-6 rounded-xl bg-white/95 border border-dashed border-[#E6DAC0]">
                    <div class="w-16 h-16 rounded-full bg-[#F7F0DB] border border-[#E6DAC0] text-[#662721] flex items-center justify-center">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
                    </div>
                    <div class="font-display font-black text-lg">{{ $tab === 'history' ? 'No voucher history yet' : 'No vouchers yet' }}</div>
                    <p class="text-xs text-[#7A5A52] max-w-sm">{{ $tab === 'history' ? 'Vouchers you have used up, or that have expired, will show up here.' : 'Vouchers you receive will show up here and at checkout.' }}</p>
                    @if($tab !== 'history')
                        <a href="{{ route('customer.booking') }}" class="bk-tap mt-1 inline-flex items-center justify-center h-11 px-6 rounded-lg bg-[#662721] hover:bg-[#511D18] text-[#F7F0DB] text-xs font-black uppercase tracking-wider shadow-md">Book a Court</a>
                    @endif
                </div>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-4">
                    @foreach($list as $v)
                        @php
                            $stub = $stubs[$v['kind']] ?? $stubs['PROMO'];
                            $usable = $v['status'] === \App\Services\Customer\CustomerAccountService::AVAILABLE;
                        @endphp
                        <article class="flex rounded-xl overflow-hidden bg-white border border-[#E6DAC0] shadow-sm {{ $usable ? '' : 'opacity-60 grayscale-[35%]' }}" data-voucher-kind="{{ $v['kind'] }}">
                            {{-- Potongan kiri --}}
                            <div class="relative w-28 sm:w-32 shrink-0 flex flex-col items-center justify-center text-center px-2 py-4 text-white" style="background: {{ $stub['bg'] }};">
                                <div class="font-display font-black text-xl sm:text-2xl leading-tight break-words">{{ $v['value'] }}</div>
                                <div class="mt-1 text-[9px] font-extrabold tracking-[0.2em] opacity-85">{{ $stub['label'] }}</div>
                                {{-- Lubang tiket --}}
                                <span class="absolute -right-2 -top-2 w-4 h-4 rounded-full bg-[#F7F0DB]"></span>
                                <span class="absolute -right-2 -bottom-2 w-4 h-4 rounded-full bg-[#F7F0DB]"></span>
                            </div>
                            <div class="w-0 border-l-2 border-dashed border-[#E6DAC0]"></div>

                            {{-- Isi kanan --}}
                            <div class="flex-1 min-w-0 p-4 flex flex-col gap-2">
                                <div class="flex items-start justify-between gap-2">
                                    <h2 class="text-sm font-extrabold leading-snug">{{ $v['title'] }}</h2>
                                    @if($v['is_new'])
                                        <span class="shrink-0 px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-amber-100 text-amber-800 border border-amber-200">New</span>
                                    @elseif(! $usable)
                                        <span class="shrink-0 px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-gray-100 text-gray-600 border border-gray-300">{{ $statusLabels[$v['status']] ?? $v['status'] }}</span>
                                    @endif
                                </div>

                                @if($v['code'])
                                    <button type="button" @click="copy(@js($v['code']))" class="self-start inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-[#F7F0DB] border border-dashed border-[#C9B99A] font-mono text-xs font-black text-[#662721] hover:bg-[#F0E4C6] transition-colors cursor-pointer" title="Copy code">
                                        {{ $v['code'] }}
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                    </button>
                                @endif

                                <ul class="text-[11px] text-[#7A5A52] space-y-0.5">
                                    @foreach($v['terms'] as $term)
                                        <li>{{ $term }}</li>
                                    @endforeach
                                </ul>

                                <div class="mt-auto pt-1 flex items-center justify-between gap-3">
                                    <div class="text-[11px] font-bold {{ $v['expiring_soon'] ? 'text-rose-700' : 'text-[#7A5A52]' }}">
                                        @if($v['expiring_soon'])
                                            {{ $v['days_left'] <= 0 ? 'Ends today' : 'Ends in '.$v['days_left'].' '.($v['days_left'] === 1 ? 'day' : 'days') }}
                                        @elseif($v['expires_at'])
                                            {{ $usable ? 'Valid until' : ($v['status'] === \App\Services\Customer\CustomerAccountService::EXPIRED ? 'Expired on' : 'Valid until') }} {{ $v['expires_at']->timezone('Asia/Jakarta')->format('d M Y') }}
                                        @else
                                            No expiry date
                                        @endif
                                    </div>
                                    @if($usable)
                                        <a href="{{ route('customer.booking') }}" class="bk-tap shrink-0 inline-flex items-center h-9 px-4 rounded-lg bg-[#662721] hover:bg-[#511D18] text-[#F7F0DB] text-[11px] font-black uppercase tracking-wider shadow-sm">Use</a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        <div x-show="toast" x-cloak x-transition.opacity class="fixed left-1/2 -translate-x-1/2 bottom-24 md:bottom-8 z-50 px-4 py-2 rounded-lg bg-[#4F2F2A] text-[#F7F0DB] text-xs font-bold shadow-lg" x-text="toast" style="display: none;"></div>
    </div>
</x-app-layout>
