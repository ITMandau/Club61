<x-filament-panels::page>
    <div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-start; padding:0.9rem 1.1rem; background:#FFFDF7; border:1.5px solid #DFC387; border-radius:14px; color:#5C410F; font-size:0.8rem; line-height:1.5;">
        <div style="flex:1 1 320px;">
            <div style="font-weight:800; color:#1F170D; font-size:0.85rem;">Metode yang bisa dipilih customer saat bayar online</div>
            Berlaku untuk checkout booking padel, bayar ulang / selisih reschedule di invoice, dan pembelian membership online.
            Urutan di sini = urutan yang tampil ke customer. Metode di luar batas nominal otomatis disembunyikan.
        </div>
        <div style="flex:0 1 320px; font-size:0.75rem; color:#7A643E;">
            Aktifkan hanya metode yang <b>sudah aktif di dashboard Midtrans</b>. QRIS mengikuti ketentuan BI: maksimal
            <b>Rp10.000.000</b> per transaksi. Pembayaran di kasir (EDC / QRIS frontdesk) tidak diatur di sini.
        </div>
        @php $bookingTimes = app(\App\Services\Padel\BookingTimeService::class); @endphp
        <div style="flex:1 1 100%; padding-top:0.6rem; border-top:1px dashed #DFC387; font-size:0.75rem; color:#7A643E;">
            Slot ditahan <b>{{ $bookingTimes->holdMinutes() }} menit</b> sebelum klik bayar, lalu customer punya
            <b>{{ $bookingTimes->paymentWindowMinutes() }} menit</b> untuk membayar (batas yang sama dikirim ke Midtrans —
            pengaturan "Payment Expiry" di dashboard Midtrans tidak dipakai). Ubah lewat tombol <b>Atur Batas Waktu</b>.
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
