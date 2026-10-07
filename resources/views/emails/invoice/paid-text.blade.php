@php($rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.'))
Halo {{ $invoice['customer']['name'] }},

Terima kasih, pembayaran {{ $invoice['type'] }} Anda sudah kami terima. Invoice lengkap terlampir dalam bentuk PDF.

No. Invoice : {{ $invoice['number'] }}
Dibayar     : {{ $invoice['paid_at'] }}
Metode      : {{ $invoice['method'] }}
@foreach($invoice['bookings'] as $booking)
Jadwal      : {{ $booking['court'] }} - {{ $booking['date'] }}, {{ $booking['time'] }}
@endforeach
@if($invoice['membership'])
Membership  : {{ $invoice['membership']['plan'] }}{{ $invoice['membership']['period'] ? ' ('.$invoice['membership']['period'].')' : '' }}
@endif
Total       : {{ $rp($invoice['grand_total']) }}

Lihat di aplikasi: {{ $invoice['invoice_url'] }}

{{ $invoice['company_address'] }}
Ada pertanyaan? Balas email ini — balasan Anda masuk ke tim Club 61.
