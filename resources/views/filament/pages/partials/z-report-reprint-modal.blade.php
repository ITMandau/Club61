{{-- Cetak ulang Z-Report dari Detail Shift Kasir — isi sama dengan struk POS (pos.partials.z-report-body), bertanda
     CETAK ULANG. Dicetak di printer thermal 58mm lewat club61PrintReceipt() seperti salinan invoice Buku Transaksi. --}}
<div x-data="{
        print() {
            if (window.club61PrintReceipt) { window.club61PrintReceipt(this.$refs.doc); } else { window.print(); }
        }
    }" style="display:flex; flex-direction:column; align-items:center; gap:0.75rem;">
    <div x-ref="doc" style="width:100%; max-width:360px; border:1px solid #E6DAC0; border-radius:10px; padding:0.75rem; background:#FFFFFF;">
        @include('pos.partials.z-report-body', ['report' => $report, 'reprint' => $reprint])
    </div>
    <button type="button" x-on:click="print()"
        style="padding:0.5rem 1.1rem; border-radius:10px; background:#662721; color:#F7F0DB; font-weight:800; font-size:0.8rem; border:1.5px solid #B8932A;">
        Cetak Z-Report
    </button>
</div>
