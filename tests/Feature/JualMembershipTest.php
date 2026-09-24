<?php

namespace Tests\Feature;

use App\Filament\Pages\JualMembership;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipPlanBenefit;
use App\Models\Pos\Order;
use App\Models\Pos\PosCashierShift;
use App\Models\Pos\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * POS Jual Membership: pencarian member lama, alur 2 langkah (selection -> payment) yang meniru
 * POS Walk-In Booking, dan form metode pembayaran yang markup-nya disamakan manual dengan
 * BookOfflineCourt (bukan lewat partial/file bersama).
 */
class JualMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected MembershipPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Services\Permission\Club61PermissionMatrix::syncAllPermissions('web');

        $this->cashier = User::factory()->cashier()->create(['is_active' => true]);
        $this->cashier->givePermissionTo(['View:JualMembership']);

        $this->plan = MembershipPlan::create([
            'code' => 'MBR-TEST-SILVER',
            'name' => 'Silver Test Plan',
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30,
            'price' => 3500000.00,
            'is_active' => true,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $this->plan->id,
            'facility' => 'PADEL',
            'quota_type' => 'HOURS',
            'quota_value' => 10,
            'discount_percent' => 20,
        ]);

        // Loket MEMBERSHIP_DESK butuh shift aktif juga (dicek PaymentOrchestratorService::markOrderAsPaid).
        PosCashierShift::create([
            'shift_number' => 'SFT-MBR-' . now()->format('Ymd') . '-0001',
            'counter' => 'MEMBERSHIP_DESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id,
            'opened_at' => now(),
            'starting_cash' => 0.00,
            'expected_cash' => 0.00,
        ]);

        $this->actingAs($this->cashier);
    }

    public function test_search_customer_returns_matching_results(): void
    {
        User::factory()->create(['name' => 'Irham Maulana', 'phone' => '081299998888']);

        Livewire::test(JualMembership::class)
            ->set('customerMode', 'search')
            ->set('customerSearch', 'irham')
            ->assertSee('Irham Maulana');
    }

    public function test_search_customer_with_short_term_returns_no_results(): void
    {
        User::factory()->create(['name' => 'Irham Maulana', 'phone' => '081299998888']);

        Livewire::test(JualMembership::class)
            ->set('customerMode', 'search')
            ->set('customerSearch', 'i')
            ->assertDontSee('Irham Maulana');
    }

    public function test_proceed_to_payment_requires_plan_selected(): void
    {
        Livewire::test(JualMembership::class)
            ->set('customerMode', 'quick_create')
            ->set('walkInName', 'Belum Pilih Paket')
            ->set('walkInPhone', '081277778888')
            ->call('proceedToPayment')
            ->assertSet('posStep', 'selection');
    }

    public function test_proceed_to_payment_requires_customer_data(): void
    {
        Livewire::test(JualMembership::class)
            ->set('selectedPlanId', $this->plan->id)
            ->set('customerMode', 'quick_create')
            ->call('proceedToPayment')
            ->assertSet('posStep', 'selection');
    }

    public function test_proceed_to_payment_moves_to_payment_step_and_back_to_selection_returns(): void
    {
        Livewire::test(JualMembership::class)
            ->set('selectedPlanId', $this->plan->id)
            ->set('customerMode', 'quick_create')
            ->set('walkInName', 'Calon Member')
            ->set('walkInPhone', '081277779999')
            ->call('proceedToPayment')
            ->assertSet('posStep', 'payment')
            ->assertSee('Layar Pembayaran & Penyelesaian Transaksi')
            ->call('backToSelection')
            ->assertSet('posStep', 'selection');
    }

    public function test_submit_sale_rejects_cash(): void
    {
        Livewire::test(JualMembership::class)
            ->set('selectedPlanId', $this->plan->id)
            ->set('customerMode', 'quick_create')
            ->set('walkInName', 'Pembeli Cash')
            ->set('walkInPhone', '081211112222')
            ->set('paymentMethod', 'CASH')
            ->call('submitSale');

        $this->assertEquals(0, Order::where('order_type', 'MEMBERSHIP')->count());
    }

    public function test_submit_sale_with_qris_requires_rrn(): void
    {
        Livewire::test(JualMembership::class)
            ->set('selectedPlanId', $this->plan->id)
            ->set('customerMode', 'quick_create')
            ->set('walkInName', 'Pembeli QRIS')
            ->set('walkInPhone', '081211113333')
            ->set('paymentMethod', 'QRIS')
            ->call('submitSale');

        $this->assertEquals(0, Order::where('order_type', 'MEMBERSHIP')->count());
    }

    public function test_submit_sale_with_qris_succeeds_and_records_qris_details(): void
    {
        Livewire::test(JualMembership::class)
            ->set('selectedPlanId', $this->plan->id)
            ->set('customerMode', 'quick_create')
            ->set('walkInName', 'Pembeli QRIS Lunas')
            ->set('walkInPhone', '081211114444')
            ->set('paymentMethod', 'QRIS')
            ->set('qrisProvider', 'GOPAY')
            ->set('qrisRrn', '998877665544')
            ->set('qrisSenderName', 'Budi Santoso')
            ->call('submitSale');

        $order = Order::where('order_type', 'MEMBERSHIP')->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals('PAID', $order->payment_status);

        $payment = Payment::where('order_id', $order->id)->first();
        $this->assertEquals('GOPAY', $payment->payload_log['qris_details']['qris_provider']);
        $this->assertEquals('998877665544', $payment->payload_log['qris_details']['qris_rrn']);

        // Regresi: penjualan membership TIDAK boleh nyasar ke order_type 'WALK_IN' — kalau ikut,
        // omzet & daftar "Transaksi Walk-In Terakhir" di halaman Walk-In Booking (BookOfflineCourt)
        // bakal tercampur dengan penjualan membership yang gak ada hubungannya sama booking lapangan.
        $this->assertEquals(0, Order::where('order_type', 'WALK_IN')->count());
    }

    public function test_submit_sale_with_edc_debit_requires_last4_approval_and_trace(): void
    {
        Livewire::test(JualMembership::class)
            ->set('selectedPlanId', $this->plan->id)
            ->set('customerMode', 'quick_create')
            ->set('walkInName', 'Pembeli EDC')
            ->set('walkInPhone', '081211115555')
            ->set('paymentMethod', 'DEBIT_CARD')
            ->call('submitSale');

        $this->assertEquals(0, Order::where('order_type', 'MEMBERSHIP')->count());
    }

    public function test_submit_sale_with_edc_debit_succeeds_and_records_card_details(): void
    {
        Livewire::test(JualMembership::class)
            ->set('selectedPlanId', $this->plan->id)
            ->set('customerMode', 'quick_create')
            ->set('walkInName', 'Pembeli EDC Lunas')
            ->set('walkInPhone', '081211116666')
            ->call('setPaymentMethod', 'DEBIT_CARD')
            ->set('edcBank', 'MANDIRI')
            ->set('edcLast4', '8842')
            ->set('edcApprovalCode', 'APPR123')
            ->set('edcTraceNumber', 'TRC001')
            ->call('submitSale');

        $order = Order::where('order_type', 'MEMBERSHIP')->latest('id')->first();
        $this->assertNotNull($order);

        $payment = Payment::where('order_id', $order->id)->first();
        $this->assertEquals('MANDIRI', $payment->payload_log['edc_details']['card_issuer']);
        $this->assertEquals('8842', $payment->payload_log['edc_details']['card_last_4']);
        $this->assertEquals('DEBIT', $payment->payload_log['edc_details']['card_type']);
    }
}
