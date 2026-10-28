@php($rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.'))
@php($t = \App\Models\Setting\InvoiceTemplateSetting::current()->resolved())
Halo {{ $invoice['customer']['name'] }},

Terima kasih, pembayaran {{ $invoice['type'] }} Anda sudah kami terima. Invoice lengkap terlampir dalam bentuk PDF.

{{ $t['document_title'] }} {{ $invoice['number'] }} — {{ $t['status_label'] }}
Dibayar     : {{ $invoice['paid_at'] }}
Metode      : {{ $invoice['method'] }}{{ ! empty($invoice['va_number']) ? ' (VA '.$invoice['va_number'].')' : '' }}
Referensi   : {{ $invoice['reference'] ?? $invoice['number'] }}
@foreach($invoice['bookings'] as $booking)
Jadwal      : {{ $booking['court'] }} - {{ $booking['date'] }}, {{ $booking['time'] }}
@endforeach
@if($invoice['membership'])
Membership  : {{ $invoice['membership']['plan'] }}{{ $invoice['membership']['period'] ? ' ('.$invoice['membership']['period'].')' : '' }}
@endif
Total       : {{ $rp($invoice['grand_total']) }}

Lihat di aplikasi: {{ $invoice['invoice_url'] }}

{{ $t['business_name'] }}
{{ $t['address'] }}
{{ implode(' · ', array_filter([$t['phone'], $t['email']])) }}
Ada pertanyaan? Balas email ini — balasan Anda masuk ke tim Club 61.

{{ $t['footer_text'] }}
