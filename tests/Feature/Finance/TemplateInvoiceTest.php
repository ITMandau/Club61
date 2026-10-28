<?php

namespace Tests\Feature\Finance;

use App\Filament\Pages\TemplateInvoice;
use App\Mail\OrderInvoiceMail;
use App\Models\Setting\InvoiceTemplateSetting;
use App\Models\User;
use App\Services\Mail\InvoicePdf;
use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Keuangan → Template Invoice: isian & logo dipakai PDF invoice email (A4), kosong = bawaan, izin terpisah untuk
 * melihat vs mengubah, logo PNG tetap transparan & logo lama dibuang, teks admin tidak bisa menyisipkan HTML.
 */
class TemplateInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create(['is_active' => true]);
    }

    private function page()
    {
        $this->actingAs($this->admin);

        return Livewire::test(TemplateInvoice::class);
    }

    public function test_admin_opens_the_page_and_sees_the_default_template_preview(): void
    {
        $this->actingAs($this->admin)->get(TemplateInvoice::getUrl())->assertOk()->assertSee('Template Invoice');

        $html = $this->page()->instance()->previewHtml;
        $this->assertStringContainsString('Club 61 Padel &amp; Wellness', $html);
        $this->assertStringContainsString(InvoiceTemplateSetting::DEFAULTS['footer_text'], $html);
        $this->assertStringContainsString('data:image/png;base64,', $html, 'logo bawaan Club 61 tampil');
    }

    public function test_saved_texts_are_used_by_the_emailed_pdf_and_blanks_fall_back_to_defaults(): void
    {
        $this->page()
            ->set('form.document_title', 'Bukti Pembayaran')
            ->set('form.status_label', 'Paid')
            ->set('form.notes_text', "Baris satu\nBaris dua")
            ->set('form.email', 'billing@club61.test')
            ->set('form.accent', 'brown')
            ->call('save')
            ->assertHasNoErrors();

        $html = InvoicePdf::html(InvoicePdf::sample());
        $this->assertStringContainsString('Bukti Pembayaran', $html);
        $this->assertStringContainsString('Paid', $html);
        $this->assertStringContainsString('Baris satu<br />', $html);
        $this->assertStringContainsString('billing@club61.test', $html);
        $this->assertStringContainsString('#4F2F2A', $html);
        $this->assertStringContainsString(InvoiceTemplateSetting::DEFAULTS['footer_text'], $html, 'kalimat penutup kosong = bawaan');

        // Lampiran email invoice = PDF A4 dari template yang sama.
        $pdf = (new OrderInvoiceMail(InvoicePdf::sample()))->attachments()[0];
        $this->assertSame('Invoice-ORD-PAD-CONTOH-0001.pdf', $pdf->as);
        $this->assertStringStartsWith('%PDF', InvoicePdf::output(InvoicePdf::sample()));
    }

    public function test_email_body_follows_the_same_template_as_the_pdf(): void
    {
        $this->page()
            ->set('form.business_name', 'Club 61 Medan')
            ->set('form.status_label', 'Paid')
            ->set('form.footer_text', 'Penutup <b>uji</b>')
            ->set('form.email', 'billing@club61.test')
            ->set('form.accent', 'ink')
            ->call('save')
            ->assertHasNoErrors();

        $mail = new OrderInvoiceMail(InvoicePdf::sample());
        $html = $mail->render();
        $this->assertStringContainsString('#231815', $html, 'warna aksen template');
        $this->assertStringNotContainsString('#D4AF37', $html, 'warna emas lama tidak dipakai lagi');
        $this->assertStringContainsString('Club 61 Medan', $html);
        $this->assertStringContainsString('Paid', $html);
        $this->assertStringContainsString('mailto:billing@club61.test', $html);
        $this->assertStringContainsString('CONTOH-REF-7F3A21', $html, 'nomor referensi seperti di PDF');
        $this->assertStringContainsString('Penutup &lt;b&gt;uji&lt;/b&gt;', $html, 'teks admin di-escape');

        $text = view('emails.invoice.paid-text', ['invoice' => InvoicePdf::sample()])->render();
        $this->assertStringContainsString('Club 61 Medan', $text);
        $this->assertStringContainsString('Referensi   : CONTOH-REF-7F3A21', $text);
    }

    public function test_admin_text_cannot_inject_html_into_the_invoice(): void
    {
        $this->page()->set('form.notes_text', '<script>alert(1)</script><b>tebal</b>')->call('save')->assertHasNoErrors();

        $html = InvoicePdf::html(InvoicePdf::sample());
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_logo_is_stored_as_transparent_png_and_the_old_one_is_removed(): void
    {
        $this->page()->set('logoUpload', UploadedFile::fake()->image('logo.png', 600, 200))->call('save')->assertHasNoErrors();
        $first = InvoiceTemplateSetting::current()->logo_path;
        $this->assertStringEndsWith('.png', $first);
        Storage::disk('public')->assertExists($first);
        $this->assertSame('image/png', getimagesize(Storage::disk('public')->path($first))['mime']);

        $this->page()->set('logoUpload', UploadedFile::fake()->image('logo2.jpg', 400, 200))->call('save')->assertHasNoErrors();
        $second = InvoiceTemplateSetting::current()->logo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        // Kembalikan bawaan: isian & logo unggahan hilang.
        $this->page()->call('resetToDefaults');
        $this->assertNull(InvoiceTemplateSetting::current()->logo_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->page()
            ->set('form.email', 'bukan-email')
            ->set('form.accent', 'neon')
            ->call('save')
            ->assertHasErrors(['form.email', 'form.accent']);

        $this->page()
            ->set('logoUpload', UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'))
            ->assertHasErrors(['logoUpload']);

        $this->assertNull(InvoiceTemplateSetting::query()->value('email'));
    }

    public function test_sample_pdf_can_be_downloaded_before_saving(): void
    {
        $this->page()
            ->set('form.document_title', 'Draf Belum Disimpan')
            ->call('downloadSample')
            ->assertFileDownloaded('Contoh-Invoice-Club61.pdf');

        $this->assertNull(InvoiceTemplateSetting::query()->value('document_title'), 'unduh contoh tidak menyimpan');
    }

    public function test_view_only_role_can_preview_but_not_change(): void
    {
        $viewer = User::factory()->cashier()->create(['is_active' => true]);
        $viewer->givePermissionTo('View:TemplateInvoice');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($viewer);

        Livewire::test(TemplateInvoice::class)
            ->assertDontSee('Simpan Template')
            ->set('form.document_title', 'Diubah kasir')
            ->call('save')
            ->assertForbidden();

        $this->assertNull(InvoiceTemplateSetting::query()->value('document_title'));
    }

    public function test_cashier_without_permission_cannot_open_the_page(): void
    {
        $this->actingAs(User::factory()->cashier()->create(['is_active' => true]))
            ->get(TemplateInvoice::getUrl())
            ->assertDenied();

        $this->assertContains('manage_invoice_template', Club61PermissionMatrix::defaultRolePermissions('admin'));
        $this->assertNotContains('manage_invoice_template', Club61PermissionMatrix::defaultRolePermissions('cashier'));
    }

    public function test_invoices_still_render_with_defaults_before_the_migration_runs(): void
    {
        Schema::drop('invoice_template_settings');
        \Illuminate\Support\Facades\Cache::forget(InvoiceTemplateSetting::CACHE_KEY);

        $html = InvoicePdf::html(InvoicePdf::sample());
        $this->assertStringContainsString('Invoice', $html);
        $this->assertStringContainsString(InvoiceTemplateSetting::DEFAULTS['footer_text'], $html);
    }
}
