<?php

namespace Tests\Feature\I18n;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Services\Padel\BookingTimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman Pesan Lapangan (/booking) & Keranjang (/cart): Bahasa Indonesia secara default, Inggris saat bahasa "en"
 * dipilih — termasuk teks di Alpine/JS yang dikirim lewat @js (status slot, pemberitahuan, label tombol) dan locale
 * format tanggal Intl.
 */
class BookingCartLocaleTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->customer()->create();
    }

    public function test_booking_page_is_indonesian_by_default(): void
    {
        $this->actingAs($this->customer)->get('/booking')->assertOk()
            ->assertSee('Pesan Lapangan')
            ->assertSee('Ketuk jam yang Anda inginkan')
            ->assertSee('Sudah dipesan / tutup')
            // Teks JS (lewat @js) ikut berbahasa Indonesia.
            ->assertSee('Jam sebelum :time hari ini sudah lewat', false)
            ->assertSee('Slot Gagal Ditahan', false)
            ->assertSee('Mengerti', false)
            ->assertSee("'id-ID'", false)
            ->assertDontSee('Tap the hours you want')
            ->assertDontSee('Slot Reservation Failed', false)
            ->assertDontSee("'en-US'", false);
    }

    public function test_booking_page_switches_to_english(): void
    {
        $this->actingAs($this->customer)->withCookie(SetLocale::COOKIE, 'en')->get('/booking')->assertOk()
            ->assertSee('Book Court')
            ->assertSee('Tap the hours you want')
            ->assertSee('Booked / closed')
            ->assertSee('Hours before :time today have passed', false)
            ->assertSee('Slot Reservation Failed', false)
            ->assertSee('Got It', false)
            ->assertSee("'en-US'", false)
            ->assertDontSee('Ketuk jam yang Anda inginkan')
            ->assertDontSee('Slot Gagal Ditahan', false);
    }

    public function test_cart_page_is_indonesian_by_default(): void
    {
        $minutes = app(BookingTimeService::class)->holdMinutes();

        $this->actingAs($this->customer)->get('/cart')->assertOk()
            ->assertSee('Keranjang Anda kosong')
            ->assertSee('Lanjut ke Pembayaran')
            ->assertSee("Slot ditahan selama {$minutes} menit")
            ->assertSee('Akses Ditolak', false)
            ->assertSee(':count sesi', false)
            ->assertSee("'id-ID'", false)
            ->assertDontSee('Your cart is empty')
            ->assertDontSee('Access Denied', false);
    }

    public function test_cart_page_switches_to_english(): void
    {
        $minutes = app(BookingTimeService::class)->holdMinutes();

        $this->actingAs($this->customer)->withCookie(SetLocale::COOKIE, 'en')->get('/cart')->assertOk()
            ->assertSee('Your cart is empty')
            ->assertSee('Proceed to Checkout')
            ->assertSee("Slots are held for {$minutes} minutes")
            ->assertSee('Access Denied', false)
            ->assertSee(':count sessions', false)
            ->assertSee("'en-US'", false)
            ->assertDontSee('Keranjang Anda kosong')
            ->assertDontSee('Akses Ditolak', false);
    }
}
