<?php

namespace Tests\Feature\Staff;

use App\Filament\Resources\Staff\Pages\ManageGuides;
use App\Models\Audit\ActivityLog;
use App\Models\Staff\Guide;
use App\Models\Staff\GuideAccessLog;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use App\Services\Staff\GuidePdfRenderer;
use App\Services\Staff\GuideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Panduan karyawan: PDF → gambar halaman di server (file asli tidak disimpan / tidak dikirim), dibaca lewat link tanpa
 * login yang bisa dinonaktifkan & dibuat ulang (seperti Digital Signage), setiap halaman ber-watermark, pembukaan dicatat.
 */
class GuideTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        Storage::fake(Guide::DISK);
        $this->admin = User::factory()->admin()->create(['is_active' => true]);

        // pdftoppm tidak ada di mesin test: renderer palsu membuat 2 halaman JPEG sungguhan.
        $this->app->instance(GuidePdfRenderer::class, new class extends GuidePdfRenderer
        {
            public function render(string $pdfPath, string $outputDir): int
            {
                foreach ([1, 2] as $page) {
                    $image = imagecreatetruecolor(400, 560);
                    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
                    imagejpeg($image, $outputDir.'/'.$page.'.jpg', 90);
                    imagedestroy($image);
                }

                return 2;
            }
        });
    }

    private function pdf(string $name = 'modul-resepsionis.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%contoh\n");
    }

    /** Panduan lewat service (seperti tombol Tambah): unggahan disimpan dulu ke disk private. */
    private function guide(bool $active = true): Guide
    {
        $path = $this->pdf()->store('guides/uploads', Guide::DISK);
        $guide = app(GuideService::class)->create(['title' => 'Panduan Resepsionis', 'version' => 'v1.0'], $path, 'modul.pdf', $this->admin);
        if ($active) {
            app(GuideService::class)->setActive($guide, true);
        }

        return $guide->fresh();
    }

    public function test_admin_uploads_a_pdf_that_becomes_inactive_page_images_and_the_pdf_is_not_kept(): void
    {
        Livewire::actingAs($this->admin)->test(ManageGuides::class)
            ->callAction('create', ['title' => 'Panduan Resepsionis', 'version' => 'v1.0', 'file' => $this->pdf()])
            ->assertHasNoActionErrors();

        $guide = Guide::sole();
        $this->assertFalse($guide->is_active, 'panduan baru nonaktif dulu');
        $this->assertSame(2, $guide->pages_count);
        $this->assertSame('modul-resepsionis.pdf', $guide->source_name);
        $this->assertSame(Guide::TOKEN_LENGTH, strlen($guide->token));
        $this->assertSame([], Storage::disk(Guide::DISK)->allFiles('guides/uploads'), 'PDF asli tidak disimpan');
        Storage::disk(Guide::DISK)->assertExists($guide->pagePath(1));

        $this->get($guide->shareUrl())->assertNotFound()->assertSee('Panduan tidak tersedia');
    }

    public function test_active_link_shows_watermarked_pages_without_login_and_records_the_visit(): void
    {
        $guide = $this->guide();

        $this->get($guide->shareUrl())->assertOk()
            ->assertSee('Panduan Resepsionis')->assertSee('v1.0')
            ->assertSee(route('guide.page', ['token' => $guide->token, 'page' => 2]), false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('X-Frame-Options', 'DENY');

        $page = $this->get(route('guide.page', ['token' => $guide->token, 'page' => 1]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        $this->assertNotSame(Storage::disk(Guide::DISK)->get($guide->pagePath(1)), $page->getContent(), 'watermark menempel di gambar');
        $this->assertNotFalse(getimagesizefromstring($page->getContent()));

        $this->get(route('guide.page', ['token' => $guide->token, 'page' => 3]))->assertNotFound();
        $this->get(route('guide.page', ['token' => $guide->token, 'page' => 0]))->assertNotFound();

        // Muat ulang dari IP yang sama dalam 10 menit tidak dicatat dobel.
        $this->get($guide->shareUrl())->assertOk();
        $this->assertSame(1, GuideAccessLog::where('guide_id', $guide->id)->count());
    }

    public function test_deactivating_or_regenerating_kills_the_old_link_immediately(): void
    {
        $guide = $this->guide();
        $old = $guide->token;
        $pageUrl = route('guide.page', ['token' => $old, 'page' => 1]);
        $this->get(route('guide.status', $old))->assertOk();

        Livewire::actingAs($this->admin)->test(ManageGuides::class)->callTableAction('toggle', $guide);
        $this->assertFalse($guide->fresh()->is_active);
        $this->get(route('guide.show', $old))->assertNotFound();
        $this->get($pageUrl)->assertNotFound();
        $this->get(route('guide.status', $old))->assertNotFound();

        // Diaktifkan lagi → link yang sama hidup lagi.
        Livewire::actingAs($this->admin)->test(ManageGuides::class)->callTableAction('toggle', $guide);
        $this->get(route('guide.show', $old))->assertOk();

        // Buat ulang link → link lama mati permanen, link baru jalan; token tidak masuk Log Aktivitas.
        Livewire::actingAs($this->admin)->test(ManageGuides::class)->callTableAction('regenerate', $guide);
        $new = $guide->fresh()->token;
        $this->assertNotSame($old, $new);
        $this->get(route('guide.show', $old))->assertNotFound();
        $this->get($pageUrl)->assertNotFound();
        $this->get(route('guide.show', $new))->assertOk();
        $this->assertTrue(ActivityLog::where('event', 'guide.link_regenerated')->exists());
        $this->assertStringNotContainsString($new, ActivityLog::all()->toJson());
        $this->assertStringNotContainsString($old, ActivityLog::all()->toJson());
    }

    public function test_non_pdf_files_are_rejected_and_nothing_is_kept(): void
    {
        Livewire::actingAs($this->admin)->test(ManageGuides::class)
            ->callAction('create', ['title' => 'Salah file', 'file' => UploadedFile::fake()->create('slide.pptx', 20, 'application/vnd.openxmlformats-officedocument.presentationml.presentation')])
            ->assertHasActionErrors(['file']);

        // Nama .pdf tapi isinya bukan PDF → ditolak dari isi file.
        $path = UploadedFile::fake()->createWithContent('palsu.pdf', 'bukan pdf')->store('guides/uploads', Guide::DISK);
        try {
            app(GuideService::class)->create(['title' => 'Palsu'], $path, 'palsu.pdf', $this->admin);
            $this->fail('File bukan PDF harus ditolak.');
        } catch (HttpException $e) {
            $this->assertStringContainsString('File bukan PDF', $e->getMessage());
        }

        $this->assertSame(0, Guide::count());
        $this->assertSame([], Storage::disk(Guide::DISK)->allFiles());
    }

    public function test_server_without_poppler_gives_a_clear_message_and_saves_nothing(): void
    {
        $this->app->instance(GuidePdfRenderer::class, new class extends GuidePdfRenderer
        {
            protected function binary(): ?string
            {
                return null;
            }
        });

        Livewire::actingAs($this->admin)->test(ManageGuides::class)
            ->callAction('create', ['title' => 'Panduan Resepsionis', 'file' => $this->pdf()])
            ->assertNotified('Panduan belum bisa disimpan');

        $this->assertSame(0, Guide::count());
        $this->assertSame([], Storage::disk(Guide::DISK)->allFiles());
    }

    public function test_replacing_the_pdf_keeps_the_link_and_removes_old_pages(): void
    {
        $guide = $this->guide();
        $oldDir = $guide->pages_path;

        Livewire::actingAs($this->admin)->test(ManageGuides::class)
            ->callTableAction('edit', $guide, ['title' => 'Panduan Resepsionis v2', 'file' => $this->pdf('modul-v2.pdf')])
            ->assertHasNoTableActionErrors();

        $guide->refresh();
        $this->assertSame('Panduan Resepsionis v2', $guide->title);
        $this->assertSame('modul-v2.pdf', $guide->source_name);
        $this->assertNotSame($oldDir, $guide->pages_path);
        $this->assertFalse(Storage::disk(Guide::DISK)->exists($oldDir.'/1.jpg'));
        $this->assertTrue($guide->is_active);
        $this->get($guide->shareUrl())->assertOk();
    }

    public function test_deleting_a_guide_removes_its_pages_and_kills_the_link(): void
    {
        $guide = $this->guide();
        $dir = $guide->pages_path;

        Livewire::actingAs($this->admin)->test(ManageGuides::class)->callTableAction('delete', $guide);

        $this->assertNull(Guide::find($guide->id));
        $this->assertSame([], Storage::disk(Guide::DISK)->allFiles($dir));
        $this->get(route('guide.show', $guide->token))->assertNotFound();
    }

    public function test_admin_preview_works_while_inactive_and_needs_the_permission(): void
    {
        $guide = $this->guide(active: false);

        $this->actingAs($this->admin)->get(route('guide.preview', $guide))->assertOk()->assertSee('Pratinjau admin')->assertSee('NONAKTIF');
        $this->actingAs($this->admin)->get(route('guide.preview.page', ['guide' => $guide, 'page' => 1]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs(User::factory()->receptionist()->create(['is_active' => true]))->get(route('guide.preview', $guide))->assertForbidden();
    }

    public function test_only_staff_with_manage_guides_open_the_admin_page(): void
    {
        $guide = $this->guide();

        $this->actingAs($this->admin)->get('/admin/panduan')->assertOk()->assertSee('Panduan Resepsionis');
        Livewire::actingAs($this->admin)->test(ManageGuides::class)
            ->mountTableAction('link', $guide)->assertMountedActionModalSee($guide->shareUrl());

        $this->actingAs(User::factory()->receptionist()->create(['is_active' => true]))->get('/admin/panduan')->assertDenied();
        $this->assertContains('manage_guides', Club61PermissionMatrix::defaultRolePermissions('admin'));
        $this->assertNotContains('manage_guides', Club61PermissionMatrix::defaultRolePermissions('receptionist'));
    }
}
