{{-- PDF invoice lunas (lampiran OrderInvoiceMail). dompdf: tabel + CSS sederhana saja (tanpa flex / grid). --}}
@php($rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.'))
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice['number'] }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1F170D; }
        .head { width: 100%; border-bottom: 2px solid #1F170D; padding-bottom: 8px; margin-bottom: 12px; }
        .brand { font-size: 18px; font-weight: bold; letter-spacing: 3px; }
        .muted { color: #6B5738; }
        .title { font-size: 14px; font-weight: bold; text-align: right; }
        .paid { display: inline-block; margin-top: 4px; padding: 2px 8px; border: 1.5px solid #15803D; color: #15803D; font-weight: bold; font-size: 10px; border-radius: 4px; }
        table.info { width: 100%; margin-bottom: 12px; }
        table.info td { vertical-align: top; padding: 2px 0; }
        .label { font-size: 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #8C6418; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.items th { background: #FAF5E8; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; padding: 6px; border-bottom: 1px solid #DFC387; }
        table.items td { padding: 6px; border-bottom: 1px solid #F3E8CE; }
        .num { text-align: right; white-space: nowrap; }
        table.totals { width: 55%; margin-left: 45%; border-collapse: collapse; }
        table.totals td { padding: 3px 6px; }
        table.totals tr.grand td { border-top: 2px solid #1F170D; font-size: 12px; font-weight: bold; padding-top: 6px; }
        .section { margin-bottom: 10px; }
        .foot { margin-top: 18px; padding-top: 8px; border-top: 1px solid #EFE3C6; font-size: 8px; color: #9E8A68; text-align: center; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="brand">CLUB 61</div>
                <div class="muted">{{ $invoice['company_address'] }}</div>
            </td>
            <td class="title">
                INVOICE<br>
                <span class="muted" style="font-size:10px; font-weight:normal;">{{ $invoice['number'] }}</span><br>
                <span class="paid">LUNAS</span>
            </td>
        </tr>
    </table>

    <table class="info">
        <tr>
            <td style="width:50%;">
                <div class="label">Ditagihkan kepada</div>
                <strong>{{ $invoice['customer']['name'] }}</strong><br>
                @if($invoice['customer']['email']){{ $invoice['customer']['email'] }}<br>@endif
                @if($invoice['customer']['phone']){{ $invoice['customer']['phone'] }}@endif
            </td>
            <td style="width:50%; text-align:right;">
                <div class="label">Pembayaran</div>
                {{ $invoice['type'] }}<br>
                Dibayar {{ $invoice['paid_at'] }}<br>
                {{ $invoice['method'] }}
                @if($invoice['va_number'])<br>VA {{ $invoice['va_number'] }}@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr><th>Item</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Subtotal</th></tr>
        </thead>
        <tbody>
            @foreach($invoice['lines'] as $line)
                <tr>
                    <td>{{ $line['name'] }}</td>
                    <td class="num">{{ $line['quantity'] }}</td>
                    <td class="num">{{ $rp($line['unit_price']) }}</td>
                    <td class="num">{{ $rp($line['subtotal']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($invoice['bookings'])
        <div class="section">
            <div class="label">Jadwal Main</div>
            @foreach($invoice['bookings'] as $booking)
                <div>{{ $booking['court'] }} &mdash; {{ $booking['date'] }}, {{ $booking['time'] }}@if($booking['code']) <span class="muted">({{ $booking['code'] }})</span>@endif</div>
            @endforeach
        </div>
    @endif

    @if($invoice['membership'])
        <div class="section">
            <div class="label">Membership</div>
            <div>{{ $invoice['membership']['plan'] }}@if($invoice['membership']['code']) <span class="muted">({{ $invoice['membership']['code'] }})</span>@endif</div>
            @if($invoice['membership']['period'])<div class="muted">Masa aktif: {{ $invoice['membership']['period'] }}</div>@endif
        </div>
    @endif

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">{{ $rp($invoice['subtotal']) }}</td></tr>
        @if($invoice['discount'] > 0)
            <tr><td>Diskon{{ $invoice['voucher_code'] ? ' ('.$invoice['voucher_code'].')' : '' }}</td><td class="num">-{{ $rp($invoice['discount']) }}</td></tr>
        @endif
        @if($invoice['service_charge'] > 0)
            <tr><td>{{ $invoice['service_charge_name'] }}</td><td class="num">{{ $rp($invoice['service_charge']) }}</td></tr>
        @endif
        @if($invoice['tax'] > 0)
            <tr><td>{{ $invoice['tax_name'] }}</td><td class="num">{{ $rp($invoice['tax']) }}</td></tr>
        @endif
        <tr class="grand"><td>Total Dibayar</td><td class="num">{{ $rp($invoice['grand_total']) }}</td></tr>
    </table>

    <div class="foot">
        Invoice ini dibuat otomatis oleh sistem Club 61 dan sah tanpa tanda tangan.
    </div>
</body>
</html>
