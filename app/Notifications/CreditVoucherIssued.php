<?php

namespace App\Notifications;

use App\Models\Pos\Voucher;
use App\Services\Finance\LedgerReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Modul 21: refund ditolak → uang customer dijadikan voucher saldo. Customer diberi tahu kode & saldonya. */
class CreditVoucherIssued extends Notification
{
    use Queueable;

    public function __construct(public Voucher $voucher) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = 'Rp '.number_format((float) $this->voucher->balance, 0, ',', '.');
        $until = $this->voucher->valid_until?->timezone(LedgerReport::TIMEZONE)->translatedFormat('d F Y');

        // Urusan uang customer → dikirim dari billing@ (lihat config/mail.php).
        return (new MailMessage)
            ->mailer('billing')
            ->subject("Voucher saldo {$amount} dari Club 61")
            ->greeting('Halo '.($notifiable->name ?? 'Member').',')
            ->line("Pengajuan refund untuk pesanan Anda tidak dapat dikembalikan dalam bentuk uang. Sebagai gantinya, dana Anda sebesar {$amount} kami simpan sebagai voucher saldo di akun Club 61 Anda.")
            ->line("Kode voucher: **{$this->voucher->code}**")
            ->line('Saldo bisa dipakai sebagian berkali-kali untuk booking lapangan padel, online maupun di kasir'.($until ? ", sampai {$until}." : '.'))
            ->action('Booking Sekarang', rtrim((string) config('app.url'), '/').'/booking')
            ->line('Voucher juga tampil otomatis di halaman checkout saat Anda login.');
    }
}
