<?php

namespace Tests\Feature;

use App\Filament\Pages\KelolaKontenWebsite;
use App\Filament\Pages\KelolaMenuFnb;
use App\Filament\Resources\Membership\Pages\ListMembershipPlans;
use App\Filament\Resources\Sponsor\Pages\ListSponsorAccessSchedules;
use App\Models\Fnb\FnbCategory;
use App\Models\Fnb\FnbMenu;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\UserMembership;
use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\ClubFinanceSetting;
use App\Models\Pos\PosCashierShift;
use App\Models\Setting\CompanyProfileFacility;
use App\Models\Sponsor\SponsorMemberVoucher;
use App\Models\Sponsor\SponsorOrganization;
use App\Models\Sponsor\SponsorOrganizationMember;
use App\Models\User;
use App\Services\Fnb\FnbPosService;
use App\Services\Padel\PadelBookingService;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Audit 18 Okt 2026 — perbaikan gelombang 2: stok bola, bukti bayar F&B, path foto dari browser, preview voucher
 * sponsor di kasir, hapus paket membership yang masih dipakai, label "sedang dikerjakan" (jadwal sponsor & KDS).
 */
class AuditFixesOct18Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected PadelCourt $court;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        ClubFinanceSetting::getSettings()->update(['is_admin_fee_enabled' => false, 'is_tax_enabled' => false]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        $this->admin = User::factory()->superAdmin()->create(['name' => 'Owner']);
        $this->court = PadelCourt::create([
            'name' => 'Court AF', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 200000, 'is_active' => true,
        ]);
    }

    private function lockedBooking(User $user, Carbon $start): PadelBooking
    {
        return PadelBooking::create([
            'booking_code' => 'BK-'.strtoupper(Str::random(6)), 'user_id' => $user->id, 'court_id' => $this->court->id,
            'booking_date' => $start->toDateString(), 'start_time' => $start, 'end_time' => $start->copy()->addHour(),
            'court_fee' => 200000, 'coach_fee' => 0, 'equipment_fee' => 0, 'total_amount' => 200000,
            'status' => 'LOCKED', 'expires_at' => now()->addMinutes(10),
        ]);
    }

    // ---------- S1: stok bola ----------

    public function test_ball_stock_comes_back_when_an_unpaid_booking_is_cancelled(): void
    {
        Http::fake();
        $customer = User::factory()->customer()->create(['name' => 'Budi']);
        $ball = CourtEquipment::create(['name' => 'Bola', 'type' => 'BALL', 'rental_price' => 35000, 'stock_quantity' => 5]);
        // Kondisi persis setelah checkout online: order belum dibayar, 3 bola sudah dipotong dari stok (5 → 2).
        $order = \App\Models\Pos\Order::create(['order_number' => 'ORD-BALL-1', 'user_id' => $customer->id, 'order_type' => 'ONLINE_BOOKING', 'subtotal' => 305000, 'grand_total' => 305000, 'payment_status' => 'UNPAID']);
        $booking = $this->lockedBooking($customer, now()->addDays(2)->setTime(10, 0));
        $booking->update(['order_id' => $order->id, 'status' => 'PENDING_PAYMENT']);
        \App\Models\Padel\PadelBookingEquipment::create(['order_id' => $order->id, 'booking_id' => $booking->id, 'equipment_id' => $ball->id, 'quantity' => 3, 'unit_price' => 35000, 'subtotal' => 105000, 'stock_deducted_at' => now()]);
        $ball->update(['stock_quantity' => 2]);
        $service = app(PadelBookingService::class);

        // Customer membatalkan pesanan yang belum dibayar → pembelian bola ikut batal.
        $this->assertSame(1, $service->releaseSlots([$booking->id], $customer));
        $this->assertSame(5, (int) $ball->fresh()->stock_quantity);

        // Idempoten: pemanggilan ulang (mis. webhook expire datang belakangan) tidak menambah stok lagi.
        $service->reverseBookingBenefits($booking->fresh());
        $this->assertSame(5, (int) $ball->fresh()->stock_quantity);
    }

    public function test_checkout_api_caps_equipment_quantity_and_rejects_duplicate_lines(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Budi']);
        $ball = CourtEquipment::create(['name' => 'Bola', 'type' => 'BALL', 'rental_price' => 35000, 'stock_quantity' => 100]);
        $booking = $this->lockedBooking($customer, now()->addDays(2)->setTime(10, 0));
        $base = ['booking_ids' => [$booking->id], 'payment_method' => 'QRIS'];

        $this->actingAs($customer)->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/padel/checkout', $base + ['equipments' => [['equipment_id' => $ball->id, 'quantity' => 11]]])
            ->assertStatus(422)->assertJsonValidationErrors('equipments.0.quantity');

        $this->actingAs($customer)->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/padel/checkout', $base + ['equipments' => [
                ['equipment_id' => $ball->id, 'quantity' => 10], ['equipment_id' => $ball->id, 'quantity' => 10],
            ]])->assertStatus(422)->assertJsonValidationErrors('equipments.0.equipment_id');

        $this->assertSame(100, (int) $ball->fresh()->stock_quantity);
    }

    // ---------- T4: bukti bayar F&B ----------

    public function test_fnb_cashier_rejects_a_reused_qris_rrn_or_edc_approval(): void
    {
        PosCashierShift::create([
            'shift_number' => 'SFT-AF-'.Str::random(6), 'counter' => 'FNB_COUNTER', 'status' => 'OPEN',
            'opened_by_id' => $this->admin->id, 'opened_at' => now(), 'starting_cash' => 0, 'expected_cash' => 0,
        ]);
        $menu = FnbMenu::create(['category_id' => FnbCategory::create(['name' => 'Coffee', 'sort_order' => 1])->id, 'name' => 'Latte', 'base_price' => 38000, 'is_available' => true]);
        $sell = fn (string $method, array $meta) => app(FnbPosService::class)->checkout(
            items: [['menu_id' => $menu->id, 'quantity' => 1]], orderType: 'TAKEAWAY', tableNumber: null,
            cashier: $this->admin, paymentMethod: $method, paymentMeta: $meta,
        );

        $sell('QRIS', ['qris_provider' => 'BCA_QRIS', 'qris_rrn' => 'RRN778899']);
        $sell('QRIS', ['qris_provider' => 'BCA_QRIS', 'qris_rrn' => 'RRN778900']); // repeat order, kode baru → aman

        try {
            $sell('QRIS', ['qris_provider' => 'BCA_QRIS', 'qris_rrn' => 'rrn778899']);
            $this->fail('RRN yang sama tidak boleh melunasi order kedua');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $edc = ['card_type' => 'DEBIT', 'card_last_4' => '1234', 'approval_code' => 'APR123', 'trace_number' => 'TR001'];
        $sell('DEBIT_CARD', $edc);
        try {
            $sell('DEBIT_CARD', $edc);
            $this->fail('approval code + trace yang sama tidak boleh dipakai ulang');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(3, \App\Models\Pos\Order::where('order_type', 'TAKEAWAY')->count(), 'order yang ditolak ikut batal (rollback)');
    }

    // ---------- S4: path foto dari browser ----------

    public function test_tampered_photo_path_cannot_delete_other_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('signature/videos/promo.mp4', 'video');
        Storage::disk('public')->put('company-profile/facilities/real.jpg', 'img');
        $facility = CompanyProfileFacility::create(['title' => 'Sauna', 'description' => 'x', 'photo_path' => 'company-profile/facilities/real.jpg', 'sort_order' => 0]);

        $page = Livewire::actingAs($this->admin)->test(KelolaKontenWebsite::class);
        $index = collect($page->get('facilities'))->search(fn ($f) => $f['id'] === $facility->id);
        $page->set("facilities.{$index}.photo_path", 'signature/videos/promo.mp4')
            ->set("facilityRemovePhoto.{$index}", true)
            ->call('save');

        Storage::disk('public')->assertExists('signature/videos/promo.mp4');      // dulu terhapus
        Storage::disk('public')->assertMissing('company-profile/facilities/real.jpg'); // foto asli fasilitas yang dihapus
        $this->assertNull($facility->fresh()->photo_path);
    }

    public function test_tampered_menu_photo_path_cannot_delete_other_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('signature/videos/promo.mp4', 'video');
        $category = FnbCategory::create(['name' => 'Coffee', 'sort_order' => 1]);
        $menu = FnbMenu::create(['category_id' => $category->id, 'name' => 'Latte', 'base_price' => 38000, 'is_available' => true]);

        Livewire::actingAs($this->admin)->test(KelolaMenuFnb::class)
            ->call('openEditMenuModal', $menu->id)
            ->set('existingMenuPhotoPath', 'signature/videos/promo.mp4')
            ->call('saveMenu');

        Storage::disk('public')->assertExists('signature/videos/promo.mp4');
        $this->assertNull($menu->fresh()->image_url, 'path kiriman browser tidak disimpan ke menu');
    }

    // ---------- S6: preview voucher sponsor di kasir = server ----------

    public function test_walk_in_sponsor_preview_uses_one_voucher_like_the_server(): void
    {
        $pic = User::factory()->create();
        $plan = MembershipPlan::create(['code' => 'CORP-AF', 'name' => 'Corp AF', 'ownership_type' => 'ORGANIZATIONAL', 'duration_days' => 365, 'price' => 1, 'is_active' => true]);
        $mbr = UserMembership::create(['membership_code' => 'CORP-AF', 'owner_type' => 'ORGANIZATIONAL', 'user_id' => $pic->id, 'plan_id' => $plan->id, 'status' => 'ACTIVE', 'start_date' => now(), 'end_date' => now()->addYear()]);
        $org = SponsorOrganization::create(['name' => 'PT AF', 'user_membership_id' => $mbr->id, 'sponsor_admin_user_id' => $pic->id]);
        $employee = User::factory()->create(['phone' => '081277700011']);
        $member = SponsorOrganizationMember::create(['sponsor_organization_id' => $org->id, 'user_id' => $employee->id, 'status' => 'ACTIVE']);
        foreach ([5, 10] as $days) {
            SponsorMemberVoucher::create(['sponsor_organization_member_id' => $member->id, 'hours_granted' => 1, 'hours_used' => 0, 'issued_at' => now(), 'expires_at' => now()->addDays($days)]);
        }
        $date = now()->addDays(2)->toDateString();

        $quote = app(PadelBookingService::class)->quoteWalkInBenefits(
            [['court_id' => $this->court->id, 'start_time' => '10:00', 'end_time' => '11:00'], ['court_id' => $this->court->id, 'start_time' => '11:00', 'end_time' => '12:00']],
            $date, $employee,
        );

        // Dulu: 2 voucher dijumlah → 2 jam gratis (Rp0) di layar, server cuma 1 jam → bayar selalu 409.
        $this->assertEquals(1.0, $quote['sponsor']['hours']);
        $this->assertEquals(200000.0, $quote['court_total_after']);

        $booking = PadelBooking::create([
            'booking_code' => 'BK-AF-2H', 'user_id' => $employee->id, 'court_id' => $this->court->id, 'booking_date' => $date,
            'start_time' => Carbon::parse("{$date} 10:00"), 'end_time' => Carbon::parse("{$date} 12:00"),
            'court_fee' => 400000, 'coach_fee' => 0, 'equipment_fee' => 0, 'total_amount' => 400000, 'status' => 'LOCKED', 'expires_at' => now()->addMinutes(10),
        ]);
        $preview = app(PadelBookingService::class)->previewMembershipBenefit([$booking->id], $employee);
        $this->assertEquals($quote['sponsor_discount'], $preview['sponsor_voucher_benefit']['court_discount_amount']);
    }

    // ---------- S2: hapus paket membership ----------

    public function test_plan_in_use_cannot_be_deleted_but_unused_plan_can(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Budi']);
        $used = MembershipPlan::create(['code' => 'GOLD-AF', 'name' => 'Gold', 'ownership_type' => 'INDIVIDUAL', 'duration_days' => 30, 'price' => 1, 'is_active' => true]);
        $active = UserMembership::create(['membership_code' => 'MBR-AF-1', 'owner_type' => 'INDIVIDUAL', 'user_id' => $customer->id, 'plan_id' => $used->id, 'status' => 'ACTIVE', 'start_date' => now(), 'end_date' => now()->addDays(30), 'qr_pass_hash' => 'QR-AF-1']);
        $unused = MembershipPlan::create(['code' => 'OLD-AF', 'name' => 'Old', 'ownership_type' => 'INDIVIDUAL', 'duration_days' => 30, 'price' => 1, 'is_active' => true]);

        Livewire::actingAs($this->admin)->test(ListMembershipPlans::class)
            ->callAction(TestAction::make('delete')->table($used))
            ->assertNotified('Paket masih dipakai member');
        $this->assertNotSoftDeleted($used);

        $this->assertFalse($used->delete(), 'model juga menolak (pengaman terakhir)');
        $this->assertNotSoftDeleted($used);

        Livewire::actingAs($this->admin)->test(ListMembershipPlans::class)->callAction(TestAction::make('delete')->table($unused));
        $this->assertSoftDeleted($unused);

        // Kartu lama yang paketnya terhapus tetap bisa membaca paketnya (dulu null → Home member 500).
        $active->update(['status' => 'EXPIRED']);
        $this->assertNotFalse($used->fresh()->delete());
        $this->assertSame('Gold', $active->fresh()->plan->name);
    }

    // ---------- Label "sedang dikerjakan" ----------

    public function test_unfinished_features_are_labelled_in_progress(): void
    {
        $kitchen = User::factory()->kitchen()->create(['name' => 'Koki']);
        $this->actingAs($kitchen)->get('/kitchen')->assertOk()
            ->assertSee('Sedang dalam pengerjaan')->assertSee('data-kds-in-progress', false)
            ->assertDontSee('#TKT-041')->assertDontSee('Smashed Avocado Toast');

        Livewire::actingAs($this->admin)->test(ListSponsorAccessSchedules::class)->assertSee('Sedang dalam proses pembuatan');
        $this->assertSame('Proses', \App\Filament\Resources\Sponsor\SponsorAccessScheduleResource::getNavigationBadge());
    }
}
