{{-- Email "Lupa Kata Sandi" (App\Notifications\Auth\ResetPasswordNotification). Gaya inline — klien email tidak membaca CSS eksternal. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atur Ulang Kata Sandi</title>
</head>
<body style="margin:0; padding:0; background:#F6F1E6; font-family:Arial, Helvetica, sans-serif; color:#1F170D;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F1E6; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background:#FFFFFF; border:2px solid #D4AF37; border-radius:16px;">
                <tr>
                    <td style="background:#1F170D; border-radius:14px 14px 0 0; padding:20px 24px; text-align:center;">
                        <div style="font-size:20px; font-weight:bold; letter-spacing:4px; color:#FFFFFF;">CLUB 61</div>
                        <div style="font-size:10px; letter-spacing:4px; color:#F5E2B5; margin-top:2px;">PADEL COURT</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 24px 8px;">
                        <p style="margin:0 0 14px; font-size:15px;">Halo {{ $name }},</p>
                        <p style="margin:0 0 14px; font-size:14px; line-height:1.6; color:#3B2B11;">
                            Kami menerima permintaan untuk mengatur ulang kata sandi akun Club 61 Anda.
                            Klik tombol di bawah untuk membuat kata sandi baru.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:8px 24px 20px;">
                        <a href="{{ $url }}" target="_blank" rel="noopener"
                           style="display:inline-block; background:#D4AF37; color:#241604; font-size:14px; font-weight:bold; text-decoration:none; padding:13px 28px; border-radius:10px; border:1px solid #B38622;">
                            Buat Kata Sandi Baru
                        </a>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 24px 24px;">
                        <p style="margin:0 0 12px; font-size:13px; line-height:1.6; color:#6B5738;">
                            Link ini berlaku <strong>{{ $minutes }} menit</strong> dan hanya bisa dipakai sekali.
                            Kalau Anda tidak merasa meminta reset, abaikan email ini. Kata sandi lama Anda tetap aman.
                        </p>
                        <p style="margin:0; font-size:11px; line-height:1.5; color:#9E8A68; word-break:break-all;">
                            Tombol tidak bisa diklik? Salin link ini ke browser:<br>
                            <a href="{{ $url }}" style="color:#8C6418;">{{ $url }}</a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="border-top:1px solid #EFE3C6; padding:14px 24px; text-align:center; font-size:11px; color:#9E8A68;">
                        {{ \App\Models\Setting\CompanyProfileSetting::receiptAddress() }}<br>
                        Email otomatis, mohon tidak dibalas.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
