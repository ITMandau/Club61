<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\BookingSystem;
use App\Filament\Pages\MasterData;
use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelBookingEquipment;
use App\Models\Padel\PadelCourt;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MasterDataPricingAndAddonsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected PadelCourt $court;
    protected CourtEquipment $activeEquipment;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->adminUser = User::factory()->create([
            'email' => 'admin_master@club61.test',
        ]);
        $this->adminUser->assignRole($role);

        $this->court = PadelCourt::create([
            'name' => 'Court 1 - Test Panoramic',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 250000.00,
            'hourly_rate_prime' => 400000.00,
            'is_active' => true,
        ]);

        $this->activeEquipment = CourtEquipment::create([
            'name' => 'Raket Babolat Pro',
            'type' => 'RACKET',
            'rental_price' => 50000.00,
            'stock_quantity' => 15,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_access_master_data_page(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(MasterData::class)
            ->assertSuccessful()
            ->assertSee('Master Data, Tarif Lapangan & Add-ons');
    }

    public function test_admin_can_update_court_rates(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(MasterData::class)
            ->call('openEditCourtModal', $this->court->id)
            ->set('courtName', 'Court 1 - Updated VIP')
            ->set('courtDescription', 'Indoor • High Ceiling AC')
            ->set('hourlyRateRegular', 320000)
            ->set('hourlyRatePrime', 480000)
            ->call('saveCourt')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('padel_courts', [
            'id' => $this->court->id,
            'name' => 'Court 1 - Updated VIP',
            'description' => 'Indoor • High Ceiling AC',
            'hourly_rate_regular' => 320000.00,
            'hourly_rate_prime' => 480000.00,
        ]);
    }

    public function test_admin_can_create_new_court(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(MasterData::class)
            ->call('openCreateCourtModal')
            ->set('courtName', 'Court 4 - Center Stadium')
            ->set('courtType', 'OUTDOOR')
            ->set('courtDescription', 'Outdoor • Championship Stadium')
            ->set('hourlyRateRegular', 280000)
            ->set('hourlyRatePrime', 420000)
            ->set('courtIsActive', true)
            ->call('saveCourt')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('padel_courts', [
            'name' => 'Court 4 - Center Stadium',
            'type' => 'OUTDOOR',
            'description' => 'Outdoor • Championship Stadium',
            'hourly_rate_regular' => 280000.00,
            'hourly_rate_prime' => 420000.00,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_toggle_court_status(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(MasterData::class)
            ->call('toggleCourtStatus', $this->court->id);

        $this->assertDatabaseHas('padel_courts', [
            'id' => $this->court->id,
            'is_active' => false,
        ]);

        Livewire::test(MasterData::class)
            ->call('toggleCourtStatus', $this->court->id);

        $this->assertDatabaseHas('padel_courts', [
            'id' => $this->court->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_and_update_equipment_addon(): void
    {
        $this->actingAs($this->adminUser);

        // Create Add-on
        Livewire::test(MasterData::class)
            ->call('openCreateEquipmentModal')
            ->set('equipmentName', 'Bola Padel Pro Can 3 Pcs')
            ->set('equipmentType', 'BALL')
            ->set('equipmentRentalPrice', 35000)
            ->set('equipmentStock', 50)
            ->set('equipmentIsActive', true)
            ->call('saveEquipment')
            ->assertHasNoErrors();

        $ball = CourtEquipment::where('name', 'Bola Padel Pro Can 3 Pcs')->first();
        $this->assertNotNull($ball);
        $this->assertEquals(35000.00, (float) $ball->rental_price);

        // Update Add-on
        Livewire::test(MasterData::class)
            ->call('openEditEquipmentModal', $ball->id)
            ->set('equipmentRentalPrice', 40000)
            ->set('equipmentStock', 45)
            ->call('saveEquipment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('court_equipments', [
            'id' => $ball->id,
            'rental_price' => 40000.00,
            'stock_quantity' => 45,
        ]);
    }

    public function test_equipment_without_rental_history_can_be_hard_deleted(): void
    {
        $this->actingAs($this->adminUser);

        $freshEquipment = CourtEquipment::create([
            'name' => 'Handuk Olahraga Baru',
            'type' => 'TOWEL',
            'rental_price' => 15000.00,
            'stock_quantity' => 30,
            'is_active' => true,
        ]);

        Livewire::test(MasterData::class)
            ->call('deleteEquipment', $freshEquipment->id);

        $this->assertDatabaseMissing('court_equipments', [
            'id' => $freshEquipment->id,
        ]);
    }

    public function test_equipment_with_rental_history_is_protected_and_only_deactivated(): void
    {
        $this->actingAs($this->adminUser);

        // Buat booking dengan riwayat rental equipment
        $booking = PadelBooking::create([
            'booking_code' => 'BK-HIST-001',
            'user_id' => $this->adminUser->id,
            'court_id' => $this->court->id,
            'booking_date' => now()->toDateString(),
            'start_time' => now()->startOfHour(),
            'end_time' => now()->startOfHour()->addHour(),
            'court_fee' => 250000.00,
            'equipment_fee' => 50000.00,
            'total_amount' => 300000.00,
            'status' => 'COMPLETED',
        ]);

        $historyItem = PadelBookingEquipment::create([
            'booking_id' => $booking->id,
            'equipment_id' => $this->activeEquipment->id,
            'quantity' => 1,
            'unit_price' => 50000.00,
            'subtotal' => 50000.00,
        ]);

        // Coba hapus item yang sudah punya riwayat rental
        Livewire::test(MasterData::class)
            ->call('deleteEquipment', $this->activeEquipment->id);

        // Item HARUS TETAP ADA di database (bukan hard deleted) tetapi statusnya menjadi is_active = false
        $this->assertDatabaseHas('court_equipments', [
            'id' => $this->activeEquipment->id,
            'is_active' => false,
        ]);

        // Riwayat rental di booking equipments harus tetap utuh 100%
        $this->assertDatabaseHas('padel_booking_equipments', [
            'id' => $historyItem->id,
            'equipment_id' => $this->activeEquipment->id,
        ]);
    }

    public function test_inactive_equipment_is_rejected_in_checkout_server_side(): void
    {
        $inactiveEquipment = CourtEquipment::create([
            'name' => 'Raket Rusak / Nonaktif',
            'type' => 'RACKET',
            'rental_price' => 75000.00,
            'stock_quantity' => 0,
            'is_active' => false,
        ]);

        $service = app(PadelBookingService::class);

        // Buat booking hold
        $holdResult = $service->holdBatchSlots([
            [
                'court_id' => $this->court->id,
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
            ],
        ], now()->addDay()->toDateString(), $this->adminUser);

        $bookingIds = collect($holdResult['bookings'])->pluck('id')->toArray();

        // Coba checkout dengan mengirimkan equipment_id yang is_active = false
        $checkoutResult = $service->checkout(
            $bookingIds,
            [
                ['equipment_id' => $inactiveEquipment->id, 'quantity' => 2],
            ],
            null,
            'bank_transfer',
            'IDEMPOTENCY-' . uniqid(),
            $this->adminUser
        );

        // Equipment nonaktif harus diabaikan, biaya peralatan 0
        $this->assertEquals(0.00, (float) $checkoutResult['data']['equipment_fee']);

        $primaryBooking = PadelBooking::find($bookingIds[0]);
        $this->assertEquals(0.00, (float) $primaryBooking->equipment_fee);
    }

    public function test_booking_system_reflects_prime_time_rates_dynamically(): void
    {
        $this->actingAs($this->adminUser);

        // Uji pada hari kerja (Senin, 21 September 2026)
        $mondayDate = '2026-09-21';

        $testComponent = Livewire::test(BookingSystem::class)
            ->set('selectedDate', $mondayDate);

        // Jam 10 hari kerja (Reguler): harus menggunakan hourly_rate_regular = 250000
        $testComponent->call('inspectEmptySlot', $this->court->id, '10');
        $inspectData = $testComponent->get('inspectData');

        $this->assertNotNull($inspectData);
        $this->assertEquals(250000.00, $inspectData['rate']);
        $this->assertFalse($inspectData['is_prime_time']);

        // Jam 18 hari kerja (Prime Time): harus menggunakan hourly_rate_prime = 400000
        $testComponent->call('inspectEmptySlot', $this->court->id, '18');
        $inspectPrime = $testComponent->get('inspectData');

        $this->assertNotNull($inspectPrime);
        $this->assertEquals(400000.00, $inspectPrime['rate']);
        $this->assertTrue($inspectPrime['is_prime_time']);
    }

    public function test_schedule_matrix_includes_court_description(): void
    {
        $this->court->update(['description' => 'Indoor • VIP Central AC']);

        $service = app(PadelBookingService::class);
        $matrix = $service->getScheduleMatrix('2026-09-21');

        $courtData = collect($matrix['courts'])->firstWhere('court_id', $this->court->id);
        $this->assertNotNull($courtData);
        $this->assertEquals('Indoor • VIP Central AC', $courtData['description']);
    }
}
