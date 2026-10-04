<x-app-layout>
    @php
        // Nama fasilitas & teks benefit dari Master Fasilitas (MembershipFacilityService) — bukan teks tetap di view.
        $facilityService = app(\App\Services\Membership\MembershipFacilityService::class);

        $plansData = $allPlans->map(function($p) {
            return [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'description' => (string) ($p->description ?? ''),
                'ownership_type' => $p->ownership_type,
                'duration_days' => $p->duration_days,
                'price' => (float) $p->price,
                'price_formatted' => 'Rp ' . number_format($p->price, 0, ',', '.'),
                // Rincian tagihan = TaxAndFeeService (kanal ONLINE, modul MEMBERSHIP), sama persis dengan checkout server.
                'bill' => (function () use ($p) {
                    $calc = app(\App\Services\Finance\TaxAndFeeService::class)->calculate(subtotal: (float) $p->price, discountAmount: 0, channel: 'ONLINE', module: 'MEMBERSHIP');
                    return [
                        'tax' => (float) $calc['tax_amount'],
                        'tax_name' => $calc['tax_name'] ?: 'Pajak',
                        'admin_fee' => (float) $calc['admin_fee_amount'],
                        'admin_fee_name' => $calc['admin_fee_name'] ?: 'Biaya Layanan',
                        'grand_total' => (float) $calc['grand_total'],
                    ];
                })(),
            ];
        })->keyBy('id');

        $initialPlan = $plansData->get($selectedPlanId) ?? $plansData->first();
    @endphp

    <div class="py-6 sm:py-8 text-[#1F170D]">
        <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16 space-y-6">

            <!-- Top Header & Navigation -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white/95 backdrop-blur-xl p-5 rounded-3xl border border-[#DFC387] shadow-sm">
                <div class="flex items-center gap-3.5">
                    <a href="{{ route('customer.my-club') }}" class="p-2.5 rounded-2xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] transition-colors" title="Kembali ke My Club">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="font-serif font-black text-xl sm:text-2xl text-[#1F170D]">Pilih &amp; Pembelian Paket Keanggotaan</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-[#FAF2DE] text-[#7A5818] border border-[#DFC387]">Club 61 Member</span>
                        </div>
                        <p class="text-xs text-[#7A643E] mt-0.5">Pilih paket, lihat rincian benefit lengkap, dan aktivasi keanggotaan Anda secara instan</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('customer.my-club') }}" 
                       class="px-4 py-2 rounded-2xl bg-[#FAF2DE] hover:bg-[#F3DFAD] border border-[#DFC387] text-[#7A5818] font-bold text-xs transition-colors">
                        Lihat Member Pass Aktif &rarr;
                    </a>
                </div>
            </div>

            <!-- Interactive Membership Plan Switcher (Cards & Mobile Pill Tabs) -->
            <div class="space-y-3">
                <div class="flex items-center justify-between px-1">
                    <div>
                        <h3 class="font-serif font-extrabold text-lg text-[#1F170D]">Pilih Paket Keanggotaan</h3>
                        <p class="text-xs text-[#7A643E]">Pilih salah satu paket di bawah untuk langsung melihat benefit lengkap dan melakukan pembayaran</p>
                    </div>
                    <span class="text-xs font-bold text-[#8C6418]">{{ $allPlans->count() }} Pilihan Paket</span>
                </div>

                <!-- Plan Switcher Grid (Responsive: 1 col on mobile, 2 col on tablet, 4 col on desktop) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                    @foreach($allPlans as $p)
                        @php
                            $isInitial = ($initialPlan['id'] ?? null) === $p->id;
                        @endphp
                        <button type="button" 
                                onclick="switchPlan('{{ $p->id }}')"
                                id="tab-btn-{{ $p->id }}"
                                class="plan-tab-btn text-left p-4 rounded-3xl border-2 transition-all cursor-pointer relative flex flex-col justify-between group {{ $isInitial ? 'bg-[#FAF5E8] border-[#D4AF37] shadow-md ring-2 ring-[#D4AF37]/30' : 'bg-white/95 border-[#DFC387] hover:border-[#D4AF37] hover:bg-[#FAF8F0]' }}">
                            
                            <!-- Active Indicator Dot -->
                            <div class="absolute top-3.5 right-3.5">
                                <span id="dot-{{ $p->id }}" class="w-3 h-3 rounded-full {{ $isInitial ? 'bg-[#D4AF37] ring-4 ring-[#D4AF37]/20' : 'bg-transparent border border-[#DFC387]' }} block transition-all"></span>
                            </div>

                            <div>
                                <div class="flex items-center gap-1.5 mb-1.5">
                                    <span class="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full {{ $p->ownership_type === 'ORGANIZATIONAL' ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-[#FAF2DE] text-[#7A5818] border border-[#DFC387]' }}">
                                        {{ $p->ownership_type }}
                                    </span>
                                    <span class="text-[10px] font-bold text-[#7A643E]">{{ $p->duration_days }} Hari</span>
                                </div>
                                <div class="font-serif font-black text-base text-[#1F170D] group-hover:text-[#8C6418] transition-colors">
                                    {{ $p->name }}
                                </div>
                                <div class="font-black text-sm text-[#8C6418] mt-1 font-mono">
                                    Rp {{ number_format($p->price, 0, ',', '.') }}
                                </div>
                            </div>

                            <div class="mt-3 pt-2.5 border-t border-[#DFC387]/50 flex items-center justify-between text-[11px] font-bold text-[#7A5818]">
                                <span>{{ $facilityService->headline($p) }}</span>
                                <span class="text-[#8C6418] font-extrabold group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Main Interactive Display: 2 Columns on Desktop/Tablet, Stacked on Mobile -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- Left Column (lg:col-span-7): Comprehensive Benefit Details -->
                <div class="lg:col-span-7 space-y-4">
                    <div class="bg-white/95 rounded-3xl p-6 sm:p-8 border-2 border-[#D4AF37] shadow-sm space-y-6"
                         style="background: linear-gradient(135deg, #FFFDF8 0%, #FFFFFF 100%);">
                        
                        <!-- Selected Plan Title & Subtitle -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[#DFC387]/50 pb-5">
                            <div>
                                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase bg-[#FAF2DE] text-[#7A5818] border border-[#DFC387]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#D4AF37]"></span>
                                    <span id="detailBadge">{{ $initialPlan['ownership_type'] === 'ORGANIZATIONAL' ? 'Paket Sponsor Corporate Pool' : 'Keanggotaan Individual VIP' }}</span>
                                </div>
                                <h3 id="detailPlanName" class="font-serif font-black text-2xl sm:text-3xl text-[#1F170D] mt-2">
                                    {{ $initialPlan['name'] }}
                                </h3>
                                <div class="flex flex-wrap items-center gap-2 text-xs font-bold text-[#8C6418] mt-1">
                                    <span id="detailPlanSub">{{ $initialPlan['ownership_type'] }} &bull; Durasi {{ $initialPlan['duration_days'] }} Hari</span>
                                    <span>&bull;</span>
                                    <span class="text-[#1E7E34]">Aktivasi Instan Otomatis</span>
                                </div>
                                <p id="detailPlanDesc" class="text-xs text-[#7A643E] mt-2 leading-relaxed max-w-xl {{ ($initialPlan['description'] ?? '') === '' ? 'hidden' : '' }}">{{ $initialPlan['description'] ?? '' }}</p>
                            </div>

                            <div class="text-left sm:text-right">
                                <span class="text-[10px] font-bold text-[#7A643E] uppercase tracking-wider block">Harga Paket</span>
                                <span id="detailPriceDisplay" class="font-serif font-black text-2xl text-[#8C6418]">
                                    {{ $initialPlan['price_formatted'] }}
                                </span>
                            </div>
                        </div>

                        <!-- Benefit Cards (dinamis per paket) -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="font-serif font-black text-xs sm:text-sm text-[#1F170D] uppercase tracking-wider">
                                    Rincian Hak Akses &amp; Benefit yang Didapat
                                </h4>
                            </div>

                            {{-- Kartu benefit dari Master Fasilitas + benefit paket (dulu 4 kartu dengan teks dummy). Satu grid per
                                 paket, yang tidak dipilih disembunyikan — semua teks lewat {{ }} (ter-escape). --}}
                            @foreach ($allPlans as $p)
                                @php
                                    $cards = $facilityService->presentPlan($p);
                                    $perks = array_values(array_filter((array) ($p->perks ?? []), fn ($perk) => is_string($perk) && trim($perk) !== ''));
                                @endphp
                                <div data-plan-benefits="{{ $p->id }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 {{ ($initialPlan['id'] ?? null) === $p->id ? '' : 'hidden' }}">
                                    @foreach ($cards as $card)
                                        <div class="p-4 rounded-2xl bg-white border border-[#DFC387] shadow-sm flex items-start gap-3.5 hover:border-[#D4AF37] transition-all">
                                            <div class="w-10 h-10 rounded-xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 text-[10px] font-black text-[#7A5818]">
                                                {{ $card['badge'] }}
                                            </div>
                                            <div class="text-xs">
                                                <strong class="text-[#1F170D] block font-extrabold text-sm">{{ $card['title'] }}</strong>
                                                @if ($card['description'])
                                                    <span class="text-[#7A643E] block mt-1 leading-relaxed">{{ $card['description'] }}</span>
                                                @endif
                                                @if ($card['details'])
                                                    <span class="text-[#8C6418] block mt-1 font-bold leading-relaxed">{{ implode(' • ', $card['details']) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach

                                    @if ($perks)
                                        <div class="p-4 rounded-2xl bg-white border border-[#DFC387] shadow-sm flex items-start gap-3.5 hover:border-[#D4AF37] transition-all">
                                            <div class="w-10 h-10 rounded-xl bg-[#FAF2DE] border border-[#DFC387] flex items-center justify-center shrink-0 text-[10px] font-black text-[#7A5818]">
                                                PERKS
                                            </div>
                                            <div class="text-xs">
                                                <strong class="text-[#1F170D] block font-extrabold text-sm">Privilege Member</strong>
                                                <ul class="text-[#7A643E] mt-1 leading-relaxed list-disc pl-4 space-y-0.5">
                                                    @foreach ($perks as $perk)
                                                        <li>{{ $perk }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    @endif

                                    @if (! $cards && ! $perks)
                                        <div class="sm:col-span-2 p-4 rounded-2xl border border-dashed border-[#DFC387] text-xs text-[#7A643E]">
                                            Rincian benefit paket ini belum diisi. Silakan hubungi frontdesk Club 61.
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <!-- Highlights Guarantee -->
                        <div class="p-4 rounded-2xl bg-[#FAF5E8] border border-[#DFC387] flex items-center justify-between text-xs text-[#7A643E]">
                            <div class="flex items-center gap-2 font-bold text-[#1F170D]">
                                <svg class="w-5 h-5 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                <span>Garansi Aktivasi Otomatis &amp; Perlindungan Hak Member</span>
                            </div>
                            <span class="font-mono font-bold text-[#8C6418]">Club 61 Medan</span>
                        </div>
                    </div>
                </div>

                <!-- Right Column (lg:col-span-5): Midtrans Cashless Checkout & Payment Selection -->
                <div class="lg:col-span-5 space-y-4">
                    <div class="bg-white/95 rounded-3xl p-6 sm:p-7 border-2 border-[#D4AF37] shadow-sm space-y-5"
                         style="background: linear-gradient(135deg, #FFFDF8 0%, #FFFFFF 100%);">
                        
                        <div class="border-b border-[#DFC387]/50 pb-3">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-[#8C6418] block">Langkah Terakhir</span>
                            <h4 class="font-serif font-black text-lg text-[#1F170D] mt-0.5">
                                Konfirmasi &amp; Payment
                            </h4>
                            <p class="text-xs text-[#7A643E] mt-0.5"></p>
                        </div>

                        <!-- Cashless Channel Selector -->
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-extrabold uppercase tracking-wider text-[#7A5818]">
                                    Choice of Cashless Payment
                                </label>
                                <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                    
                                </span>
                            </div>

                            @php $onlineMethods = app(\App\Services\Payment\OnlinePaymentMethodService::class)->forFrontend(); @endphp
                            <div class="space-y-2" id="paymentMethodList">
                                @foreach ($onlineMethods as $i => $m)
                                    <label data-method-option data-min="{{ $m['min_amount'] ?? '' }}" data-max="{{ $m['max_amount'] ?? '' }}"
                                        class="p-3.5 rounded-2xl border-2 border-[#DFC387] bg-white hover:bg-[#FAF2DE]/50 cursor-pointer flex items-start gap-3 transition-all text-xs font-bold text-[#1F170D] has-[:checked]:border-[#B38622] has-[:checked]:bg-[#FAF2DE] shadow-sm">
                                        <input type="radio" name="payment_method" value="{{ $m['code'] }}" @checked($i === 0) class="text-[#D4AF37] focus:ring-[#D4AF37] w-4 h-4 mt-0.5">
                                        <span class="w-8 h-8 rounded-lg bg-white border border-[#DFC387] flex items-center justify-center font-bold text-[10px] text-[#8C6418] shrink-0">{{ $m['badge'] }}</span>
                                        <div>
                                            <div class="font-extrabold text-xs">{{ $m['name'] }}</div>
                                            @if ($m['note'])
                                                <div class="text-[11px] text-[#7A643E] font-normal mt-0.5 leading-relaxed">{{ $m['note'] }}</div>
                                            @endif
                                            @if ($m['max_amount'])
                                                <div class="text-[10px] text-[#8C7A58] font-normal mt-0.5">Maksimal Rp {{ number_format($m['max_amount'], 0, ',', '.') }} per transaksi</div>
                                            @endif
                                        </div>
                                    </label>
                                @endforeach
                                <div id="noPaymentMethodNotice" class="hidden p-3.5 rounded-2xl border-2 border-dashed border-rose-200 bg-rose-50 text-xs text-rose-700 font-bold">
                                    Belum ada metode pembayaran online untuk paket ini. Silakan beli di frontdesk Club 61.
                                </div>
                            </div>
                        </div>

                        <!-- Ringkasan Tagihan -->
                        <div class="p-4 rounded-2xl bg-[#FAF8F2] border border-[#DFC387] space-y-2">
                            <div class="flex justify-between items-center text-xs text-[#7A643E]">
                                <span>Harga Paket Keanggotaan:</span>
                                <span id="billSubtotal" class="font-bold text-[#1F170D]">{{ $initialPlan['price_formatted'] }}</span>
                            </div>
                            <div id="billTaxRow" class="flex justify-between items-center text-xs text-[#7A643E] {{ $initialPlan['bill']['tax'] > 0 ? '' : 'hidden' }}">
                                <span id="billTaxName">{{ $initialPlan['bill']['tax_name'] }}:</span>
                                <span id="billTax" class="font-bold text-[#1F170D]">Rp {{ number_format($initialPlan['bill']['tax'], 0, ',', '.') }}</span>
                            </div>
                            <div id="billAdminFeeRow" class="flex justify-between items-center text-xs text-[#7A643E] {{ $initialPlan['bill']['admin_fee'] > 0 ? '' : 'hidden' }}">
                                <span id="billAdminFeeName">{{ $initialPlan['bill']['admin_fee_name'] }}:</span>
                                <span id="billAdminFee" class="font-bold text-[#1F170D]">Rp {{ number_format($initialPlan['bill']['admin_fee'], 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between items-center pt-2.5 border-t border-[#DFC387]/50 text-sm font-bold">
                                <span class="font-serif font-black text-[#1F170D]">Total Pembayaran Cashless:</span>
                                <span id="billGrandTotal" class="font-serif font-black text-xl text-[#8C6418]">Rp {{ number_format($initialPlan['bill']['grand_total'], 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <!-- Direct Pay Button -->
                        <button type="button" 
                                id="btnSubmitCheckout"
                                onclick="submitMembershipCheckout()"
                                class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-[#D4AF37] via-[#B38622] to-[#8C6418] hover:brightness-105 text-[#FAF5E6] font-black text-xs sm:text-sm uppercase tracking-wider shadow-lg hover:shadow-xl transition-all active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                            <svg class="w-4 h-4 text-[#FAF5E6]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span id="btnSubmitText">Bayar via Midtrans Snap (Cashless) &rarr;</span>
                        </button>

                        <div class="text-center text-[10px] text-[#8C7A58] leading-tight">
                            Dengan mengklik tombol bayar, Anda menyetujui syarat &amp; ketentuan membership Club 61. Transaksi dienkripsi 256-bit oleh Midtrans.
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- Luxury Club 61 Notice Dialog (Replacing browser native alert) -->
    <div id="luxuryNoticeModal" 
         class="fixed inset-0 z-50 bg-black/75 backdrop-blur-md hidden items-center justify-center p-4 transition-all duration-300">
        <div class="w-full max-w-md bg-white rounded-3xl border-2 border-[#D4AF37] shadow-2xl p-6 sm:p-8 space-y-5 text-center relative"
             style="background: linear-gradient(135deg, #FFFDF8 0%, #FFFFFF 100%);">
            
            <!-- Icon Header -->
            <div id="noticeIconContainer" class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto shadow-sm">
                <!-- SVG injected via JS -->
            </div>

            <!-- Content -->
            <div class="space-y-2">
                <h3 id="noticeTitle" class="font-serif font-black text-xl sm:text-2xl text-[#1F170D]"></h3>
                <p id="noticeMessage" class="text-xs sm:text-sm text-[#7A643E] leading-relaxed"></p>
            </div>

            <!-- Action Button -->
            <div class="pt-2">
                <button type="button" 
                        id="noticeActionBtn"
                        onclick="executeNoticeAction()"
                        class="w-full py-3.5 px-4 rounded-2xl text-[#1E160A] text-xs font-black uppercase tracking-wider block shadow-md hover:brightness-105 transition-all cursor-pointer active:scale-98"
                        style="background: linear-gradient(180deg, #F5DE9B 0%, #D4AF37 50%, #A87D18 100%); color: #1E160A; border: 1.5px solid #FFF3CD;">
                    <span id="noticeActionBtnText">OK, Lanjutkan</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Midtrans Snap Script Integration -->
    <script src="{{ config('services.midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" data-client-key="{{ config('services.midtrans.client_key') }}"></script>

    <script>
        const plansMap = @json($plansData);
        let currentSelectedPlanId = '{{ $initialPlan['id'] ?? '' }}';
        let noticeCallback = null;

        function showLuxuryNotice({ title, message, type = 'success', btnText = 'OK, Lanjutkan', onConfirm = null }) {
            noticeCallback = onConfirm;

            document.getElementById('noticeTitle').innerText = title;
            document.getElementById('noticeMessage').innerText = message;
            document.getElementById('noticeActionBtnText').innerHTML = btnText;

            const iconContainer = document.getElementById('noticeIconContainer');

            if (type === 'success') {
                iconContainer.className = 'w-16 h-16 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center mx-auto shadow-sm';
                iconContainer.innerHTML = `
                    <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                `;
            } else if (type === 'error') {
                iconContainer.className = 'w-16 h-16 rounded-2xl bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center mx-auto shadow-sm';
                iconContainer.innerHTML = `
                    <svg class="w-8 h-8 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                `;
            } else {
                iconContainer.className = 'w-16 h-16 rounded-2xl bg-[#FAF2DE] border border-[#DFC387] text-[#8C6418] flex items-center justify-center mx-auto shadow-sm';
                iconContainer.innerHTML = `
                    <svg class="w-8 h-8 text-[#8C6418]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                `;
            }

            const modal = document.getElementById('luxuryNoticeModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeLuxuryNotice() {
            const modal = document.getElementById('luxuryNoticeModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        function executeNoticeAction() {
            closeLuxuryNotice();
            if (typeof noticeCallback === 'function') {
                noticeCallback();
            }
        }

        function switchPlan(planId) {
            const plan = plansMap[planId];
            if (!plan) return;

            currentSelectedPlanId = planId;

            // 1. Update tab styling
            document.querySelectorAll('.plan-tab-btn').forEach(btn => {
                btn.classList.remove('bg-[#FAF5E8]', 'border-[#D4AF37]', 'shadow-md', 'ring-2', 'ring-[#D4AF37]/30');
                btn.classList.add('bg-white/95', 'border-[#DFC387]');
            });

            document.querySelectorAll('[id^="dot-"]').forEach(dot => {
                dot.classList.remove('bg-[#D4AF37]', 'ring-4', 'ring-[#D4AF37]/20');
                dot.classList.add('bg-transparent', 'border', 'border-[#DFC387]');
            });

            const activeBtn = document.getElementById('tab-btn-' + planId);
            if (activeBtn) {
                activeBtn.classList.remove('bg-white/95', 'border-[#DFC387]');
                activeBtn.classList.add('bg-[#FAF5E8]', 'border-[#D4AF37]', 'shadow-md', 'ring-2', 'ring-[#D4AF37]/30');
            }

            const activeDot = document.getElementById('dot-' + planId);
            if (activeDot) {
                activeDot.classList.remove('bg-transparent', 'border', 'border-[#DFC387]');
                activeDot.classList.add('bg-[#D4AF37]', 'ring-4', 'ring-[#D4AF37]/20');
            }

            // 2. Update Header & Subtitle
            document.getElementById('detailPlanName').innerText = plan.name;
            document.getElementById('detailPlanSub').innerText = plan.ownership_type + ' • Durasi ' + plan.duration_days + ' Hari';
            document.getElementById('detailBadge').innerText = plan.ownership_type === 'ORGANIZATIONAL' ? 'Paket Sponsor Corporate Pool' : 'Keanggotaan Individual VIP';
            document.getElementById('detailPriceDisplay').innerText = plan.price_formatted;

            // 3. Benefit & privilege: grid per paket sudah dirender server (Master Fasilitas) — cukup tampilkan yang dipilih.
            document.querySelectorAll('[data-plan-benefits]').forEach(grid => {
                grid.classList.toggle('hidden', grid.dataset.planBenefits !== planId);
            });
            const desc = document.getElementById('detailPlanDesc');
            if (desc) {
                desc.innerText = plan.description || '';
                desc.classList.toggle('hidden', !plan.description);
            }

            // 7. Update Bill Breakdown (pajak & biaya layanan sesungguhnya)
            const rupiah = (v) => 'Rp ' + Math.round(v).toLocaleString('id-ID');
            document.getElementById('billSubtotal').innerText = plan.price_formatted;
            document.getElementById('billTaxRow').classList.toggle('hidden', !(plan.bill.tax > 0));
            document.getElementById('billTaxName').innerText = plan.bill.tax_name + ':';
            document.getElementById('billTax').innerText = rupiah(plan.bill.tax);
            document.getElementById('billAdminFeeRow').classList.toggle('hidden', !(plan.bill.admin_fee > 0));
            document.getElementById('billAdminFeeName').innerText = plan.bill.admin_fee_name + ':';
            document.getElementById('billAdminFee').innerText = rupiah(plan.bill.admin_fee);
            document.getElementById('billGrandTotal').innerText = rupiah(plan.bill.grand_total);
            filterPaymentMethods(plan.bill.grand_total);

            // 8. Update Mobile Bar
            const mobileName = document.getElementById('mobilePlanName');
            const mobilePrice = document.getElementById('mobilePlanPrice');
            if (mobileName) mobileName.innerText = plan.name;
            if (mobilePrice) mobilePrice.innerText = plan.price_formatted;
        }

        /** Sembunyikan metode di luar batas nominal (mis. QRIS maks Rp10 juta untuk paket Corporate). */
        function filterPaymentMethods(total) {
            let firstVisible = null;
            let checkedStillVisible = false;
            document.querySelectorAll('[data-method-option]').forEach(label => {
                const min = label.dataset.min === '' ? null : parseFloat(label.dataset.min);
                const max = label.dataset.max === '' ? null : parseFloat(label.dataset.max);
                const ok = (min === null || total >= min) && (max === null || total <= max);
                const input = label.querySelector('input');
                label.classList.toggle('hidden', !ok);
                input.disabled = !ok;
                if (ok && !firstVisible) firstVisible = input;
                if (ok && input.checked) checkedStillVisible = true;
            });
            if (!checkedStillVisible && firstVisible) firstVisible.checked = true;
            document.getElementById('noPaymentMethodNotice').classList.toggle('hidden', !!firstVisible);
            document.getElementById('btnSubmitCheckout').disabled = !firstVisible;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const plan = plansMap[currentSelectedPlanId];
            if (plan) filterPaymentMethods(plan.bill.grand_total);
        });

        async function submitMembershipCheckout() {
            if (!currentSelectedPlanId) return;

            const methodInput = document.querySelector('input[name="payment_method"]:checked');
            if (!methodInput) return;
            const paymentMethod = methodInput.value;

            const btn = document.getElementById('btnSubmitCheckout');
            const btnText = document.getElementById('btnSubmitText');
            btn.disabled = true;
            btnText.innerText = 'Menghubungkan Midtrans...';

            try {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const res = await fetch('/api/v1/membership/checkout', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({
                        plan_id: currentSelectedPlanId,
                        payment_method: paymentMethod
                    })
                });

                const data = await res.json();

                // Masih ada pesanan paket LAIN yang belum dibayar → arahkan ke pesanan itu (lanjut bayar / batalkan).
                if (res.status === 409 && data.data && data.data.pending_purchase) {
                    btn.disabled = false;
                    btnText.innerText = 'Bayar via Midtrans Snap (Cashless) →';
                    showLuxuryNotice({
                        title: 'Ada Pesanan Belum Dibayar',
                        message: data.message,
                        type: 'info',
                        btnText: 'Lihat Pesanan Saya &rarr;',
                        onConfirm: function() {
                            window.location.href = '{{ route('customer.invoice') }}?membership_id=' + encodeURIComponent(data.data.pending_purchase.id);
                        }
                    });
                    return;
                }

                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Gagal memproses pembayaran membership.');
                }

                // Pesanan yang sama ternyata sudah dibayar (webhook terlambat) — jangan buka pembayaran lagi.
                if (data.data.already_paid) {
                    showLuxuryNotice({
                        title: 'Pembayaran Sudah Diterima',
                        message: data.message,
                        type: 'success',
                        btnText: 'Buka Member Pass &rarr;',
                        onConfirm: function() {
                            window.location.href = '{{ route('customer.my-club') }}';
                        }
                    });
                    return;
                }

                const payment = data.data.payment;

                // 1. Jika Simulator Mock Driver
                if (payment && payment.is_mock) {
                    showLuxuryNotice({
                        title: 'Pembayaran Berhasil!',
                        message: 'Pembayaran berhasil dikonfirmasi. Paket keanggotaan Anda telah aktif dan seluruh kuota fasilitas (Padel, Gym, Sauna) siap digunakan.',
                        type: 'success',
                        btnText: 'Buka Member Pass &rarr;',
                        onConfirm: function() {
                            window.location.href = '{{ route('customer.my-club') }}';
                        }
                    });
                    return;
                }

                // 2. Jika Midtrans Snap Token tersedia
                if (payment && payment.snap_token && window.snap && typeof window.snap.pay === 'function') {
                    btnText.innerText = 'Menunggu Pembayaran...';
                    window.snap.pay(payment.snap_token, {
                        onSuccess: function(result) {
                            showLuxuryNotice({
                                title: 'Pembayaran Berhasil!',
                                message: 'Pembayaran berhasil dikonfirmasi. Paket keanggotaan Anda telah aktif dan kuota fasilitas siap digunakan.',
                                type: 'success',
                                btnText: 'Buka Member Pass &rarr;',
                                onConfirm: function() {
                                    window.location.href = '{{ route('customer.my-club') }}';
                                }
                            });
                        },
                        onPending: function(result) {
                            showLuxuryNotice({
                                title: 'Menunggu Pembayaran',
                                message: 'Silakan selesaikan transaksi cashless Anda sesuai instruksi Midtrans sebelum batas waktu berakhir.',
                                type: 'info',
                                btnText: 'Cek Status Invoice &rarr;',
                                onConfirm: function() {
                                    window.location.href = '{{ route('customer.my-club') }}';
                                }
                            });
                        },
                        onError: function(result) {
                            btn.disabled = false;
                            btnText.innerText = 'Bayar via Midtrans Snap (Cashless) \u2192';
                            showLuxuryNotice({
                                title: 'Pembayaran Dibatalkan',
                                message: 'Transaksi tidak berhasil atau dibatalkan. Silakan coba kembali atau pilih saluran pembayaran lainnya.',
                                type: 'error',
                                btnText: 'Tutup &amp; Coba Lagi'
                            });
                        },
                        onClose: function() {
                            btn.disabled = false;
                            btnText.innerText = 'Bayar via Midtrans Snap (Cashless) \u2192';
                        }
                    });
                    return;
                }

                // 3. Fallback Payment URL
                if (payment && payment.payment_url) {
                    window.location.href = payment.payment_url;
                    return;
                }

                // 4. Fallback Default
                showLuxuryNotice({
                    title: 'Pesanan Dibuat',
                    message: 'Pesanan keanggotaan berhasil dibuat! Silakan cek menu invoice untuk instruksi pembayaran.',
                    type: 'info',
                    btnText: 'Lihat Invoice &rarr;',
                    onConfirm: function() {
                        window.location.href = '{{ route('customer.my-club') }}';
                    }
                });

            } catch (err) {
                btn.disabled = false;
                btnText.innerText = 'Bayar via Midtrans Snap (Cashless) \u2192';
                showLuxuryNotice({
                    title: 'Terjadi Kesalahan',
                    message: err.message || 'Gagal memproses pembayaran membership.',
                    type: 'error',
                    btnText: 'Tutup'
                });
            }
        }
    </script>
</x-app-layout>
