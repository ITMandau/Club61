<?php

namespace App\Console\Commands;

use App\Models\Pos\Order;
use App\Services\Mail\OrderInvoiceMailer;
use Illuminate\Console\Command;

/**
 * Cek kenapa invoice suatu order tidak sampai + kirim (ulang) lewat akun billing@. Kata sandi SMTP tidak pernah ditampilkan.
 * Cek saja:   php artisan invoice:send ORD-PAD-XXXX --check
 * Kirim:      php artisan invoice:send ORD-PAD-XXXX            (yang sudah pernah terkirim butuh --force)
 */
class SendOrderInvoice extends Command
{
    protected $signature = 'invoice:send {order : Nomor order, mis. ORD-PAD-XXXX} {--check : Hanya cek, tidak mengirim} {--force : Kirim lagi walau sudah pernah terkirim}';

    protected $description = 'Cek & kirim (ulang) email invoice lunas sebuah order lewat akun billing';

    public function handle(OrderInvoiceMailer $mailer): int
    {
        $order = Order::with('user')->where('order_number', (string) $this->argument('order'))->first();
        if (! $order) {
            $this->error('Order tidak ditemukan.');

            return self::FAILURE;
        }

        $email = $order->user?->email;
        $billing = (array) config('mail.mailers.billing', []);
        $transport = $billing['transport'] ?? '-';

        $this->table(['Pemeriksaan', 'Nilai'], [
            ['Tipe order', $order->order_type],
            ['Status bayar', $order->payment_status],
            ['Email akun customer', $email ?: '(tidak ada akun / email kosong)'],
            ['Invoice terkirim', $order->invoice_emailed_at ? \Illuminate\Support\Carbon::parse($order->invoice_emailed_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB' : 'belum'],
            ['Mailer billing', $transport.(isset($billing['host']) ? ' • '.$billing['host'].':'.($billing['port'] ?? '-') : '')],
            ['Username billing', filled($billing['username'] ?? null) ? $billing['username'] : '(KOSONG)'],
            ['Password billing', filled($billing['password'] ?? null) ? 'terisi' : '(KOSONG)'],
            ['Config di-cache', app()->configurationIsCached() ? 'ya — perubahan .env baru terbaca setelah php artisan config:cache' : 'tidak'],
        ]);

        $problems = array_filter([
            ! in_array($order->order_type, OrderInvoiceMailer::ORDER_TYPES, true) ? 'Tipe order ini memang tidak dikirimi invoice (hanya booking online & membership).' : null,
            $order->payment_status !== 'PAID' ? 'Order belum PAID di sistem — notifikasi Midtrans belum diterima server (cek URL notifikasi di dashboard Midtrans & cron schedule:run).' : null,
            ! filter_var($email, FILTER_VALIDATE_EMAIL) ? 'Akun customer tidak punya email yang valid — invoice dikirim ke email AKUN, bukan email yang diketik di halaman bayar.' : null,
            in_array($transport, ['log', 'array'], true) ? "Mailer billing pakai transport {$transport}: email cuma ditulis ke log, tidak dikirim. Set MAIL_MAILER=smtp." : null,
            $transport === 'smtp' && (blank($billing['username'] ?? null) || blank($billing['password'] ?? null)) ? 'MAIL_BILLING_USERNAME / MAIL_BILLING_PASSWORD belum terbaca aplikasi.' : null,
        ]);

        foreach ($problems as $problem) {
            $this->warn('• '.$problem);
        }

        if ($this->option('check')) {
            if ($problems === []) {
                $this->info('Tidak ada masalah pengaturan. Jalankan tanpa --check untuk mengirim.');
            }

            return self::SUCCESS;
        }

        if ($order->invoice_emailed_at) {
            if (! $this->option('force')) {
                $this->warn('Invoice order ini sudah pernah terkirim. Pakai --force untuk mengirim lagi.');

                return self::SUCCESS;
            }
            $order->forceFill(['invoice_emailed_at' => null])->save();
        }

        if ($mailer->send($order->id)) {
            $this->info("Invoice terkirim ke {$email}. Kalau tidak ada di Kotak Masuk, cek folder Spam/Promosi.");

            return self::SUCCESS;
        }

        $this->error('Invoice TIDAK terkirim.'.($mailer->lastError ? ' Error SMTP: '.$mailer->lastError : ' Lihat alasan di atas.'));

        return self::FAILURE;
    }
}
