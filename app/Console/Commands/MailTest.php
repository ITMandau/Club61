<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Cek pengaturan SMTP di server tanpa harus memicu "Lupa Kata Sandi" / pembayaran. Kata sandi SMTP tidak pernah ditampilkan.
 * Akun utama (noreply@): php artisan mail:test alamat@email.com
 * Akun billing (invoice): php artisan mail:test alamat@email.com --mailer=billing
 */
class MailTest extends Command
{
    protected $signature = 'mail:test {to : Alamat email penerima tes} {--mailer= : Akun pengirim: kosong = utama (noreply), billing = invoice}';

    protected $description = 'Kirim satu email tes untuk memastikan pengaturan MAIL_* di .env sudah benar';

    public function handle(): int
    {
        $to = (string) $this->argument('to');

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Alamat email tidak valid.');

            return self::FAILURE;
        }

        $mailer = (string) ($this->option('mailer') ?: config('mail.default'));
        $config = (array) config("mail.mailers.{$mailer}", []);
        if ($config === []) {
            $this->error("Mailer \"{$mailer}\" tidak ada di config/mail.php.");

            return self::FAILURE;
        }

        $this->table(['Pengaturan', 'Nilai'], [
            ['Mailer', $mailer],
            ['Transport', $config['transport'] ?? '-'],
            ['Host', $config['host'] ?? '-'],
            ['Port', $config['port'] ?? '-'],
            ['Encryption', $config['scheme'] ?? $config['encryption'] ?? '-'],
            ['Username', $config['username'] ?? '-'],
            ['Pengirim (From)', $config['from']['address'] ?? config('mail.from.address')],
            ['Balasan ke (Reply-To)', config('mail.reply_to.address') ?: '-'],
            ['APP_URL (dipakai di link email)', config('app.url')],
        ]);

        if (in_array($config['transport'] ?? null, ['log', 'array'], true)) {
            $this->warn("Transport {$config['transport']}: email TIDAK benar-benar dikirim (cuma ditulis ke log). Set MAIL_MAILER=smtp untuk production.");
        }

        try {
            Mail::mailer($mailer)->raw(
                "Email tes dari aplikasi Club 61 (akun {$mailer}).\n\nKalau email ini sampai, pengaturan SMTP akun ini sudah benar.",
                fn ($message) => $message->to($to)->subject("Tes Email Club 61 ({$mailer})"),
            );
        } catch (\Throwable $e) {
            $this->error('Gagal mengirim: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Email tes terkirim ke {$to}. Cek kotak masuk (dan folder Spam).");

        return self::SUCCESS;
    }
}
