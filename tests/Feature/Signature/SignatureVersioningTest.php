<?php

namespace Tests\Feature\Signature;

use App\Filament\Pages\Signature;
use App\Models\Signature\SignatureContent;
use App\Models\Signature\SignaturePlaylistItem;
use App\Models\Signature\SignatureScreen;
use App\Models\Signature\SignatureTemplate;
use App\Models\Signature\SignatureVersion;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use App\Services\Signature\PublishResult;
use App\Services\Signature\SignatureFileJanitor;
use App\Services\Signature\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Riwayat versi Digital Signage: versi baru hanya dibuat kalau tampilannya berubah, Kembalikan memindahkan tanda
 * Tayang (bukan menyalin), dan riwayat dibatasi. Dulu setiap klik Tayangkan / Kembalikan menambah versi.
 */
class SignatureVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected SignatureScreen $screen;

    protected SignatureTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        Storage::fake('public');
        $this->admin = User::factory()->superAdmin()->create();
        $this->screen = app(SignatureService::class)->ensureDefaults();
        $this->template = SignatureTemplate::find($this->screen->template_id);
    }

    private function publish(): PublishResult
    {
        return app(SignatureService::class)->publish($this->screen->fresh(), $this->admin);
    }

    private function airing(): SignatureVersion
    {
        return $this->screen->fresh()->publishedVersion;
    }

    public function test_publishing_without_changes_does_not_create_a_version(): void
    {
        $this->assertSame(PublishResult::CREATED, $this->publish()->outcome);
        $this->assertSame(PublishResult::UNCHANGED, $this->publish()->outcome);
        $this->assertSame(PublishResult::UNCHANGED, $this->publish()->outcome);
        $this->assertSame(1, SignatureVersion::count());

        $this->actingAs($this->admin);
        Livewire::test(Signature::class)->call('publish')->assertNotified('Tidak ada perubahan');
        $this->assertSame(1, SignatureVersion::count());
    }

    public function test_going_back_to_an_old_layout_reuses_that_version(): void
    {
        $this->publish();                                        // v1
        $this->template->update(['name' => 'Promo Malam']);
        $this->assertSame(PublishResult::CREATED, $this->publish()->outcome); // v2

        // Draft dikembalikan seperti semula lalu Tayangkan → versi 1 tayang lagi, tidak ada v3.
        $this->template->update(['name' => 'Monitoring + Panel']);
        $result = $this->publish();
        $this->assertSame(PublishResult::REUSED, $result->outcome);
        $this->assertSame(1, $result->version->version);
        $this->assertSame(2, SignatureVersion::count());
        $this->assertSame(1, $this->airing()->version);
        $this->assertNotNull(SignatureVersion::where('version', 2)->sole()->aired_until, 'v2 tercatat berhenti tayang');
        $this->assertNull($this->airing()->aired_until);

        // TV ikut berganti (nomor versi di data live berubah → TV memuat ulang).
        $this->assertSame(1, $this->getJson("/signature/{$this->screen->token}/live")->json('version'));
    }

    public function test_restore_moves_the_on_air_mark_instead_of_copying(): void
    {
        $this->publish();
        $this->template->update(['name' => 'Versi Dua']);
        $this->publish();
        $first = SignatureVersion::where('version', 1)->sole();

        $this->travel(5)->minutes();
        $this->actingAs($this->admin);
        $page = Livewire::test(Signature::class)->call('restoreVersion', $first->id)->assertNotified('Versi 1 ditayangkan lagi');

        $this->assertSame(2, SignatureVersion::count());
        $this->assertSame($first->id, $this->airing()->id);
        $this->assertTrue($this->airing()->aired_at->gt($first->created_at), 'waktu mulai tayang lagi dicatat');
        $this->assertSame($this->admin->id, $this->airing()->aired_by_id);

        $page->call('restoreVersion', $first->id)->assertNotified('Versi 1 sudah tayang');
        $this->assertSame(2, SignatureVersion::count());
    }

    public function test_history_keeps_the_latest_versions_and_never_drops_the_one_on_air(): void
    {
        foreach (range(1, SignatureService::MAX_VERSIONS + 2) as $i) {
            $this->template->update(['name' => "Tampilan {$i}"]);
            $this->publish();
        }
        $this->assertSame(SignatureService::MAX_VERSIONS, SignatureVersion::count());
        $this->assertSame(3, SignatureVersion::min('version'), 'v1 & v2 terlama dibuang');
        $this->assertSame(SignatureService::MAX_VERSIONS + 2, $this->airing()->version, 'yang tayang = versi terbaru, tidak ikut dibuang');

        // Nomor versi tetap naik (tidak dipakai ulang setelah versi lama dibuang).
        $this->template->update(['name' => 'Tampilan baru']);
        $this->assertSame(SignatureService::MAX_VERSIONS + 3, $this->publish()->version->version);
    }

    public function test_migration_fills_airing_times_for_existing_history(): void
    {
        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_10_14_100001_add_airing_to_signature_versions.php'])->assertSuccessful();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('signature_versions', 'aired_until'));

        // Riwayat gaya lama: v1, v2, v3 (v3 tayang), tiap versi tayang sampai versi berikutnya dibuat.
        $times = [now()->subDays(3), now()->subDays(2), now()->subDay()];
        $ids = [];
        foreach ($times as $i => $time) {
            $ids[] = $id = strtolower((string) \Illuminate\Support\Str::ulid());
            \Illuminate\Support\Facades\DB::table('signature_versions')->insert([
                'id' => $id, 'screen_id' => $this->screen->id, 'version' => $i + 1, 'payload' => '{}', 'note' => 'Tayangkan',
                'published_by_id' => $this->admin->id, 'created_at' => $time,
            ]);
        }
        \Illuminate\Support\Facades\DB::table('signature_screens')->where('id', $this->screen->id)->update(['published_version_id' => $ids[2]]);

        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_14_100001_add_airing_to_signature_versions.php'])->assertSuccessful();

        $rows = SignatureVersion::orderBy('version')->get()->keyBy('version');
        $this->assertEquals($times[1]->toDateTimeString(), $rows[1]->aired_until->toDateTimeString(), 'v1 berhenti saat v2 dibuat');
        $this->assertEquals($times[2]->toDateTimeString(), $rows[2]->aired_until->toDateTimeString());
        $this->assertNull($rows[3]->aired_until, 'v3 masih tayang');
        $this->assertEquals($times[0]->toDateTimeString(), $rows[1]->aired_at->toDateTimeString());
        $this->assertSame($this->admin->id, $rows[3]->aired_by_id);
    }

    public function test_restored_away_version_keeps_its_files_for_seven_days_after_it_stopped_airing(): void
    {
        Storage::disk('public')->put('signature/videos/promo.mp4', 'video');
        $promo = SignatureContent::create(['name' => 'Promo', 'type' => 'VIDEO', 'file_path' => 'signature/videos/promo.mp4', 'duration_seconds' => 10]);
        SignaturePlaylistItem::create(['template_id' => $this->template->id, 'zone_key' => 'main', 'content_id' => $promo->id, 'sort_order' => 9]);

        $this->publish();                                        // v1 berisi promo
        $promo->delete();
        $this->publish();                                        // v2 tanpa promo
        $withPromo = SignatureVersion::where('version', 1)->sole();

        // v1 tayang lagi 10 hari kemudian, lalu diganti → filenya dihitung dari saat berhenti tayang, bukan saat dibuat.
        $this->travel(10)->days();
        app(SignatureService::class)->restore($withPromo, $this->admin);
        $this->travel(1)->days();
        app(SignatureService::class)->restore(SignatureVersion::where('version', 2)->sole(), $this->admin);

        $this->travel(6)->days();
        app(SignatureFileJanitor::class)->sweep();
        Storage::disk('public')->assertExists('signature/videos/promo.mp4');

        $this->travel(2)->days();
        app(SignatureFileJanitor::class)->sweep();
        Storage::disk('public')->assertMissing('signature/videos/promo.mp4');
    }
}
