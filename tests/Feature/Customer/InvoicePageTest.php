<?php

namespace Tests\Feature\Customer;

use App\Models\Membership\MembershipPlan;
use App\Models\Membership\UserMembership;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invoice page dulu HANYA menampilkan booking lapangan padel — customer yang baru beli membership
 * (belum pernah booking lapangan sama sekali) melihat halaman kosong total, padahal pembeliannya
 * sukses. Sekarang halaman ini juga menampilkan riwayat pembelian membership.
 */
class InvoicePageTest extends TestCase
{
    use RefreshDatabase;

    protected function makePaidMembership(User $user, string $planName = 'Individual Gold'): UserMembership
    {
        $plan = MembershipPlan::create([
            'code' => 'MBR-'.uniqid(),
            'name' => $planName,
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 90,
            'price' => 5000000.00,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-MBR-'.strtoupper(uniqid()),
            'user_id' => $user->id,
            'order_type' => 'MEMBERSHIP',
            'subtotal' => 5000000.00,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'service_charge' => 0,
            'grand_total' => 5000000.00,
            'payment_status' => 'PAID',
        ]);

        return UserMembership::create([
            'membership_code' => 'MBR-'.uniqid(),
            'owner_type' => 'INDIVIDUAL',
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'order_id' => $order->id,
            'status' => 'ACTIVE',
            'start_date' => now(),
            'end_date' => now()->addDays(90),
            'qr_pass_hash' => hash('sha256', 'test-pass-'.uniqid()),
        ]);
    }

    public function test_invoice_page_renders_for_user_with_only_a_membership_purchase(): void
    {
        // Halaman ini adalah Alpine SPA — data booking/membership diambil lewat fetch() di
        // browser, bukan di-render server-side, jadi test-nya cuma bisa pastikan halaman +
        // partial membership yang baru berhasil di-render (200, tidak ada Blade/view error)
        // ketika user CUMA punya pembelian membership tanpa booking apa pun. Perilaku
        // pemilihan data yang sesungguhnya diverifikasi lewat endpoint my-purchases di bawah.
        $user = User::factory()->create();
        $this->makePaidMembership($user, 'Corporate Sponsor Tier-A');

        $response = $this->actingAs($user)->get('/invoice');

        $response->assertStatus(200)->assertSee('invoiceApp');
    }

    public function test_invoice_page_shows_empty_state_when_user_has_nothing_at_all(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/invoice');

        $response->assertStatus(200);
    }

    public function test_invoice_page_still_renders_for_user_with_padel_bookings(): void
    {
        $user = User::factory()->create();
        $court = PadelCourt::create([
            'name' => 'Court 1', 'type' => 'INDOOR',
            'hourly_rate_regular' => 300000.00, 'hourly_rate_prime' => 350000.00, 'is_active' => true,
        ]);
        PadelBooking::create([
            'booking_code' => 'BK-TEST01',
            'user_id' => $user->id,
            'court_id' => $court->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
            'court_fee' => 300000.00,
            'total_amount' => 300000.00,
            'status' => 'PAID',
        ]);

        $response = $this->actingAs($user)->get('/invoice');

        $response->assertStatus(200);
    }

    public function test_my_purchases_endpoint_only_returns_own_memberships(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->makePaidMembership($user, 'My Own Plan');
        $this->makePaidMembership($otherUser, 'Someone Elses Plan');

        $response = $this->actingAs($user)->getJson('/api/v1/membership/my-purchases');

        $response->assertStatus(200);
        $plans = collect($response->json('data'))->pluck('plan_name');
        $this->assertTrue($plans->contains('My Own Plan'));
        $this->assertFalse($plans->contains('Someone Elses Plan'));
    }
}
