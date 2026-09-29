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
    }
}
