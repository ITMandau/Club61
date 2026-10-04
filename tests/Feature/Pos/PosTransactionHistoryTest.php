<?php

namespace Tests\Feature\Pos;

use App\Filament\Pages\BookOfflineCourt;
use App\Filament\Pages\JualMembership;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipPlanBenefit;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\Role;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Riwayat transaksi & cetak ulang struk di POS Walk-In Padel dan POS Jual Membership. */
class PosTransactionHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected PadelCourt $court;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        $this->admin = User::factory()->superAdmin()->create(['name' => 'Kasir Owner']);
        $this->customer = User::factory()->customer()->create(['name' => 'Budi Padel', 'phone' => '081200000001']);
        $this->court = PadelCourt::create([
            'name' => 'Court History',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 200000,
            'hourly_rate_prime' => 300000,
            'is_active' => true,
        ]);
    }

    private function walkInSale(string $code = 'BK-HIST-001', ?Carbon $at = null): Payment
    {
        $at ??= now();
        $order = Order::create([
            'order_number' => 'ORD-'.$code,
            'user_id' => $this->customer->id,
            'cashier_id' => $this->admin->id,
            'order_type' => 'WALK_IN',
            'subtotal' => 200000,
            'grand_total' => 200000,
            'payment_status' => 'PAID',
        ]);
        $start = now()->addDay()->setTime(10, 0);
        PadelBooking::create([
            'booking_code' => $code,
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => $start->toDateString(),
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'court_fee' => 200000,
            'total_amount' => 200000,
            'status' => 'PAID',
            'qr_code_hash' => 'QR-'.$code,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'CASHIER_POS',
            'transaction_id' => 'ORD-'.$code,
            'amount' => 200000,
            'payment_method' => 'QRIS',
            'status' => 'SUCCESS',
            'payload_log' => ['cashier_name' => 'Kasir Owner', 'qris_details' => ['provider' => 'BCA_QRIS', 'rrn' => 'RRN'.$code]],
        ]);
        Payment::query()->toBase()->where('id', $payment->id)->update(['created_at' => $at, 'updated_at' => $at, 'paid_at' => $at]);

        return $payment->fresh();
    }

    public function test_walk_in_history_lists_todays_counter_payments_and_reprints_the_receipt(): void
    {
        $sale = $this->walkInSale();
        $this->walkInSale('BK-HIST-OLD', now()->subDays(3));
        $this->actingAs($this->admin);

        $page = Livewire::test(BookOfflineCourt::class)
            ->assertSee('Riwayat Transaksi')
            ->call('showHistory')
            ->assertSet('posStep', 'history')
            ->assertSee('ORD-BK-HIST-001')
            ->assertSee('Booking Walk-In')
            ->assertSee('Budi Padel')
            ->assertDontSee('ORD-BK-HIST-OLD')
            ->call('viewTransactionReceipt', $sale->id)
            ->assertSet('showSuccessModal', true)
            ->assertSet('completedOrderData.order_number', 'ORD-BK-HIST-001')
            ->assertSet('completedOrderData.receipt_type', 'SALE')
            ->assertSet('completedOrderData.payment_meta.qris_rrn', 'RRNBK-HIST-001')
            ->assertSee('CETAK ULANG')
            ->assertSee('BK-HIST-001');

        // Tutup struk = kembali ke Riwayat, bukan ke layar kasir.
        $page->call('closeSuccessModal')->assertSet('posStep', 'history')->assertSet('showSuccessModal', false);

        // Riwayat tanggal lain & pencarian.
        $page->set('historyDate', now('Asia/Jakarta')->subDays(3)->toDateString())->assertSee('ORD-BK-HIST-OLD')
            ->set('historyDate', now('Asia/Jakarta')->toDateString())->set('historySearch', 'tidak-ada')->assertDontSee('ORD-BK-HIST-001');
    }

    public function test_reschedule_settlement_prints_a_settlement_receipt_and_appears_in_history(): void
    {
        $sale = $this->walkInSale('BK-HIST-RS');
        $booking = PadelBooking::where('booking_code', 'BK-HIST-RS')->first();
        $date = $booking->booking_date->format('Y-m-d');
        app(PadelBookingService::class)->adminRescheduleBooking(
            bookingId: $booking->id,
            newCourtId: $this->court->id,
            newDate: $date,
            newStartTimeStr: '18:00',
            reason: 'pindah prime',
            adminUser: $this->admin,
            isDeltaPaid: false,
        );
        PosCashierShift::create([
            'shift_number' => 'SFT-PADEL-HIST', 'counter' => 'PADEL_FRONTDESK', 'status' => 'OPEN',
            'opened_by_id' => $this->admin->id, 'opened_at' => now(), 'starting_cash' => 0, 'expected_cash' => 0,
        ]);
        $this->actingAs($this->admin);

        $page = Livewire::test(BookOfflineCourt::class)
            ->call('startSettlement', $booking->id)
            ->call('proceedToPayment')
            ->set('qrisRrn', 'RRNSETTLE77')
            ->call('submitWalkInBooking')
            ->assertSet('showSuccessModal', true)
            ->assertSet('completedOrderData.receipt_type', 'SETTLEMENT')
            ->assertSet('completedOrderData.paid_before', 200000.0)
            ->assertSee('PELUNASAN SELISIH')
            ->assertSee('Dipindah dari');

        $page->call('closeSuccessModal')
            ->call('showHistory')
            ->assertSee('Pelunasan Selisih Reschedule');
        $this->assertNotNull($sale);
    }

    public function test_history_excludes_fnb_membership_and_online_payments(): void
    {
        $fnb = Order::create(['order_number' => 'ORD-FNB-HIST', 'order_type' => 'DINE_IN', 'subtotal' => 50000, 'grand_total' => 50000, 'payment_status' => 'PAID']);
        Payment::create(['order_id' => $fnb->id, 'payment_gateway' => 'CASHIER_POS', 'transaction_id' => 'ORD-FNB-HIST', 'amount' => 50000, 'payment_method' => 'QRIS', 'status' => 'SUCCESS']);
        $online = Order::create(['order_number' => 'ORD-ONLINE-HIST', 'order_type' => 'ONLINE_BOOKING', 'subtotal' => 200000, 'grand_total' => 200000, 'payment_status' => 'PAID']);
        Payment::create(['order_id' => $online->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-ONLINE-HIST', 'amount' => 200000, 'payment_method' => 'BCA_VA', 'status' => 'SUCCESS']);
        $this->actingAs($this->admin);

        Livewire::test(BookOfflineCourt::class)
            ->call('showHistory')
            ->assertDontSee('ORD-FNB-HIST')
            ->assertDontSee('ORD-ONLINE-HIST');
    }

    public function test_walk_in_history_requires_counter_permission(): void
    {
        $sale = $this->walkInSale();
        $kitchen = User::factory()->kitchen()->create();
        Role::findByName('kitchen', 'web')->givePermissionTo('View:BookOfflineCourt');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($kitchen);

        Livewire::test(BookOfflineCourt::class)
            ->assertDontSee('Riwayat Transaksi')
            ->call('showHistory')
            ->assertSet('posStep', 'selection')
            ->call('viewTransactionReceipt', $sale->id)
            ->assertForbidden();
    }

    private function plan(): MembershipPlan
    {
        $plan = MembershipPlan::create([
            'code' => 'MBR-HIST',
            'name' => 'Gold History',
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30,
            'price' => 1000000,
            'is_active' => true,
        ]);
        MembershipPlanBenefit::create(['plan_id' => $plan->id, 'facility' => 'PADEL', 'quota_type' => 'HOURS', 'quota_value' => 10, 'discount_percent' => 10]);

        return $plan;
    }

    public function test_membership_sale_appears_in_history_and_receipt_can_be_reprinted(): void
    {
        $plan = $this->plan();
        // Penjualan membership wajib masuk shift meja frontdesk (PADEL_FRONTDESK), termasuk super_admin.
        PosCashierShift::create([
            'shift_number' => 'SFT-PADEL-MBR-HIST', 'counter' => 'PADEL_FRONTDESK', 'status' => 'OPEN',
            'opened_by_id' => $this->admin->id, 'opened_at' => now(), 'starting_cash' => 0, 'expected_cash' => 0,
        ]);
        $this->actingAs($this->admin);

        $page = Livewire::test(JualMembership::class)
            ->set('walkInName', 'Siti Member')
            ->set('walkInPhone', '081299990000')
            ->call('selectPlan', $plan->id)
            ->set('paymentMethod', 'QRIS')
            ->set('qrisRrn', 'RRNMBR001')
            ->call('submitSale')
            ->assertSet('showSuccessModal', true)
            ->assertSee('No. Order')
            ->assertSee('Gold History');

        $orderNumber = $page->get('completedMembershipData.order_number');
        $this->assertStringStartsWith('ORD-POS-MBR-', $orderNumber);

        $page->call('closeReceipt')
            ->assertSet('posStep', 'selection')
            ->call('showHistory')
            ->assertSet('posStep', 'history')
            ->assertSee($orderNumber)
            ->assertSee('Siti Member');

        $orderId = Order::where('order_number', $orderNumber)->value('id');
        $page->call('viewTransactionReceipt', $orderId)
            ->assertSet('showSuccessModal', true)
            ->assertSet('completedMembershipData.is_reprint', true)
            ->assertSee('CETAK ULANG')
            ->assertSee('RRNMBR001')
            ->call('closeReceipt')
            ->assertSet('posStep', 'history');
    }

    public function test_membership_history_requires_sell_membership_permission(): void
    {
        $order = Order::create(['order_number' => 'ORD-POS-MBR-X', 'order_type' => 'MEMBERSHIP', 'subtotal' => 1, 'grand_total' => 1, 'payment_status' => 'PAID']);
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'CASHIER_POS', 'transaction_id' => 'ORD-POS-MBR-X', 'amount' => 1, 'payment_method' => 'QRIS', 'status' => 'SUCCESS']);
        $kitchen = User::factory()->kitchen()->create();
        Role::findByName('kitchen', 'web')->givePermissionTo('View:JualMembership');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($kitchen);

        Livewire::test(JualMembership::class)
            ->call('showHistory')
            ->assertSet('posStep', 'selection')
            ->call('viewTransactionReceipt', $order->id)
            ->assertForbidden();
    }
}
