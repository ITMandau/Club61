{{-- Link klaim promo cara sebar C — disalin admin untuk banner / bio Instagram / WhatsApp. --}}
<div x-data="{ copied: false }" style="display:flex; gap:0.5rem; align-items:center;">
    <input type="text" readonly value="{{ $url }}" x-ref="link" @focus="$event.target.select()" data-claim-link
           style="flex:1; min-width:0; border:1.5px solid #E6DAC0; border-radius:8px; padding:0.55rem 0.7rem; font-family:monospace; font-size:0.8rem; color:#4F2F2A; background:#FCF8EE;">
    <button type="button"
            @click="navigator.clipboard?.writeText($refs.link.value).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
            style="flex-shrink:0; padding:0.55rem 0.9rem; border-radius:8px; background:#662721; color:#F7F0DB; font-weight:800; font-size:0.8rem; border:none; cursor:pointer;">
        <span x-show="!copied">Salin</span><span x-show="copied" x-cloak>Tersalin</span>
    </button>
</div>
