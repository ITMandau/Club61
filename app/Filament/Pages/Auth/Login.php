<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

class Login extends BaseLogin
{
    /**
     * Customize the login form email field to accept either email or username/shortcut.
     */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email atau Username (admin)')
            ->placeholder('admin@club61.com atau admin')
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    /**
     * Transform form input credentials so 'admin' resolves to 'admin@club61.com'
     * and flexible dev credentials work smoothly.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $login = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (strcasecmp($login, 'admin') === 0) {
            $login = 'admin@club61.com';
        } elseif (! str_contains($login, '@')) {
            $found = User::where('phone', $login)
                ->orWhere('email', $login)
                ->orWhere('email', $login.'@club61.com')
                ->first();
            if ($found) {
                $login = $found->email;
            }
        }

        return [
            'email' => $login,
            'password' => $password,
        ];
    }
}
