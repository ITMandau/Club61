<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profile_settings', function (Blueprint $table) {
            $table->string('hero_badge_text_en')->nullable()->after('hero_badge_text');
            $table->string('hero_headline_line1_en')->nullable()->after('hero_headline_line1');
            $table->string('hero_headline_highlight_en')->nullable()->after('hero_headline_highlight');
            $table->string('hero_headline_line2_en')->nullable()->after('hero_headline_line2');
            $table->text('hero_subtitle_en')->nullable()->after('hero_subtitle');
            $table->string('operating_hours_text_en')->nullable()->after('operating_hours_text');
            $table->string('footer_tagline_en')->nullable()->after('footer_tagline');
        });

        Schema::table('company_profile_facilities', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('title');
            $table->text('description_en')->nullable()->after('description');
            $table->json('amenities_en')->nullable()->after('amenities');
        });

        Schema::table('company_profile_value_props', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('title');
            $table->string('description_en')->nullable()->after('description');
        });

        $this->backfillDefaultEnglishTranslations();

        // Update lewat DB::table() tidak memicu event "saved" model, jadi cache singleton
        // CompanyProfileSetting::current() harus dibuang manual — kalau tidak, halaman depan
        // terus pakai objek lama (tanpa kolom *_en) dan toggle EN tidak berefek.
        Cache::forget('company_profile_settings');
    }

    /**
     * Isi draft terjemahan Inggris untuk konten default yang sudah ter-seed lewat migration
     * sebelumnya (2026_09_28_*). Staf tetap bisa ubah/timpa lewat panel "Konten Website" —
     * ini cuma draft awal supaya toggle bahasa langsung kepakai tanpa nunggu staf isi manual.
     * Di-match lewat identifier stabil (id=1, title asli, icon_key) — kalau baris itu sudah
     * diubah/diganti staf, update ini tidak akan cocok dan cukup dilewati (aman, tidak error).
     */
    private function backfillDefaultEnglishTranslations(): void
    {
        DB::table('company_profile_settings')->where('id', 1)->update([
            'hero_subtitle_en' => 'An internationally-standard integrated facility at Gedung Indosat Medan: {court_count} Panoramic Full-Indoor Air-Conditioned Padel Courts, plus Thermal Wellness Recovery (Sauna & 4°C Ice Bath).',
        ]);

        $facilityTranslations = [
            'Padel Arena' => [
                'description_en' => 'Fully air-conditioned, internationally-standard indoor panoramic padel courts.',
                'amenities_en' => ['Professional-grade flooring', 'Tournament-standard LED lighting', 'Panoramic glass walls'],
            ],
            'Wellness Suite' => [
                'description_en' => 'A post-match recovery space with sauna and ice bath.',
                'amenities_en' => ['Sauna', '4°C ice bath', 'Private changing room'],
            ],
            'Social Lounge' => [
                'description_en' => 'A relaxed lounge and artisan cafe for before or after your game.',
                'amenities_en' => ['Specialty coffee', 'Full F&B menu', 'Casual lounge seating'],
            ],
        ];

        foreach ($facilityTranslations as $title => $values) {
            DB::table('company_profile_facilities')->where('title', $title)->update([
                'description_en' => $values['description_en'],
                'amenities_en' => json_encode($values['amenities_en']),
            ]);
        }

        $valuePropTranslations = [
            'booking' => ['title_en' => 'Real-Time Online Booking', 'description_en' => 'Check court availability and pay straight from your phone — no phone calls needed.'],
            'membership' => ['title_en' => 'Flexible Membership', 'description_en' => 'Play-hour packages for individuals and corporate partners alike.'],
            'sponsor' => ['title_en' => 'Corporate Sponsor Program', 'description_en' => 'An employee benefit solution for our partner companies.'],
            'voucher' => ['title_en' => 'Regular Promos & Vouchers', 'description_en' => 'Exclusive offers for active members.'],
            'support' => ['title_en' => 'Responsive Frontdesk Service', 'description_en' => 'Our staff are ready to help with rescheduling and anything else you need on-site.'],
            'shield' => ['title_en' => 'Secure, Fully-Logged Transactions', 'description_en' => 'Every payment is neatly recorded through our integrated system.'],
        ];

        foreach ($valuePropTranslations as $iconKey => $values) {
            DB::table('company_profile_value_props')->where('icon_key', $iconKey)->update($values);
        }
    }

    public function down(): void
    {
        Schema::table('company_profile_settings', function (Blueprint $table) {
            $table->dropColumn([
                'hero_badge_text_en',
                'hero_headline_line1_en',
                'hero_headline_highlight_en',
                'hero_headline_line2_en',
                'hero_subtitle_en',
                'operating_hours_text_en',
                'footer_tagline_en',
            ]);
        });

        Schema::table('company_profile_facilities', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'description_en', 'amenities_en']);
        });

        Schema::table('company_profile_value_props', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'description_en']);
        });
    }
};
