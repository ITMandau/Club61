<?php

namespace Tests\Feature\Payment;

use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman customer tidak lagi memakai layanan / nilai demo: QR dibuat di browser (bukan api.qrserver.com yang ikut
 * menerima kode akses gate), dan popup Midtrans hanya dimuat dengan client key asli (bukan 'SB-Mid-client-demo-61').
 */
class CustomerPaymentScriptsTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        $this->customer = User::factory()->customer()->create();
    }

    public function test_invoice_page_renders_qr_locally_without_external_services(): void
    {
        $html = $this->actingAs($this->customer)->get('/invoice')->assertOk()->getContent();

        $this->assertStringNotContainsString('api.qrserver.com/v1', $html);
        $this->assertStringNotContainsString('CLUB61-DEMO', $html);
        $this->assertStringNotContainsString('cdnjs.cloudflare.com/ajax/libs/qrcodejs', $html);
        $this->assertStringContainsString(asset('js/qrcode.min.js'), $html);
        $this->assertStringContainsString('renderQr($el, currentTicket.qr_code_hash)', $html);
        $this->assertStringContainsString('renderQr($el, currentTicket.qr_pass_hash)', $html);
        $this->assertFileExists(public_path('js/qrcode.min.js'));
    }

    public function test_snap_is_loaded_only_with_a_real_client_key(): void
    {
        \App\Models\Membership\MembershipPlan::create(['code' => 'MBR-SNAP', 'name' => 'Silver', 'ownership_type' => 'INDIVIDUAL', 'duration_days' => 30, 'price' => 1500000, 'is_active' => true]);
        config(['services.midtrans.client_key' => null]);

        foreach (['/invoice', '/membership'] as $page) {
            $html = $this->actingAs($this->customer)->get($page)->assertOk()->getContent();
            $this->assertStringNotContainsString('snap/snap.js', $html, $page);
            $this->assertStringNotContainsString('SB-Mid-client-demo-61', $html, $page);
            $this->assertStringContainsString('MIDTRANS_CLIENT_KEY belum diisi', $html, $page);
        }

        config(['services.midtrans.client_key' => 'SB-Mid-client-REALKEY']);
        $html = $this->get('/invoice')->assertOk()->getContent();
        $this->assertStringContainsString('app.sandbox.midtrans.com/snap/snap.js', $html);
        $this->assertStringContainsString('data-client-key="SB-Mid-client-REALKEY"', $html);
    }

    public function test_membership_page_without_active_plans_shows_an_empty_state(): void
    {
        // Dulu error 500 ($initialPlan kosong) kalau admin menonaktifkan semua paket.
        $this->actingAs($this->customer)->get('/membership')
            ->assertOk()
            ->assertSee('Paket membership belum tersedia');
    }
}
