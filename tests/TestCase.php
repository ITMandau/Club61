<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * PENJAGA DATABASE. Dijalankan saat aplikasi test dibuat — SEBELUM RefreshDatabase menjalankan migrate:fresh.
     * Test hanya boleh jalan di environment `testing` dengan sqlite di memori. Selain itu (mis. config ter-cache dari
     * `php artisan optimize` yang menunjuk MySQL lokal) test DIHENTIKAN, karena migrate:fresh akan mengosongkan
     * database sungguhan. Pernah terjadi 15 Okt 2026 — jangan dilonggarkan.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if ($app->environment() !== 'testing' || $connection !== 'sqlite' || $database !== ':memory:' || $app->configurationIsCached()) {
            fwrite(STDERR, "\n\n[TEST DIHENTIKAN] Database test bukan sqlite :memory: (env={$app->environment()}, koneksi={$connection}, database={$database}, config cache=".($app->configurationIsCached() ? 'ya' : 'tidak').").\n"
                ."Test TIDAK dijalankan supaya database sungguhan tidak dikosongkan oleh migrate:fresh. Periksa phpunit.xml & bootstrap/cache.\n\n");
            exit(1);
        }

        return $app;
    }

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
