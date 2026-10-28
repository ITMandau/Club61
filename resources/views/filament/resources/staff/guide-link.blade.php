{{-- Modal "Link" di Karyawan & Akses → Panduan (GuideResource). --}}
@php($url = $guide->shareUrl())
<div class="space-y-4" x-data="{ copied: false, copy() {
        const input = this.$refs.link;
        const done = () => { this.copied = true; setTimeout(() => this.copied = false, 2000); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(input.value).then(done); return; }
        input.select(); document.execCommand('copy'); done();
    } }">
    @if ($guide->is_active)
        <div class="rounded-lg border border-success-200 bg-success-50 px-3 py-2 text-sm text-success-700">
            Link <strong>aktif</strong> — siapa pun yang memegang link ini bisa membaca panduan (tanpa login, tanpa unduh).
        </div>
    @else
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
            Link <strong>nonaktif</strong> — belum bisa dibuka. Aktifkan dari menu ⋮ setelah dicek lewat Pratinjau.
        </div>
    @endif

    <div class="flex gap-2">
        <input x-ref="link" type="text" readonly value="{{ $url }}" x-on:focus="$el.select()"
               class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-xs text-gray-800">
        <button type="button" x-on:click="copy()"
                class="shrink-0 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-700">
            <span x-show="! copied">Salin link</span>
            <span x-show="copied" x-cloak>Tersalin</span>
        </button>
    </div>

    <p class="text-xs leading-relaxed text-gray-500">
        Bagikan hanya ke karyawan. Halaman yang dibuka berupa gambar dengan watermark tanggal-jam &amp; IP pembuka, dan setiap
        pembukaan dicatat di Log akses. Kalau link tersebar ke orang yang salah, pakai <strong>Buat ulang link</strong>.
    </p>
</div>
