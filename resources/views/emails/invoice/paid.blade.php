{{--
    Email invoice lunas (App\Mail\OrderInvoiceMail) — gaya sama dengan PDF terlampir (emails/invoice/pdf.blade.php): logo,
    warna aksen, identitas usaha & kalimat penutup dari Keuangan → Template Invoice. Isinya ringkasan; rincian lengkap di PDF.
    Klien email tidak membaca CSS eksternal → semua gaya inline, tata letak pakai tabel.
--}}
@php
    $setting = \App\Models\Setting\InvoiceTemplateSetting::current();
    $t = $setting->resolved();
    $accent = $t['accent_hex'];
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $logoFile = $setting->logoFile();
    $label = 'font-size:10px; font-weight:bold; letter-spacing:1.5px; text-transform:uppercase; color:#8E7B70;';
    // Alamat / telepon / email dijadikan tautan bergaya sendiri: tanpa ini Gmail mengubahnya jadi tautan biru bergaris bawah.
    $quiet = 'color:#8E7B70; text-decoration:none;';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $t['document_title'] }} {{ $invoice['number'] }}</title>
</head>
<body style="margin:0; padding:0; background:#F4F1EC; font-family:Helvetica, Arial, sans-serif; color:#2A1A17;">
{{-- Pratinjau di daftar kotak masuk (tidak tampil di badan email). --}}
<div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">Pembayaran {{ $invoice['type'] }} sudah kami terima — invoice {{ $invoice['number'] }} terlampir.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F1EC; padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px; background:#FFFFFF; border:1px solid #E8DFD4; border-radius:12px; overflow:hidden;">
                <tr><td style="height:5px; line-height:5px; font-size:0; background:{{ $accent }};">&nbsp;</td></tr>

                {{-- HEADER: logo kiri, judul + nomor + status kanan (sama dengan PDF). --}}
                <tr>
                    <td style="padding:26px 28px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="vertical-align:middle;">
                                    @if ($logoFile)
                                        {{-- Logo ditanam di email (bukan link) — tetap tampil walau gambar dari internet diblokir klien email. --}}
                                        <img src="{{ isset($message) ? $message->embed($logoFile) : $setting->logoDataUri() }}" alt="{{ $t['business_name'] }}" style="display:block; max-width:150px; max-height:60px; height:auto; border:0;">
                                    @else
                                        <div style="font-size:18px; font-weight:bold; letter-spacing:1px; color:{{ $accent }};">{{ $t['business_name'] }}</div>
                                    @endif
                                </td>
                                <td align="right" style="vertical-align:top;">
                                    <div style="font-size:22px; font-weight:bold; letter-spacing:3px; text-transform:uppercase; color:{{ $accent }}; line-height:1;">{{ $t['document_title'] }}</div>
                                    <div style="margin-top:6px; font-size:12px; color:#2A1A17;">No. {{ $invoice['number'] }}</div>
                                    <div style="margin-top:8px;"><span style="display:inline-block; padding:3px 11px; border:1px solid #2F7D4F; border-radius:12px; background:#EEF7F1; color:#2F7D4F; font-size:10px; font-weight:bold; letter-spacing:1.5px; text-transform:uppercase;">{{ $t['status_label'] }}</span></div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr><td style="padding:20px 28px 0;"><div style="border-top:1px solid #E8DFD4; font-size:0; line-height:0;">&nbsp;</div></td></tr>

                {{-- SAPAAN --}}
                <tr>
                    <td style="padding:20px 28px 0;">
                        <p style="margin:0 0 8px; font-size:15px; font-weight:bold;">Halo {{ $invoice['customer']['name'] }},</p>
                        <p style="margin:0; font-size:14px; line-height:1.6; color:#4A3A33;">
                            Terima kasih, pembayaran <strong style="color:#2A1A17;">{{ $invoice['type'] }}</strong> Anda sudah kami terima.
                            Invoice lengkap terlampir dalam bentuk PDF.
                        </p>
                    </td>
                </tr>

                {{-- RINGKASAN PEMBAYARAN (2 × 2, sama isinya dengan pita ringkasan di PDF) --}}
                <tr>
                    <td style="padding:20px 28px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8F4EE; border-radius:8px;">
                            <tr>
                                <td width="50%" style="padding:14px 16px 7px; vertical-align:top;">
                                    <div style="{{ $label }}">Tanggal bayar</div>
                                    <div style="margin-top:4px; font-size:13px; font-weight:bold;">{{ $invoice['paid_at'] }}</div>
                                </td>
                                <td width="50%" style="padding:14px 16px 7px; vertical-align:top;">
                                    <div style="{{ $label }}">Metode</div>
                                    <div style="margin-top:4px; font-size:13px; font-weight:bold;">{{ $invoice['method'] }}</div>
                                    @if (! empty($invoice['va_number']))<div style="font-size:12px; color:#4A3A33;">VA {{ $invoice['va_number'] }}</div>@endif
                                </td>
                            </tr>
                            <tr>
                                <td width="50%" style="padding:7px 16px 14px; vertical-align:top;">
                                    <div style="{{ $label }}">Jenis transaksi</div>
                                    <div style="margin-top:4px; font-size:13px; font-weight:bold;">{{ $invoice['type'] }}</div>
                                </td>
                                <td width="50%" style="padding:7px 16px 14px; vertical-align:top;">
                                    <div style="{{ $label }}">Referensi</div>
                                    <div style="margin-top:4px; font-size:12px; color:#2A1A17; word-wrap:break-word; overflow-wrap:break-word;">{{ $invoice['reference'] ?? $invoice['number'] }}</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                @if ($invoice['bookings'])
                    <tr>
                        <td style="padding:22px 28px 0;">
                            <div style="{{ $label }} padding-bottom:4px;">Jadwal main</div>
                            @foreach ($invoice['bookings'] as $booking)
                                <div style="padding:10px 0; border-bottom:1px solid #EADFD2; font-size:13px; line-height:1.5;">
                                    <strong>{{ $booking['court'] }}</strong><br>
                                    <span style="color:#4A3A33;">{{ $booking['date'] }} &middot; {{ $booking['time'] }}</span>
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @endif

                @if ($invoice['membership'])
                    <tr>
                        <td style="padding:22px 28px 0;">
                            <div style="{{ $label }} padding-bottom:4px;">Membership</div>
                            <div style="padding:10px 0; border-bottom:1px solid #EADFD2; font-size:13px; line-height:1.5;">
                                <strong>{{ $invoice['membership']['plan'] }}</strong>
                                @if ($invoice['membership']['period'])<br><span style="color:#4A3A33;">Masa aktif {{ $invoice['membership']['period'] }}</span>@endif
                            </div>
                        </td>
                    </tr>
                @endif

                {{-- TOTAL (blok warna aksen, sama dengan PDF) --}}
                <tr>
                    <td style="padding:20px 28px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $accent }}; border-radius:8px;">
                            <tr>
                                <td style="padding:14px 16px; color:#FFFFFF; font-size:10px; font-weight:bold; letter-spacing:1.5px; text-transform:uppercase;">Total dibayar</td>
                                <td align="right" style="padding:14px 16px; color:#FFFFFF; font-size:20px; font-weight:bold; white-space:nowrap;">{{ $rp($invoice['grand_total']) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:24px 28px 26px;">
                        <a href="{{ $invoice['invoice_url'] }}" target="_blank" rel="noopener"
                           style="display:inline-block; background:{{ $accent }}; color:#FFFFFF; font-size:14px; font-weight:bold; text-decoration:none; padding:12px 28px; border-radius:8px;">
                            Lihat di Aplikasi Club 61
                        </a>
                        <div style="margin-top:12px; font-size:12px; color:#8E7B70;">Rincian item, diskon &amp; pajak ada di PDF terlampir.</div>
                    </td>
                </tr>

                {{-- KAKI: identitas usaha + kalimat penutup dari Template Invoice --}}
                <tr>
                    <td style="padding:18px 28px 22px; border-top:1px solid #E8DFD4; background:#FBF9F6; font-size:12px; line-height:1.6; color:#8E7B70;">
                        <div style="font-weight:bold; color:#4A3A33;">{{ $t['business_name'] }}</div>
                        @if ($t['address'])
                            <div><a href="https://maps.google.com/?q={{ urlencode($t['address']) }}" target="_blank" rel="noopener" style="{{ $quiet }}">{{ $t['address'] }}</a></div>
                        @endif
                        @if ($t['phone'] || $t['email'])
                            <div>
                                @if ($t['phone'])<a href="tel:{{ preg_replace('/[^0-9+]/', '', $t['phone']) }}" style="{{ $quiet }}">{{ $t['phone'] }}</a>@endif
                                @if ($t['phone'] && $t['email']) &middot; @endif
                                @if ($t['email'])<a href="mailto:{{ $t['email'] }}" style="{{ $quiet }}">{{ $t['email'] }}</a>@endif
                            </div>
                        @endif
                        <div style="margin-top:10px;">Ada pertanyaan? Balas email ini atau hubungi kami — balasan Anda masuk ke tim Club 61.</div>
                        <div style="margin-top:6px; font-size:11px; color:#A89A90;">{{ $t['footer_text'] }}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
