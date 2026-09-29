<x-filament-panels::page>
    @php $organizations = $this->organizations; @endphp

    @if($organizations->isEmpty())
        <div style="padding:2.5rem; text-align:center; background:#FFFFFF; border:1.5px solid #DFC387; border-radius:16px; color:#6B5738;">
            <div style="font-weight:800; color:#1F170D;">Belum ada akun sponsor / corporate.</div>
            <div style="font-size:0.8rem; margin-top:0.35rem;">Buat lewat menu Kelola Sponsor (beli paket corporate, atau "Berikan Membership Corporate" oleh super admin).</div>
        </div>
    @else
        <div style="display:flex; flex-wrap:wrap; align-items:center; gap:0.75rem;">
            <label for="sponsor-org-select" style="font-size:0.8rem; font-weight:800; color:#5C410F;">Pilih Sponsor</label>
            <select id="sponsor-org-select" wire:model.live="organizationId"
                style="min-width:320px; padding:0.55rem 0.8rem; border-radius:10px; border:1.5px solid #DFC387; background:#FFFDF7; font-size:0.85rem; font-weight:700; color:#1F170D;">
                @foreach($organizations as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
            <span style="font-size:0.75rem; color:#7A643E;">Tampilan hanya-lihat — aksi rilis voucher & roster tetap dilakukan PIC dari portalnya sendiri.</span>
        </div>

        @if($organizationId)
            <iframe wire:key="sponsor-frame-{{ $organizationId }}"
                src="{{ route('corporate.preview', ['organization' => $organizationId]) }}"
                title="Dashboard Sponsor Team"
                style="width:100%; height:calc(100vh - 230px); min-height:640px; border:1.5px solid #DFC387; border-radius:16px; background:#FBF9F5;"></iframe>
        @endif
    @endif
</x-filament-panels::page>
