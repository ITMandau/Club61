<?php

namespace Tests\Feature\Sponsor;

use App\Filament\Pages\BookOfflineCourt;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\UserMembership;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\PosCashierShift;
use App\Models\Pos\Voucher;
use App\Models\Sponsor\SponsorMemberVoucher;
use App\Models\Sponsor\SponsorOrganization;
use App\Models\Sponsor\SponsorOrganizationMember;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * 1 transaksi = 1 voucher: voucher jam corporate tidak bisa digabung kode promo (bisnis rugi dua kali potong) â€”
 * customer / kasir memilih salah satu. Voucher saldo (refund customer) tetap boleh karena itu uang customer sendiri.
 */
class CorporateVoucherPromoComboTest extends TestCase
{
    use RefreshDatabase;

    protected PadelBookingService $service;

    protected PadelCourt $court;

    protected User $employee;

    protected SponsorMemberVoucher $hours;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Services\Permission\Club61PermissionMatrix::syncAllPermissions('web');
        $this->service = app(PadelBookingService::class);
        $this->court = PadelCourt::create(['name' => 'Court Combo', 'type' => 'INDOOR', 'hourly_rate_regular' => 300000, 'hourly_rate_prime' => 300000, 'is_active' => true]);

        $pic = User::factory()->create();
        $plan = MembershipPlan::create(['code' => 'MBR-CORP-COMBO', 'name' => 'Corporate B2B', 'ownership_type' => 'ORGANIZATIONAL', 'duration_days' => 365, 'price' => 1000000, 'is_active' => true]);
        $membership = UserMembership::create([
            'membership_code' => 'MBR-CORP-COMBO-1', 'owner_type' => 'ORGANIZATIONAL', 'user_id' => $pic->id, 'plan_id' => $plan->id,
            'status' => 'ACTIVE', 'start_date' => now(), 'end_date' => now()->addYear(),
        ]);
        $org = SponsorOrganization::create(['name' => 'PT Combo', 'user_membership_id' => $membership->id, 'sponsor_admin_user_id' => $pic->id]);
        $this->employee = User::factory()->customer()->create(['name' => 'Budi Corp', 'phone' => '081277700055', 'is_active' => true]);
        $member = SponsorOrganizationMember::create(['sponsor_organization_id' => $org->id, 'user_id' => $this->employee->id, 'status' => 'ACTIVE']);
        // Sisa 1 jam: booking 2 jam â†’ 1 jam ditanggung corporate, sisanya dibayar (di situlah kode promo dulu ikut memotong).
        $this->hours = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id, 'hours_granted' => 1, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        Voucher::create(['code' => 'DISKON100K', 'discount_type' => 'FIXED', 'discount_value' => 100000, 'min_order_amount' => 0, 'quota' => 10, 'used_count' => 0, 'valid_until' => now()->addMonth(), 'is_active' => true]);
        Voucher::create(['code' => 'KR-SALDOBUDI', 'user_id' => $this->employee->id, 'discount_type' => Voucher::TYPE_CREDIT, 'discount_value' => 100000, 'balance' => 100000, 'min_order_amount' => 0, 'used_count' => 0, 'valid_until' => now()->addMonth(), 'is_active' => true]);
    }

    private function lockedBooking(): PadelBooking
    {
        $start = Carbon::tomorrow()->setTime(10, 0);

        return PadelBooking::create([
            'booking_code' => 'BK-'.strtoupper(Str::random(6)), 'user_id' => $this->employee->id, 'court_id' => $this->court->id,
            'booking_date' => $start->toDateString(), 'start_time' => $start, 'end_time' => $start->copy()->addHours(2),
            'court_fee' => 600000, 'coach_fee' => 0, 'equipment_fee' => 0, 'total_amount' => 600000, 'status' => 'LOCKED',
        ]);
    }

    private function checkout(PadelBooking $booking, ?string $code, ?string $sponsorVoucherId = null): array
    {
        return $this->service->checkout(
            bookingIds: [$booking->id], equipments: [], voucherCode: $code, paymentMethod: 'QRIS',
            idempotencyKey: Str::uuid()->toString(), user: $this->employee, sponsorVoucherId: $sponsorVoucherId,
        );
    }

    public function test_online_checkout_rejects_corporate_hours_combined_with_a_promo_code(): void
    {
        $booking = $this->lockedBooking();

        try {
            $this->checkout($booking, 'DISKON100K');
            $this->fail('Voucher jam corporate + kode promo harus ditolak');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('tidak bisa digabung', $e->getMessage());
        }

        // Semuanya dibatalkan: jam corporate tidak terpotong, booking tetap bisa di-checkout ulang.
        $this->assertEquals(0.0, (float) $this->hours->fresh()->hours_used);
        $this->assertSame('LOCKED', $booking->fresh()->status);
        $this->assertEquals(600000.0, (float) $booking->fresh()->court_fee);
        $this->assertSame(0, Order::count());
    }

    public function test_customer_who_turns_corporate_hours_off_can_use_the_promo_code(): void
    {
        $this->checkout($this->lockedBooking(), 'DISKON100K', 'NONE');

        $order = Order::sole();
        $this->assertSame('DISKON100K', $order->voucher_code);
        $this->assertEquals(100000.0, (float) $order->discount_amount);
        $this->assertEquals(0.0, (float) $this->hours->fresh()->hours_used);
    }

    public function test_credit_voucher_from_a_refund_still_works_with_corporate_hours(): void
    {
        $this->checkout($this->lockedBooking(), 'KR-SALDOBUDI');

        $order = Order::sole();
        $this->assertSame('KR-SALDOBUDI', $order->voucher_code, 'voucher saldo = uang customer sendiri, bukan diskon');
        $this->assertEquals(1.0, (float) $this->hours->fresh()->hours_used);
    }

    public function test_walk_in_checkout_rejects_corporate_hours_combined_with_a_promo_code(): void
    {
        $cashier = User::factory()->cashier()->create(['is_active' => true]);
        PosCashierShift::create([
            'shift_number' => 'SFT-COMBO-0001', 'counter' => 'PADEL_FRONTDESK', 'status' => 'OPEN',
            'opened_by_id' => $cashier->id, 'opened_at' => now(), 'starting_cash' => 0, 'expected_cash' => 0,
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('tidak bisa digabung');

        try {
            $this->service->processWalkInCheckout(
                customer: $this->employee,
                slots: [['court_id' => $this->court->id, 'start_time' => '10:00:00', 'end_time' => '11:00:00'], ['court_id' => $this->court->id, 'start_time' => '11:00:00', 'end_time' => '12:00:00']],
                bookingDate: now()->addDays(3)->format('Y-m-d'), equipments: [], paymentMethod: 'QRIS', cashier: $cashier,
                voucherCode: 'DISKON100K',
            );
        } finally {
            $this->assertEquals(0.0, (float) $this->hours->fresh()->hours_used);
        }
    }

    public function test_pos_screen_makes_the_cashier_pick_corporate_hours_or_the_promo_code(): void
    {
        $cashier = User::factory()->cashier()->create(['is_active' => true]);
        $cashier->givePermissionTo(['View:BookOfflineCourt', 'process_walkin_booking']);
        $this->actingAs($cashier);
        $date = now()->addDays(3)->format('Y-m-d');

        $slot = fn (int $h) => [
            'court_id' => $this->court->id, 'court_name' => $this->court->name,
            'start_time' => sprintf('%02d:00:00', $h), 'end_time' => sprintf('%02d:00:00', $h + 1),
            'time_label' => sprintf('%02d:00 - %02d:00', $h, $h + 1), 'price' => 300000.00,
        ];

        $component = Livewire::test(BookOfflineCourt::class)
            ->set('bookingDate', $date)
            ->set('selectedSlots', ["{$this->court->id}_10:00:00" => $slot(10), "{$this->court->id}_11:00:00" => $slot(11)])
            ->call('setCustomerMode', 'search')
            ->call('selectCustomer', $this->employee->id);

        // Jam corporate menyala â†’ kode promo ditolak.
        $component->set('voucherInput', 'DISKON100K')->call('applyVoucher')
            ->assertSet('appliedVoucherCode', null)
            ->assertNotified('Voucher Tidak Bisa Dipakai');

        // Kasir mematikan jam corporate â†’ kode promo bisa dipakai.
        $component->call('toggleSponsorVoucherBenefit')
            ->set('voucherInput', 'DISKON100K')->call('applyVoucher')
            ->assertSet('appliedVoucherCode', 'DISKON100K');
        $this->assertEquals(100000.0, $component->instance()->voucherDiscount);

        // Jam corporate dinyalakan lagi â†’ kode promo dilepas.
        $component->call('toggleSponsorVoucherBenefit')
            ->assertSet('useSponsorVoucherBenefit', true)
            ->assertSet('appliedVoucherCode', null)
            ->assertNotified('Kode Promo Dilepas');
    }
}
