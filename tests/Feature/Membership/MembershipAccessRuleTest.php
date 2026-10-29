<?php

namespace Tests\Feature\Membership;

use App\Filament\Resources\Membership\Pages\ListMembershipPlans;
use App\Models\Audit\ActivityLog;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipPlanBenefit;
use App\Models\Membership\UserMembership;
use App\Models\Membership\UserMembershipBalance;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Padel\PadelHoliday;
use App\Models\Padel\PadelPeakHourRule;
use App\Models\Pos\ClubFinanceSetting;
use App\Models\Sponsor\SponsorMemberVoucher;
use App\Models\Sponsor\SponsorOrganization;
use App\Models\Sponsor\SponsorOrganizationMember;
use App\Models\User;
use App\Services\Membership\MembershipBalanceService;
use App\Services\Membership\PadelAccessRule;
use App\Services\Padel\PadelBookingService;
use App\Services\Padel\PeakHourService;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Modul 28 Batch 2 (meeting 28 Okt 2026): aturan akses paket (hari berlaku, jam akses, hanya di luar peak) dicek di
 * semua jalur pemakaian jam paket — termasuk karyawan corporate yang dulu tidak dicek. Kartu memegang salinan aturan
 * saat dibeli; super admin bisa menerapkan aturan terbaru ke kartu aktif. Paket "Hanya POS / Admin" tidak dijual online.
 */
class MembershipAccessRuleTest extends TestCase
{
    use RefreshDatabase;

    protected PadelBookingService $service;

    protected User $customer;

    protected PadelCourt $court;

    protected Carbon $monday;

    protected Carbon $saturday;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        ClubFinanceSetting::getSettings()->update(['is_admin_fee_enabled' => false, 'is_tax_enabled' => false]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        // Peak: Senin–Jumat 17–24, Sabtu & Minggu (dan tanggal merah) seharian.
        PadelPeakHourRule::query()->delete();
        foreach (range(1, 5) as $day) {
            PadelPeakHourRule::create(['day_of_week' => $day, 'start_hour' => 17, 'end_hour' => 24]);
        }
        PadelPeakHourRule::create(['day_of_week' => 0, 'start_hour' => 0, 'end_hour' => 24]);
        PadelPeakHourRule::create(['day_of_week' => 6, 'start_hour' => 0, 'end_hour' => 24]);
        app(PeakHourService::class)->flush();

        $this->service = app(PadelBookingService::class);
        $this->customer = User::factory()->customer()->create(['name' => 'Budi']);
        $this->court = PadelCourt::create([
            'name' => 'Court AR', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 200000, 'is_active' => true,
        ]);
        $this->monday = now()->next(Carbon::MONDAY)->startOfDay();
        $this->saturday = $this->monday->copy()->next(Carbon::SATURDAY);
    }

    private function at(Carbon $day, int $hour): Carbon
    {
        return $day->copy()->setTime($hour, 0);
    }

    /** Kartu member pribadi dengan kuota jam PADEL dan aturan akses tertentu. */
    private function memberCard(array $rules, ?User $owner = null, string $ownerType = 'INDIVIDUAL'): UserMembershipBalance
    {
        $code = 'MBR-AR-'.strtoupper(Str::random(5));
        $plan = MembershipPlan::create(['code' => $code, 'name' => 'Paket '.$code, 'ownership_type' => $ownerType, 'duration_days' => 90, 'price' => 1000000, 'is_active' => true]);
        $membership = UserMembership::create([
            'membership_code' => $code, 'owner_type' => $ownerType, 'user_id' => ($owner ?? $this->customer)->id, 'plan_id' => $plan->id,
            'status' => 'ACTIVE', 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDays(90)->toDateString(), 'qr_pass_hash' => 'QR-'.$code,
        ]);

        return UserMembershipBalance::create(array_merge([
            'user_membership_id' => $membership->id, 'facility' => 'PADEL', 'quota_type' => 'HOURS', 'initial_quota' => 100, 'remaining_quota' => 100,
        ], $rules));
    }

