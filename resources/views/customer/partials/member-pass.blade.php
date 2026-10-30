{{--
    Kartu member digital (Modul 23 — dipindah dari My Club ke Profile): paket, kode, masa aktif, QR kartu, sisa kuota per
    fasilitas. QR digambar di browser dari qr_pass_hash (public/js/qrcode.min.js, tanpa layanan pihak ketiga).
    @param \App\Models\Membership\UserMembership $membership (with plan, balances)
--}}
@php
    $facilityService = app(\App\Services\Membership\MembershipFacilityService::class);
    $endDate = $membership->end_date ? \Carbon\Carbon::parse($membership->end_date) : null;
    $daysLeft = $endDate ? (int) now()->startOfDay()->diffInDays($endDate->copy()->startOfDay(), false) : null;
@endphp
<div class="relative overflow-hidden rounded-xl border-2 border-[#662721] shadow-[0_15px_35px_rgba(102,39,33,0.18)] p-5 sm:p-6"
     style="background: linear-gradient(135deg, #F7F0DB 0%, #FFFFFF 100%);">
    <div class="flex flex-col md:flex-row gap-5 md:items-center">
        @if($membership->qr_pass_hash)
            <div class="shrink-0 self-center md:self-auto p-2 bg-white rounded-lg border border-[#E6DAC0] shadow-sm">
                <div class="w-36 h-36" data-member-qr="{{ $membership->qr_pass_hash }}" aria-label="{{ __('membership.pass.qr_label') }}"></div>
            </div>
        @endif

        <div class="flex-1 min-w-0 space-y-3">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase bg-[#F7F0DB] text-[#662721] border border-[#E6DAC0]">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ __('membership.pass.title') }}
                </div>
                <div class="font-display font-black text-2xl text-[#4F2F2A] mt-2">{{ $membership->plan?->name ?? 'Membership' }}</div>
                <div class="text-xs font-mono font-bold text-[#662721] mt-0.5">{{ __('membership.pass.code', ['code' => $membership->membership_code]) }}</div>
                <div class="text-xs text-[#7A5A52] mt-1">
                    {{ __('membership.pass.active') }}
                    @if($membership->start_date) {{ __('membership.pass.from') }} <strong>{{ \Carbon\Carbon::parse($membership->start_date)->translatedFormat('d M Y') }}</strong> @endif
                    @if($endDate)
                        {{ __('membership.pass.until') }} <strong>{{ $endDate->translatedFormat('d M Y') }}</strong>
                        <span class="{{ $daysLeft !== null && $daysLeft <= 7 ? 'text-rose-700 font-bold' : '' }}">{{ trans_choice('membership.pass.days_left', max(0, $daysLeft)) }}</span>
                    @else
                        {{ __('membership.pass.no_end_date') }}
                    @endif
                </div>
            </div>

            @if($membership->balances->isNotEmpty())
                <div class="grid grid-cols-2 sm:grid-cols-3 2xl:grid-cols-4 gap-2.5">
                    @foreach($membership->balances as $bal)
                        <div class="p-3 rounded-lg bg-white border border-[#E6DAC0] shadow-sm text-center min-w-0">
                            <div class="text-[10px] font-extrabold uppercase tracking-wider text-[#662721] truncate">{{ $facilityService->name($bal->facility) }}</div>
                            <div class="font-display font-black text-lg text-[#4F2F2A] mt-0.5 truncate">
                                @if($bal->quota_type === 'HOURS')
                                    {{ __('membership.pass.hours', ['value' => (float) $bal->remaining_quota]) }}
                                @elseif($bal->quota_type === 'VISITS')
                                    {{ $bal->initial_quota ? __('membership.pass.visits', ['value' => (float) $bal->remaining_quota]) : __('membership.pass.unlimited') }}
                                @elseif((float) $bal->discount_percent > 0)
                                    {{ __('membership.pass.percent_off', ['value' => (float) $bal->discount_percent]) }}
                                @else
                                    {{ __('membership.pass.included') }}
                                @endif
                            </div>
                            <div class="text-[10px] text-[#7A5A52] mt-0.5 truncate">
                                @if((float) $bal->discount_percent > 0 && $bal->quota_type !== 'NONE')
                                    {{ __('membership.pass.plus_percent_off', ['value' => (float) $bal->discount_percent]) }}
                                @elseif($bal->booking_priority_days > 0)
                                    {{ __('membership.pass.priority_booking', ['days' => $bal->booking_priority_days]) }}
                                @else
                                    {{ __('membership.pass.active_benefit') }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script src="{{ asset('js/qrcode.min.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (! window.QRCode) return;
                document.querySelectorAll('[data-member-qr]').forEach(function (el) {
                    new QRCode(el, { text: el.getAttribute('data-member-qr'), width: 220, height: 220, colorDark: '#000000', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.H });
                    el.querySelectorAll('img, canvas').forEach(function (node) { node.style.width = '100%'; node.style.height = '100%'; });
                });
            });
        </script>
    @endpush
@endonce
