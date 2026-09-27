{{--
    Tab navigasi atas buat pindah antar-halaman POS (Walk-In Booking <-> Jual Membership) 1 klik,
    tanpa melebur logic/form kedua halaman jadi satu file. Tiap tab = link biasa ke route Filament
    page masing-masing (bukan Livewire component gabungan), jadi masing-masing tetap 100% independen.

    Parameter:
    - $activePos: 'walkin' | 'membership'
--}}
<div style="display:flex; gap:0.5rem; padding:0.5rem; background:#FAF5E8; border:1.5px solid #DFC387; border-radius:14px; margin-bottom:0.75rem; flex-wrap:wrap;">
    <a href="{{ route('filament.admin.pages.book-offline-court') }}" wire:navigate
        style="flex:1; min-width:180px; display:flex; align-items:center; justify-content:center; gap:0.5rem; padding:0.6rem 1rem; border-radius:10px; font-size:0.8125rem; font-weight:800; text-decoration:none; transition:all 0.15s;
        {{ $activePos === 'walkin'
            ? 'background:linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color:#1F170D; border:1.5px solid #B38622; box-shadow:0 2px 6px rgba(179,134,34,0.35);'
            : 'background:#FFFFFF; color:#7A643E; border:1.5px solid #DFC387;' }}">
        <svg style="width:16px; height:16px; flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        POS Walk-In Booking
    </a>
    <a href="{{ route('filament.admin.pages.jual-membership') }}" wire:navigate
        style="flex:1; min-width:180px; display:flex; align-items:center; justify-content:center; gap:0.5rem; padding:0.6rem 1rem; border-radius:10px; font-size:0.8125rem; font-weight:800; text-decoration:none; transition:all 0.15s;
        {{ $activePos === 'membership'
            ? 'background:linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color:#1F170D; border:1.5px solid #B38622; box-shadow:0 2px 6px rgba(179,134,34,0.35);'
            : 'background:#FFFFFF; color:#7A643E; border:1.5px solid #DFC387;' }}">
        <svg style="width:16px; height:16px; flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        </svg>
        POS Jual Membership
    </a>
</div>
