<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Halaman depan default-nya Bahasa Indonesia terlepas dari APP_LOCALE di .env ("en") —
     * itu cuma default framework Laravel yang tidak diubah, bukan bahasa tampilan situs.
     * Untuk request HTTP asli, App\Http\Middleware\SetLocale yang memaksa locale ke "id"
     * kecuali toggle bahasa di session bilang "en". Test yang manggil method model langsung
     * (bukan lewat $this->get()) tidak melewati middleware itu, jadi di-set manual di sini
     * supaya perilakunya konsisten dengan halaman depan yang sesungguhnya.
     */
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('id');

        // Test tidak boleh menembak API luar (Midtrans dsb.) — yang butuh wajib Http::fake().
        \Illuminate\Support\Facades\Http::preventStrayRequests();
    }

    /**
     * Ganti user di tengah test = login baru. Di aplikasi, ganti akun selalu lewat logout (sesi dikosongkan) lalu login;
     * actingAs() tidak, sehingga hash password user SEBELUMNYA masih ada di sesi dan AuthenticateSession (grup web)
     * menganggapnya sesi basi lalu logout. Hash itu dibuang supaya perilakunya sama dengan login sungguhan.
     */
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null)
    {
        if ($this->app->bound('session') && $this->app['auth']->guard($guard)->user()?->getAuthIdentifier() !== $user->getAuthIdentifier()) {
            $this->app['session']->forget('password_hash_'.($guard ?? $this->app['auth']->getDefaultDriver()));
        }

        return parent::actingAs($user, $guard);
    }
}
