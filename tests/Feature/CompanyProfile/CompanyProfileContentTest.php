<?php

namespace Tests\Feature\CompanyProfile;

use App\Filament\Pages\KelolaKontenWebsite;
use App\Models\Setting\CompanyProfileFacility;
use App\Models\Setting\CompanyProfileSetting;
use App\Models\Setting\CompanyProfileValueProp;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipPlanBenefit;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CompanyProfileContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
    }

    public function test_current_singleton_always_returns_same_row(): void
    {
        $first = CompanyProfileSetting::current();
        $second = CompanyProfileSetting::current();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CompanyProfileSetting::count());
    }

    public function test_staff_without_permission_cannot_access_kelola_konten_website(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier);

        $this->assertFalse(KelolaKontenWebsite::canAccess());
    }

    public function test_admin_role_can_access_kelola_konten_website(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->assertTrue(KelolaKontenWebsite::canAccess());
    }

    public function test_welcome_page_renders_court_count_and_facility_cards_from_database(): void
    {
        $settings = CompanyProfileSetting::current();
        $settings->court_count = 7;
        $settings->facility_cards = [
            ['icon_key' => 'arena', 'title' => 'Kustom Arena Test', 'subtitle' => 'Subtitle Test'],
        ];
        $settings->hero_subtitle = 'Ada {court_count} lapangan tersedia sekarang.';
        $settings->save();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Ada 7 lapangan tersedia sekarang.');
        $response->assertSee('Kustom Arena Test');
    }

    public function test_login_page_and_welcome_page_show_identical_court_count_and_facility_cards(): void
    {
        $settings = CompanyProfileSetting::current();
        $settings->court_count = 5;
        $settings->facility_cards = [
            ['icon_key' => 'wellness', 'title' => 'Regression Guard Card', 'subtitle' => 'Sub Regression'],
        ];
        $settings->save();

        $welcome = $this->get('/');
        $login = $this->get('/login');

        // Angka jumlah lapangan tampil di kedua halaman (beda kalimat, sumber sama).
        $welcome->assertSee('5 Lapangan Padel');
        $login->assertSee('5 COURTS OPEN', false);

        // Daftar kartu fasilitas identik di kedua halaman.
        $welcome->assertSee('Regression Guard Card');
        $login->assertSee('Regression Guard Card');
    }

    public function test_facility_card_icon_key_outside_closed_list_falls_back_to_star(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $page = new KelolaKontenWebsite();
        $page->mount();
        $page->facilityCards = [
            ['icon_key' => '<script>alert(1)</script>', 'title' => 'Injected Card', 'subtitle' => 'x'],
        ];
        $page->save();

        $saved = CompanyProfileSetting::current()->fresh()->facility_cards;

        $this->assertSame('star', $saved[0]['icon_key']);
        $this->assertSame('Injected Card', $saved[0]['title']);
    }

    public function test_empty_title_cards_are_dropped_on_save(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $page = new KelolaKontenWebsite();
        $page->mount();
        $page->facilityCards = [
            ['icon_key' => 'arena', 'title' => 'Valid Card', 'subtitle' => 'x'],
            ['icon_key' => 'arena', 'title' => '   ', 'subtitle' => 'should be dropped'],
        ];
        $page->save();

        $saved = CompanyProfileSetting::current()->fresh()->facility_cards;

        $this->assertCount(1, $saved);
        $this->assertSame('Valid Card', $saved[0]['title']);
    }

    public function test_hero_subtitle_court_count_placeholder_interpolates_correctly(): void
    {
        $settings = CompanyProfileSetting::current();
        $settings->court_count = 9;
        $settings->hero_subtitle = 'Kami punya {court_count} lapangan siap main.';
        $settings->save();

        $this->assertSame('Kami punya 9 lapangan siap main.', $settings->renderedHeroSubtitle());
    }

    public function test_admin_can_add_facility_with_photo_and_it_renders_on_welcome_page(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(KelolaKontenWebsite::class)
            ->call('addFacility')
            ->set('facilities.3.title', 'Gym Fitness Corner')
            ->set('facilities.3.description', 'Area angkat beban ringan.')
            ->set('facilities.3.amenities_text', "Dumbbell\nMatras")
            ->set('facilityUploads.3', UploadedFile::fake()->image('gym.jpg', 800, 600))
            ->call('save');

        $saved = CompanyProfileFacility::where('title', 'Gym Fitness Corner')->first();

        $this->assertNotNull($saved);
        $this->assertNotNull($saved->photo_path);
        Storage::disk('public')->assertExists($saved->photo_path);
        $this->assertSame(['Dumbbell', 'Matras'], $saved->amenities);

        $response = $this->get('/');
        $response->assertSee('Gym Fitness Corner');
    }

    public function test_removing_facility_deletes_its_photo_file_from_disk(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(KelolaKontenWebsite::class)
            ->call('addFacility')
            ->set('facilities.3.title', 'Temp Facility')
            ->set('facilityUploads.3', UploadedFile::fake()->image('temp.jpg'))
            ->call('save');

        $saved = CompanyProfileFacility::where('title', 'Temp Facility')->firstOrFail();
        $photoPath = $saved->photo_path;
        Storage::disk('public')->assertExists($photoPath);

        // Instance Livewire baru (mount() memuat ulang dari DB) — cari index-nya secara dinamis,
        // jangan asumsikan posisi tetap, karena 3 fasilitas default dari seed migration juga ikut
        // di-mount dan urutannya tidak dijamin sama persis dengan saat pertama ditambahkan.
        $component = Livewire::test(KelolaKontenWebsite::class);
        $indexToRemove = collect($component->get('facilities'))->search(fn ($f) => $f['title'] === 'Temp Facility');
        $this->assertNotFalse($indexToRemove, 'Temp Facility tidak ditemukan di form setelah remount.');

        $component->call('removeFacility', $indexToRemove)->call('save');

        $this->assertNull(CompanyProfileFacility::find($saved->id));
        Storage::disk('public')->assertMissing($photoPath);
    }

    public function test_value_props_appear_on_welcome_page(): void
    {
        CompanyProfileValueProp::query()->delete();
        CompanyProfileValueProp::create([
            'icon_key' => 'booking',
            'title' => 'Regression Value Prop',
            'description' => 'Deskripsi regresi.',
            'sort_order' => 1,
        ]);

        $response = $this->get('/');

        $response->assertSee('Regression Value Prop');
    }

    public function test_welcome_page_renders_when_footer_social_links_is_empty_json_string(): void
    {
        // Regresi: tanpa cast 'array' pada footer_social_links, Eloquent mengembalikan nilai
        // kolom JSON apa adanya sebagai string literal (mis. "[]") alih-alih array PHP yang
        // sudah di-decode. String "[]" itu truthy dan !empty() di PHP, jadi lolos ke @foreach
        // di welcome.blade.php dan bikin ErrorException "foreach() argument must be of type
        // array|object, string given" — persis yang dilaporkan user.
        $settings = CompanyProfileSetting::current();
        \Illuminate\Support\Facades\DB::table('company_profile_settings')
            ->where('id', $settings->id)
            ->update(['footer_social_links' => '[]']);

        \Illuminate\Support\Facades\Cache::forget(CompanyProfileSetting::CACHE_KEY);

        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_footer_social_links_saved_via_admin_panel_round_trips_as_array(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(KelolaKontenWebsite::class)
            ->set('footerInstagram', 'https://instagram.com/club61')
            ->call('save');

        $fresh = CompanyProfileSetting::current()->fresh();

        $this->assertIsArray($fresh->footer_social_links);
        $this->assertSame('https://instagram.com/club61', $fresh->footer_social_links['instagram']);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('https://instagram.com/club61', false);
    }

    public function test_invalid_maps_embed_url_is_rejected_and_welcome_page_falls_back_to_generated_map(): void
    {
        // Regresi: staf pernah tidak sengaja mengetik alamat biasa (bukan link) di field
        // "URL Embed Google Maps", yang tersimpan apa adanya lalu bikin iframe di halaman
        // depan gagal dimuat ("refused to connect") karena browser mencoba membukanya
        // sebagai URL relatif ke web ini sendiri.
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(KelolaKontenWebsite::class)
            ->set('mapsEmbedUrl', 'Fatmawati, Jl. RS. Fatmawati Raya No.17, Jakarta Selatan')
            ->call('save');

        $fresh = CompanyProfileSetting::current()->fresh();
        $this->assertSame('', $fresh->maps_embed_url);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('google.com/maps', false);
        $response->assertDontSee('Fatmawati, Jl. RS. Fatmawati');
    }

    public function test_valid_maps_embed_url_is_kept_as_is(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(KelolaKontenWebsite::class)
            ->set('mapsEmbedUrl', 'https://www.google.com/maps/embed?pb=custom-embed-code')
            ->call('save');

        $fresh = CompanyProfileSetting::current()->fresh();
        $this->assertSame('https://www.google.com/maps/embed?pb=custom-embed-code', $fresh->maps_embed_url);
    }

    public function test_welcome_page_defaults_to_indonesian(): void
    {
        $settings = CompanyProfileSetting::current();
        $settings->hero_subtitle = 'Teks Indonesia asli.';
        $settings->hero_subtitle_en = 'The real English text.';
        $settings->save();

        $response = $this->get('/');

        $response->assertSee('Teks Indonesia asli.');
        $response->assertDontSee('The real English text.');
    }

    public function test_lang_switch_route_persists_locale_in_session_and_shows_english_content(): void
    {
        $settings = CompanyProfileSetting::current();
        $settings->hero_subtitle = 'Teks Indonesia asli.';
        $settings->hero_subtitle_en = 'The real English text.';
        $settings->save();

        $this->get('/lang/en')->assertRedirect();

        $response = $this->get('/');

        $response->assertSee('The real English text.');
        $response->assertDontSee('Teks Indonesia asli.');
    }

    public function test_hero_card_shows_photo_from_facility_with_matching_title(): void
    {
        CompanyProfileFacility::where('title', 'Padel Arena')->update(['photo_path' => 'company-profile/facilities/test-arena.jpg']);

        $this->get('/')->assertSee('/storage/company-profile/facilities/test-arena.jpg', false);
    }

    public function test_english_translation_written_directly_to_db_shows_after_cache_is_cleared(): void
    {
        // Regresi: migration backfill terjemahan pakai DB::table() (tanpa event model), jadi
        // cache singleton lama tetap dipakai dan subtitle hero tidak pernah berubah ke EN.
        CompanyProfileSetting::current();
        \Illuminate\Support\Facades\DB::table('company_profile_settings')->where('id', 1)->update([
            'hero_subtitle_en' => 'Fresh English subtitle from backfill.',
        ]);
        \Illuminate\Support\Facades\Cache::forget(CompanyProfileSetting::CACHE_KEY);

        $this->get('/lang/en');
        $this->get('/')->assertSee('Fresh English subtitle from backfill.');
    }

    public function test_lang_switch_rejects_unknown_locale(): void
    {
        $this->get('/lang/fr')->assertNotFound();
    }

    public function test_localized_field_falls_back_to_indonesian_when_english_translation_is_empty(): void
    {
        $settings = CompanyProfileSetting::current();
        $settings->footer_tagline = 'Tagline Indonesia.';
        $settings->footer_tagline_en = null;
        $settings->save();

        session(['site_locale' => 'en']);
        app()->setLocale('en');

        $this->assertSame('Tagline Indonesia.', $settings->localized('footer_tagline'));
    }

    public function test_welcome_page_shows_all_active_individual_membership_plans_with_readable_benefits(): void
    {
        // Regresi: benefit dengan quota_type "NONE" (artinya "tanpa kuota tetap, diskon per
        // pakai" — BUKAN benefit kosong) sebelumnya dirender jadi teks rusak "0 none GYM"
        // karena kode lama selalu mengira ada quota_value numerik.
        $plan = MembershipPlan::create([
            'code' => 'TEST-DISKON',
            'name' => 'Test Diskon Plan',
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30,
            'price' => 99000,
            'is_active' => true,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $plan->id,
            'facility' => 'GYM',
            'quota_type' => 'NONE',
            'quota_value' => null,
            'discount_percent' => 10,
        ]);

        $response = $this->get('/');

        $response->assertSee('Test Diskon Plan');
        $response->assertSee('Diskon 10% Fitness &amp; Gym', false);
        $response->assertDontSee('0 none GYM');
    }

    public function test_facilities_and_value_props_are_scoped_to_active_only(): void
    {
        CompanyProfileFacility::create([
            'title' => 'Inactive Facility',
            'sort_order' => 99,
            'is_active' => false,
        ]);
        CompanyProfileValueProp::create([
            'icon_key' => 'star',
            'title' => 'Inactive Value Prop',
            'sort_order' => 99,
            'is_active' => false,
        ]);

        $response = $this->get('/');

        $response->assertDontSee('Inactive Facility');
        $response->assertDontSee('Inactive Value Prop');
    }
}
