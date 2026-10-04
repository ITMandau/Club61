<x-filament-panels::page>
    <div style="padding:0.85rem 1.1rem; background:#FFFDF7; border:1.5px solid #DFC387; border-radius:14px; color:#5C410F; font-size:0.8rem; line-height:1.55;">
        <div style="font-weight:800; color:#1F170D; font-size:0.85rem;">Uang customer yang harus dikembalikan</div>
        Kelebihan bayar, pembayaran ganda, dan uang yang masuk setelah booking dibatalkan / hangus. Kembalikan uangnya dulu
        (transfer, void EDC, atau refund di Dashboard Midtrans), lalu tekan <b>Proses</b> dan isi nomor referensinya —
        refund otomatis tercatat di Buku Transaksi sebagai uang keluar.
    </div>

    {{ $this->table }}
</x-filament-panels::page>
