<?php

namespace Tests\Feature\Security;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit 16 Okt 2026 (#4): isStaff() meloloskan role non-frontline mana pun (trainer, stylist, role baru bikinan admin)
 * walau role itu tidak punya izin apa pun. Check-in & bayar ulang tidak boleh bergantung pada isStaff().
 */
class StaffPermissionGuardTest extends TestCase
{
    use RefreshDatabase;

    private PadelCourt $court;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->court = PadelCourt::create(['name' => 'Court A', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true]);
        $this->customer = User::factory()->create(['role' => 'CUSTOMER', 'is_active' => true]);
    }

    private function booking(string $status, string $code): PadelBooking
    {
        return PadelBooking::create([
            'booking_code' => $code,
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => now()->toDateString(),
            'start_time' => now()->addMinutes(15),
            'end_time' => now()->addMinutes(75),
            'court_fee' => 200000,
            'total_amount' => 200000,
            'status' => $status,
            'expires_at' => now()->addMinutes(10),
            'qr_code_hash' => hash_hmac('sha256', $code.$this->customer->id, config('app.key')),
        ]);
    }

    private function staff(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    public function test_role_without_check_in_permission_cannot_check_in_even_if_it_counts_as_staff(): void
    {
        $trainer = $this->staff('TRAINER');
        $this->assertTrue($trainer->isStaff(), 'prasyarat: role non-frontline dianggap staf oleh isStaff()');
        $booking = $this->booking('PAID', 'BK-PAD-GUARD1');

        $this->withToken($trainer->createToken('t')->plainTextToken)->postJson('/api/v1/padel/check-in', ['qr_code_hash' => $booking->qr_code_hash])->assertForbidden();

        $this->assertSame('PAID', $booking->fresh()->status);
        $this->assertNull($booking->fresh()->checked_in_at);
    }

    public function test_web_check_in_follows_the_permission_not_the_role(): void
    {
        $booking = $this->booking('PAID', 'BK-PAD-GUARD5');

        $this->actingAs($this->staff('TRAINER'))->postJson('/pos/check-in', ['code' => $booking->booking_code])->assertForbidden();
        $this->assertSame('PAID', $booking->fresh()->status);

        $this->actingAs($this->staff('RECEPTIONIST'))->postJson('/pos/check-in', ['code' => $booking->booking_code])->assertOk();
        $this->assertSame('CHECKED_IN', $booking->fresh()->status);
    }

    public function test_receptionist_with_check_in_permission_can_check_in(): void
    {
        $receptionist = $this->staff('RECEPTIONIST');
        $this->assertFalse($receptionist->isStaff(), 'prasyarat: resepsionis bukan isStaff() tapi punya izin check-in');
        $booking = $this->booking('PAID', 'BK-PAD-GUARD2');

        $this->withToken($receptionist->createToken('t')->plainTextToken)->postJson('/api/v1/padel/check-in', ['qr_code_hash' => $booking->qr_code_hash])
            ->assertOk()->assertJsonPath('data.booking_code', 'BK-PAD-GUARD2');
        $this->assertSame('CHECKED_IN', $booking->fresh()->status);
    }

    public function test_customer_cannot_check_in(): void
    {
        $other = User::factory()->create(['role' => 'CUSTOMER', 'is_active' => true]);
        $booking = $this->booking('PAID', 'BK-PAD-GUARD3');

        $this->withToken($other->createToken('t')->plainTextToken)->postJson('/api/v1/padel/check-in', ['qr_code_hash' => $booking->qr_code_hash])->assertForbidden();
        $this->assertSame('PAID', $booking->fresh()->status);
    }

    public function test_staff_cannot_pay_again_for_someone_elses_booking(): void
    {
        $booking = $this->booking('PENDING_PAYMENT', 'BK-PAD-GUARD4');

        foreach (['TRAINER', 'CASHIER', 'ADMIN'] as $role) {
            $this->app['auth']->forgetGuards(); // guard Sanctum menyimpan user request sebelumnya di dalam satu test
            $this->withToken($this->staff($role)->createToken('t')->plainTextToken)
                ->postJson("/api/v1/padel/bookings/{$booking->id}/retry-payment", ['payment_method' => 'QRIS'])
                ->assertForbidden();
        }

        $this->assertSame('PENDING_PAYMENT', $booking->fresh()->status);
    }
}
