<?php

namespace Tests\Feature\Membership;

use App\Filament\Pages\Kustomer;
use App\Models\Membership\FacilityCheckin;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipPlanBenefit;
use App\Models\Membership\UserMembership;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerDirectoryLiveMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Services\Permission\Club61PermissionMatrix::syncAllPermissions('web');
        \App\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->syncPermissions(
            \App\Services\Permission\Club61PermissionMatrix::getAllPermissionSlugs()
        );
    }

    public function test_kustomer_page_renders_live_members_and_monitoring_logs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $plan = MembershipPlan::create([
            'code' => 'MBR-TEST',
            'name' => 'Test Plan',
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30,
            'price' => 1000000.00,
            'is_active' => true,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $plan->id,
            'facility' => 'PADEL',
            'quota_type' => 'HOURS',
            'quota_value' => 10.00,
            'discount_percent' => 10.00,
        ]);

        $customer = User::factory()->create(['name' => 'Budi Testing']);

        $service = app(\App\Services\Membership\MembershipBalanceService::class);
        $membership = $service->purchasePlan($customer, $plan, ['status' => 'ACTIVE']);
        $service->activateMembership($membership);

        Livewire::actingAs($admin)
            ->test(Kustomer::class)
            ->assertSee('Budi Testing')
            ->assertSee($membership->membership_code)
            ->set('activeTab', 'live_monitoring')
            ->assertSee('Aktivasi membership paket');
    }

    public function test_individual_member_detail_displays_habit_analysis_and_padel_schedule(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $court = PadelCourt::create([
            'name' => 'Court 1 - Panoramic Indoor',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 300000.00,
            'hourly_rate_prime' => 450000.00,
        ]);

        $plan = MembershipPlan::create([
            'code' => 'MBR-SILVER-TEST',
            'name' => 'Silver Padel Addict',
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30,
            'price' => 3500000.00,
            'is_active' => true,
        ]);

        $customer = User::factory()->create(['name' => 'Budi Habit Test', 'phone' => '081234567890']);

        $service = app(\App\Services\Membership\MembershipBalanceService::class);
        $membership = $service->purchasePlan($customer, $plan, ['status' => 'ACTIVE']);
        $service->activateMembership($membership);

        PadelBooking::create([
            'booking_code' => 'BK-TEST-001',
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'booking_date' => now()->toDateString(),
            'start_time' => now()->setTime(17, 0),
            'end_time' => now()->setTime(19, 0),
            'court_fee' => 0.00,
            'total_amount' => 0.00,
            'status' => 'CHECKED_IN',
            'checked_in_at' => now()->setTime(16, 55),
            'member_hours_consumed' => 2.00,
        ]);

        Livewire::actingAs($admin)
            ->test(Kustomer::class)
            ->call('openDetail', $membership->id)
            ->assertSee('Analisis Kebiasaan & Pola Bermain')
            ->assertSee('Jadwal & Riwayat Reservasi Lapangan Padel')
            ->assertSee('Court 1 - Panoramic Indoor')
            ->assertSee('BK-TEST-001')
            ->assertSee('CHECKED IN')
            ->assertSee('-2 Jam Kuota');
    }

    public function test_corporate_member_detail_displays_employee_roster_and_idle_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $plan = MembershipPlan::create([
            'code' => 'MBR-CORP-TEST',
            'name' => 'Corporate Sponsor Plan',
            'ownership_type' => 'ORGANIZATIONAL',
            'duration_days' => 90,
            'price' => 25000000.00,
            'is_active' => true,
        ]);

        $corpUser = User::factory()->create(['name' => 'PT Sinar Harapan Abadi']);

        $service = app(\App\Services\Membership\MembershipBalanceService::class);
        $membership = $service->purchasePlan($corpUser, $plan, [
            'owner_type' => 'ORGANIZATIONAL',
            'status' => 'ACTIVE',
        ]);
        $service->activateMembership($membership);

        Livewire::actingAs($admin)
            ->test(Kustomer::class)
            ->call('openDetail', $membership->id)
            ->assertSee('Corporate Sponsor Pool System')
            ->assertSee('Budi Pratama')
            ->assertSee('Siti Rahmawati')
            ->assertSee('Ahmad Fauzi')
            ->assertSee('KOSONG / PASIF')
            ->assertSee('Blast Pengingat via WhatsApp');
    }

    public function test_online_membership_checkout_api_works_for_web_customer(): void
    {
        $plan = MembershipPlan::create([
            'code' => 'MBR-ONLINE',
            'name' => 'Online Gold Plan',
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30,
            'price' => 5000000.00,
            'is_active' => true,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $plan->id,
            'facility' => 'PADEL',
            'quota_type' => 'HOURS',
            'quota_value' => 20.00,
            'discount_percent' => 20.00,
        ]);

        $customer = User::factory()->create(['name' => 'Online Customer']);

        $response = $this->actingAs($customer)
            ->postJson('/api/v1/membership/checkout', [
                'plan_id' => $plan->id,
                'payment_method' => 'QRIS',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        // In mock simulator mode, it activates immediately
        $this->assertDatabaseHas('user_memberships', [
            'user_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_customer_my_club_page_renders_plans_and_links_to_membership(): void
    {
        $plan = MembershipPlan::create([
            'code' => 'MBR-VIP-01',
            'name' => 'Silver Padel Addict VIP',
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30,
            'price' => 3500000.00,
            'is_active' => true,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $plan->id,
            'facility' => 'PADEL',
            'quota_type' => 'HOURS',
            'quota_value' => 10.00,
            'discount_percent' => 20.00,
        ]);

        $customer = User::factory()->create(['name' => 'Andi Wijaya VIP']);

        $response = $this->actingAs($customer)->get(route('customer.my-club'));

        $response->assertStatus(200)
            ->assertSee('Silver Padel Addict VIP')
            ->assertSee('Lihat Detail &amp; Beli Online', false);
    }

    public function test_customer_membership_page_renders_interactive_blade_without_modal(): void
    {
        $plan = MembershipPlan::create([
            'code' => 'MBR-VIP-02',
            'name' => 'Gold Ultimate VIP',
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30,
            'price' => 6000000.00,
            'is_active' => true,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $plan->id,
            'facility' => 'PADEL',
            'quota_type' => 'HOURS',
            'quota_value' => 25.00,
            'discount_percent' => 30.00,
        ]);

        $customer = User::factory()->create(['name' => 'Budi Gold VIP']);

        $response = $this->actingAs($customer)->get(route('customer.membership', ['plan' => $plan->id]));

        $response->assertStatus(200)
            ->assertSee('Pilih &amp; Pembelian Paket Keanggotaan', false)
            ->assertSee('Gold Ultimate VIP')
            ->assertSee('Rincian Hak Akses &amp; Benefit yang Didapat', false)
            ->assertSee('Bayar via Midtrans Snap (Cashless)', false)
            ->assertSee('QRIS Instant', false)
            ->assertSee('Virtual Account Bank &amp; Kartu Kredit', false);
    }
}