    private function lockedBooking(Carbon $start, User $user): PadelBooking
    {
        return PadelBooking::create([
            'booking_code' => 'BK-'.strtoupper(Str::random(6)), 'user_id' => $user->id, 'court_id' => $this->court->id,
            'booking_date' => $start->toDateString(), 'start_time' => $start, 'end_time' => $start->copy()->addHour(),
            'court_fee' => 200000, 'coach_fee' => 0, 'equipment_fee' => 0, 'total_amount' => 200000,
            'status' => 'LOCKED', 'expires_at' => now()->addMinutes(10),
        ]);
    }

    private function checkout(PadelBooking $booking, User $user, ?string $balanceId = null): void
    {
        $this->service->checkout(
            bookingIds: [$booking->id], equipments: [], voucherCode: null, paymentMethod: 'QRIS',
            idempotencyKey: Str::uuid()->toString(), user: $user, membershipBalanceId: $balanceId,
        );
    }

    private function quote(Carbon $start, User $user): array
    {
        return $this->service->quoteWalkInBenefits(
            [['court_id' => $this->court->id, 'start_time' => $start->format('H:i'), 'end_time' => $start->copy()->addHour()->format('H:i')]],
            $start->toDateString(), $user,
        );
    }

    public function test_rule_checks_days_hours_and_peak_including_red_dates(): void
    {
        $rule = (new MembershipPlanBenefit)->forceFill(['access_days' => [1, 2, 3, 4, 5], 'time_window_start' => '06:00:00', 'time_window_end' => '16:00:00', 'off_peak_only' => true]);

        $this->assertSame('Sen–Jum · 06:00–16:00 · di luar jam peak', PadelAccessRule::label($rule));
        $this->assertSame('on Mon–Fri, 06:00–16:00, outside peak hours', PadelAccessRule::labelEn($rule));
        $this->assertNull(PadelAccessRule::violation($rule, $this->at($this->monday, 10), $this->at($this->monday, 11)));
        $this->assertStringContainsString('hari Sab', PadelAccessRule::violation($rule, $this->at($this->saturday, 10), $this->at($this->saturday, 11)));
        $this->assertStringContainsString('jam akses', PadelAccessRule::violation($rule, $this->at($this->monday, 15), $this->at($this->monday, 17)));

        $offPeak = (new MembershipPlanBenefit)->forceFill(['off_peak_only' => true]);
        $this->assertStringContainsString('jam 17:00 termasuk jam peak', PadelAccessRule::violation($offPeak, $this->at($this->monday, 16), $this->at($this->monday, 18)));

        // "Hanya di jam peak" (Weekday Prime Time): kebalikannya.
        $peakOnly = (new MembershipPlanBenefit)->forceFill(['peak_only' => true]);
        $this->assertNull(PadelAccessRule::violation($peakOnly, $this->at($this->monday, 18), $this->at($this->monday, 20)));
        $this->assertStringContainsString('jam 16:00 bukan jam peak', PadelAccessRule::violation($peakOnly, $this->at($this->monday, 16), $this->at($this->monday, 18)));
        $this->assertSame('di jam peak', PadelAccessRule::label($peakOnly));

        // Ringkasan jam berlaku (jam buka 06–24): di luar peak = Sen–Jum 06–17, weekend tidak berlaku.
        $this->assertSame([['Sen–Jum', '06:00–17:00'], ['Sab, Min', 'Tidak berlaku']], PadelAccessRule::summary(PadelAccessRule::usableHours($offPeak, 6, 24)));
        $this->assertSame([['Sen–Jum', '17:00–24:00'], ['Sab, Min', '06:00–24:00']], PadelAccessRule::summary(PadelAccessRule::usableHours($peakOnly, 6, 24)));

        // Tanggal merah di hari Senin = aturan peak hari Minggu (seharian peak).
        PadelHoliday::create(['date' => $this->monday->toDateString(), 'name' => 'Libur Nasional']);
        app(PeakHourService::class)->flush();
        $this->assertNotNull(PadelAccessRule::violation($offPeak, $this->at($this->monday, 10), $this->at($this->monday, 11)));

        // Ketujuh hari dicentang = tanpa batas hari.
        $allDays = (new MembershipPlanBenefit)->forceFill(['access_days' => range(1, 7)]);
        $this->assertFalse(PadelAccessRule::hasRules($allDays));
        $this->assertSame('Sen, Rab, Jum', PadelAccessRule::daysLabel([1, 3, 5]));
        $this->assertSame('Sab, Min', PadelAccessRule::daysLabel([6, 7]));

        // Kalimat untuk kasir: bahasa sehari-hari, tanpa istilah corporate / sponsor.
        $this->assertSame('Jam ini di luar ketentuan voucher. Voucher tidak bisa dipakai, bayar harga normal. Berlaku: di luar jam peak.', PadelAccessRule::outsideNotice('di luar jam peak', voucher: true));
        $this->assertSame('Jam ini di luar ketentuan paket member. Kuota tidak bisa dipakai, bayar harga normal.', PadelAccessRule::outsideNotice(null, voucher: false));
    }

