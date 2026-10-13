<?php

namespace Tests\Feature\Signature;

use App\Filament\Pages\Signature;
use App\Models\Signature\SignatureContent;
use App\Models\Signature\SignaturePlaylistItem;
use App\Models\Signature\SignatureScreen;
use App\Models\Signature\SignatureSponsor;
use App\Models\Signature\SignatureVersion;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use App\Services\Signature\SignatureFileJanitor;
use App\Services\Signature\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * File Digital Signage: yang masih tayang tidak pernah dihapus; yang tidak tayang di mana pun langsung dihapus; yang
 * baru berhenti tayang ditahan 7 hari lalu disapu otomatis. Dulu file langsung dihapus walau TV masih menayangkannya.
 */
class SignatureFileJanitorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected SignatureScreen $screen;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        Storage::fake('public');
        $this->admin = User::factory()->superAdmin()->create();
        $this->screen = app(SignatureService::class)->ensureDefaults();
    }

    private function upload(string $name, string $path): SignatureContent
    {
        Storage::disk('public')->put($path, str_repeat('x', 1024));

        return SignatureContent::create(['name' => $name, 'type' => 'VIDEO', 'file_path' => $path, 'duration_seconds' => 10]);
    }

    private function putOnScreen(SignatureContent $content): void
    {
        SignaturePlaylistItem::create(['template_id' => $this->screen->template_id, 'zone_key' => 'main', 'content_id' => $content->id, 'sort_order' => 99]);
    }

    private function publish(): SignatureVersion
    {
        return app(SignatureService::class)->publish($this->screen->fresh(), $this->admin);
    }

    private function sweep(): array
    {
        return app(SignatureFileJanitor::class)->sweep();
    }

    public function test_file_that_never_aired_is_deleted_right_away(): void
    {
        $promo = $this->upload('Promo', 'signature/videos/promo.mp4');
        $promo->delete();
        Storage::disk('public')->assertMissing('signature/videos/promo.mp4');

        $other = $this->upload('Lainnya', 'signature/videos/v1.mp4');
        Storage::disk('public')->put('signature/videos/v2.mp4', 'baru');
        $other->update(['file_path' => 'signature/videos/v2.mp4']);
        Storage::disk('public')->assertMissing('signature/videos/v1.mp4');
        Storage::disk('public')->assertExists('signature/videos/v2.mp4');
    }

    public function test_airing_file_is_kept_until_it_has_not_aired_for_seven_days(): void
    {
        $promo = $this->upload('Promo', 'signature/videos/promo.mp4');
        $this->putOnScreen($promo);
        $this->publish();

        // Dihapus dari library saat masih tayang → TV tetap bisa memutarnya.
        $promo->delete();
        Storage::disk('public')->assertExists('signature/videos/promo.mp4');
        $this->assertStringContainsString('signature/videos/promo.mp4', json_encode($this->getJson("/signature/{$this->screen->token}/payload")->json('playlists.main'), JSON_UNESCAPED_SLASHES));
        $this->sweep();
        Storage::disk('public')->assertExists('signature/videos/promo.mp4'); // masih tayang → tidak pernah disapu

        // Tayangkan versi baru tanpa video itu → berhenti tayang, ditahan 7 hari.
        $this->publish();
        $this->travel(6)->days();
        $this->sweep();
        Storage::disk('public')->assertExists('signature/videos/promo.mp4');

        $this->travel(2)->days();
        $this->assertSame(1, $this->sweep()['deleted']);
        Storage::disk('public')->assertMissing('signature/videos/promo.mp4');
    }

    public function test_sweep_keeps_library_files_and_fresh_uploads(): void
    {
        $this->upload('Di library, belum pernah tayang', 'signature/videos/library.mp4');
        Storage::disk('public')->put('signature/sponsors/logo.png', 'logo');
        SignatureSponsor::create(['kind' => 'BRAND', 'name' => 'Brand', 'logo_path' => 'signature/sponsors/logo.png']);
        Storage::disk('public')->put('signature/videos/baru-diupload.mp4', 'belum tersimpan di database');
        Storage::disk('public')->put('lain/bukan-signature.txt', 'jangan disentuh');

        $this->sweep();
        Storage::disk('public')->assertExists('signature/videos/baru-diupload.mp4'); // form belum selesai disimpan

        $this->travel(30)->days();
        $this->sweep();
        Storage::disk('public')->assertExists('signature/videos/library.mp4');
        Storage::disk('public')->assertExists('signature/sponsors/logo.png');
        Storage::disk('public')->assertExists('lain/bukan-signature.txt');
        Storage::disk('public')->assertMissing('signature/videos/baru-diupload.mp4'); // sisa upload yang tidak pernah disimpan
    }

    public function test_restoring_a_version_whose_file_was_cleaned_is_refused(): void
    {
        $promo = $this->upload('Promo', 'signature/videos/promo.mp4');
        $this->putOnScreen($promo);
        $first = $this->publish();
        $promo->delete();
        $this->publish();
        $this->travel(8)->days();
        $this->sweep();

        $this->actingAs($this->admin);
        Livewire::test(Signature::class)
            ->call('restoreVersion', $first->id)
            ->assertNotified("Versi {$first->version} tidak bisa dikembalikan");

        $this->assertSame(2, SignatureVersion::where('screen_id', $this->screen->id)->count(), 'tidak ada versi baru dengan slide kosong');
    }

    public function test_cleaning_command_logs_what_it_freed(): void
    {
        $promo = $this->upload('Promo', 'signature/videos/promo.mp4');
        $this->putOnScreen($promo);
        $this->publish();
        $promo->delete();
        $this->publish();
        $this->travel(8)->days();

        $this->artisan('signature:clean-files')->expectsOutputToContain('1 file dihapus')->assertSuccessful();
        $this->assertSame(1, \App\Models\Audit\ActivityLog::where('event', 'signature.files_cleaned')->count());
    }
}
