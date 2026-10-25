<x-filament-panels::page>
    <div class="c61" style="padding: 0; gap: 1rem;">
        @include('filament.partials.c61-admin-style')
        <div class="c61-card">
            <div class="c61-card-body" style="font-size: 0.8125rem; line-height: 1.55; color: var(--c-muted); padding: 0.9rem 1.25rem;">
                Sesi coaching yang dibooking lewat POS Walk-In (tab <b style="color: var(--c-brown);">Coaching</b>). Siapkan lapangan &amp; perlengkapan sebelum sesi,
                lalu tekan <b style="color: var(--c-brown);">Ingatkan Coach</b> untuk membuka WhatsApp dengan pesan pengingat yang sudah terisi.
            </div>
        </div>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
