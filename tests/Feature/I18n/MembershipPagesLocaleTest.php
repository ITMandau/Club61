<?php

namespace Tests\Feature\I18n;

use App\Http\Middleware\SetLocale;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\UserMembership;
use App\Models\Sponsor\SponsorOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Situs customer dua bahasa — halaman membership, My Club, coaching, invoice & corporate tampil Bahasa Indonesia
 * secara default dan Bahasa Inggris saat pengunjung memilih "en" (cookie site_locale).
 */
class MembershipPagesLocaleTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->customer()->create(['name' => 'Budi Santoso']);
    }

    private function plan(array $attrs = []): MembershipPlan
    {
        return MembershipPlan::create(array_merge([
            'code' => 'MBR-I18N-'.uniqid(), 'name' => 'Gold Club', 'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30, 'price' => 1000000, 'is_active' => true,
        ], $attrs));
    }

    private function english(): static
    {
        return $this->withCookie(SetLocale::COOKIE, 'en');
    }

    public function test_membership_page_is_indonesian_by_default_and_english_when_chosen(): void
    {
        $this->plan();

        $this->actingAs($this->customer)->get('/membership')->assertOk()
            ->assertSee('Pilih &amp; Pembelian Paket Keanggotaan', false)
            ->assertSee('Bayar Sekarang (Cashless)')
            ->assertSee('30 Hari')
            ->assertSee('Gold Club') // nama paket dari database tidak diterjemahkan
            ->assertDontSee('Choose &amp; Buy a Membership Package', false);

        $this->english()->actingAs($this->customer)->get('/membership')->assertOk()
            ->assertSee('Choose &amp; Buy a Membership Package', false)
            ->assertSee('Pay Now (Cashless)')
            ->assertSee('30 Days')
            ->assertSee('Gold Club')
            ->assertDontSee('Bayar Sekarang (Cashless)');
    }

    public function test_my_club_page_follows_the_chosen_language(): void
    {
        $plan = $this->plan();
        UserMembership::create([
            'membership_code' => 'MBR-I18N-1', 'owner_type' => 'INDIVIDUAL', 'user_id' => $this->customer->id, 'plan_id' => $plan->id,
            'status' => 'ACTIVE', 'start_date' => now()->subDay(), 'end_date' => now()->addDays(20),
        ]);

        $this->actingAs($this->customer)->get('/my-club')->assertOk()
            ->assertSee('Fasilitas Eksklusif Member')
            ->assertSee('Protokol Wellness')
            ->assertSee('Lihat di Profil')
            ->assertSee('Anda adalah member <strong>Gold Club</strong>', false)
            ->assertDontSee('Exclusive Member Facilities');

        $this->english()->actingAs($this->customer)->get('/my-club')->assertOk()
            ->assertSee('Exclusive Member Facilities')
            ->assertSee('Wellness Protocol')
            ->assertSee('View in Profile')
            ->assertSee("You're a <strong>Gold Club</strong> member", false)
            ->assertDontSee('Fasilitas Eksklusif Member');
    }

    public function test_coaching_page_follows_the_chosen_language(): void
    {
        $this->actingAs($this->customer)->get(route('customer.coaching'))->assertOk()
            ->assertSee('Coaching di Club 61')
            ->assertDontSee('Coaching at Club 61');

        $this->english()->actingAs($this->customer)->get(route('customer.coaching'))->assertOk()
            ->assertSee('Coaching at Club 61')
            ->assertDontSee('Coaching di Club 61');
    }

    public function test_invoice_page_and_its_script_texts_follow_the_chosen_language(): void
    {
        $this->actingAs($this->customer)->get('/invoice')->assertOk()
            ->assertSee('Invoice &amp; Kode Booking', false)
            ->assertSee('Belum ada invoice')
            ->assertSee('Total tiket')
            ->assertSee('Menunggu pembayaran') // label status di JavaScript
            ->assertDontSee('Invoice &amp; Booking Code', false);

        $this->english()->actingAs($this->customer)->get('/invoice')->assertOk()
            ->assertSee('Invoice &amp; Booking Code', false)
            ->assertSee('No invoices yet')
            ->assertSee('Total ticket amount')
            ->assertSee('Awaiting payment')
            ->assertDontSee('Invoice &amp; Kode Booking', false);
    }

    public function test_corporate_page_follows_the_chosen_language(): void
    {
        $pic = User::factory()->create();
        $plan = $this->plan(['name' => 'Corporate B2B Padel', 'ownership_type' => 'ORGANIZATIONAL', 'duration_days' => 365]);
        $membership = UserMembership::create([
            'membership_code' => 'MBR-I18N-CORP', 'owner_type' => 'ORGANIZATIONAL', 'user_id' => $pic->id, 'plan_id' => $plan->id,
            'status' => 'ACTIVE', 'start_date' => now(), 'end_date' => now()->addYear(),
        ]);
        SponsorOrganization::create(['name' => 'PT Bahasa Test', 'user_membership_id' => $membership->id, 'sponsor_admin_user_id' => $pic->id]);

        $this->actingAs($pic)->get('/corporate')->assertOk()
            ->assertSee('Kelola Tim Corporate')
            ->assertSee('Kuota Kontrak')
            ->assertSee('PT Bahasa Test') // nama perusahaan dari database tidak diterjemahkan
            ->assertDontSee('Manage Corporate Team');

        $this->english()->actingAs($pic)->get('/corporate')->assertOk()
            ->assertSee('Manage Corporate Team')
            ->assertSee('Contract Quota')
            ->assertSee('PT Bahasa Test')
            ->assertDontSee('Kelola Tim Corporate');
    }

    /** Setiap kunci terjemahan yang dipanggil di view halaman-halaman ini harus ada di lang/id dan lang/en (tidak ada salah ketik). */
    public function test_every_translation_key_used_in_these_views_exists_in_both_languages(): void
    {
        $views = [
            'customer/membership.blade.php', 'customer/my-club.blade.php', 'customer/coaching.blade.php',
            'customer/corporate.blade.php', 'customer/promo-claim.blade.php', 'customer/invoice.blade.php',
            'customer/partials/member-pass.blade.php',
        ];
        $views = array_merge($views, array_map(fn ($p) => 'customer/invoice-partials/'.basename($p), glob(resource_path('views/customer/invoice-partials/*.blade.php'))));

        $keys = [];
        foreach ($views as $view) {
            preg_match_all("/(?:__|trans_choice)\\('((?:membership|myclub|coaching|corporate|promo|invoice)\\.[a-z0-9_.]+)'/", file_get_contents(resource_path('views/'.$view)), $m);
            $keys = array_merge($keys, $m[1]);
        }
        $keys = array_unique(array_map(fn ($k) => rtrim($k, '.'), $keys));
        $this->assertGreaterThan(100, count($keys));

        foreach ($keys as $key) {
            foreach (['id', 'en'] as $locale) {
                $this->assertTrue(Lang::has($key, $locale, false), "Kunci {$key} belum ada di lang/{$locale}");
            }
        }
    }
}
