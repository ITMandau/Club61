<?php

namespace Tests\Feature\I18n;

use App\Http\Middleware\SetLocale;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\UserMembership;
use App\Models\Membership\UserMembershipBalance;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use App\Support\SiteLocale;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Pesan server (respons API) mengikuti bahasa yang dipilih customer di situs (session / cookie 'site_locale'):
 * default Bahasa Indonesia, Inggris kalau customer memilih EN. Aplikasi / integrasi dengan bearer token tanpa pilihan
 * bahasa tetap menerima teks yang sama persis dengan sebelumnya (dan locale aplikasinya tidak diubah).
 */
class ApiMessagesLocaleTest extends TestCase
{
    use RefreshDatabase;

    private const ID = [
        'pos_only' => 'Paket ini hanya dijual di front desk Club 61. Silakan hubungi admin.',
        'voucher' => 'Kode voucher tidak ditemukan atau sudah tidak aktif.',
        'outside' => 'Jam ini di luar ketentuan paket member (berlaku: Sen–Jum). Kuota tidak bisa dipakai untuk jam ini.',
    ];

    private const EN = [
        'pos_only' => 'This plan is only sold at the Club 61 front desk. Please contact our staff.',
        'voucher' => 'Voucher code not found or no longer active.',
        'outside' => 'This time is outside your membership rules (valid on Mon–Fri). Your quota cannot be used for this time.',
    ];

    protected User $customer;

    protected MembershipPlan $posOnlyPlan;

    protected UserMembershipBalance $card;

    protected PadelCourt $court;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');

        $this->customer = User::factory()->customer()->create(['name' => 'Budi']);
        $this->posOnlyPlan = MembershipPlan::create([
            'code' => 'MBR-I18N-POS', 'name' => 'Kontrak', 'ownership_type' => 'ORGANIZATIONAL', 'duration_days' => 365,
            'price' => 1000000, 'is_active' => true, 'sales_channel' => MembershipPlan::SALES_POS_ONLY,
        ]);

