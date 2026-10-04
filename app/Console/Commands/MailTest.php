<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Cek pengaturan SMTP di server tanpa harus memicu "Lupa Kata Sandi". Kata sandi SMTP tidak pernah ditampilkan.
 */
class MailTest extends Command
{
    protected $signature = 'mail:test {to : Alamat email penerima tes}';

    protected $description = 'Kirim satu email tes untuk memastikan pengaturan MAIL_* di .env sudah benar';

    public function handle(): int
    {
        $to = (string) $this->argument('to');

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Alamat email tidak valid.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $config = (array) config("mail.mailers.{$mailer}", []);

        $this->table(['Pengaturan', 'Nilai'], [
            ['MAIL_MAILER', $mailer],
            ['MAIL_HOST', $config['host'] ?? '-'],
            ['MAIL_PORT', $config['port'] ?? '-'],
            ['MAIL_SCHEME / ENCRYPTION', $config['scheme'] ?? $config['encryption'] ?? '-'],
            ['MAIL_USERNAME', $config['username'] ?? '-'],
            ['MAIL_FROM_ADDRESS', config('mail.from.address')],
            ['MAIL_FROM_NAME', config('mail.from.name')],
            ['APP_URL (dipakai di link email)', config('app.url')],
        ]);

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER={$mailer}: email TIDAK benar-benar dikirim (cuma ditulis ke log). Ganti ke smtp untuk production.");
        }

        try {
            Mail::raw(
                "Email tes dari aplikasi Club 61.\n\nKalau email ini sampai, pengaturan SMTP sudah benar dan email \"Lupa Kata Sandi\" bisa terkirim ke customer.",
                fn ($message) => $message->to($to)->subject('Tes Email Club 61'),
            );
        } catch (\Throwable $e) {
            $this->error('Gagal mengirim: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Email tes terkirim ke {$to}. Cek kotak masuk (dan folder Spam).");

        return self::SUCCESS;
    }
}
