<?php

namespace Tests\Feature\Api;

use App\Models\Padel\PadelCourt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jam yang sudah lewat di halaman booking customer: aturan sama dengan POS Walk-In
 * (jam sebelum jam berjalan = PAST, jam berjalan masih boleh), dan server ikut menolak.
 */
class PastSlotProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected PadelCourt $court;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-30 14:30:00', 'Asia/Jakarta'));

        $this->court = PadelCourt::create([
            'name' => 'Court Uji',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 200000,
            'hourly_rate_prime' => 300000,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function statusesFor(string $date): array
    {
        $slots = $this->getJson("/api/v1/padel/schedule?date={$date}")
            ->assertOk()
            ->json('data.courts.0.slots');

        return collect($slots)->mapWithKeys(fn ($s) => [$s['local_start'] => $s['status']])->all();
    }

    public function test_today_hours_before_current_hour_are_past_and_current_hour_stays_bookable(): void
    {
        $statuses = $this->statusesFor('2026-09-30');

        $this->assertSame('PAST', $statuses['06:00']);
        $this->assertSame('PAST', $statuses['13:00']);
        $this->assertSame('AVAILABLE', $statuses['14:00']);
        $this->assertSame('AVAILABLE', $statuses['15:00']);
    }

    public function test_future_date_has_no_past_hours_and_past_date_is_fully_past(): void
    {
        $this->assertNotContains('PAST', $this->statusesFor('2026-10-01'));
        $this->assertSame(['PAST'], array_values(array_unique($this->statusesFor('2026-09-29'))));
    }

    public function test_server_rejects_holding_a_past_hour_even_if_ui_is_bypassed(): void
    {
        $customer = User::factory()->customer()->create();
        $token = $customer->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/padel/hold-slot', [
                'booking_date' => '2026-09-30',
                'slots' => [['court_id' => $this->court->id, 'start_time' => '10:00', 'end_time' => '11:00']],
            ])
            ->assertStatus(422);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/padel/hold-slot', [
                'booking_date' => '2026-09-30',
                'slots' => [['court_id' => $this->court->id, 'start_time' => '14:00', 'end_time' => '15:00']],
            ])
            ->assertStatus(201);
    }

    public function test_customer_booking_page_renders_past_state(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->get('/booking')
            ->assertOk()
            ->assertSee("status === 'PAST'", false)
            // Jam yang sudah lewat tidak bisa dipilih: disembunyikan & diganti catatan.
            ->assertSee('today have passed');
    }
}
