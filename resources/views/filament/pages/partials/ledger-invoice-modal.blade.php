{{-- Pembungkus invoice salinan admin di Buku Transaksi: isi struk + tombol cetak (jendela cetak terpisah). --}}
<div x-data="{
        print() {
            const html = this.$refs.doc.innerHTML;
            const w = window.open('', '_blank', 'width=480,height=720');
            if (! w) { return; }
            w.document.write('<!doctype html><html><head><meta charset=\'utf-8\'><title>Salinan Admin</title><style>body{margin:16px;font-family:monospace;} *{-webkit-print-color-adjust:exact;print-color-adjust:exact;}</style></head><body>' + html + '</body></html>');
            w.document.close();
            w.focus();
            w.onload = () => { w.print(); };
            setTimeout(() => { try { w.print(); } catch (e) {} }, 400);
        }
    }" style="display:flex; flex-direction:column; align-items:center; gap:0.75rem;">
    <div x-ref="doc" style="width:100%; display:flex; justify-content:center;">
        @include($view, $data)
    </div>
    <button type="button" x-on:click="print()"
        style="padding:0.5rem 1.1rem; border-radius:10px; background:#D4AF37; color:#1F170D; font-weight:800; font-size:0.8rem; border:1.5px solid #B8932A;">
        Cetak Salinan
    </button>
</div>
