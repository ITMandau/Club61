{{-- Pilihan QRIS di layar kasir: "Bayar Otomatis" (popup QR / VA yang sama dengan checkout online) = utama, QRIS manual
     (input RRN) = cadangan. Metode otomatis = yang dicentang "Tampil di Kasir" di menu Metode Pembayaran Online.
     Butuh properti Livewire $qrisMode & $posOnlineMethod; pemanggil menyembunyikan form manual lewat
     PosMidtransQrisService::resolveMethod() (null = tidak ada metode otomatis → hanya manual). --}}
@php($posMethods = app(\App\Services\Payment\OnlinePaymentMethodService::class)->forPos(isset($grandTotal) ? (float) $grandTotal : null))
@if($posMethods !== [])
    @php($activeAuto = \App\Services\Pos\PosMidtransQrisService::resolveMethod($posOnlineMethod, isset($grandTotal) ? (float) $grandTotal : null))
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; margin-bottom:0.85rem;">
        @foreach(['MIDTRANS' => ['Bayar Otomatis', 'QR / VA tampil di layar, lunas terkonfirmasi otomatis'], 'MANUAL' => ['QRIS Manual', 'Cadangan: QRIS statis + input RRN']] as $mode => [$modeLabel, $modeHint])
            <button type="button" wire:click="$set('qrisMode', '{{ $mode }}')"
                style="text-align:left; padding:0.6rem 0.75rem; border-radius:10px; cursor:pointer; border:1.5px solid {{ $qrisMode === $mode ? '#B38622' : '#DFC387' }}; background:{{ $qrisMode === $mode ? '#FAF2DE' : '#FFFFFF' }};">
                <div style="font-size:0.8125rem; font-weight:900; color:#1F170D;">{{ $modeLabel }}</div>
                <div style="font-size:0.6875rem; color:#7A643E;">{{ $modeHint }}</div>
            </button>
        @endforeach
    </div>

    @if($qrisMode === 'MIDTRANS')
        @if(count($posMethods) > 1)
            <div style="display:flex; flex-wrap:wrap; gap:0.4rem; margin-bottom:0.65rem;">
                @foreach($posMethods as $pm)
                    <button type="button" wire:click="$set('posOnlineMethod', '{{ $pm['code'] }}')"
                        style="padding:0.4rem 0.75rem; border-radius:999px; font-size:0.75rem; font-weight:800; cursor:pointer; white-space:nowrap; border:1.5px solid {{ $activeAuto === $pm['code'] ? '#B38622' : '#DFC387' }}; background:{{ $activeAuto === $pm['code'] ? '#FAF2DE' : '#FFFFFF' }}; color:#1F170D;">
                        {{ $pm['label'] }}
                    </button>
                @endforeach
            </div>
        @endif
        <div style="background:#FAF5E8; border:1px dashed #DFC387; border-radius:10px; padding:0.85rem 1rem; font-size:0.8125rem; line-height:1.55; color:#5C410F;">
            Klik tombol bayar → popup <strong>{{ collect($posMethods)->firstWhere('code', $activeAuto)['label'] ?? $activeAuto }}</strong> muncul di layar
            (sama seperti checkout online). Struk keluar otomatis setelah pembayaran diterima — tidak perlu input RRN.
        </div>
    @endif
@endif
