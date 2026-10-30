<?php

namespace Tests\Feature\I18n;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman Pembayaran (/checkout): Bahasa Indonesia secara default, Inggris saat bahasa "en" dipilih — termasuk teks
 * Alpine/JS yang dikirim lewat objek i18n (@js): pop-up akses membership / voucher, pemberitahuan voucher & pembayaran,
 * dan locale format tanggal Intl. Keranjang dibaca dari localStorage, jadi halaman cukup dirender untuk customer login.
 */
class CheckoutLocaleTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->customer()->create();
    }

    public function test_checkout_page_is_indonesian_by_default(): void
    {
        $this->actingAs($this->customer)->get('/checkout')->assertOk()
            ->assertSee('Pembayaran')
            ->assertSee('Sewa Alat &amp; Tambahan', false)
            ->assertSee('Ringkasan Pembayaran')
            ->assertSee('Metode Pembayaran')
            ->assertSee('Ketentuan Booking')
            ->assertSee('<strong>Tidak ada refund</strong>', false)
            // Nilai dinamis tetap di <b x-text> yang sama, urutan kalimat mengikuti bahasa.
            ->assertSee('Memakai <b class="text-[#662721]" x-text="t(\'hr\', { count: membershipBenefit.hours_to_consume })"></b> dari kuota Anda', false)
            ->assertSee('Waktu tahan '.app(\App\Services\Padel\BookingTimeService::class)->holdMinutes().' menit sudah berakhir', false)
            ->assertSee('Lihat E-Ticket Saya')
            // Teks JS (objek i18n) ikut berbahasa Indonesia.
            ->assertSee('Hanya berlaku: :rules. Booking ini dikenakan harga normal.', false)
            ->assertSee('Membership tidak berlaku di jam ini', false)
            ->assertSee('Kode Promo Dilepas', false)
            ->assertSee('Bayar Sekarang', false)
            ->assertSee("'id-ID'", false)
            // Label aturan akses dipilih dari server sesuai bahasa (access_rule / access_rule_en).
            ->assertSee("this.locale === 'id' ? 'access_rule' : 'access_rule_en'", false)
            ->assertDontSee('Payment Summary')
            ->assertDontSee('Normal price applies to this booking.', false)
            ->assertDontSee('Promo Code Removed', false)
            ->assertDontSee("'en-US'", false);
    }

    public function test_checkout_page_switches_to_english(): void
    {
        $this->actingAs($this->customer)->withCookie(SetLocale::COOKIE, 'en')->get('/checkout')->assertOk()
            ->assertSee('Checkout')
            ->assertSee('Equipment Rental &amp; Add-ons', false)
            ->assertSee('Payment Summary')
            ->assertSee('Booking policy')
            ->assertSee('<strong>No refunds</strong>', false)
            ->assertSee('View My E-Ticket')
            ->assertSee('Valid only :rules. Normal price applies to this booking.', false)
            ->assertSee('Membership not valid at this time', false)
            ->assertSee('Promo Code Removed', false)
            ->assertSee('Pay Now', false)
            ->assertSee("'en-US'", false)
            ->assertDontSee('Ringkasan Pembayaran')
            ->assertDontSee('Booking ini dikenakan harga normal.', false)
            ->assertDontSee('Kode Promo Dilepas', false)
            ->assertDontSee("'id-ID'", false);
    }

    public function test_both_languages_render_the_same_checkout_markup_hooks(): void
    {
        // Atribut yang dipakai JS / test lain tidak boleh hilang saat bahasa diganti.
        foreach (['id', 'en'] as $locale) {
            $this->actingAs($this->customer)->withCookie(SetLocale::COOKIE, $locale)->get('/checkout')->assertOk()
                ->assertSee('data-access-notice', false)
                ->assertSee('data-membership-notice', false)
                ->assertSee('data-voucher-terms', false)
                ->assertSee('data-confirm-breakdown', false)
                ->assertSee('i18n: JSON.parse(', false);
        }
    }
}
