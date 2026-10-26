<?php

namespace Tests\Feature\Auth;

use App\Filament\Resources\Coaching\CoachingRateResource;
use App\Models\Audit\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use App\Services\Permission\HomeRouteResolver;
use App\Support\StaffAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Izin staf dicabut / tidak ada saat membuka halaman: dipindah ke halaman yang masih boleh + notifikasi (bukan 403),
 * tamu & customer tetap 403, tidak ada redirect berulang, dan sidik jari hak akses berubah saat role diubah.
 */
class StaffAccessRevokedTest extends TestCase
{
    use RefreshDatabase;

    protected User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        $this->receptionist = User::factory()->receptionist()->create(['is_active' => true]);
    }

    private function revoke(string $permission): void
    {
        Role::findByName('receptionist', 'web')->revokePermissionTo($permission);
        $this->receptionist->forgetCachedPermissions();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_staff_opening_a_page_without_permission_is_moved_to_an_allowed_page(): void
    {
        $this->actingAs($this->receptionist);
        $landing = HomeRouteResolver::resolve($this->receptionist);
        $this->assertNotSame(HomeRouteResolver::CUSTOMER, $landing);

        $this->get(CoachingRateResource::getUrl('index'))
            ->assertRedirect($landing)
            ->assertSessionHas('filament.notifications');

        $this->assertTrue(ActivityLog::where('event', 'auth.access_denied')->exists(), 'percobaan tetap tercatat');
    }

    public function test_permission_revoked_while_the_page_is_open_redirects_on_the_next_visit(): void
    {
        Role::findByName('receptionist', 'web')->givePermissionTo('manage_coaching');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $url = CoachingRateResource::getUrl('index');

        $this->actingAs($this->receptionist)->get($url)->assertOk();

        $this->revoke('manage_coaching');
        $this->actingAs($fresh = $this->receptionist->fresh()); // request berikutnya memuat izin terbaru

        $this->get($url)->assertRedirect(HomeRouteResolver::resolve($fresh));
    }

    public function test_guest_customer_api_and_livewire_requests_still_get_403_or_login(): void
    {
        $customer = User::factory()->create();
        $url = CoachingRateResource::getUrl('index');

        $this->get($url)->assertRedirect('/login');
        $this->actingAs($customer)->get('/pos')->assertForbidden();

        $this->actingAs($this->receptionist);
        $this->getJson($url)->assertForbidden();
        $this->withHeader('X-Livewire', '1')->get($url)->assertForbidden();
    }

    public function test_no_redirect_loop_when_the_landing_page_itself_is_denied(): void
    {
        $kitchen = User::factory()->kitchen()->create(['is_active' => true]);
        Role::findByName('kitchen', 'web')->revokePermissionTo('view_kitchen_kds');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Paling banyak satu kali dialihkan, lalu berhenti di 403 (halaman pendaratan yang sama ditolak tidak dialihkan lagi).
        $this->actingAs($kitchen);
        $first = $this->get('/kitchen');
        if ($first->isRedirect()) {
            $this->get($first->headers->get('Location'))->assertForbidden();
        } else {
            $first->assertForbidden();
        }
    }

    public function test_silent_probe_is_redirected_and_logged_without_a_notification(): void
    {
        $this->actingAs($this->receptionist)
            ->withHeader('X-Club61-Access-Probe', '1')
            ->get(CoachingRateResource::getUrl('index'))
            ->assertRedirect(HomeRouteResolver::resolve($this->receptionist))
            ->assertSessionMissing('filament.notifications');

        $this->assertSame(1, ActivityLog::where('event', 'auth.access_denied')->count());
    }

    public function test_access_version_changes_when_the_role_changes(): void
    {
        $this->get(route('staff.access-version'))->assertRedirect('/login');

        $before = $this->actingAs($this->receptionist)->getJson(route('staff.access-version'))->assertOk()->json('v');
        $this->assertSame(StaffAccess::version($this->receptionist), $before);

        $this->revoke('checkin_padel_ticket');
        $this->actingAs($this->receptionist->fresh());

        $after = $this->getJson(route('staff.access-version'))->json('v');
        $this->assertNotSame($before, $after);
    }

    public function test_admin_panel_and_pos_screens_load_the_access_watcher(): void
    {
        $this->actingAs($this->receptionist);

        $this->get(HomeRouteResolver::resolve($this->receptionist))
            ->assertOk()
            ->assertSee('club61AccessWatch', false)
            ->assertSee('akses\/versi', false);
    }
}
