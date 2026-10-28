{{-- Modal "Link" di Karyawan & Akses → Panduan (GuideResource). Gaya inline: kelas Tailwind bebas tidak ikut ter-build di tema panel. --}}
@php($url = $guide->shareUrl())
<div style="font-size:0.85rem; color:#4F2F2A;" x-data="{ copied: false, copy() {
        const input = this.$refs.link;
        const done = () => { this.copied = true; setTimeout(() => this.copied = false, 2000); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(input.value).then(done); return; }
        input.select(); document.execCommand('copy'); done();
    } }">
    @if ($guide->is_active)
        <div style="display:flex; gap:0.5rem; align-items:flex-start; padding:0.65rem 0.8rem; border-radius:0.6rem; background:#EEF7F1; border:1px solid #CDE8D6; color:#1F6B41;">
            <span style="width:0.5rem; height:0.5rem; margin-top:0.35rem; border-radius:999px; background:#2F7D4F; flex:none;"></span>
            <span>Link <b>aktif</b> — siapa pun yang memegang link ini bisa membaca panduan (tanpa login, tanpa unduh).</span>
        </div>
    @else
        <div style="display:flex; gap:0.5rem; align-items:flex-start; padding:0.65rem 0.8rem; border-radius:0.6rem; background:#F5F3F1; border:1px solid #E5E0DB; color:#5B514C;">
            <span style="width:0.5rem; height:0.5rem; margin-top:0.35rem; border-radius:999px; background:#9C928C; flex:none;"></span>
            <span>Link <b>nonaktif</b> — belum bisa dibuka. Aktifkan dari menu ⋮ setelah dicek lewat Pratinjau.</span>
        </div>
    @endif

    <div style="display:flex; gap:0.5rem; margin-top:0.9rem;">
        <input x-ref="link" type="text" readonly value="{{ $url }}" x-on:focus="$el.select()"
               style="flex:1; min-width:0; padding:0.6rem 0.75rem; border:1px solid #E6DAC0; border-radius:0.6rem; background:#FCFAF7; color:#2A1A17; font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:0.78rem;">
        <button type="button" x-on:click="copy()"
                style="flex:none; padding:0.6rem 1rem; border:0; border-radius:0.6rem; background:#662721; color:#FFFFFF; font-weight:600; font-size:0.85rem; cursor:pointer;">
            <span x-show="! copied">Salin link</span>
            <span x-show="copied" x-cloak>Tersalin ✓</span>
        </button>
    </div>

    <p style="margin:0.9rem 0 0; font-size:0.78rem; line-height:1.6; color:#8A7A72;">
        Bagikan hanya ke karyawan. Halaman yang dibuka berupa gambar dengan watermark tanggal-jam &amp; IP pembuka, dan setiap
        pembukaan dicatat di Log akses. Kalau link tersebar ke orang yang salah, pakai <b style="color:#4F2F2A;">Buat ulang link</b>.
    </p>
</div>
