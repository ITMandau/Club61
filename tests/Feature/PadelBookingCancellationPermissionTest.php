<?php

namespace Tests\Feature;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Role;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PadelBookingCancellationPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $customerWithPermission;
    protected User $customerWithoutPermission;
    protected PadelCourt $court;
    protected string $bookingDate;

    protected function setUp(): void
    {
        parent::setUp();

        Club61PermissionMatrix::syncAllPermissions('web');

        $this->court = PadelCourt::create([
            'name' => 'Court Central Test',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 200000.00,
            'hourly_rate_prime' => 300000.00,
            'is_active' => true,
        ]);

        $this->bookingDate = now()->addDays(2)->format('Y-m-d');

        $customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $customerRole->givePermissionTo('cancel_padel_booking');

        $this->customerWithPermission = User::create([
            'name' => 'Permitted Customer',
            'email' => 'permitted@club61.test',
            'phone' => '081234567891',
            'password' => bcrypt('password123'),
            'role' => 'customer',
            'is_active' => true,
        ]);
        $this->customerWithPermission->syncRoles([$customerRole]);

        $this->customerWithoutPermission = User::create([
            'name' => 'Blocked Customer',
            'email' => 'blocked@club61.test',
            'phone' => '081234567892',
            'password' => bcrypt('password123'),
            'role' => 'customer',
            'is_active' => true,
        ]);
        $this->customerWithoutPermission->syncRoles([$customerRole]);
    }

    public function test_user_with_permission_can_cancel_pending_booking(): void
    {
        $booking = PadelBooking::create([
            'booking_code' => 'BK-PERM-OK',
            'user_id' => $this->customerWithPermission->id,
            'court_id' => $this->court->id,
            'booking_date' => $this->bookingDate,
            'start_time' => Carbon::parse("{$this->bookingDate} 10:00:00"),
            'end_time' => Carbon::parse("{$this->bookingDate} 11:00:00"),
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'PENDING_PAYMENT',
            'qr_code_hash' => hash('sha256', 'BK-PERM-OK'),
        ]);

        $response = $this->actingAs($this->customerWithPermission, 'sanctum')
            ->postJson('/api/v1/padel/release-slot', [
                'booking_ids' => [$booking->id],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('CANCELLED', $booking->fresh()->status);
    }

    public function test_user_without_permission_is_forbidden_from_cancelling_pending_booking(): void
    {
        // Simulasi admin mencabut izin pembatalan di menu Role & Hak Akses
        $customerRole = Role::findByName('customer', 'web');
        $customerRole->revokePermissionTo('cancel_padel_booking');
        app('cache')->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)->forget(config('permission.cache.key'));

        $booking = PadelBooking::create([
            'booking_code' => 'BK-PERM-DENY',
            'user_id' => $this->customerWithoutPermission->id,
            'court_id' => $this->court->id,
            'booking_date' => $this->bookingDate,
            'start_time' => Carbon::parse("{$this->bookingDate} 14:00:00"),
            'end_time' => Carbon::parse("{$this->bookingDate} 15:00:00"),
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'PENDING_PAYMENT',
            'qr_code_hash' => hash('sha256', 'BK-PERM-DENY'),
        ]);

        $response = $this->actingAs($this->customerWithoutPermission, 'sanctum')
            ->postJson('/api/v1/padel/release-slot', [
                'booking_ids' => [$booking->id],
            ]);

        $response->assertStatus(403);
        $this->assertEquals('PENDING_PAYMENT', $booking->fresh()->status);
    }

    public function test_user_without_permission_can_still_release_temporary_cart_lock(): void
    {
        // Simulasi admin mencabut izin pembatalan tiket
        $customerRole = Role::findByName('customer', 'web');
        $customerRole->revokePermissionTo('cancel_padel_booking');
        app('cache')->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)->forget(config('permission.cache.key'));

        $booking = PadelBooking::create([
            'booking_code' => 'BK-CART-LOCK',
            'user_id' => $this->customerWithoutPermission->id,
            'court_id' => $this->court->id,
            'booking_date' => $this->bookingDate,
            'start_time' => Carbon::parse("{$this->bookingDate} 16:00:00"),
            'end_time' => Carbon::parse("{$this->bookingDate} 17:00:00"),
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'LOCKED',
            'qr_code_hash' => hash('sha256', 'BK-CART-LOCK'),
        ]);

        $response = $this->actingAs($this->customerWithoutPermission, 'sanctum')
            ->postJson('/api/v1/padel/release-slot', [
                'booking_ids' => [$booking->id],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('CANCELLED', $booking->fresh()->status);
    }

    public function test_checkout_and_cart_respect_cancellation_permission(): void
    {
        // 1. Customer with permission
        $resAllowed = $this->actingAs($this->customerWithPermission)->get('/checkout');
        $resAllowed->assertStatus(200)
            ->assertSee('canCancelBooking: true', false);

        // 2. Revoke permission
        $customerRole = Role::findByName('customer', 'web');
        $customerRole->revokePermissionTo('cancel_padel_booking');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $resDenied = $this->actingAs($this->customerWithoutPermission)->get('/checkout');
        $resDenied->assertStatus(200)
            ->assertSee('canCancelBooking: false', false);

        $resCartDenied = $this->actingAs($this->customerWithoutPermission)->get('/cart');
        $resCartDenied->assertStatus(200)
            ->assertSee('canCancelBooking: false', false);
    }
}
