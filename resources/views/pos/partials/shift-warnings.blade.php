{{-- Pengingat di layar POS: shift lupa ditutup, EDC belum di-settle menjelang 22.00 (ClosesPosShift::getShiftWarningsProperty). --}}
@foreach ($this->shiftWarnings as $warning)
    <div role="status" style="background:#FFFBEB; border:1.5px solid #FCD34D; color:#78350F; border-radius:10px; padding:0.55rem 0.8rem; font-size:0.75rem; font-weight:700; margin-bottom:0.5rem;">
        &#9888; {{ $warning }}
    </div>
@endforeach
