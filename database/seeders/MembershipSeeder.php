<?php

namespace Database\Seeders;

use App\Models\Membership\FacilityCheckin;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipPlanBenefit;
use App\Models\Membership\MembershipUsageLog;
use App\Models\Membership\UserMembership;
use App\Models\Membership\UserMembershipBalance;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MembershipSeeder extends Seeder
{
    public function run(): void
    {
        // 1. SEED MEMBERSHIP PLANS & BENEFIT MATRICES
        $plans = [
            [
                'code' => 'MBR-BRONZE',
                'name' => 'Bronze Active',
                'ownership_type' => 'INDIVIDUAL',
                'duration_days' => 30,
                'price' => 1500000.00,
                'is_active' => true,
                'benefits' => [
                    ['facility' => 'GYM', 'quota_type' => 'VISITS', 'quota_value' => 12, 'discount_percent' => 0, 'booking_priority_days' => 0],
                    ['facility' => 'SAUNA', 'quota_type' => 'VISITS', 'quota_value' => 4, 'discount_percent' => 10, 'booking_priority_days' => 0],
                    ['facility' => 'PADEL', 'quota_type' => 'NONE', 'quota_value' => null, 'discount_percent' => 10, 'booking_priority_days' => 0],
                ],
            ],
            [
                'code' => 'MBR-SILVER',
                'name' => 'Silver Padel Addict',
                'ownership_type' => 'INDIVIDUAL',
                'duration_days' => 30,
                'price' => 3500000.00,
                'is_active' => true,
                'benefits' => [
                    ['facility' => 'PADEL', 'quota_type' => 'HOURS', 'quota_value' => 10, 'discount_percent' => 20, 'booking_priority_days' => 7],
                    ['facility' => 'GYM', 'quota_type' => 'VISITS', 'quota_value' => null, 'discount_percent' => 0, 'booking_priority_days' => 0],
                    ['facility' => 'SAUNA', 'quota_type' => 'VISITS', 'quota_value' => 4, 'discount_percent' => 20, 'booking_priority_days' => 0],
                ],
            ],
            [
                'code' => 'MBR-GOLD',
                'name' => 'Gold Ultimate Club 61',
                'ownership_type' => 'INDIVIDUAL',
                'duration_days' => 30,
                'price' => 6000000.00,
                'is_active' => true,
                'benefits' => [
                    ['facility' => 'PADEL', 'quota_type' => 'HOURS', 'quota_value' => 25, 'discount_percent' => 30, 'booking_priority_days' => 14],
                    ['facility' => 'GYM', 'quota_type' => 'VISITS', 'quota_value' => null, 'discount_percent' => 0, 'booking_priority_days' => 0],
                    ['facility' => 'SAUNA', 'quota_type' => 'VISITS', 'quota_value' => 10, 'discount_percent' => 30, 'booking_priority_days' => 0],
                ],
            ],
            [
                'code' => 'MBR-CORP',
                'name' => 'Corporate Sponsor Tier-A',
                'ownership_type' => 'ORGANIZATIONAL',
                'duration_days' => 90,
                'price' => 25000000.00,
                'is_active' => true,
                'benefits' => [
                    ['facility' => 'PADEL', 'quota_type' => 'HOURS', 'quota_value' => 120, 'discount_percent' => 25, 'booking_priority_days' => 14],
                    ['facility' => 'GYM', 'quota_type' => 'VISITS', 'quota_value' => null, 'discount_percent' => 0, 'booking_priority_days' => 0],
                    ['facility' => 'SAUNA', 'quota_type' => 'VISITS', 'quota_value' => null, 'discount_percent' => 0, 'booking_priority_days' => 0],
                ],
            ],
        ];

        $createdPlans = [];
        foreach ($plans as $pData) {
            $benefits = $pData['benefits'];
            unset($pData['benefits']);

            $plan = MembershipPlan::firstOrCreate(
                ['code' => $pData['code']],
                $pData
            );
            $plan->update($pData);

            foreach ($benefits as $b) {
                MembershipPlanBenefit::firstOrCreate(
                    [
                        'plan_id' => $plan->id,
                        'facility' => $b['facility'],
                    ],
                    $b
                );
            }

            $createdPlans[$plan->code] = $plan;
        }

        // 2. SEED CUSTOMER USERS
        $defaultPassword = Hash::make('password123');

        $userBudi = User::firstOrCreate(
            ['email' => 'budi@gmail.com'],
            [
                'name' => 'Andi Wijaya (Customer)',
                'phone' => '081234567890',
                'password' => $defaultPassword,
                'is_active' => true,
            ]
        );
        if (! $userBudi->hasRole('customer')) {
            $userBudi->assignRole('customer');
        }

        $userReza = User::firstOrCreate(
            ['email' => 'reza.rahardian@club61.com'],
            [
                'name' => 'Reza Rahardian',
                'phone' => '081299887766',
                'password' => $defaultPassword,
                'is_active' => true,
            ]
        );
        if (! $userReza->hasRole('customer')) {
            $userReza->assignRole('customer');
        }

        $userCorp = User::firstOrCreate(
            ['email' => 'corporate.sinar@club61.com'],
            [
                'name' => 'PT Sinar Harapan Abadi',
                'phone' => '081311223344',
                'password' => $defaultPassword,
                'is_active' => true,
            ]
        );
        if (! $userCorp->hasRole('customer')) {
            $userCorp->assignRole('customer');
        }

        $userDewi = User::firstOrCreate(
            ['email' => 'dewi.lestari@club61.com'],
            [
                'name' => 'Dewi Lestari',
                'phone' => '081555667788',
                'password' => $defaultPassword,
                'is_active' => true,
            ]
        );
        if (! $userDewi->hasRole('customer')) {
            $userDewi->assignRole('customer');
        }

        $court1 = PadelCourt::first();
        $court2 = PadelCourt::skip(1)->first() ?? $court1;
        $court3 = PadelCourt::skip(2)->first() ?? $court1;

        // 3. SEED DUMMY USER MEMBERSHIPS, BALANCES & TRACK RECORDS

        // Customer 1: Budi (Active Silver Member with 8 Hours Padel remaining)
        $silverPlan = $createdPlans['MBR-SILVER'];
        $budiCode = 'C61-MEM-' . now()->format('Ymd') . '-0001';
        $budiMembership = UserMembership::firstOrCreate(
            ['membership_code' => $budiCode],
            [
                'owner_type' => 'INDIVIDUAL',
                'user_id' => $userBudi->id,
                'plan_id' => $silverPlan->id,
                'start_date' => now()->subDays(5)->toDateString(),
                'end_date' => now()->addDays(25)->toDateString(),
                'status' => 'ACTIVE',
                'qr_pass_hash' => hash('sha256', $budiCode . '_budi_secret'),
                'purchase_price_snapshot' => $silverPlan->price,
            ]
        );

        if ($budiMembership->wasRecentlyCreated || $budiMembership->balances()->count() === 0) {
            $budiPadelBalance = UserMembershipBalance::create([
                'user_membership_id' => $budiMembership->id,
                'facility' => 'PADEL',
                'quota_type' => 'HOURS',
                'initial_quota' => 10.00,
                'remaining_quota' => 8.00,
                'discount_percent' => 20.00,
                'booking_priority_days' => 7,
            ]);

            MembershipUsageLog::create([
                'balance_id' => $budiPadelBalance->id,
                'change_type' => 'TOPUP',
                'quantity' => 10.00,
                'notes' => 'Aktivasi paket membership Silver Padel Addict (10 Jam)',
            ]);

            MembershipUsageLog::create([
                'balance_id' => $budiPadelBalance->id,
                'change_type' => 'DECREMENT',
                'quantity' => -2.00,
                'notes' => 'Booking Lapangan 1 - Padel Panoramic (2 Jam)',
            ]);

            UserMembershipBalance::create([
                'user_membership_id' => $budiMembership->id,
                'facility' => 'GYM',
                'quota_type' => 'VISITS',
                'initial_quota' => null,
                'remaining_quota' => 0.00,
                'discount_percent' => 0.00,
                'booking_priority_days' => 0,
            ]);

            $budiSaunaBalance = UserMembershipBalance::create([
                'user_membership_id' => $budiMembership->id,
                'facility' => 'SAUNA',
                'quota_type' => 'VISITS',
                'initial_quota' => 4.00,
                'remaining_quota' => 3.00,
                'discount_percent' => 20.00,
                'booking_priority_days' => 0,
            ]);

            MembershipUsageLog::create([
                'balance_id' => $budiSaunaBalance->id,
                'change_type' => 'TOPUP',
                'quantity' => 4.00,
                'notes' => 'Aktivasi paket benefit Sauna (4 Sesi)',
            ]);

            MembershipUsageLog::create([
                'balance_id' => $budiSaunaBalance->id,
                'change_type' => 'DECREMENT',
                'quantity' => -1.00,
                'notes' => 'Check-in Sauna Finnish Cedarwood (1 Sesi)',
            ]);

            // Track Record Nyata Booking Padel Budi
            if ($court1) {
                PadelBooking::firstOrCreate(
                    ['booking_code' => 'BK-PAD-BUDI-01'],
                    [
                        'user_id' => $userBudi->id,
                        'court_id' => $court1->id,
                        'booking_date' => now()->subDays(2)->toDateString(),
                        'start_time' => now()->subDays(2)->setTime(16, 0),
                        'end_time' => now()->subDays(2)->setTime(18, 0),
                        'court_fee' => 0.00,
                        'total_amount' => 0.00,
                        'status' => 'CHECKED_IN',
                        'checked_in_at' => now()->subDays(2)->setTime(15, 52),
                        'membership_balance_id' => $budiPadelBalance->id,
                        'member_hours_consumed' => 2.00,
                    ]
                );

                PadelBooking::firstOrCreate(
                    ['booking_code' => 'BK-PAD-BUDI-02'],
                    [
                        'user_id' => $userBudi->id,
                        'court_id' => $court2 ? $court2->id : $court1->id,
                        'booking_date' => now()->addDays(2)->toDateString(),
                        'start_time' => now()->addDays(2)->setTime(17, 0),
                        'end_time' => now()->addDays(2)->setTime(19, 0),
                        'court_fee' => 0.00,
                        'total_amount' => 0.00,
                        'status' => 'CONFIRMED',
                        'membership_balance_id' => $budiPadelBalance->id,
                        'member_hours_consumed' => 2.00,
                    ]
                );
            }

            // Track Record Checkin Fasilitas Budi
            FacilityCheckin::create([
                'facility' => 'GYM',
                'balance_id' => $budiPadelBalance->id,
                'user_id' => $userBudi->id,
                'checkin_at' => now()->subDays(3)->setTime(8, 30),
            ]);

            FacilityCheckin::create([
                'facility' => 'SAUNA',
                'balance_id' => $budiSaunaBalance->id,
                'user_id' => $userBudi->id,
                'checkin_at' => now()->subDays(2)->setTime(18, 15),
            ]);
        }

        // Customer 2: Reza (Active Gold Member)
        $goldPlan = $createdPlans['MBR-GOLD'];
        $rezaCode = 'C61-MEM-' . now()->format('Ymd') . '-0002';
        $rezaMembership = UserMembership::firstOrCreate(
            ['membership_code' => $rezaCode],
            [
                'owner_type' => 'INDIVIDUAL',
                'user_id' => $userReza->id,
                'plan_id' => $goldPlan->id,
                'start_date' => now()->subDays(2)->toDateString(),
                'end_date' => now()->addDays(28)->toDateString(),
                'status' => 'ACTIVE',
                'qr_pass_hash' => hash('sha256', $rezaCode . '_reza_secret'),
                'purchase_price_snapshot' => $goldPlan->price,
            ]
        );

        if ($rezaMembership->wasRecentlyCreated || $rezaMembership->balances()->count() === 0) {
            $rezaPadel = UserMembershipBalance::create([
                'user_membership_id' => $rezaMembership->id,
                'facility' => 'PADEL',
                'quota_type' => 'HOURS',
                'initial_quota' => 25.00,
                'remaining_quota' => 21.00,
                'discount_percent' => 30.00,
                'booking_priority_days' => 14,
            ]);

            MembershipUsageLog::create([
                'balance_id' => $rezaPadel->id,
                'change_type' => 'TOPUP',
                'quantity' => 25.00,
                'notes' => 'Aktivasi kuota awal Gold Ultimate (25 Jam)',
            ]);

            MembershipUsageLog::create([
                'balance_id' => $rezaPadel->id,
                'change_type' => 'DECREMENT',
                'quantity' => -4.00,
                'notes' => 'Reservasi Court 2 Indoor Match Play (4 Jam)',
            ]);

            UserMembershipBalance::create([
                'user_membership_id' => $rezaMembership->id,
                'facility' => 'GYM',
                'quota_type' => 'VISITS',
                'initial_quota' => null,
                'remaining_quota' => 0.00,
                'discount_percent' => 0.00,
                'booking_priority_days' => 0,
            ]);

            $rezaSauna = UserMembershipBalance::create([
                'user_membership_id' => $rezaMembership->id,
                'facility' => 'SAUNA',
                'quota_type' => 'VISITS',
                'initial_quota' => 10.00,
                'remaining_quota' => 8.00,
                'discount_percent' => 30.00,
                'booking_priority_days' => 0,
            ]);

            MembershipUsageLog::create([
                'balance_id' => $rezaSauna->id,
                'change_type' => 'TOPUP',
                'quantity' => 10.00,
                'notes' => 'Aktivasi kuota Sauna & Ice Bath Gold (10 Sesi)',
            ]);

            MembershipUsageLog::create([
                'balance_id' => $rezaSauna->id,
                'change_type' => 'DECREMENT',
                'quantity' => -2.00,
                'notes' => 'Check-in Sauna & Cold Plunge Weekend (2 Sesi)',
            ]);

            if ($court2) {
                PadelBooking::firstOrCreate(
                    ['booking_code' => 'BK-PAD-REZA-01'],
                    [
                        'user_id' => $userReza->id,
                        'court_id' => $court2->id,
                        'booking_date' => now()->subDays(1)->toDateString(),
                        'start_time' => now()->subDays(1)->setTime(18, 0),
                        'end_time' => now()->subDays(1)->setTime(20, 0),
                        'court_fee' => 0.00,
                        'total_amount' => 0.00,
                        'status' => 'COMPLETED',
                        'membership_balance_id' => $rezaPadel->id,
                        'member_hours_consumed' => 2.00,
                    ]
                );

                PadelBooking::firstOrCreate(
                    ['booking_code' => 'BK-PAD-REZA-02'],
                    [
                        'user_id' => $userReza->id,
                        'court_id' => $court1 ? $court1->id : $court2->id,
                        'booking_date' => now()->addDays(3)->toDateString(),
                        'start_time' => now()->addDays(3)->setTime(19, 0),
                        'end_time' => now()->addDays(3)->setTime(21, 0),
                        'court_fee' => 0.00,
                        'total_amount' => 0.00,
                        'status' => 'CONFIRMED',
                        'membership_balance_id' => $rezaPadel->id,
                        'member_hours_consumed' => 2.00,
                    ]
                );
            }
        }

        // Customer 3: Corporate Member (PT Sinar Harapan)
        $corpPlan = $createdPlans['MBR-CORP'];
        $corpCode = 'C61-CORP-' . now()->format('Ymd') . '-0001';
        $corpMembership = UserMembership::firstOrCreate(
            ['membership_code' => $corpCode],
            [
                'owner_type' => 'ORGANIZATIONAL',
                'user_id' => $userCorp->id,
                'plan_id' => $corpPlan->id,
                'start_date' => now()->subDays(10)->toDateString(),
                'end_date' => now()->addDays(80)->toDateString(),
                'status' => 'ACTIVE',
                'qr_pass_hash' => hash('sha256', $corpCode . '_corp_secret'),
                'purchase_price_snapshot' => $corpPlan->price,
            ]
        );

        $corpMembersData = [
            [
                'name' => 'Budi Pratama',
                'role' => 'Software Engineering Lead',
                'email' => 'budi.pratama@sinarharapan.com',
                'phone' => '081233445566',
                'allocated_hours' => 20,
                'used_hours' => 6,
                'remaining_hours' => 14,
                'last_played' => now()->subDays(3)->format('d M Y, 19:00 WIB (Court 1)'),
                'next_schedule' => now()->addDays(2)->format('d M Y, 19:00 - 21:00 WIB (Court 1)'),
                'status' => 'ACTIVE',
                'notes' => 'Rutin tanding ganda malam hari sehabis kantor',
            ],
            [
                'name' => 'Siti Rahmawati',
                'role' => 'Finance & Accounting Manager',
                'email' => 'siti.rahma@sinarharapan.com',
                'phone' => '081277889900',
                'allocated_hours' => 15,
                'used_hours' => 4,
                'remaining_hours' => 11,
                'last_played' => now()->subDays(5)->format('d M Y, 17:00 WIB (Court 2)'),
                'next_schedule' => now()->addDays(4)->format('d M Y, 17:00 - 19:00 WIB (Court 2)'),
                'status' => 'ACTIVE',
                'notes' => 'Sesi sore santai + sauna & ice bath',
            ],
            [
                'name' => 'Dimas Setiawan',
                'role' => 'Marketing & Brand Specialist',
                'email' => 'dimas.s@sinarharapan.com',
                'phone' => '081399001122',
                'allocated_hours' => 15,
                'used_hours' => 2,
                'remaining_hours' => 13,
                'last_played' => now()->subDays(8)->format('d M Y, 08:00 WIB (Court 3)'),
                'next_schedule' => now()->addDays(5)->format('d M Y, 08:00 - 10:00 WIB (Court 3)'),
                'status' => 'ACTIVE',
                'notes' => 'Sesi akhir pekan pagi',
            ],
            [
                'name' => 'Ahmad Fauzi',
                'role' => 'UI/UX Product Designer',
                'email' => 'ahmad.fauzi@sinarharapan.com',
                'phone' => '081344556677',
                'allocated_hours' => 15,
                'used_hours' => 0,
                'remaining_hours' => 15,
                'last_played' => null,
                'next_schedule' => null,
                'status' => 'IDLE',
                'notes' => 'Kosong / Pasif: Didaftarkan 10 hari lalu, belum pernah booking atau check-in',
            ],
            [
                'name' => 'Nadia Putri',
                'role' => 'HR Talent Acquisition',
                'email' => 'nadia.putri@sinarharapan.com',
                'phone' => '081388776655',
                'allocated_hours' => 15,
                'used_hours' => 0,
                'remaining_hours' => 15,
                'last_played' => null,
                'next_schedule' => null,
                'status' => 'IDLE',
                'notes' => 'Kosong / Pasif: Belum pernah ada jadwal bermain',
            ],
            [
                'name' => 'Rian Kurniawan',
                'role' => 'B2B Sales Executive',
                'email' => 'rian.k@sinarharapan.com',
                'phone' => '081311559933',
                'allocated_hours' => 15,
                'used_hours' => 0,
                'remaining_hours' => 15,
                'last_played' => null,
                'next_schedule' => null,
                'status' => 'IDLE',
                'notes' => 'Kosong / Pasif: Belum pernah ada jadwal bermain',
            ],
        ];

        $corpPadel = UserMembershipBalance::updateOrCreate(
            [
                'user_membership_id' => $corpMembership->id,
                'facility' => 'PADEL',
            ],
            [
                'quota_type' => 'HOURS',
                'initial_quota' => 120.00,
                'remaining_quota' => 108.00,
                'discount_percent' => 25.00,
                'booking_priority_days' => 14,
                'extra_benefits' => [
                    'corporate_members' => $corpMembersData,
                ],
            ]
        );

        if ($corpMembership->wasRecentlyCreated || $corpMembership->balances()->count() <= 1) {
            MembershipUsageLog::create([
                'balance_id' => $corpPadel->id,
                'change_type' => 'TOPUP',
                'quantity' => 120.00,
                'notes' => 'Aktivasi alokasi Corporate Padel Tier-A (120 Jam)',
            ]);

            MembershipUsageLog::create([
                'balance_id' => $corpPadel->id,
                'change_type' => 'DECREMENT',
                'quantity' => -12.00,
                'notes' => 'Corporate Gathering & Sesi Internal Match (12 Jam)',
            ]);

            UserMembershipBalance::firstOrCreate(
                [
                    'user_membership_id' => $corpMembership->id,
                    'facility' => 'GYM',
                ],
                [
                    'quota_type' => 'VISITS',
                    'initial_quota' => null,
                    'remaining_quota' => 0.00,
                    'discount_percent' => 0.00,
                    'booking_priority_days' => 0,
                ]
            );

            UserMembershipBalance::firstOrCreate(
                [
                    'user_membership_id' => $corpMembership->id,
                    'facility' => 'SAUNA',
                ],
                [
                    'quota_type' => 'VISITS',
                    'initial_quota' => null,
                    'remaining_quota' => 0.00,
                    'discount_percent' => 0.00,
                    'booking_priority_days' => 0,
                ]
            );
        }

        // Customer 4: Dewi (Expired Bronze Member)
        $bronzePlan = $createdPlans['MBR-BRONZE'];
        $dewiCode = 'C61-MEM-' . now()->subDays(45)->format('Ymd') . '-0003';
        $dewiMembership = UserMembership::firstOrCreate(
            ['membership_code' => $dewiCode],
            [
                'owner_type' => 'INDIVIDUAL',
                'user_id' => $userDewi->id,
                'plan_id' => $bronzePlan->id,
                'start_date' => now()->subDays(45)->toDateString(),
                'end_date' => now()->subDays(15)->toDateString(),
                'status' => 'EXPIRED',
                'qr_pass_hash' => hash('sha256', $dewiCode . '_dewi_secret'),
                'purchase_price_snapshot' => $bronzePlan->price,
            ]
        );

        if ($dewiMembership->wasRecentlyCreated || $dewiMembership->balances()->count() === 0) {
            $dewiGym = UserMembershipBalance::create([
                'user_membership_id' => $dewiMembership->id,
                'facility' => 'GYM',
                'quota_type' => 'VISITS',
                'initial_quota' => 12.00,
                'remaining_quota' => 0.00,
                'discount_percent' => 0.00,
                'booking_priority_days' => 0,
            ]);

            MembershipUsageLog::create([
                'balance_id' => $dewiGym->id,
                'change_type' => 'TOPUP',
                'quantity' => 12.00,
                'notes' => 'Aktivasi kuota awal Gym Bronze (12 Sesi)',
            ]);

            MembershipUsageLog::create([
                'balance_id' => $dewiGym->id,
                'change_type' => 'DECREMENT',
                'quantity' => -12.00,
                'notes' => 'Seluruh sesi gym telah digunakan selama masa aktif',
            ]);

            $dewiSauna = UserMembershipBalance::create([
                'user_membership_id' => $dewiMembership->id,
                'facility' => 'SAUNA',
                'quota_type' => 'VISITS',
                'initial_quota' => 4.00,
                'remaining_quota' => 0.00,
                'discount_percent' => 10.00,
                'booking_priority_days' => 0,
            ]);

            MembershipUsageLog::create([
                'balance_id' => $dewiSauna->id,
                'change_type' => 'TOPUP',
                'quantity' => 4.00,
                'notes' => 'Aktivasi kuota awal Sauna Bronze (4 Sesi)',
            ]);

            MembershipUsageLog::create([
                'balance_id' => $dewiSauna->id,
                'change_type' => 'DECREMENT',
                'quantity' => -4.00,
                'notes' => 'Seluruh sesi sauna telah digunakan',
            ]);

            UserMembershipBalance::create([
                'user_membership_id' => $dewiMembership->id,
                'facility' => 'PADEL',
                'quota_type' => 'NONE',
                'initial_quota' => null,
                'remaining_quota' => 0.00,
                'discount_percent' => 10.00,
                'booking_priority_days' => 0,
            ]);

            // Booking masa lalu Dewi
            if ($court3) {
                PadelBooking::firstOrCreate(
                    ['booking_code' => 'BK-PAD-DEWI-01'],
                    [
                        'user_id' => $userDewi->id,
                        'court_id' => $court3->id,
                        'booking_date' => now()->subDays(38)->toDateString(),
                        'start_time' => now()->subDays(38)->setTime(9, 0),
                        'end_time' => now()->subDays(38)->setTime(11, 0),
                        'court_fee' => 225000.00,
                        'total_amount' => 225000.00,
                        'status' => 'COMPLETED',
                        'checked_in_at' => now()->subDays(38)->setTime(8, 55),
                    ]
                );
            }
        }
    }
}
