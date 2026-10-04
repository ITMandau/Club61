<?php

namespace Tests\Feature\Pos;

use App\Filament\Pages\JualMembership;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipPlanBenefit;
use App\Models\Membership\UserMembership;
use App\Models\Membership\UserMembershipBalance;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\Pos\Refund;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Integritas penjualan di POS Jual Membership: wajib shift PADEL_FRONTDESK (termasuk super_admin),
 * bukti bayar tervalidasi & tidak boleh dipakai ulang, izin server-side, anti submit ganda, dan cetak
 * ulang struk memakai snapshot saat penjualan.
 */
class MembershipSaleIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected MembershipPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');

        $this->cashier = User::factory()->cashier()->create(['name' => 'Kasir Membership', 'is_active' => true]);
        $this->cashier->givePermissionTo(['View:JualMembership', 'sell_membership']);

        $this->plan = MembershipPlan::create([
            'code' => 'MBR-INTEGRITY',
            'name' => 'Gold Integrity',
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30,
            'price' => 1000000,
            'is_active' => true,
        ]);
        MembershipPlanBenefit::create(['plan_id' => $this->plan->id, 'facility' => 'PADEL', 'quota_type' => 'HOURS', 'quota_value' => 10, 'discount_percent' => 10]);
    }

    private function openFrontdeskShift(User $by): PosCashierShift
    {
        return PosCashierShift::create([
            'shift_number' => 'SFT-PADEL-'.now()->format('Ymd').'-0001',
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $by->id,
            'opened_at' => now(),
            'starting_cash' => 0,
            'expected_cash' => 0,
        ]);
    }

    private function saleForm(string $phone = '081255550001', string $rrn = 'RRNINTEG01')
    {
        return Livewire::test(JualMembership::class)
            ->set('walkInName', 'Member Integritas')
            ->set('walkInPhone', $phone)
            ->call('selectPlan', $this->plan->id)
            ->set('paymentMethod', 'QRIS')
            ->set('qrisRrn', $rrn);
    }

    public function test_cashier_with_permission_and_open_frontdesk_shift_can_sell_and_payment_is_in_shift(): void
    {
        $shift = $this->openFrontdeskShift($this->cashier);
        $this->actingAs($this->cashier);

        $this->saleForm()->call('submitSale')->assertSet('showSuccessModal', true);

        $order = Order::where('order_type', 'MEMBERSHIP')->sole();
        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame('PAID', $order->payment_status);
        $this->assertSame($shift->id, $payment->pos_shift_id);
        $this->assertSame('RRNINTEG01', $payment->payload_log['qris_details']['rrn']);
        $this->assertSame('BCA_QRIS', $payment->payload_log['qris_details']['provider']);
        $this->assertSame(1, $shift->payments()->count());
    }

    public function test_sale_is_rejected_without_open_frontdesk_shift_even_for_super_admin(): void
    {
        $owner = User::factory()->superAdmin()->create();
        // Shift loket lama MEMBERSHIP_DESK tidak dihitung — membership masuk shift meja frontdesk.
        PosCashierShift::create([
            'shift_number' => 'SFT-MBR-OLD', 'counter' => 'MEMBERSHIP_DESK', 'status' => 'OPEN',
            'opened_by_id' => $owner->id, 'opened_at' => now(), 'starting_cash' => 0, 'expected_cash' => 0,
        ]);
        $this->actingAs($owner);

        $this->saleForm()
            ->call('proceedToPayment')
            ->assertSet('posStep', 'selection')
            ->call('submitSale')
            ->assertSet('showSuccessModal', false);

        $this->assertSame(0, Order::where('order_type', 'MEMBERSHIP')->count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, UserMembership::count());
    }

    public function test_unknown_payment_method_is_rejected(): void
    {
        $this->openFrontdeskShift($this->cashier);
        $this->actingAs($this->cashier);

        $this->saleForm()->set('paymentMethod', 'GRATIS')->call('submitSale')->assertSet('showSuccessModal', false);

        $this->assertSame(0, Order::where('order_type', 'MEMBERSHIP')->count());
        $this->assertSame(0, Payment::count());
    }

    public function test_reused_qris_rrn_is_rejected(): void
    {
        $this->openFrontdeskShift($this->cashier);
        $this->actingAs($this->cashier);

        $this->saleForm('081255550001', 'RRNDUP777')->call('submitSale')->assertSet('showSuccessModal', true);
        $this->saleForm('081255550002', 'rrndup777')->call('submitSale')->assertSet('showSuccessModal', false);

        $this->assertSame(1, Order::where('order_type', 'MEMBERSHIP')->count());
        $this->assertSame(1, UserMembership::count());
    }

    public function test_reused_edc_approval_code_is_rejected(): void
    {
        $this->openFrontdeskShift($this->cashier);
        $this->actingAs($this->cashier);

        foreach (['081255550011', '081255550012'] as $phone) {
            Livewire::test(JualMembership::class)
                ->set('walkInName', 'Member EDC')
                ->set('walkInPhone', $phone)
                ->call('selectPlan', $this->plan->id)
                ->call('setPaymentMethod', 'DEBIT_CARD')
                ->set('edcLast4', '4321')
                ->set('edcApprovalCode', 'APR999')
                ->set('edcTraceNumber', 'TRC555')
                ->call('submitSale');
        }

        $this->assertSame(1, Order::where('order_type', 'MEMBERSHIP')->count());
        $payment = Payment::sole();
        $this->assertSame('APR999', $payment->payload_log['edc_details']['approval_code']);
        $this->assertSame('EDC_BCA', $payment->payload_log['edc_details']['terminal']);
    }

    public function test_sale_without_sell_membership_permission_is_forbidden(): void
    {
        $viewer = User::factory()->cashier()->create();
        $viewer->givePermissionTo('View:JualMembership');
        $this->openFrontdeskShift($viewer);
        $this->actingAs($viewer);

        $this->saleForm()->call('submitSale')->assertForbidden();

        $this->assertSame(0, Order::where('order_type', 'MEMBERSHIP')->count());
    }

    public function test_second_submit_after_success_creates_no_second_order_or_quota(): void
    {
        $this->openFrontdeskShift($this->cashier);
        $this->actingAs($this->cashier);

        $page = $this->saleForm()->call('submitSale')->assertSet('showSuccessModal', true)
            // Keranjang & bukti bayar langsung dikosongkan, struk tetap tampil.
            ->assertSet('selectedPlanId', null)
            ->assertSet('qrisRrn', '')
            ->assertSet('completedMembershipData.plan_name', 'Gold Integrity');

        // Klik ulang apa adanya, lalu request rekayasa yang mengisi ulang keranjang sementara struk masih tampil.
        $page->call('submitSale')
            ->call('selectPlan', $this->plan->id)
            ->set('walkInPhone', '081255550001')
            ->set('qrisRrn', 'RRNINTEG02')
            ->call('submitSale');

        $this->assertSame(1, Order::where('order_type', 'MEMBERSHIP')->count());
        $this->assertSame(1, UserMembership::count());
        $this->assertEquals(10.0, (float) UserMembershipBalance::sum('remaining_quota'));
    }

    public function test_concurrent_identical_submit_is_blocked_by_lock(): void
    {
        $this->openFrontdeskShift($this->cashier);
        $this->actingAs($this->cashier);

        $lock = Cache::lock('pos_membership_sale:'.$this->cashier->id.':'.$this->plan->id.':081255550001', 30);
        $this->assertTrue($lock->get());

        $this->saleForm()->call('submitSale')->assertSet('showSuccessModal', false);
        $this->assertSame(0, Order::where('order_type', 'MEMBERSHIP')->count());

        $lock->release();
    }

    public function test_reprint_shows_sale_time_snapshot_not_current_balance(): void
    {
        $this->openFrontdeskShift($this->cashier);
        $this->actingAs($this->cashier);

        $page = $this->saleForm()->call('submitSale');
        $sold = $page->get('completedMembershipData');
        $this->assertEquals(10.0, $sold['balances'][0]['remaining_quota']);

        // Setelah dijual: kuota terpakai & masa aktif berubah.
        $card = UserMembership::sole();
        UserMembershipBalance::where('user_membership_id', $card->id)->update(['remaining_quota' => 3]);
        $card->update(['end_date' => now()->addYear()->toDateString()]);

        $orderId = Order::where('order_type', 'MEMBERSHIP')->value('id');
        $page->call('closeReceipt')
            ->call('viewTransactionReceipt', $orderId)
            ->assertSet('completedMembershipData.is_reprint', true)
            ->assertSet('completedMembershipData.balances.0.remaining_quota', 10.0)
            ->assertSet('completedMembershipData.end_date', $sold['end_date'])
            ->assertSet('completedMembershipData.membership_code', $sold['membership_code'])
            ->assertSee('RRNINTEG01');
    }

    public function test_history_shows_refunded_status_and_survives_malformed_date(): void
    {
        $this->openFrontdeskShift($this->cashier);
        $this->actingAs($this->cashier);

        $page = $this->saleForm()->call('submitSale')->call('closeReceipt')->call('showHistory')->assertSee('LUNAS');

        $order = Order::where('order_type', 'MEMBERSHIP')->sole();
        Refund::create([
            'order_id' => $order->id,
            'payment_id' => Payment::sole()->id,
            'refund_amount' => $order->grand_total,
            'reason' => 'tes refund',
            'status' => 'PROCESSED',
            'processed_at' => now(),
        ]);

        $page->set('historySearch', $order->order_number)->assertSee('DIREFUND');

        $page->set('historyDate', 'bukan-tanggal')
            ->assertOk()
            ->assertSet('historyDate', now('Asia/Jakarta')->toDateString());
    }
}
