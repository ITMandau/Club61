{{-- Email invoice lunas (App\Mail\OrderInvoiceMail). Gaya inline — klien email tidak membaca CSS eksternal. PDF invoice terlampir. --}}
@php($rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.'))
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice['number'] }}</title>
</head>
<body style="margin:0; padding:0; background:#F6F1E6; font-family:Arial, Helvetica, sans-serif; color:#1F170D;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F1E6; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#FFFFFF; border:2px solid #D4AF37; border-radius:16px;">
                <tr>
                    <td style="background:#1F170D; border-radius:14px 14px 0 0; padding:20px 24px; text-align:center;">
                        <div style="font-size:20px; font-weight:bold; letter-spacing:4px; color:#FFFFFF;">CLUB 61</div>
                        <div style="font-size:10px; letter-spacing:4px; color:#F5E2B5; margin-top:2px;">PADEL COURT</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px 24px 6px;">
                        <p style="margin:0 0 6px; font-size:15px;">Halo {{ $invoice['customer']['name'] }},</p>
                        <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#3B2B11;">
                            Terima kasih, pembayaran <strong>{{ $invoice['type'] }}</strong> Anda sudah kami terima.
                            Invoice lengkap terlampir dalam bentuk PDF.
                        </p>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FAF5E8; border:1px solid #EFE3C6; border-radius:10px;">
                            <tr>
                                <td style="padding:12px 14px; font-size:12px; color:#6B5738;">No. Invoice</td>
                                <td style="padding:12px 14px; font-size:13px; font-weight:bold; text-align:right;">{{ $invoice['number'] }}</td>
                            </tr>
                            <tr>
                                <td style="padding:0 14px 12px; font-size:12px; color:#6B5738;">Dibayar</td>
                                <td style="padding:0 14px 12px; font-size:13px; text-align:right;">{{ $invoice['paid_at'] }}</td>
                            </tr>
                            <tr>
                                <td style="padding:0 14px 12px; font-size:12px; color:#6B5738;">Metode</td>
                                <td style="padding:0 14px 12px; font-size:13px; text-align:right;">{{ $invoice['method'] }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                @if($invoice['bookings'])
                    <tr>
                        <td style="padding:16px 24px 0;">
                            <div style="font-size:11px; font-weight:bold; letter-spacing:1px; color:#8C6418; text-transform:uppercase; margin-bottom:6px;">Jadwal Main</div>
                            @foreach($invoice['bookings'] as $booking)
                                <div style="font-size:13px; line-height:1.5; padding:8px 0; border-bottom:1px solid #F3E8CE;">
                                    <strong>{{ $booking['court'] }}</strong><br>
                                    <span style="color:#6B5738;">{{ $booking['date'] }} &bull; {{ $booking['time'] }}</span>
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @endif

                @if($invoice['membership'])
                    <tr>
                        <td style="padding:16px 24px 0;">
                            <div style="font-size:11px; font-weight:bold; letter-spacing:1px; color:#8C6418; text-transform:uppercase; margin-bottom:6px;">Membership</div>
                            <div style="font-size:13px; line-height:1.5;">
                                <strong>{{ $invoice['membership']['plan'] }}</strong><br>
                                @if($invoice['membership']['period'])<span style="color:#6B5738;">Masa aktif: {{ $invoice['membership']['period'] }}</span>@endif
                            </div>
                        </td>
                    </tr>
                @endif

                <tr>
                    <td style="padding:18px 24px 4px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">
                            <tr>
                                <td style="padding:10px 0 0; border-top:2px solid #1F170D; font-weight:bold; font-size:15px;">Total Dibayar</td>
                                <td style="padding:10px 0 0; border-top:2px solid #1F170D; font-weight:bold; font-size:15px; text-align:right;">{{ $rp($invoice['grand_total']) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:20px 24px 22px;">
                        <a href="{{ $invoice['invoice_url'] }}" target="_blank" rel="noopener"
                           style="display:inline-block; background:#D4AF37; color:#241604; font-size:14px; font-weight:bold; text-decoration:none; padding:12px 26px; border-radius:10px; border:1px solid #B38622;">
                            Lihat di Aplikasi Club 61
                        </a>
                    </td>
                </tr>
                <tr>
                    <td style="border-top:1px solid #EFE3C6; padding:14px 24px; text-align:center; font-size:11px; line-height:1.6; color:#9E8A68;">
                        {{ $invoice['company_address'] }}<br>
                        Ada pertanyaan? Balas email ini atau hubungi kami — balasan Anda masuk ke tim Club 61.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
