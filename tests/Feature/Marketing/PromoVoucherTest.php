<?php

namespace Tests\Feature\Marketing;

use App\Filament\Pages\DaftarVoucher;
use App\Models\Audit\ActivityLog;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\UserMembership;
use App\Models\Membership\UserMembershipBalance;
use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\ClubFinanceSetting;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\Voucher;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\PromoVoucherService;
use App\Services\Finance\VoucherService;
use App\Services\Padel\PadelBookingService;
use App\Services\Permission\Club61PermissionMatrix;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Modul 22 tahap 2 — voucher promo dinamis (cara sebar A) dari halaman Daftar Voucher, dan aturan pemakaiannya di
 * VoucherService::resolve() untuk checkout online & kasir.
 */
class PromoVoucherTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected PadelCourt $court;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        ClubFinanceSetting::getSettings()->update(['is_admin_fee_enabled' => false, 'is_tax_enabled' => false]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        $this->admin = User::factory()->superAdmin()->create(['name' => 'Owner']);
        $this->customer = User::factory()->customer()->create(['name' => 'Budi']);
        $this->court = PadelCourt::create(['name' => 'Court PR', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 200000, 'is_active' => true]);
    }

    private function promo(array $overrides = []): Voucher
    {
        return app(PromoVoucherService::class)->create($overrides + [
            'name' => 'Promo Pembukaan', 'code' => 'OPEN20', 'discount_type' => Voucher::TYPE_PERCENT, 'discount_value' => 20,
            'max_discount_amount' => 50000, 'categories' => ['PADEL'], 'channel' => Voucher::CHANNEL_ALL,
            'valid_until' => now()->addDays(30)->toDateString(), 'is_active' => true,
        ], $this->admin);
    }

    private function resolve(string $code, ?User $user = null, float $court = 200000, float $addon = 0, string $channel = 'ONLINE', bool $member = false): array
    {
        return app(VoucherService::class)->resolve($code, $user ?? $this->customer, $court + $addon, context: [
            'channel' => $channel, 'amounts' => ['PADEL' => $court, 'PADEL_ADDON' => $addon], 'membership_applied' => $member,
        ]);
    }

    private function order(string $code, string $status, ?User $user = null, float $discount = 40000): Order
    {
        return Order::create([
            'order_number' => 'ORD-PR-'.Str::random(6), 'user_id' => ($user ?? $this->customer)->id, 'order_type' => 'ONLINE_BOOKING',
            'subtotal' => 200000, 'discount_amount' => $discount, 'grand_total' => 200000 - $discount, 'payment_status' => $status, 'voucher_code' => $code,
        ]);
    }

    public function test_applied_voucher_shows_its_discount_and_rules(): void
    {
        $promo = $this->promo(['min_order_amount' => 300000, 'channel' => Voucher::CHANNEL_POS, 'valid_until' => '2026-12-31']);

        $terms = app(PromoVoucherService::class)->terms($promo);
        $this->assertSame('Promo Pembukaan', $terms['name']);
        $this->assertSame('Diskon 20% (maks. '.\App\Filament\Pages\BukuTransaksi::rupiah(50000).') sewa lapangan', $terms['headline']);
        $this->assertSame([
            'Min. sewa lapangan '.\App\Filament\Pages\BukuTransaksi::rupiah(300000),
            'Berlaku s/d 31 Dec 2026', 'Khusus kasir', 'Tidak bisa digabung benefit member', '1x per akun',
        ], $terms['rules']);

        // Checkout online menerima data yang sama untuk dirangkai jadi teks.
        $this->promo(['code' => 'ONLINE20', 'name' => 'Promo Online', 'stack_with_membership' => true, 'valid_until' => '2026-12-31']);
        $this->actingAs($this->customer)->postJson('/api/v1/padel/vouchers/check', ['code' => 'ONLINE20', 'amount' => 200000, 'court_amount' => 200000])
            ->assertOk()
            ->assertJsonPath('data.name', 'Promo Online')
            ->assertJsonPath('data.value', 20)
            ->assertJsonPath('data.max_discount', 50000)
            ->assertJsonPath('data.channel', 'ALL')
            ->assertJsonPath('data.stack_with_membership', true)
            ->assertJsonPath('data.valid_until', '31 Dec 2026');
    }

    // ---------- Daftar Voucher (buat & ubah promo) ----------

    public function test_admin_creates_a_promo_from_the_voucher_list(): void
    {
        Livewire::actingAs($this->admin)->test(DaftarVoucher::class)
            ->callAction(TestAction::make('create')->table(), [
                'name' => 'Promo Instagram', 'code' => 'ig-nov', 'discount_type' => 'FIXED', 'discount_value' => 25000,
                'min_order_amount' => 150000, 'categories' => ['PADEL'], 'channel' => 'ONLINE', 'stack_with_membership' => false,
                'quota' => 100, 'valid_from' => now()->toDateString(), 'valid_until' => now()->addDays(14)->toDateString(), 'is_active' => true,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Promo IG-NOV dibuat.');

        $promo = Voucher::where('code', 'IG-NOV')->sole();
        $this->assertSame('Promo Instagram', $promo->name);
        $this->assertSame(['PADEL'], $promo->categories);
        $this->assertSame('ONLINE', $promo->channel);
        $this->assertSame(100, $promo->quota);
        $this->assertSame($this->admin->id, $promo->created_by_id);
        $this->assertTrue(ActivityLog::where('event', 'promo.created')->where('module', 'MARKETING')->exists());

        // Satu tabel: promo dan voucher saldo customer tampil bersama, dibedakan di kolom Kode & Asal.
        Voucher::create(['user_id' => $this->customer->id, 'code' => 'KR-SALDO01', 'discount_type' => 'CREDIT', 'discount_value' => 50000, 'balance' => 50000, 'quota' => null, 'used_count' => 0, 'valid_until' => now()->addMonth(), 'is_active' => true]);
        $this->actingAs($this->admin)->get(DaftarVoucher::getUrl())->assertOk()
            ->assertSee('IG-NOV')->assertSee('Promo Instagram')->assertSee('KR-SALDO01')->assertSee('Refund ditolak')->assertSee('Kode promo');

        // Ubah & nonaktifkan promo dari tabel yang sama; voucher saldo tidak bisa diubah sebagai promo.
        Livewire::actingAs($this->admin)->test(DaftarVoucher::class)
            ->assertTableActionVisible('edit', $promo)
            ->assertTableActionHidden('edit', Voucher::where('code', 'KR-SALDO01')->sole())
            ->callTableAction('edit', $promo, ['name' => 'Promo Instagram Desember'])
            ->assertHasNoTableActionErrors()
            ->callTableAction('togglePromo', $promo)
            ->assertNotified('Promo IG-NOV dinonaktifkan.');
        $this->assertSame('Promo Instagram Desember', $promo->fresh()->name);
        $this->assertFalse($promo->fresh()->is_active);
    }

    public function test_promo_that_has_not_started_is_listed_as_scheduled_not_active(): void
    {
        $running = $this->promo();
        $later = $this->promo(['code' => 'LATER10', 'valid_from' => now()->addDays(3)->toDateString(), 'valid_until' => now()->addDays(10)->toDateString()]);

        $this->assertSame('TERJADWAL', DaftarVoucher::statusOf($later));
        Livewire::actingAs($this->admin)->test(DaftarVoucher::class)
            ->filterTable('status', 'AKTIF')
            ->assertCanSeeTableRecords([$running])
            ->assertCanNotSeeTableRecords([$later])
            ->filterTable('status', 'TERJADWAL')
            ->assertCanSeeTableRecords([$later])
            ->assertCanNotSeeTableRecords([$running]);
    }

    public function test_invalid_promo_input_is_rejected_with_a_message(): void
    {
        $this->promo();

        foreach ([
            ['code' => 'OPEN20'],                       // kode sudah dipakai
            ['code' => 'KR-PROMO1'],                    // awalan voucher saldo
            ['code' => 'NEW1', 'discount_value' => 150], // persen > 100
            ['code' => 'NEW2', 'categories' => []],     // tanpa kategori
            ['code' => 'NEW3', 'valid_from' => now()->addDays(5)->toDateString(), 'valid_until' => now()->addDay()->toDateString()],
        ] as $override) {
            try {
                $this->promo($override);
                $this->fail('harus ditolak: '.json_encode($override));
            } catch (HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
            }
        }
        $this->assertSame(1, Voucher::count());
    }

    public function test_staff_without_manage_vouchers_can_view_but_not_create(): void
    {
        $role = Role::create(['name' => 'marketing_viewer', 'guard_name' => 'web']);
        $role->syncPermissions(['View:DaftarVoucher']);
        $viewer = User::factory()->create();
        $viewer->syncRoles([$role]);

        Livewire::actingAs($viewer)->test(DaftarVoucher::class)->assertActionHidden(TestAction::make('create')->table());
        $this->assertSame(0, Voucher::count());
    }

    // ---------- Aturan pemakaian ----------

    public function test_promo_only_cuts_the_court_rental_not_add_ons(): void
    {
        $this->promo(['max_discount_amount' => null]);

        $result = $this->resolve('open20', court: 200000, addon: 100000);

        $this->assertNull($result['error']);
        $this->assertEquals(40000, $result['discount']); // 20% × 200rb lapangan, bukan × 300rb
    }

    public function test_minimum_order_is_measured_on_the_court_rental(): void
    {
        $this->promo(['min_order_amount' => 250000]);

        $this->assertStringContainsString('Minimal transaksi', (string) $this->resolve('OPEN20', court: 200000, addon: 100000)['error']);
        $this->assertNull($this->resolve('OPEN20', court: 300000)['error']);
    }

    public function test_period_and_channel_are_enforced(): void
    {
        $this->promo(['code' => 'SOON', 'valid_from' => now()->addDays(3)->toDateString()]);
        $this->assertStringContainsString('baru berlaku', (string) $this->resolve('SOON')['error']);

        $this->promo(['code' => 'ONLINEONLY', 'channel' => 'ONLINE']);
        $this->assertNull($this->resolve('ONLINEONLY', channel: 'ONLINE')['error']);
        $this->assertStringContainsString('booking online', (string) $this->resolve('ONLINEONLY', channel: 'POS')['error']);

        $this->promo(['code' => 'POSONLY', 'channel' => 'POS']);
        $this->assertStringContainsString('di kasir', (string) $this->resolve('POSONLY', channel: 'ONLINE')['error']);
    }

    public function test_membership_stacking_follows_the_promo_switch(): void
    {
        $this->promo(['code' => 'NOSTACK']);
        $this->promo(['code' => 'STACKOK', 'stack_with_membership' => true]);

        $this->assertStringContainsString('benefit member', (string) $this->resolve('NOSTACK', member: true)['error']);
        $this->assertNull($this->resolve('STACKOK', member: true)['error']);
    }

    public function test_one_use_per_account_and_it_comes_back_when_the_order_is_cancelled(): void
    {
        $this->promo();
        $other = User::factory()->customer()->create(['name' => 'Siti']);

        $pending = $this->order('OPEN20', 'UNPAID');
        $this->assertStringContainsString('sudah pernah kamu pakai', (string) $this->resolve('OPEN20')['error']);
        $this->assertNull($this->resolve('OPEN20', $other)['error'], 'akun lain tetap bisa');

        // Checkout tidak dibayar sampai batas bayar / dibatalkan → jatah akun kembali (T2).
        $pending->update(['payment_status' => 'CANCELLED']);
        $this->assertNull($this->resolve('OPEN20')['error']);

        $this->order('OPEN20', 'PAID');
        $this->assertStringContainsString('sudah pernah kamu pakai', (string) $this->resolve('OPEN20')['error']);
    }

    public function test_paid_then_cancelled_order_returns_quota_and_the_account_allowance(): void
    {
        $promo = $this->promo(['quota' => 5]);
        $order = $this->order('OPEN20', 'PAID');
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => $order->order_number, 'amount' => 160000, 'payment_method' => 'QRIS', 'status' => 'SUCCESS']);
        app(VoucherService::class)->consume($order);
        $this->assertSame(4, $promo->fresh()->quota);
        $this->assertSame(1, $promo->fresh()->used_count);

        PadelBooking::create([
            'booking_code' => 'BK-PR-1', 'order_id' => $order->id, 'user_id' => $this->customer->id, 'court_id' => $this->court->id,
            'booking_date' => now()->addDay()->toDateString(), 'start_time' => now()->addDay()->setTime(10, 0), 'end_time' => now()->addDay()->setTime(11, 0),
            'court_fee' => 160000, 'total_amount' => 160000, 'status' => 'CANCELLED',
        ]);
        app(VoucherService::class)->restoreCreditForCancelledOrder($order);
        app(VoucherService::class)->restoreCreditForCancelledOrder($order->fresh()); // idempoten

        $this->assertSame(5, $promo->fresh()->quota);
        $this->assertSame(0, $promo->fresh()->used_count);
        $this->assertNull($this->resolve('OPEN20')['error'], 'jatah akun kembali');
    }

    public function test_quota_counts_unpaid_orders_in_flight(): void
    {
        $this->promo(['quota' => 1]);
        $this->order('OPEN20', 'UNPAID', User::factory()->customer()->create(['name' => 'Ani']));

        $this->assertStringContainsString('Kuota voucher sudah habis', (string) $this->resolve('OPEN20')['error']);
    }

    public function test_deactivated_promo_is_rejected(): void
    {
        $promo = $this->promo();
        app(PromoVoucherService::class)->setActive($promo, false, $this->admin);

        $this->assertStringContainsString('tidak aktif', (string) $this->resolve('OPEN20')['error']);
    }

    public function test_used_promo_locks_its_discount_but_quota_and_period_can_change(): void
    {
        $promo = $this->promo(['quota' => 10]);
        $promo->update(['quota' => 7, 'used_count' => 3]);
        $service = app(PromoVoucherService::class);
        $base = ['name' => 'Promo Pembukaan', 'code' => 'OPEN20', 'discount_type' => 'PERCENT', 'discount_value' => 20, 'max_discount_amount' => 50000,
            'categories' => ['PADEL'], 'channel' => 'ALL', 'valid_until' => now()->addDays(60)->toDateString(), 'is_active' => true];

        try {
            $service->update($promo, ['discount_value' => 30] + $base, $this->admin);
            $this->fail('potongan promo yang sudah dipakai tidak boleh diubah');
        } catch (HttpException $e) {
            $this->assertStringContainsString('sudah dipakai', $e->getMessage());
        }

        try {
            $service->update($promo, ['quota' => 2] + $base, $this->admin);
            $this->fail('kuota total tidak boleh di bawah yang sudah dipakai');
        } catch (HttpException $e) {
            $this->assertStringContainsString('lebih kecil', $e->getMessage());
        }

        $service->update($promo, ['quota' => 20] + $base, $this->admin);
        $this->assertSame(17, $promo->fresh()->quota, 'kuota total 20 − 3 terpakai');
        $this->assertSame(20, $promo->fresh()->totalQuota());
    }

    // ---------- Checkout ----------

    public function test_online_checkout_applies_the_promo_to_court_only_and_rejects_an_invalid_code(): void
    {
        $this->promo(['max_discount_amount' => null]);
        $ball = CourtEquipment::create(['name' => 'Bola', 'type' => 'BALL', 'rental_price' => 50000, 'stock_quantity' => 10]);
        $service = app(PadelBookingService::class);
        $hold = fn () => PadelBooking::create([
            'booking_code' => 'BK-'.strtoupper(Str::random(6)), 'user_id' => $this->customer->id, 'court_id' => $this->court->id,
            'booking_date' => now()->addDays(2)->toDateString(), 'start_time' => now()->addDays(2)->setTime(10, 0), 'end_time' => now()->addDays(2)->setTime(11, 0),
            'court_fee' => 200000, 'coach_fee' => 0, 'equipment_fee' => 0, 'total_amount' => 200000, 'status' => 'LOCKED', 'expires_at' => now()->addMinutes(10),
        ]);

        try {
            $service->checkout(bookingIds: [$hold()->id], equipments: [], voucherCode: 'NOPE', paymentMethod: 'QRIS', idempotencyKey: (string) Str::uuid(), user: $this->customer);
            $this->fail('kode tidak valid harus ditolak, bukan diabaikan diam-diam');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $result = $service->checkout(bookingIds: [$hold()->id], equipments: [['equipment_id' => $ball->id, 'quantity' => 2]], voucherCode: 'OPEN20',
            paymentMethod: 'QRIS', idempotencyKey: (string) Str::uuid(), user: $this->customer);

        $order = Order::where('voucher_code', 'OPEN20')->sole();
        $this->assertEquals(40000, (float) $order->discount_amount); // 20% × 200rb lapangan; bola 100rb tidak dipotong
        $this->assertEquals(260000, (float) $order->grand_total);
        $this->assertTrue($result['success']);

        // Invoice customer memuat potongan voucher + aturannya, jadi Rp400rb → Rp350rb tidak muncul tanpa penjelasan.
        $ticket = $service->getTicket(PadelBooking::where('order_id', $order->id)->value('id'), $this->customer);
        $this->assertSame([
            'code' => 'OPEN20', 'discount' => 40000.0, 'is_credit' => false, 'name' => 'Promo Pembukaan',
            'type' => 'PERCENT', 'value' => 20.0, 'max_discount' => null,
        ], $ticket->order_voucher);

        // Struk kasir (cetak ulang memakai catatan yang sama).
        $this->assertSame('Promo Pembukaan · Diskon 20% sewa lapangan', app(PromoVoucherService::class)->receiptNote('OPEN20'));
        $this->assertNull(app(PromoVoucherService::class)->receiptNote(null));
    }
}
