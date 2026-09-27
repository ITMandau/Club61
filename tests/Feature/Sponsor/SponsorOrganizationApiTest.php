<?php

namespace Tests\Feature\Sponsor;

use App\Models\Membership\MembershipPlan;
use App\Models\Membership\UserMembership;
use App\Models\Membership\UserMembershipBalance;
use App\Models\Sponsor\SponsorMemberVoucher;
use App\Models\Sponsor\SponsorOrganization;
use App\Models\Sponsor\SponsorOrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SponsorOrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOrganization(User $pic): SponsorOrganization
    {
        $plan = MembershipPlan::create([
            'code' => 'MBR-CORP-'.uniqid(),
            'name' => 'Corporate B2B Padel',
            'ownership_type' => 'ORGANIZATIONAL',
            'duration_days' => 365,
            'price' => 50000000.00,
            'is_active' => true,
        ]);

        $membership = UserMembership::create([
            'membership_code' => 'MBR-CORP-'.uniqid(),
            'owner_type' => 'ORGANIZATIONAL',
            'user_id' => $pic->id,
            'plan_id' => $plan->id,
            'status' => 'ACTIVE',
            'start_date' => now(),
            'end_date' => now()->addYear(),
        ]);

        return SponsorOrganization::create([
            'name' => 'PT Uji Corporate',
            'user_membership_id' => $membership->id,
            'sponsor_admin_user_id' => $pic->id,
        ]);
    }

    /**
     * Kuota kontrak BUKAN field terpisah di SponsorOrganization — diambil dari benefit paket
     * membership (facility PADEL, quota_type HOURS) yang staf konfigurasi lewat Master Data,
     * disalin jadi UserMembershipBalance. Helper ini mensimulasikan itu di data test.
     */
    protected function givePadelHourQuota(SponsorOrganization $org, float $hours): UserMembershipBalance
    {
        return UserMembershipBalance::create([
            'user_membership_id' => $org->user_membership_id,
            'facility' => 'PADEL',
            'quota_type' => 'HOURS',
            'initial_quota' => $hours,
            'remaining_quota' => $hours,
        ]);
    }

    public function test_pic_can_view_own_organization_summary(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);

        $response = $this->actingAs($pic)->getJson('/api/v1/sponsor/organization');

        $response->assertStatus(200)
            ->assertJsonPath('data.organization.id', $org->id)
            ->assertJsonPath('data.active_member_count', 0);
    }

    public function test_non_pic_user_gets_404(): void
    {
        $randomUser = User::factory()->create();

        $this->actingAs($randomUser)->getJson('/api/v1/sponsor/organization')->assertStatus(404);
    }

    public function test_pic_can_add_member_manually_with_initial_voucher_and_default_password(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);

        $response = $this->actingAs($pic)->postJson('/api/v1/sponsor/organization/members', [
            'name' => 'Budi Karyawan',
            'phone' => '+62 812-3456-7890',
            'initial_hours' => 5,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', ['phone' => '081234567890', 'name' => 'Budi Karyawan']);
        $newUser = User::where('phone', '081234567890')->first();
        $this->assertTrue(Hash::check('567890', $newUser->password));

        $member = SponsorOrganizationMember::where('sponsor_organization_id', $org->id)
            ->where('user_id', $newUser->id)->first();
        $this->assertNotNull($member);
        $this->assertEquals(5.0, $member->totalRemainingHours());
    }

    public function test_adding_same_active_member_twice_is_rejected(): void
    {
        $pic = User::factory()->create();
        $this->makeOrganization($pic);

        $this->actingAs($pic)->postJson('/api/v1/sponsor/organization/members', [
            'name' => 'Siti', 'phone' => '081234567890',
        ])->assertStatus(201);

        $this->actingAs($pic)->postJson('/api/v1/sponsor/organization/members', [
            'name' => 'Siti Lagi', 'phone' => '081234567890',
        ])->assertStatus(422);
    }

    public function test_pic_can_release_additional_voucher_without_touching_existing_one(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);
        $employee = User::factory()->create(['phone' => '081200000001']);
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => $employee->id,
            'status' => 'ACTIVE',
        ]);
        $this->actingAs($pic)->postJson("/api/v1/sponsor/organization/members/{$member->id}/release-voucher", ['hours' => 3])
            ->assertStatus(201);

        $this->actingAs($pic)->postJson("/api/v1/sponsor/organization/members/{$member->id}/release-voucher", ['hours' => 4])
            ->assertStatus(201);

        $this->assertEquals(2, $member->vouchers()->count());
        $this->assertEquals(7.0, $member->totalRemainingHours());
    }

    public function test_releasing_voucher_beyond_contract_quota_is_rejected(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);
        $this->givePadelHourQuota($org, 10);
        $employee = User::factory()->create(['phone' => '081200000030']);
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id, 'user_id' => $employee->id, 'status' => 'ACTIVE',
        ]);

        $this->actingAs($pic)->postJson("/api/v1/sponsor/organization/members/{$member->id}/release-voucher", ['hours' => 7])
            ->assertStatus(201);

        // Sisa kuota tinggal 3 jam — minta 5 jam lagi harus ditolak, bukan malah menembus kuota.
        $response = $this->actingAs($pic)->postJson("/api/v1/sponsor/organization/members/{$member->id}/release-voucher", ['hours' => 5]);

        $response->assertStatus(422)->assertJsonFragment(['success' => false]);
        $this->assertEquals(7.0, $org->totalHoursReleased());
    }

    public function test_organization_without_quota_set_is_unlimited(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic); // tidak ada UserMembershipBalance PADEL — unlimited
        $employee = User::factory()->create(['phone' => '081200000031']);
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id, 'user_id' => $employee->id, 'status' => 'ACTIVE',
        ]);

        $this->actingAs($pic)->postJson("/api/v1/sponsor/organization/members/{$member->id}/release-voucher", ['hours' => 500])
            ->assertStatus(201);

        $this->assertNull($org->remainingQuota());
    }

    public function test_bulk_release_reports_failure_for_members_once_quota_is_exhausted(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);
        $this->givePadelHourQuota($org, 8);
        $active1 = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => User::factory()->create(['phone' => '081200000032'])->id,
            'status' => 'ACTIVE',
        ]);
        $active2 = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => User::factory()->create(['phone' => '081200000033'])->id,
            'status' => 'ACTIVE',
        ]);

        // Uniform 5 jam x 2 anggota = 10 jam diminta, tapi kuota cuma 8 — anggota pertama lolos
        // (habis 5, sisa 3), anggota kedua ditolak (minta 5, sisa cuma 3).
        $response = $this->actingAs($pic)->postJson('/api/v1/sponsor/organization/members/bulk-release-voucher', [
            'mode' => 'uniform',
            'hours' => 5,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.released', 1);
        $this->assertCount(1, $response->json('data.failed'));
        $this->assertEquals(5.0, $org->totalHoursReleased());
    }

    public function test_correcting_voucher_hours_upward_beyond_quota_is_rejected(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);
        $this->givePadelHourQuota($org, 10);
        $employee = User::factory()->create(['phone' => '081200000034']);
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id, 'user_id' => $employee->id, 'status' => 'ACTIVE',
        ]);
        $voucher = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 5, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        // Menaikkan dari 5 ke 20 jam butuh tambahan 15 jam, padahal sisa kuota cuma 5 — ditolak.
        $this->actingAs($pic)->patchJson("/api/v1/sponsor/organization/vouchers/{$voucher->id}", ['hours_granted' => 20])
            ->assertStatus(422);

        $this->assertEquals(5.0, (float) $voucher->fresh()->hours_granted);
    }

    public function test_pic_can_bulk_release_uniform_hours_to_all_active_members(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);
        $active1 = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => User::factory()->create(['phone' => '081200000020'])->id,
            'status' => 'ACTIVE',
        ]);
        $active2 = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => User::factory()->create(['phone' => '081200000021'])->id,
            'status' => 'ACTIVE',
        ]);
        $revoked = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => User::factory()->create(['phone' => '081200000022'])->id,
            'status' => 'REVOKED',
        ]);

        $response = $this->actingAs($pic)->postJson('/api/v1/sponsor/organization/members/bulk-release-voucher', [
            'mode' => 'uniform',
            'hours' => 5,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.released', 2);
        $this->assertEquals(5.0, $active1->totalRemainingHours());
        $this->assertEquals(5.0, $active2->totalRemainingHours());
        $this->assertEquals(0.0, $revoked->totalRemainingHours());
    }

    public function test_pic_can_bulk_release_different_hours_per_member(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);
        $memberA = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => User::factory()->create(['phone' => '081200000023'])->id,
            'status' => 'ACTIVE',
        ]);
        $memberB = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => User::factory()->create(['phone' => '081200000024'])->id,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($pic)->postJson('/api/v1/sponsor/organization/members/bulk-release-voucher', [
            'mode' => 'per_member',
            'allocations' => [
                ['member_id' => $memberA->id, 'hours' => 10],
                ['member_id' => $memberB->id, 'hours' => 3],
            ],
        ]);

        $response->assertStatus(200)->assertJsonPath('data.released', 2);
        $this->assertEquals(10.0, $memberA->totalRemainingHours());
        $this->assertEquals(3.0, $memberB->totalRemainingHours());
    }

    public function test_pic_cannot_bulk_release_to_another_organizations_members(): void
    {
        $picA = User::factory()->create();
        $this->makeOrganization($picA);

        $picB = User::factory()->create();
        $orgB = $this->makeOrganization($picB);
        $memberOfB = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $orgB->id,
            'user_id' => User::factory()->create(['phone' => '081200000025'])->id,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($picA)->postJson('/api/v1/sponsor/organization/members/bulk-release-voucher', [
            'mode' => 'per_member',
            'allocations' => [
                ['member_id' => $memberOfB->id, 'hours' => 10],
            ],
        ]);

        $response->assertStatus(200)->assertJsonPath('data.released', 0);
        $this->assertCount(1, $response->json('data.failed'));
        $this->assertEquals(0.0, $memberOfB->totalRemainingHours());
    }

    public function test_pic_can_correct_voucher_hours(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);
        $employee = User::factory()->create(['phone' => '081200000010']);
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id, 'user_id' => $employee->id, 'status' => 'ACTIVE',
        ]);
        $voucher = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 5, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($pic)->patchJson("/api/v1/sponsor/organization/vouchers/{$voucher->id}", ['hours_granted' => 10])
            ->assertStatus(200);

        $this->assertEquals(10.0, (float) $voucher->fresh()->hours_granted);
    }

    public function test_cannot_correct_voucher_hours_below_amount_already_used(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);
        $employee = User::factory()->create(['phone' => '081200000011']);
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id, 'user_id' => $employee->id, 'status' => 'ACTIVE',
        ]);
        $voucher = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 10, 'hours_used' => 6,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($pic)->patchJson("/api/v1/sponsor/organization/vouchers/{$voucher->id}", ['hours_granted' => 5])
            ->assertStatus(422);

        $this->assertEquals(10.0, (float) $voucher->fresh()->hours_granted);
    }

    public function test_pic_can_delete_unused_voucher_but_not_a_used_one(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);
        $employee = User::factory()->create(['phone' => '081200000012']);
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id, 'user_id' => $employee->id, 'status' => 'ACTIVE',
        ]);
        $unusedVoucher = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 5, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);
        $usedVoucher = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 5, 'hours_used' => 2,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($pic)->deleteJson("/api/v1/sponsor/organization/vouchers/{$unusedVoucher->id}")
            ->assertStatus(200);
        $this->assertDatabaseMissing('sponsor_member_vouchers', ['id' => $unusedVoucher->id]);

        $this->actingAs($pic)->deleteJson("/api/v1/sponsor/organization/vouchers/{$usedVoucher->id}")
            ->assertStatus(422);
        $this->assertDatabaseHas('sponsor_member_vouchers', ['id' => $usedVoucher->id]);
    }

    public function test_pic_cannot_edit_or_delete_another_organizations_voucher(): void
    {
        $picA = User::factory()->create();
        $this->makeOrganization($picA);

        $picB = User::factory()->create();
        $orgB = $this->makeOrganization($picB);
        $employeeOfB = User::factory()->create(['phone' => '081200000013']);
        $memberOfB = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $orgB->id, 'user_id' => $employeeOfB->id, 'status' => 'ACTIVE',
        ]);
        $voucherOfB = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $memberOfB->id,
            'hours_granted' => 5, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($picA)->patchJson("/api/v1/sponsor/organization/vouchers/{$voucherOfB->id}", ['hours_granted' => 99])
            ->assertStatus(404);
        $this->actingAs($picA)->deleteJson("/api/v1/sponsor/organization/vouchers/{$voucherOfB->id}")
            ->assertStatus(404);

        $this->assertEquals(5.0, (float) $voucherOfB->fresh()->hours_granted);
    }

    public function test_pic_can_revoke_and_reactivate_member(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);
        $employee = User::factory()->create(['phone' => '081200000002']);
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => $employee->id,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($pic)->postJson("/api/v1/sponsor/organization/members/{$member->id}/revoke")
            ->assertStatus(200)->assertJsonPath('data.status', 'REVOKED');

        $this->actingAs($pic)->postJson("/api/v1/sponsor/organization/members/{$member->id}/reactivate")
            ->assertStatus(200)->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_pic_cannot_manage_another_organizations_member(): void
    {
        $picA = User::factory()->create();
        $this->makeOrganization($picA);

        $picB = User::factory()->create();
        $orgB = $this->makeOrganization($picB);
        $employeeOfB = User::factory()->create(['phone' => '081200000003']);
        $memberOfB = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $orgB->id,
            'user_id' => $employeeOfB->id,
            'status' => 'ACTIVE',
        ]);

        // PIC A mencoba mencabut akses anggota milik organisasi B lewat ID yang ditebak/diketahui.
        $this->actingAs($picA)
            ->postJson("/api/v1/sponsor/organization/members/{$memberOfB->id}/revoke")
            ->assertStatus(404);

        $this->assertEquals('ACTIVE', $memberOfB->fresh()->status);
    }

    public function test_csv_import_creates_reactivates_skips_duplicates_issues_vouchers_and_reports_invalid_rows(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);

        $existingActive = User::factory()->create(['phone' => '081234567891']);
        SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => $existingActive->id,
            'status' => 'ACTIVE',
        ]);

        $existingRevoked = User::factory()->create(['phone' => '081234567892']);
        SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => $existingRevoked->id,
            'status' => 'REVOKED',
        ]);

        $csvContent = <<<CSV
        name,phone,jam
        Budi Santoso,081234567893,10
        Sudah Aktif,081234567891,5
        Diaktifkan Lagi,+6281234567892,3
        Nomor Salah,abc,2
        Jam Salah,081234567894,minus
        CSV;

        $file = UploadedFile::fake()->createWithContent('roster.csv', $csvContent);

        $response = $this->actingAs($pic)->post('/api/v1/sponsor/organization/members/import-csv', [
            'file' => $file,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(1, $data['imported']);
        $this->assertEquals(1, $data['reactivated']);
        $this->assertEquals(1, $data['skipped_duplicate']);
        $this->assertEquals(3, $data['vouchers_issued']); // Budi (baru), Sudah Aktif (top-up), Diaktifkan Lagi (reaktivasi)
        $this->assertCount(2, $data['failed_rows']); // Nomor Salah (HP invalid) + Jam Salah (jam non-numerik)

        $newMember = SponsorOrganizationMember::whereHas('user', fn ($q) => $q->where('phone', '081234567893'))->first();
        $this->assertEquals(10.0, $newMember->totalRemainingHours());

        $reactivatedMember = SponsorOrganizationMember::where('user_id', $existingRevoked->id)->first();
        $this->assertEquals('ACTIVE', $reactivatedMember->status);
        $this->assertEquals(3.0, $reactivatedMember->totalRemainingHours());
    }

    public function test_reuploading_identical_csv_in_the_same_period_updates_instead_of_duplicating(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);

        $csvContent = "name,phone,hours\nBudi Santoso,081234500099,10\n";

        // Ke-upload "dobel tidak sengaja" — persis file yang sama, 2x berturut-turut.
        $this->actingAs($pic)->post('/api/v1/sponsor/organization/members/import-csv', [
            'file' => UploadedFile::fake()->createWithContent('roster.csv', $csvContent),
        ])->assertStatus(200)->assertJsonPath('data.vouchers_issued', 1);

        $second = $this->actingAs($pic)->post('/api/v1/sponsor/organization/members/import-csv', [
            'file' => UploadedFile::fake()->createWithContent('roster.csv', $csvContent),
        ]);
        $second->assertStatus(200)
            ->assertJsonPath('data.vouchers_issued', 0)
            ->assertJsonPath('data.vouchers_updated', 1);

        $member = SponsorOrganizationMember::whereHas('user', fn ($q) => $q->where('phone', '081234500099'))->first();
        $this->assertEquals(1, $member->vouchers()->count(), 'Upload dobel di periode yang sama tidak boleh bikin voucher kedua.');
        $this->assertEquals(10.0, $member->totalRemainingHours());
    }

    public function test_reuploading_csv_in_same_period_with_corrected_hours_updates_existing_voucher(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);

        $this->actingAs($pic)->post('/api/v1/sponsor/organization/members/import-csv', [
            'file' => UploadedFile::fake()->createWithContent('roster.csv', "name,phone,hours\nBudi Santoso,081234500098,10\n"),
        ])->assertStatus(200);

        // Minggu ke-2: koreksi jam Budi jadi 15 (bukan nambah voucher baru).
        $this->actingAs($pic)->post('/api/v1/sponsor/organization/members/import-csv', [
            'file' => UploadedFile::fake()->createWithContent('roster.csv', "name,phone,hours\nBudi Santoso,081234500098,15\n"),
        ])->assertStatus(200)->assertJsonPath('data.vouchers_updated', 1);

        $member = SponsorOrganizationMember::whereHas('user', fn ($q) => $q->where('phone', '081234500098'))->first();
        $this->assertEquals(1, $member->vouchers()->count());
        $this->assertEquals(15.0, $member->totalRemainingHours());
    }

    public function test_csv_import_in_a_new_period_creates_a_separate_voucher(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);

        \Carbon\Carbon::setTestNow('2026-09-05');
        $this->actingAs($pic)->post('/api/v1/sponsor/organization/members/import-csv', [
            'file' => UploadedFile::fake()->createWithContent('roster.csv', "name,phone,hours\nBudi Santoso,081234500097,10\n"),
        ])->assertStatus(200)->assertJsonPath('data.vouchers_issued', 1);

        \Carbon\Carbon::setTestNow('2026-10-05');
        $this->actingAs($pic)->post('/api/v1/sponsor/organization/members/import-csv', [
            'file' => UploadedFile::fake()->createWithContent('roster.csv', "name,phone,hours\nBudi Santoso,081234500097,8\n"),
        ])->assertStatus(200)->assertJsonPath('data.vouchers_issued', 1);
        \Carbon\Carbon::setTestNow();

        $member = SponsorOrganizationMember::whereHas('user', fn ($q) => $q->where('phone', '081234500097'))->first();
        $this->assertEquals(2, $member->vouchers()->count(), 'Periode (bulan) berbeda harus tetap bikin voucher terpisah.');
        $this->assertEquals(18.0, $member->totalRemainingHours());
    }

    public function test_csv_correction_below_hours_used_fails_gracefully_without_breaking_import(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);

        $this->actingAs($pic)->post('/api/v1/sponsor/organization/members/import-csv', [
            'file' => UploadedFile::fake()->createWithContent('roster.csv', "name,phone,hours\nBudi Santoso,081234500096,10\n"),
        ])->assertStatus(200);

        $member = SponsorOrganizationMember::whereHas('user', fn ($q) => $q->where('phone', '081234500096'))->first();
        $voucher = $member->vouchers()->first();
        $voucher->update(['hours_used' => 8]);

        $response = $this->actingAs($pic)->post('/api/v1/sponsor/organization/members/import-csv', [
            'file' => UploadedFile::fake()->createWithContent('roster.csv', "name,phone,hours\nBudi Santoso,081234500096,3\n"),
        ]);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.failed_rows'));

        $this->assertEquals(10.0, (float) $voucher->fresh()->hours_granted, 'Koreksi yang gagal tidak boleh mengubah data yang sudah ada.');
    }

    public function test_csv_import_row_beyond_contract_quota_is_reported_as_failed_row(): void
    {
        $pic = User::factory()->create();
        $org = $this->makeOrganization($pic);
        $this->givePadelHourQuota($org, 8);

        $response = $this->actingAs($pic)->post('/api/v1/sponsor/organization/members/import-csv', [
            'file' => UploadedFile::fake()->createWithContent('roster.csv', "name,phone,hours\nBudi Santoso,081234500097,5\nSiti Rahma,081234500098,5\n"),
        ]);

        $response->assertStatus(200);
        // Budi (5 jam) lolos, sisa kuota 3 jam — Siti (minta 5 jam) gagal karena melebihi sisa kuota.
        $this->assertEquals(1, $response->json('data.vouchers_issued'));
        $this->assertCount(1, $response->json('data.failed_rows'));
        $this->assertEquals(5.0, $org->totalHoursReleased());
    }
}
