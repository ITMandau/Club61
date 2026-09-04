<?php

namespace Database\Seeders;

use App\Models\Fnb\FnbCategory;
use App\Models\Fnb\FnbMenu;
use App\Models\Fnb\FnbModifierGroup;
use App\Models\Fnb\FnbModifierOption;
use App\Models\Fnb\RawMaterial;
use App\Models\Fnb\RecipeBom;
use App\Models\Fnb\TableQrCode;
use App\Models\Gym\GymPackage;
use App\Models\Merch\MerchProduct;
use App\Models\Merch\MerchVariant;
use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Voucher;
use App\Models\Salon\SalonService;
use App\Models\Staff\StaffProfile;
use App\Models\User;
use App\Models\Wellness\WellnessFacility;
use App\Models\Wellness\WellnessSlot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. SEED USERS & STAFF
        $password = Hash::make('Password123!');

        $admin = User::create([
            'name' => 'Super Admin Club 61',
            'email' => 'admin@club61.com',
            'phone' => '08110000001',
            'password' => $password,
            'role' => 'SUPER_ADMIN',
            'is_active' => true,
        ]);

        $cashier = User::create([
            'name' => 'Kasir Frontdesk POS',
            'email' => 'cashier@club61.com',
            'phone' => '08110000002',
            'password' => $password,
            'role' => 'CASHIER',
            'is_active' => true,
        ]);

        $barista = User::create([
            'name' => 'Barista Cafe Club 61',
            'email' => 'barista@club61.com',
            'phone' => '08110000003',
            'password' => $password,
            'role' => 'KITCHEN',
            'is_active' => true,
        ]);

        $coachUser = User::create([
            'name' => 'Coach Budi Santoso',
            'email' => 'coach.budi@club61.com',
            'phone' => '08110000004',
            'password' => $password,
            'role' => 'TRAINER',
            'is_active' => true,
        ]);

        $coachProfile = StaffProfile::create([
            'user_id' => $coachUser->id,
            'profession' => 'TRAINER',
            'bio' => 'Certified WPT Padel Coach with 6 years experience in Spain and Asia.',
            'hourly_rate' => 150000.00,
            'commission_rate' => 15.00,
            'is_available' => true,
        ]);

        $stylistUser = User::create([
            'name' => 'Siti Hair Stylist',
            'email' => 'stylist.siti@club61.com',
            'phone' => '08110000005',
            'password' => $password,
            'role' => 'STYLIST',
            'is_active' => true,
        ]);

        $stylistProfile = StaffProfile::create([
            'user_id' => $stylistUser->id,
            'profession' => 'STYLIST',
            'bio' => 'Senior Hair Specialist in modern coloring & scalp treatment.',
            'hourly_rate' => 100000.00,
            'commission_rate' => 10.00,
            'is_available' => true,
        ]);

        $customer = User::create([
            'name' => 'Andi Wijaya (Customer)',
            'email' => 'budi@gmail.com',
            'phone' => '081234567890',
            'password' => $password,
            'role' => 'CUSTOMER',
            'is_active' => true,
        ]);

        // 2. SEED PADEL COURTS & EQUIPMENTS
        $courts = [
            ['name' => 'Court 1 - Panoramic Indoor', 'type' => 'INDOOR', 'hourly_rate_regular' => 300000.00, 'hourly_rate_prime' => 450000.00],
            ['name' => 'Court 2 - Panoramic Indoor', 'type' => 'INDOOR', 'hourly_rate_regular' => 300000.00, 'hourly_rate_prime' => 450000.00],
            ['name' => 'Court 3 - Open Air Outdoor', 'type' => 'OUTDOOR', 'hourly_rate_regular' => 250000.00, 'hourly_rate_prime' => 375000.00],
            ['name' => 'Court 4 - Championship Arena', 'type' => 'INDOOR', 'hourly_rate_regular' => 350000.00, 'hourly_rate_prime' => 500000.00],
        ];
        foreach ($courts as $c) {
            PadelCourt::create($c);
        }

        CourtEquipment::create(['name' => 'Raket Babolat Counter Viper', 'type' => 'RACKET', 'rental_price' => 50000.00, 'stock_quantity' => 20]);
        CourtEquipment::create(['name' => 'Raket Nox AT10 Genius 18K', 'type' => 'RACKET', 'rental_price' => 65000.00, 'stock_quantity' => 15]);
        CourtEquipment::create(['name' => 'Bola Padel Pro (1 Can / 3 Pcs)', 'type' => 'BALL', 'rental_price' => 35000.00, 'stock_quantity' => 50]);

        // 3. SEED WELLNESS FACILITIES & SLOTS
        $coldPlunge = WellnessFacility::create([
            'name' => 'Ice Bath / Cold Plunge',
            'max_capacity_per_slot' => 6,
            'duration_minutes' => 45,
            'price_per_person' => 125000.00,
        ]);

        $sauna = WellnessFacility::create([
            'name' => 'Finnish Cedarwood Sauna',
            'max_capacity_per_slot' => 8,
            'duration_minutes' => 45,
            'price_per_person' => 150000.00,
        ]);

        // Generate slots for today
        $today = now()->format('Y-m-d');
        $slotTimes = ['09:00:00', '10:00:00', '11:00:00', '14:00:00', '15:00:00', '16:00:00', '19:00:00', '20:00:00'];
        foreach ($slotTimes as $st) {
            WellnessSlot::create([
                'facility_id' => $coldPlunge->id,
                'session_date' => $today,
                'start_time' => "$today $st",
                'end_time' => date('Y-m-d H:i:s', strtotime("$today $st +45 minutes")),
                'max_capacity' => 6,
                'booked_count' => 0,
                'status' => 'AVAILABLE',
            ]);
            WellnessSlot::create([
                'facility_id' => $sauna->id,
                'session_date' => $today,
                'start_time' => "$today $st",
                'end_time' => date('Y-m-d H:i:s', strtotime("$today $st +45 minutes")),
                'max_capacity' => 8,
                'booked_count' => 0,
                'status' => 'AVAILABLE',
            ]);
        }

        // 4. SEED SALON SERVICES
        SalonService::create(['name' => 'Signature Haircut & Wash', 'duration_minutes' => 45, 'price' => 150000.00]);
        SalonService::create(['name' => 'Balayage Color Treatment', 'duration_minutes' => 120, 'price' => 750000.00]);
        SalonService::create(['name' => 'Organic Scalp Spa & Blowdry', 'duration_minutes' => 60, 'price' => 250000.00]);

        // 5. SEED GYM PACKAGES
        GymPackage::create(['name' => 'Monthly Unlimited Access', 'duration_days' => 30, 'visit_limit' => null, 'price' => 750000.00]);
        GymPackage::create(['name' => '10-Sessions Flexi Pass', 'duration_days' => 60, 'visit_limit' => 10, 'price' => 500000.00]);
        GymPackage::create(['name' => 'Annual VIP Membership', 'duration_days' => 365, 'visit_limit' => null, 'price' => 6500000.00]);

        // 6. SEED CAFE (F&B), RAW MATERIALS & BOM
        $catCoffee = FnbCategory::create(['name' => 'Specialty Coffee', 'sort_order' => 1]);
        $catNonCoffee = FnbCategory::create(['name' => 'Matcha & Wellness Drinks', 'sort_order' => 2]);
        $catFood = FnbCategory::create(['name' => 'Artisan Bakery & Toast', 'sort_order' => 3]);

        $rawBeans = RawMaterial::create(['code' => 'RAW-BEANS', 'name' => 'Single Origin Arabica Beans', 'unit' => 'GRAM', 'current_stock' => 15000.00, 'min_alert_stock' => 2000.00]);
        $rawOatMilk = RawMaterial::create(['code' => 'RAW-OATMILK', 'name' => 'Oatside Barista Blend', 'unit' => 'ML', 'current_stock' => 25000.00, 'min_alert_stock' => 5000.00]);
        $rawFreshMilk = RawMaterial::create(['code' => 'RAW-MILK', 'name' => 'Greenfields Fresh Milk', 'unit' => 'ML', 'current_stock' => 30000.00, 'min_alert_stock' => 5000.00]);
        $rawMatcha = RawMaterial::create(['code' => 'RAW-MATCHA', 'name' => 'Ceremonial Grade Uji Matcha', 'unit' => 'GRAM', 'current_stock' => 3000.00, 'min_alert_stock' => 500.00]);

        $menuSpanishLatte = FnbMenu::create([
            'category_id' => $catCoffee->id,
            'name' => 'Iced Spanish Latte',
            'description' => 'Espresso double shot with condensed milk and chilled fresh milk.',
            'base_price' => 38000.00,
            'station' => 'BAR',
            'is_available' => true,
        ]);
        RecipeBom::create(['menu_id' => $menuSpanishLatte->id, 'raw_material_id' => $rawBeans->id, 'quantity_used' => 18.00]);
        RecipeBom::create(['menu_id' => $menuSpanishLatte->id, 'raw_material_id' => $rawFreshMilk->id, 'quantity_used' => 160.00]);

        $menuMatcha = FnbMenu::create([
            'category_id' => $catNonCoffee->id,
            'name' => 'Ceremonial Oat Matcha Latte',
            'description' => 'Stone-ground Uji matcha whisked with Oatside barista milk.',
            'base_price' => 45000.00,
            'station' => 'BAR',
            'is_available' => true,
        ]);
        RecipeBom::create(['menu_id' => $menuMatcha->id, 'raw_material_id' => $rawMatcha->id, 'quantity_used' => 8.00]);
        RecipeBom::create(['menu_id' => $menuMatcha->id, 'raw_material_id' => $rawOatMilk->id, 'quantity_used' => 200.00]);

        $menuToast = FnbMenu::create([
            'category_id' => $catFood->id,
            'name' => 'Smashed Avocado Sourdough',
            'description' => 'Rustic sourdough with poached egg, hass avocado, and feta.',
            'base_price' => 55000.00,
            'station' => 'KITCHEN',
            'is_available' => true,
        ]);

        $modMilk = FnbModifierGroup::create(['name' => 'Milk Option', 'is_required' => false, 'max_selection' => 1]);
        FnbModifierOption::create(['group_id' => $modMilk->id, 'name' => 'Oatside Oat Milk', 'extra_price' => 8000.00]);
        FnbModifierOption::create(['group_id' => $modMilk->id, 'name' => 'Almond Milk', 'extra_price' => 10000.00]);

        $modSugar = FnbModifierGroup::create(['name' => 'Sweetness Level', 'is_required' => false, 'max_selection' => 1]);
        FnbModifierOption::create(['group_id' => $modSugar->id, 'name' => 'Less Sugar (50%)', 'extra_price' => 0.00]);
        FnbModifierOption::create(['group_id' => $modSugar->id, 'name' => 'No Sugar (0%)', 'extra_price' => 0.00]);

        TableQrCode::create(['table_number' => 'Table 01', 'qr_hash' => Str::random(32), 'is_active' => true]);
        TableQrCode::create(['table_number' => 'Table 02', 'qr_hash' => Str::random(32), 'is_active' => true]);
        TableQrCode::create(['table_number' => 'VIP Lounge 1', 'qr_hash' => Str::random(32), 'is_active' => true]);

        // 7. SEED MERCHANDISE
        $jersey = MerchProduct::create([
            'name' => 'Club 61 Pro Match Jersey',
            'brand' => 'Club 61 Official',
            'description' => 'Aeroready breathable fabric designed for high intensity padel rallies.',
            'base_price' => 299000.00,
            'is_active' => true,
        ]);
        MerchVariant::create(['product_id' => $jersey->id, 'sku' => 'C61-JRS-BLK-M', 'color' => 'Stealth Black', 'size' => 'M', 'stock_quantity' => 25]);
        MerchVariant::create(['product_id' => $jersey->id, 'sku' => 'C61-JRS-BLK-L', 'color' => 'Stealth Black', 'size' => 'L', 'stock_quantity' => 30]);
        MerchVariant::create(['product_id' => $jersey->id, 'sku' => 'C61-JRS-WHT-L', 'color' => 'Chalk White', 'size' => 'L', 'stock_quantity' => 20]);

        // 8. SEED PROMO VOUCHERS
        Voucher::create([
            'code' => 'CLUB61WELCOME',
            'discount_type' => 'PERCENT',
            'discount_value' => 20.00,
            'min_order_amount' => 100000.00,
            'max_discount_amount' => 50000.00,
            'quota' => 200,
            'used_count' => 0,
            'valid_until' => now()->addMonths(3),
            'is_active' => true,
        ]);
        Voucher::create([
            'code' => 'PADELMANIA',
            'discount_type' => 'FIXED',
            'discount_value' => 30000.00,
            'min_order_amount' => 250000.00,
            'quota' => 100,
            'used_count' => 0,
            'valid_until' => now()->addMonths(1),
            'is_active' => true,
        ]);
    }
}
