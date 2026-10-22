<?php

namespace Tests\Feature\Api;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\User;
use App\Services\Padel\BookingTimeService;
use App\Services\Padel\PadelBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Audit go-live G17: batas tahan slot lewat aplikasi / website customer — maks 4 jam per permintaan, maks 4 jam belum
 * dibayar per akun, maks 30 hari ke depan. Kasir walk-in tidak dibatasi.
 */
class HoldLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected PadelCourt $court1;

    protected PadelCourt $court2;

    protected string $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->customer()->create();
        $this->court1 = PadelCourt::create(['name' => 'Court A', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true]);
        $this->court2 = PadelCourt::create(['name' => 'Court B', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true]);
        $this->date = now()->addDay()->toDateString();
    }

    private function hold(array $slots, ?string $date = null, ?User $user = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user ?? $this->customer, 'sanctum')->postJson('/api/v1/padel/hold-slot', [
            'booking_date' => $date ?? $this->date,
            'slots' => $slots,
        ]);
    }

    private function slot(PadelCourt $court, int $from, int $to): array
    {
        return ['court_id' => $court->id, 'start_time' => sprintf('%02d:00', $from), 'end_time' => sprintf('%02d:00', $to)];
    }

    public function test_one_request_holds_at_most_four_hours_however_the_slots_are_split(): void
    {
        // 5 jam: 5 slot 1 jam, atau 1 slot panjang 5 jam — sama-sama ditolak.
        $this->hold([$this->slot($this->court1, 8, 9), $this->slot($this->court1, 9, 10), $this->slot($this->court1, 10, 11), $this->slot($this->court2, 8, 9), $this->slot($this->court2, 9, 10)])
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->hold([$this->slot($this->court1, 6, 11)])->assertStatus(422);
        $this->assertSame(0, PadelBooking::count());

        $this->hold([$this->slot($this->court1, 8, 10), $this->slot($this->court2, 8, 10)])->assertStatus(201);
    }

    public function test_an_account_holds_at_most_four_unpaid_hours_and_expired_or_paid_ones_do_not_count(): void
    {
        $this->hold([$this->slot($this->court1, 8, 11)])->assertStatus(201);   // 3 jam belum dibayar

        $this->hold([$this->slot($this->court2, 8, 10)])->assertStatus(422);   // 3 + 2 > 4
        $this->hold([$this->slot($this->court2, 8, 9)])->assertStatus(201);    // 3 + 1 = 4

        // Sudah dibayar → tidak dihitung lagi.
        PadelBooking::where('user_id', $this->customer->id)->update(['status' => 'PAID']);
        $this->hold([$this->slot($this->court2, 12, 16)])->assertStatus(201);

        // Waktu tahan habis → dilepas, tidak menghalangi tahan berikutnya.
        $this->travel(app(BookingTimeService::class)->holdMinutes() + 1)->minutes();
        $this->hold([$this->slot($this->court1, 17, 21)])->assertStatus(201);
    }

    public function test_slots_can_be_held_at_most_thirty_days_ahead(): void
    {
        $this->hold([$this->slot($this->court1, 8, 9)], now()->addDays(PadelBookingService::MAX_DAYS_AHEAD + 1)->toDateString())
            ->assertStatus(422)->assertJsonValidationErrors('booking_date');
        $this->hold([$this->slot($this->court1, 8, 9)], now()->addDays(PadelBookingService::MAX_DAYS_AHEAD)->toDateString())
            ->assertStatus(201);
    }

    public function test_other_customers_are_not_limited_by_someone_elses_holds(): void
    {
        $this->hold([$this->slot($this->court1, 8, 12)])->assertStatus(201);
        $this->hold([$this->slot($this->court2, 8, 12)], user: User::factory()->customer()->create())->assertStatus(201);
    }

    public function test_cashier_walk_in_holds_are_not_limited(): void
    {
        $cashier = User::factory()->cashier()->create();

        $hold = app(PadelBookingService::class)->holdBatchSlots([$this->slot($this->court1, 8, 14)], $this->date, $cashier);

        $this->assertCount(1, $hold['bookings']);
        $this->assertSame(6, (int) $hold['bookings'][0]->start_time->diffInHours($hold['bookings'][0]->end_time));
    }
}