    public function test_member_card_only_covers_bookings_inside_the_package_rules(): void
    {
        $card = $this->memberCard(['access_days' => [1, 2, 3, 4, 5], 'off_peak_only' => true]);

        $this->assertTrue($this->quote($this->at($this->monday, 10), $this->customer)['membership']['applies']);

        foreach ([$this->at($this->monday, 18), $this->at($this->saturday, 10)] as $start) {
            $quote = $this->quote($start, $this->customer);
            $this->assertFalse($quote['membership']['applies']);
            $this->assertSame('OUTSIDE_TIME_WINDOW', $quote['membership']['rejected_reason']);
            $this->assertSame('Sen–Jum · di luar jam peak', $quote['membership']['access_rule']);
            $this->assertEquals(0, $quote['membership_discount']);
        }

        // Online: kartu terdeteksi otomatis → harga normal; kartu dipilih eksplisit → ditolak dengan alasan jelas.
        $auto = $this->lockedBooking($this->at($this->saturday, 10), $this->customer);
        $preview = $this->service->previewMembershipBenefit([$auto->id], $this->customer);
        $this->assertSame('OUTSIDE_TIME_WINDOW', $preview['rejected_reason']);
        $this->assertSame('on Mon–Fri, outside peak hours', $preview['access_rule_en']);
        $this->checkout($auto, $this->customer);
        $this->assertEquals(200000, (float) $auto->fresh()->court_fee);
        $this->assertEquals(100, (float) $card->fresh()->remaining_quota);

        try {
            $this->checkout($this->lockedBooking($this->at($this->saturday, 12), $this->customer), $this->customer, $card->id);
            $this->fail('Kartu yang dipilih untuk hari di luar aturan harus ditolak.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame('Jam ini di luar ketentuan paket member (berlaku: Sen–Jum · di luar jam peak). Kuota tidak bisa dipakai untuk jam ini.', $e->getMessage());
        }

        $inside = $this->lockedBooking($this->at($this->monday, 10), $this->customer);
        $this->checkout($inside, $this->customer);
        $this->assertEquals(0, (float) $inside->fresh()->court_fee);
        $this->assertEquals(99, (float) $card->fresh()->remaining_quota);
    }

    public function test_corporate_employee_hours_follow_the_company_package_rules(): void
    {
        $pic = User::factory()->create();
        $companyCard = $this->memberCard(['off_peak_only' => true], $pic, 'ORGANIZATIONAL');
        $org = SponsorOrganization::create(['name' => 'PT Protel', 'user_membership_id' => $companyCard->user_membership_id, 'sponsor_admin_user_id' => $pic->id, 'status' => SponsorOrganization::STATUS_ACTIVE]);
        $employee = User::factory()->create(['phone' => '081200007777']);
        $member = SponsorOrganizationMember::create(['sponsor_organization_id' => $org->id, 'user_id' => $employee->id, 'status' => 'ACTIVE']);
        $voucher = SponsorMemberVoucher::create(['sponsor_organization_member_id' => $member->id, 'hours_granted' => 5, 'hours_used' => 0, 'issued_at' => now(), 'expires_at' => now()->addMonths(2)]);

        // Jam peak → jam corporate tidak dipakai (layar kasir & checkout sama).
        $peakQuote = $this->quote($this->at($this->monday, 18), $employee);
        $this->assertSame('OUTSIDE_TIME_WINDOW', $peakQuote['sponsor']['rejected_reason']);
        $this->assertSame('di luar jam peak', $peakQuote['sponsor']['access_rule']);
        $this->assertEquals(0, $peakQuote['sponsor_discount']);

        $peak = $this->lockedBooking($this->at($this->monday, 18), $employee);
        $preview = $this->service->previewMembershipBenefit([$peak->id], $employee);
        $this->assertFalse($preview['sponsor_voucher_benefit']['has_benefit']);
        $this->assertSame('OUTSIDE_TIME_WINDOW', $preview['sponsor_voucher_benefit']['rejected_reason']);
        $this->assertSame('outside peak hours', $preview['sponsor_voucher_benefit']['access_rule_en'], 'halaman customer berbahasa Inggris');
        $this->checkout($peak, $employee);
        $this->assertEquals(200000, (float) $peak->fresh()->court_fee);
        $this->assertEquals(0, (float) $voucher->fresh()->hours_used);

        // Di luar peak → tetap ditanggung jam corporate seperti biasa.
        $this->assertTrue($this->quote($this->at($this->monday, 10), $employee)['sponsor']['applies']);
        $offPeak = $this->lockedBooking($this->at($this->monday, 10), $employee);
        $this->checkout($offPeak, $employee);
        $this->assertEquals(0, (float) $offPeak->fresh()->court_fee);
        $this->assertEquals(1, (float) $voucher->fresh()->hours_used);
    }

    public function test_reschedule_outside_the_package_rules_drops_the_member_benefit(): void
    {
        $card = $this->memberCard(['access_days' => [1, 2, 3, 4, 5]]);
        $booking = PadelBooking::create([
            'booking_code' => 'BK-AR-RS', 'user_id' => $this->customer->id, 'court_id' => $this->court->id,
            'booking_date' => $this->monday->toDateString(), 'start_time' => $this->at($this->monday, 10), 'end_time' => $this->at($this->monday, 11),
            'court_fee' => 0, 'member_discount_court' => 200000, 'membership_balance_id' => $card->id, 'member_hours_consumed' => 1,
            'coach_fee' => 0, 'equipment_fee' => 0, 'total_amount' => 0, 'status' => 'PAID',
        ]);

        $tuesday = $this->service->quoteReschedule($booking, $this->court, $this->at($this->monday->copy()->addDay(), 10), $this->at($this->monday->copy()->addDay(), 11));
        $this->assertFalse($tuesday['member_benefit_dropped']);

        $saturday = $this->service->quoteReschedule($booking, $this->court, $this->at($this->saturday, 10), $this->at($this->saturday, 11));
        $this->assertTrue($saturday['member_benefit_dropped']);
        $this->assertSame('Jam baru di luar ketentuan paket member (berlaku: Sen–Jum), jadi dihitung harga normal. Jam kuota yang terpakai dikembalikan.', $saturday['benefit_dropped_reason']);
    }

    public function test_sold_cards_keep_their_rules_until_a_super_admin_applies_the_plan(): void
    {
        $plan = MembershipPlan::create(['code' => 'MBR-AR-PLAN', 'name' => 'Protel 100', 'ownership_type' => 'INDIVIDUAL', 'duration_days' => 90, 'price' => 1000000, 'is_active' => true]);
        $benefit = MembershipPlanBenefit::create(['plan_id' => $plan->id, 'facility' => 'PADEL', 'quota_type' => 'HOURS', 'quota_value' => 100]);
        $balances = app(MembershipBalanceService::class);

        $old = $balances->purchasePlan($this->customer, $plan, ['status' => 'ACTIVE'])->balances->firstWhere('facility', 'PADEL');
        $this->assertNull($old->access_days);

        // Paket diubah: kartu lama tetap, pembeli baru langsung dapat aturan terbaru.
        $benefit->update(['access_days' => [1, 2, 3, 4, 5], 'off_peak_only' => true]);
        $this->assertNull($old->fresh()->access_days);
        $new = $balances->purchasePlan(User::factory()->customer()->create(), $plan->fresh(), ['status' => 'ACTIVE'])->balances->firstWhere('facility', 'PADEL');
        $this->assertSame([1, 2, 3, 4, 5], $new->access_days);
        $this->assertTrue($new->off_peak_only);

        // Tombol hanya untuk izin apply_membership_plan_rules (bawaan super admin saja).
        $this->assertNotContains('apply_membership_plan_rules', Club61PermissionMatrix::defaultRolePermissions('admin'));
        Livewire::actingAs(User::factory()->admin()->create(['is_active' => true]))->test(ListMembershipPlans::class)
            ->assertTableActionHidden('applyRules', $plan);

        $remaining = (float) $old->fresh()->remaining_quota;
        Livewire::actingAs(User::factory()->superAdmin()->create(['is_active' => true]))->test(ListMembershipPlans::class)
            ->callTableAction('applyRules', $plan)
            ->assertNotified('Aturan paket diterapkan');

        $old->refresh();
        $this->assertSame([1, 2, 3, 4, 5], $old->access_days);
        $this->assertTrue($old->off_peak_only);
        $this->assertEquals($remaining, (float) $old->remaining_quota, 'kuota tidak disentuh');
        $this->assertTrue(ActivityLog::where('event', 'membership_plan.rules_applied')->exists());
    }

    private function editPlan(MembershipPlan $plan)
    {
        return Livewire::actingAs(User::factory()->admin()->create(['is_active' => true]))
            ->test(\App\Filament\Resources\Membership\Pages\EditMembershipPlan::class, ['record' => $plan->getRouteKey()]);
    }

    public function test_admin_picks_one_waktu_berlaku_option_and_sees_the_usable_hours(): void
    {
        $plan = MembershipPlan::create(['code' => 'MBR-AR-FORM', 'name' => 'Form Test', 'ownership_type' => 'ORGANIZATIONAL', 'duration_days' => 30, 'price' => 500000, 'is_active' => true]);
        $padel = MembershipPlanBenefit::create(['plan_id' => $plan->id, 'facility' => 'PADEL', 'quota_type' => 'HOURS', 'quota_value' => 100]);
        $gym = MembershipPlanBenefit::create(['plan_id' => $plan->id, 'facility' => 'GYM', 'quota_type' => 'VISITS', 'quota_value' => 10]);
        $field = fn (MembershipPlanBenefit $b, string $name) => "data.benefits.record-{$b->id}.{$name}";

        // Satu pilihan "Hanya di luar jam peak" → ringkasan jam langsung tampil (peak test: Sen–Jum 17–24, weekend seharian).
        $this->editPlan($plan)
            ->assertSet($field($padel, 'access_mode'), 'ANY')
            ->set('data.sales_channel', MembershipPlan::SALES_POS_ONLY)
            ->set($field($padel, 'access_mode'), 'OFF_PEAK')
            ->assertSee('Paket Dapat Digunakan')->assertSee('06:00–17:00')->assertSee('Tidak berlaku')
            ->set($field($gym, 'time_window_start'), '07:00') // gym tetap memakai jam akses biasa
            ->set($field($gym, 'time_window_end'), '21:00')
            ->call('save')
            ->assertHasNoFormErrors();

        $padel->refresh();
        $this->assertSame(MembershipPlan::SALES_POS_ONLY, $plan->fresh()->sales_channel);
        $this->assertTrue($padel->off_peak_only);
        $this->assertFalse($padel->peak_only);
        $this->assertNull($padel->access_days);
        $this->assertNull($padel->time_window_start);
        $this->assertSame('07:00', substr((string) $gym->fresh()->time_window_start, 0, 5));
        $this->assertFalse($gym->fresh()->off_peak_only);

        // "Hanya di jam peak" (mis. Weekday Prime Time).
        $this->editPlan($plan)->assertSet($field($padel, 'access_mode'), 'OFF_PEAK')
            ->set($field($padel, 'access_mode'), 'PEAK')->call('save')->assertHasNoFormErrors();
        $this->assertTrue($padel->fresh()->peak_only);
        $this->assertFalse($padel->fresh()->off_peak_only);

        // "Atur manual": hari + jam 24 jam; wajib minimal satu hari.
        $page = $this->editPlan($plan)->assertSet($field($padel, 'access_mode'), 'PEAK')
            ->set($field($padel, 'access_mode'), 'CUSTOM')
            ->call('save')
            ->assertHasFormErrors(["benefits.record-{$padel->id}.access_days" => 'required']);
        $page->set($field($padel, 'access_days'), ['5', '6', '7'])
            ->set($field($padel, 'time_window_start'), '06:00')
            ->set($field($padel, 'time_window_end'), '22:00')
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Jum–Min · 06:00–22:00', PadelAccessRule::label($padel->fresh()));
        $this->assertFalse($padel->fresh()->peak_only);
        $this->editPlan($plan)->assertSet($field($padel, 'access_mode'), 'CUSTOM');

        // "Kapan saja" mengosongkan semua batas.
        $this->editPlan($plan)->set($field($padel, 'access_mode'), 'ANY')->call('save')->assertHasNoFormErrors();
        $this->assertFalse(PadelAccessRule::hasRules($padel->fresh()));
    }

    public function test_a_rule_that_leaves_no_usable_hour_cannot_be_saved(): void
    {
        $plan = MembershipPlan::create(['code' => 'MBR-AR-ZERO', 'name' => 'Zero Test', 'ownership_type' => 'INDIVIDUAL', 'duration_days' => 30, 'price' => 500000, 'is_active' => true]);
        $padel = MembershipPlanBenefit::create(['plan_id' => $plan->id, 'facility' => 'PADEL', 'quota_type' => 'HOURS', 'quota_value' => 10]);

        // Master Data menandai semua jam sebagai peak → "Hanya di luar jam peak" tidak menyisakan satu jam pun.
        foreach (range(0, 6) as $day) {
            PadelPeakHourRule::updateOrCreate(['day_of_week' => $day], ['start_hour' => 0, 'end_hour' => 24]);
        }
        PadelPeakHourRule::whereIn('day_of_week', range(0, 6))->where('start_hour', '!=', 0)->delete();
        app(PeakHourService::class)->flush();

        $this->editPlan($plan)
            ->set("data.benefits.record-{$padel->id}.access_mode", 'OFF_PEAK')
            ->assertSee('Tidak ada jam yang dapat digunakan')
            ->call('save')
            ->assertHasFormErrors(["benefits.record-{$padel->id}.access_mode"]);
        $this->assertFalse($padel->fresh()->off_peak_only);

        // Kebalikannya tetap bisa: "Hanya di jam peak".
        $this->editPlan($plan)->set("data.benefits.record-{$padel->id}.access_mode", 'PEAK')->call('save')->assertHasNoFormErrors();
        $this->assertTrue($padel->fresh()->peak_only);
    }

    public function test_pos_only_plans_are_hidden_from_customers_and_cannot_be_bought_online(): void
    {
        $public = MembershipPlan::create(['code' => 'MBR-AR-PUB', 'name' => 'Silver Publik', 'ownership_type' => 'INDIVIDUAL', 'duration_days' => 30, 'price' => 500000, 'is_active' => true]);
        $contract = MembershipPlan::create(['code' => 'MBR-AR-POS', 'name' => 'Kontrak Protelindo', 'ownership_type' => 'ORGANIZATIONAL', 'duration_days' => 365, 'price' => 1000000, 'is_active' => true, 'sales_channel' => MembershipPlan::SALES_POS_ONLY]);
        $this->assertTrue($public->fresh()->isSoldOnline(), 'paket lama / bawaan tetap dijual online');

        $ids = collect($this->getJson('/api/v1/membership/plans')->assertOk()->json('data'))->pluck('id');
        $this->assertContains($public->id, $ids);
        $this->assertNotContains($contract->id, $ids);

        $this->actingAs($this->customer)->get('/membership')->assertOk()->assertSee('Silver Publik')->assertDontSee('Kontrak Protelindo');

        $this->actingAs($this->customer)->postJson('/api/v1/membership/checkout', ['plan_id' => $contract->id, 'payment_method' => 'QRIS'])
            ->assertStatus(422)->assertJsonPath('message', 'Paket ini hanya dijual di front desk Club 61. Silakan hubungi admin.');
        $this->assertSame(0, UserMembership::where('plan_id', $contract->id)->count());
    }
}