        // Kartu member yang hanya berlaku Senin–Jumat; booking hari Sabtu memilih kartu itu → ditolak (OUTSIDE_TIME_WINDOW).
        $plan = MembershipPlan::create(['code' => 'MBR-I18N', 'name' => 'Paket Weekday', 'ownership_type' => 'INDIVIDUAL', 'duration_days' => 90, 'price' => 1000000, 'is_active' => true]);
        $membership = UserMembership::create([
            'membership_code' => 'MBR-I18N', 'owner_type' => 'INDIVIDUAL', 'user_id' => $this->customer->id, 'plan_id' => $plan->id,
            'status' => 'ACTIVE', 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDays(90)->toDateString(), 'qr_pass_hash' => 'QR-MBR-I18N',
        ]);
        $this->card = UserMembershipBalance::create([
            'user_membership_id' => $membership->id, 'facility' => 'PADEL', 'quota_type' => 'HOURS', 'initial_quota' => 100, 'remaining_quota' => 100,
            'access_days' => [1, 2, 3, 4, 5],
        ]);
        $this->court = PadelCourt::create(['name' => 'Court I18N', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 200000, 'is_active' => true]);
    }

    public function test_site_requests_without_a_language_choice_get_indonesian(): void
    {
        $this->assertMessages(self::ID, fn () => $this->fromSite()->actingAs($this->customer));
    }

    public function test_site_requests_follow_english_chosen_in_the_session(): void
    {
        $this->assertMessages(self::EN, fn () => $this->fromSite()->actingAs($this->customer)->withSession(['site_locale' => 'en']));
        $this->assertSame('en', app()->getLocale(), 'pesan validasi bawaan ikut bahasa pilihan');
    }

    public function test_site_requests_follow_english_chosen_by_cookie(): void
    {
        $this->assertMessages(self::EN, fn () => $this->fromSite()->actingAs($this->customer)->withCookie(SetLocale::COOKIE, 'en'));
    }

    public function test_indonesian_choice_keeps_indonesian(): void
    {
        $this->assertMessages(self::ID, fn () => $this->fromSite()->actingAs($this->customer)->withCookie(SetLocale::COOKIE, 'id'));
    }

    public function test_bearer_token_clients_without_a_choice_get_the_same_text_and_locale_as_before(): void
    {
        $localeBefore = app()->getLocale();
        $token = $this->customer->createToken('mobile')->plainTextToken;
        // Header Accept-Language sengaja diabaikan (browser / HP mengirimnya otomatis).
        $this->assertMessages(self::ID, fn () => $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept-Language' => 'en-US,en;q=0.9']));

        $this->assertSame($localeBefore, app()->getLocale(), 'locale API tanpa pilihan bahasa tidak diubah (pesan validasi bawaan tetap)');
        $this->withHeaders(['Authorization' => "Bearer {$token}"])->postJson('/api/v1/padel/vouchers/check', ['code' => 'X'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Validasi data gagal.')
            ->assertJsonPath('errors.amount.0', __('validation.required', ['attribute' => 'amount'], $localeBefore));
    }

    public function test_admin_and_cashier_livewire_requests_always_get_indonesian(): void
    {
        $request = Request::create('/livewire/update', 'POST');
        $request->headers->set('X-Livewire', 'true');
        SiteLocale::remember($request, 'en');
        $this->app->instance('request', $request);
        $this->assertSame('id', SiteLocale::forMessages());
        $this->assertSame(self::ID['voucher'], SiteLocale::text('voucher.not_found_or_inactive'));

        $request->headers->remove('X-Livewire');
        $this->assertSame(self::EN['voucher'], SiteLocale::text('voucher.not_found_or_inactive'));

        // Tanpa pilihan (console, antrean, halaman Filament) → Bahasa Indonesia apa pun APP_LOCALE-nya.
        $this->app->instance('request', Request::create('/'));
        $this->assertSame(self::ID['pos_only'], SiteLocale::text('membership.pos_only'));
    }

    /**
     * Request dari halaman situs: Referer domain stateful → Sanctum memakai session + cookie situs. withCredentials():
     * request JSON di test baru membawa cookie kalau diminta (fetch same-origin di browser selalu membawanya).
     */
    private function fromSite(): static
    {
        config(['sanctum.stateful' => ['localhost']]);

        return $this->withCredentials()->withHeader('Referer', 'http://localhost/checkout');
    }

    /** @param  callable(): static  $client */
    private function assertMessages(array $expected, callable $client): void
    {
        // Checkout membership: paket "Hanya POS / Admin".
        $client()->postJson('/api/v1/membership/checkout', ['plan_id' => $this->posOnlyPlan->id, 'payment_method' => 'QRIS'])
            ->assertStatus(422)->assertJsonPath('message', $expected['pos_only']);

        // Cek kode voucher yang tidak ada.
        $client()->postJson('/api/v1/padel/vouchers/check', ['code' => 'TIDAKADA', 'amount' => 200000])
            ->assertStatus(422)->assertJsonPath('message', $expected['voucher']);

        // Checkout booking hari Sabtu dengan kartu member yang hanya berlaku Senin–Jumat.
        $this->checkoutOutsideAccess($client)->assertStatus(422)->assertJsonPath('message', $expected['outside']);
        $this->assertEquals(100, (float) $this->card->fresh()->remaining_quota);
    }

    private function checkoutOutsideAccess(callable $client): TestResponse
    {
        $start = now()->next(Carbon::SATURDAY)->setTime(10, 0);
        $booking = PadelBooking::create([
            'booking_code' => 'BK-'.strtoupper(Str::random(6)), 'user_id' => $this->customer->id, 'court_id' => $this->court->id,
            'booking_date' => $start->toDateString(), 'start_time' => $start, 'end_time' => $start->copy()->addHour(),
            'court_fee' => 200000, 'coach_fee' => 0, 'equipment_fee' => 0, 'total_amount' => 200000,
            'status' => 'LOCKED', 'expires_at' => now()->addMinutes(10),
        ]);

        return $client()->withHeader('X-Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/padel/checkout', [
            'booking_ids' => [$booking->id], 'payment_method' => 'QRIS', 'membership_balance_id' => $this->card->id,
        ]);
    }
}
