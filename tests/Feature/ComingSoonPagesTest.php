<?php

namespace Tests\Feature;

use App\Filament\Pages\DaftarVoucher;
use App\Filament\Pages\KelolaClub;
use App\Filament\Pages\KelolaKaryawan;
use App\Filament\Pages\KelolaTurnamen;
use App\Filament\Pages\Marketing;
use App\Filament\Pages\MasterData;
use App\Models\Role;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman yang modulnya belum dibangun tampil sebagai "Segera Hadir" / "Coming soon" — bukan data contoh yang
 * tertulis di kode (coach, kode promo, turnamen, fasilitas karangan).
 */
class ComingSoonPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
    }

    public function test_unbuilt_admin_pages_show_coming_soon_instead_of_dummy_data(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $dummy = [
            KelolaClub::class => ['4 Padel Arena', 'Finnish Cedar Sauna', 'WiFi 300 Mbps'],
            KelolaKaryawan::class => ['Coach Budi Santoso', 'Siti Hair Stylist', 'Komisi 15%'],
            KelolaTurnamen::class => ['Club 61 Open Championship 2026', 'Rp 25.000.000', '24 / 32 Pasang'],
            Marketing::class => ['HAPPYHOUR', 'WELLNESSPACK', 'MEMBERGETMEMBER', 'Terpakai: 48 / 100'],
        ];

        foreach ($dummy as $page => $texts) {
            $this->assertSame('Segera', $page::getNavigationBadge(), $page);

            $response = $this->get($page::getUrl())->assertOk()->assertSee('Segera Hadir')->assertSee('Yang direncanakan');
            foreach ($texts as $text) {
                $response->assertDontSee($text);
            }
        }

        // Link ke halaman yang sudah jalan.
        $this->get(KelolaClub::getUrl())->assertSee(MasterData::getUrl(), false);
        $this->get(Marketing::getUrl())->assertSee(DaftarVoucher::getUrl(), false);
    }

    public function test_links_only_show_pages_the_staff_can_open(): void
    {
        $role = Role::create(['name' => 'marketing_only', 'guard_name' => 'web']);
        $role->syncPermissions(['View:Marketing']);
        $staff = User::factory()->create();
        $staff->syncRoles([$role]);

        $this->actingAs($staff)->get(Marketing::getUrl())->assertOk()
            ->assertSee('Segera Hadir')
            ->assertDontSee(DaftarVoucher::getUrl(), false)
            ->assertDontSee('Sudah bisa dipakai sekarang');
    }

    public function test_customer_home_marks_unbuilt_features_as_coming_soon(): void
    {
        $this->actingAs(User::factory()->customer()->create())->get('/dashboard')->assertOk()
            ->assertSee('data-coming-soon="value-pack"', false)
            ->assertSee('data-coming-soon="tournaments"', false)
            ->assertSee('Value Pack — coming soon')
            ->assertSee('Tournaments — coming soon')
            ->assertDontSee('Prize pool Rp 50.000.000')
            ->assertDontSee('2 Hours complimentary Sauna')
            ->assertDontSee('Diamond Club');
    }
}
