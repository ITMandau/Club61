{{--
    Halaman bayar DOKU SNAP buatan sendiri (Modul 27b): QRIS (QR digambar di browser dari teks QR DOKU) atau nomor
    VA (BCA / Mandiri / BRI / BNI / BSI) + cara bayar. Variabel: $display = ekspresi Alpine data pay_display (DokuSnapService::display), mis.
    'currentTicket.pay_display'. Status lunas dicek server (polling invoice yang sudah ada) — panel ini hanya tampilan.
--}}
<template x-if="{{ $display }}">
    <div data-doku-snap-panel class="rounded-xl border border-[#E6DAC0] bg-white p-4 space-y-3">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="text-[10px] font-extrabold uppercase tracking-wider text-[#662721]"
                     x-text="{{ $display }}.type === 'QRIS' ? @js(__('invoice.scan_qris')) : @js(__('invoice.transfer_va')).replace(':bank', ({{ $display }}.bank || ''))"></div>
                <div class="text-lg font-black tabular-nums text-[#4F2F2A]" x-text="'Rp ' + formatNumber({{ $display }}.amount)"></div>
            </div>
            <div class="shrink-0 text-right">
                <div class="text-[10px] text-[#7A5A52]">{{ __('invoice.pay_within') }}</div>
                <div class="text-sm font-black tabular-nums text-[#662721]" x-text="snapCountdown({{ $display }}.expires_at)"></div>
            </div>
        </div>

        {{-- QRIS --}}
        <template x-if="{{ $display }}.type === 'QRIS'">
            <div class="space-y-2.5 text-center">
                <div class="inline-block p-3 bg-white rounded-xl border-2 border-[#E6DAC0]">
                    <div class="text-[11px] font-black tracking-[0.25em] text-[#4F2F2A] mb-1.5">QRIS</div>
                    <div x-effect="renderQr($el, {{ $display }}.qr_content, 0, 'M')" role="img" aria-label="{{ __('invoice.qris_aria') }}"
                         class="w-56 h-56 mx-auto bg-white"></div>
                </div>
                <p class="text-[11px] text-[#7A5A52] leading-snug">{{ __('invoice.qris_help') }}</p>
                <button type="button" @click="downloadSnapQr($el.closest('[data-doku-snap-panel]'))"
                        class="bk-tap h-9 px-4 rounded-lg border border-[#E6DAC0] bg-[#F7F0DB] text-[#662721] text-xs font-bold">{{ __('invoice.save_qr') }}</button>
            </div>
        </template>

        {{-- Virtual Account --}}
        <template x-if="{{ $display }}.type === 'VA'">
            <div class="space-y-3">
                <div class="p-3 rounded-lg bg-[#F7F0DB] border border-[#E6DAC0] flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[10px] text-[#7A5A52]" x-text="@js(__('invoice.va_number')).replace(':bank', ({{ $display }}.bank || ''))"></div>
                        <div class="font-mono font-black text-lg tracking-wider text-[#4F2F2A] break-all" x-text="{{ $display }}.va_number"></div>
                    </div>
                    <button type="button" @click="copySnapText({{ $display }}.va_number)"
                            class="bk-tap shrink-0 h-9 px-3 rounded-lg bg-[#662721] text-[#F7F0DB] text-xs font-bold">{{ __('invoice.copy') }}</button>
                </div>
                <template x-if="{{ $display }}.bank === 'BCA'">
                    <div class="space-y-1.5 text-[11px] text-[#4F2F2A]">
                        <details class="rounded-lg border border-[#E6DAC0] px-3 py-2">
                            <summary class="font-bold cursor-pointer">BCA mobile (m-BCA)</summary>
                            <ol class="list-decimal pl-4 mt-1.5 space-y-0.5 text-[#7A5A52]">
                                <li>{!! __('invoice.bca_mobile_1') !!}</li>
                                <li>{!! __('invoice.bca_mobile_2') !!}</li>
                                <li>{!! __('invoice.bca_mobile_3') !!}</li>
                            </ol>
                        </details>
                        <details class="rounded-lg border border-[#E6DAC0] px-3 py-2">
                            <summary class="font-bold cursor-pointer">KlikBCA</summary>
                            <ol class="list-decimal pl-4 mt-1.5 space-y-0.5 text-[#7A5A52]">
                                <li>{!! __('invoice.klikbca_1') !!}</li>
                                <li>{!! __('invoice.klikbca_2') !!}</li>
                                <li>{!! __('invoice.klikbca_3') !!}</li>
                            </ol>
                        </details>
                        <details class="rounded-lg border border-[#E6DAC0] px-3 py-2">
                            <summary class="font-bold cursor-pointer">ATM BCA</summary>
                            <ol class="list-decimal pl-4 mt-1.5 space-y-0.5 text-[#7A5A52]">
                                <li>{!! __('invoice.atm_bca_1') !!}</li>
                                <li>{!! __('invoice.atm_bca_2') !!}</li>
                                <li>{!! __('invoice.atm_bca_3') !!}</li>
                            </ol>
                        </details>
                    </div>
                </template>
                {{-- Bank lain (Mandiri / BRI / BNI / BSI): langkah umum — nama menu tiap aplikasi bank berbeda. --}}
                <template x-if="{{ $display }}.bank !== 'BCA'">
                    <ol class="list-decimal pl-4 space-y-0.5 text-[11px] text-[#7A5A52]">
                        <li>{{ __('invoice.other_bank_1_before') }}<b x-text="({{ $display }}.bank || 'bank')"></b>{{ __('invoice.other_bank_1_after') }}</li>
                        <li>{!! __('invoice.other_bank_2') !!}</li>
                        <li>{!! __('invoice.other_bank_3') !!}</li>
                    </ol>
                </template>
            </div>
        </template>

        <div class="flex items-center gap-2 text-[11px] text-[#662721]">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
            <span>{{ __('invoice.auto_confirm') }}</span>
        </div>
        <template x-if="{{ $display }}.sandbox">
            <p class="text-[10px] leading-snug rounded-lg px-2.5 py-1.5 bg-amber-50 border border-amber-200 text-amber-900">{{ __('invoice.sandbox_note') }}</p>
        </template>
    </div>
</template>
