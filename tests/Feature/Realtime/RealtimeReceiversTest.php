<?php

namespace Tests\Feature\Realtime;

use App\Filament\Pages\BookingSystem;
use App\Filament\Pages\Signature;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use App\Services\Signature\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Layar penerima: konfigurasi realtime hanya dikirim ke browser kalau Reverb aktif (tanpa secret), dan tanpa Reverb
 * semua layar tetap jalan dengan polling cadangan.
 */
class RealtimeReceiversTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        config(['app.url' => 'https://club61.test']);
    }

    private function enableRealtime(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'public-app-key',
            'broadcasting.connections.reverb.secret' => 'super-secret-value',
            'broadcasting.connections.reverb.app_id' => '61',
            // Alamat client ikut APP_URL — jangan terpengaruh REVERB_CLIENT_* di .env laptop developer.
            'broadcasting.connections.reverb.client' => ['host' => null, 'port' => null, 'scheme' => null],
        ]);
    }

    public function test_tv_player_connects_only_when_realtime_is_on(): void
    {
        $screen = app(SignatureService::class)->ensureDefaults();

        $this->get("/signature/{$screen->token}")->assertOk()->assertDontSee('club61-live.js')->assertDontSee('window.SIG_REALTIME =', false);

        $this->enableRealtime();
        $this->get("/signature/{$screen->token}")->assertOk()
            ->assertSee('js/club61-live.js', false)
            ->assertSee('"key":"public-app-key","host":"club61.test","port":443,"tls":true', false)
            ->assertDontSee('super-secret-value');
    }

    public function test_client_address_can_be_overridden_for_local_reverb(): void
    {
        $this->enableRealtime();
        config(['broadcasting.connections.reverb.client' => ['host' => '127.0.0.1', 'port' => '8080', 'scheme' => 'http']]);
        $screen = app(SignatureService::class)->ensureDefaults();

        $this->get("/signature/{$screen->token}")->assertSee('"host":"127.0.0.1","port":8080,"tls":false', false);
    }

    public function test_customer_booking_page_gets_live_updates_only_when_realtime_is_on(): void
    {
        $this->actingAs(User::factory()->customer()->create());

        $this->get('/booking')->assertOk()->assertSee('startLiveUpdates')->assertDontSee('window.BK_REALTIME =', false);

        $this->enableRealtime();
        $this->get('/booking')->assertOk()->assertSee('window.BK_REALTIME =', false)->assertSee('"key":"public-app-key"', false)->assertDontSee('super-secret-value');
    }

    public function test_admin_board_falls_back_to_polling_without_reverb(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        // Tanpa Reverb: Echo tidak dinyalakan, papan tetap refresh sendiri (polling cadangan 10 dtk, seperti dulu).
        $this->get(BookingSystem::getUrl())->assertOk()
            ->assertSee("club61LiveRefresh({ dateProp: 'selectedDate', fallback: 10, safety: 60 })", false)
            ->assertDontSee('wire:poll.10s', false)
            ->assertDontSee('new window.EchoFactory', false);
    }

    public function test_admin_board_listens_through_filament_echo_when_reverb_is_on(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        // Panel Filament di-boot sekali per aplikasi → konfigurasi diset sebelum request pertama.
        $this->enableRealtime();
        $this->get(BookingSystem::getUrl())->assertOk()
            ->assertSee('new window.EchoFactory', false)
            ->assertSee('public-app-key')
            ->assertSee('booking-board')
            ->assertDontSee('super-secret-value');
    }

    public function test_signage_status_can_be_checked_without_rerendering_the_page(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $screen = app(SignatureService::class)->ensureDefaults();
        $screen->forceFill(['last_seen_at' => now()->subSeconds(30)])->saveQuietly();

        Livewire::test(Signature::class)
            ->assertSee('club61ScreenStatus', false)
            ->call('screenLastSeen')
            ->assertReturned($screen->fresh()->last_seen_at->toIso8601String());
    }
}
