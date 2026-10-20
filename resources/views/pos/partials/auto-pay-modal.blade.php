{{--
    Kartu "Bayar Otomatis" di layar kasir (App\Services\Pos\PosAutoPayment) — tampilan mengikuti gateway tagihannya:
    Midtrans (Snap embed) atau DOKU (halaman DOKU Checkout mode pop-up). Variabel: $pendingQris, $pollAction,
    $cancelAction, $simulateAction.
--}}
@if($pendingQris)
    @if(($pendingQris['gateway'] ?? 'MIDTRANS') === 'DOKU')
        @include('pos.partials.doku-qris-modal')
    @else
        @include('pos.partials.midtrans-qris-modal')
    @endif
@endif
