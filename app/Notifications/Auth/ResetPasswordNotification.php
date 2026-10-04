<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Email "Lupa Kata Sandi" berbahasa Indonesia dengan tampilan Club 61.
 */
class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $data = [
            'name' => $notifiable->name ?: 'Member Club 61',
            'url' => $this->resetUrl($notifiable),
            'minutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
        ];

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi Akun Club 61')
            ->view(['emails.auth.reset-password', 'emails.auth.reset-password-text'], $data);
    }

    /**
     * Link selalu memakai APP_URL, bukan host dari request — kalau tidak, orang luar bisa meminta reset
     * untuk email korban dengan header Host / X-Forwarded-Host palsu (trustProxies '*') sehingga link
     * berisi token yang dikirim ke korban mengarah ke domain penyerang.
     */
    protected function resetUrl($notifiable): string
    {
        $path = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);

        return rtrim((string) config('app.url'), '/').$path;
    }
}
