{{--
    Popup pembayaran Midtrans (Snap). Hanya dimuat kalau MIDTRANS_CLIENT_KEY terisi — dulu jatuh ke key palsu
    'SB-Mid-client-demo-61' sehingga popup gagal tanpa penjelasan. Tanpa key, halaman memakai halaman pembayaran
    Midtrans (redirect_url) dan tidak pernah menganggap order lunas.
--}}
@php($midtransClientKey = trim((string) config('services.midtrans.client_key')))
@if ($midtransClientKey !== '')
    <script src="{{ config('services.midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}"
        data-client-key="{{ $midtransClientKey }}"></script>
@else
    <script>console.error('MIDTRANS_CLIENT_KEY belum diisi di .env: popup pembayaran dinonaktifkan, customer diarahkan ke halaman pembayaran Midtrans.');</script>
@endif
