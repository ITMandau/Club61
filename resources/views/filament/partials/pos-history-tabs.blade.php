{{--
    Tab "Kasir | Riwayat Transaksi" untuk halaman POS (Walk-In Padel & Jual Membership).
    Param: $isHistory (bool), $canShowHistory (bool).
--}}
@if ($canShowHistory)
    <div style="display:flex; gap:0.4rem; margin-bottom:0.75rem;">
        <button type="button" wire:click="showCashier"
            style="padding:0.45rem 1rem; border-radius:9px; font-size:0.75rem; font-weight:800; cursor:pointer;
            {{ ! $isHistory ? 'background:#1F170D; color:#F0DB9D; border:1.5px solid #1F170D;' : 'background:#FFFFFF; color:#7A643E; border:1.5px solid #DFC387;' }}">
            Kasir
        </button>
        <button type="button" wire:click="showHistory"
            style="padding:0.45rem 1rem; border-radius:9px; font-size:0.75rem; font-weight:800; cursor:pointer;
            {{ $isHistory ? 'background:#1F170D; color:#F0DB9D; border:1.5px solid #1F170D;' : 'background:#FFFFFF; color:#7A643E; border:1.5px solid #DFC387;' }}">
            Riwayat Transaksi
        </button>
    </div>
@endif
