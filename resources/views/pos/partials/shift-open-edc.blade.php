{{-- Buka shift: mesin EDC loket ini yang belum di-settle sejak kemarin (ClosesPosShift::getUnsettledEdcProperty). --}}
@if (! empty($this->unsettledEdc))
    <div style="background:#FFFBEB; border:1.5px solid #FCD34D; color:#78350F; border-radius:10px; padding:0.6rem 0.8rem; font-size:0.75rem; line-height:1.45;">
        <div style="font-weight:900;">&#9888; Mesin EDC belum di-settle sejak kemarin</div>
        <div style="margin:0.2rem 0 0.4rem;">Settle dulu di mesinnya (transaksi kemarin bisa cair H+2), lalu centang:</div>
        @foreach ($this->unsettledEdc as $terminal => $label)
            <label style="display:flex; align-items:center; gap:0.4rem; font-weight:700; cursor:pointer;">
                <input type="checkbox" wire:model="settledAtOpening.{{ $terminal }}"> Sudah saya settle — {{ $label }}
            </label>
        @endforeach
    </div>
@endif
