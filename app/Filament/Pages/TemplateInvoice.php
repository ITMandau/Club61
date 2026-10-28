<?php

namespace App\Filament\Pages;

use App\Models\Setting\InvoiceTemplateSetting;
use App\Services\Audit\ActivityLogger;
use App\Services\Mail\InvoicePdf;
use App\Services\Media\SecureImageUploader;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Keuangan → Template Invoice: logo, identitas usaha, judul & kalimat PDF invoice lunas yang dilampirkan di email
 * customer (App\Services\Mail\InvoicePdf). Pratinjau memakai template yang sama persis dengan PDF; kolom kosong =
 * teks bawaan (InvoiceTemplateSetting::DEFAULTS / Konten Website).
 */
class TemplateInvoice extends Page
{
    use HasPageShield;
    use WithFileUploads;

    public const MANAGE = 'manage_invoice_template';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Template Invoice';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Template Invoice';

    protected static ?string $slug = 'template-invoice';

    protected string $view = 'filament.pages.template-invoice';

    /** Isian (kosong = bawaan). */
    public array $form = [
        'business_name' => '', 'address' => '', 'phone' => '', 'email' => '', 'website' => '', 'tax_id' => '',
        'document_title' => '', 'status_label' => '', 'intro_text' => '', 'notes_text' => '', 'footer_text' => '',
        'accent' => 'terra',
    ];

    public $logoUpload = null;

    public bool $removeLogo = false;

    public function mount(): void
    {
        $this->fillFromSaved();
    }

    public function getCanManageProperty(): bool
    {
        return (bool) auth()->user()?->can(self::MANAGE);
    }

    /** Teks bawaan per kolom — ditampilkan sebagai placeholder supaya admin tahu apa yang dipakai kalau dikosongkan. */
    public function getDefaultsProperty(): array
    {
        return (new InvoiceTemplateSetting)->resolved();
    }

    public function getHasCustomLogoProperty(): bool
    {
        return ! $this->removeLogo && (bool) InvoiceTemplateSetting::current()->logo_path;
    }

    /** HTML pratinjau = template PDF yang sama, dengan isian yang belum disimpan. */
    public function getPreviewHtmlProperty(): string
    {
        return InvoicePdf::html(InvoicePdf::sample(), $this->draft(), $this->draftLogo());
    }

    public function updatedLogoUpload(): void
    {
        $this->resetErrorBag('logoUpload');
        $this->validateOnly('logoUpload', $this->logoRules());
        $this->removeLogo = false;
    }

    /** Batalkan logo baru / logo unggahan → logo bawaan (baru berlaku setelah Simpan). */
    public function useDefaultLogo(): void
    {
        $this->logoUpload = null;
        $this->removeLogo = true;
        $this->resetErrorBag('logoUpload');
    }

    public function save(): void
    {
        abort_unless($this->canManage, 403, 'Akses ditolak: Anda tidak memiliki izin ['.self::MANAGE.'] untuk mengubah template invoice.');

        $this->validate($this->rules());

        $setting = InvoiceTemplateSetting::firstOrNew(['id' => 1]);
        $setting->fill($this->cleanForm());

        if ($this->logoUpload) {
            $setting->logo_path = SecureImageUploader::storePng($this->logoUpload, 'invoice-template');
        } elseif ($this->removeLogo) {
            $setting->logo_path = null;
        }

        $setting->save();

        $this->logoUpload = null;
        $this->removeLogo = false;
        $this->fillFromSaved();

        Notification::make()->title('Template invoice disimpan')->body('Invoice lunas berikutnya yang dikirim ke email customer memakai template ini.')->success()->send();
    }

    public function resetToDefaults(): void
    {
        abort_unless($this->canManage, 403, 'Akses ditolak: Anda tidak memiliki izin ['.self::MANAGE.'] untuk mengubah template invoice.');

        $setting = InvoiceTemplateSetting::firstOrNew(['id' => 1]);
        $setting->fill(array_fill_keys(array_keys($this->form), null) + ['logo_path' => null]);
        $setting->save();

        $this->logoUpload = null;
        $this->removeLogo = false;
        $this->fillFromSaved();

        Notification::make()->title('Template dikembalikan ke bawaan')->success()->send();
    }

    /** Contoh PDF dari isian saat ini (sebelum disimpan pun bisa dicek). */
    public function downloadSample(): StreamedResponse
    {
        $this->validate($this->rules());

        ActivityLogger::record(
            module: 'FINANCE',
            event: 'invoice_template.sample_downloaded',
            description: 'Mengunduh contoh PDF Template Invoice',
        );

        $pdf = InvoicePdf::output(InvoicePdf::sample(), $this->draft(), $this->draftLogo());

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, 'Contoh-Invoice-Club61.pdf', ['Content-Type' => 'application/pdf']);
    }

    private function fillFromSaved(): void
    {
        $saved = InvoiceTemplateSetting::current();
        foreach (array_keys($this->form) as $key) {
            $this->form[$key] = (string) ($saved->{$key} ?? '');
        }
        if (! array_key_exists($this->form['accent'], InvoiceTemplateSetting::ACCENTS)) {
            $this->form['accent'] = InvoiceTemplateSetting::DEFAULTS['accent'];
        }
    }

    /** @return array<string, ?string> */
    private function cleanForm(): array
    {
        $clean = [];
        foreach ($this->form as $key => $value) {
            $value = trim((string) $value);
            $clean[$key] = $value === '' ? null : $value;
        }

        return $clean;
    }

    private function draft(): InvoiceTemplateSetting
    {
        $saved = InvoiceTemplateSetting::current();
        $draft = new InvoiceTemplateSetting($this->cleanForm());
        $draft->logo_path = $this->removeLogo ? null : $saved->logo_path;

        return $draft;
    }

    /** Logo yang baru dipilih (belum disimpan) untuk pratinjau — hanya kalau benar-benar gambar. */
    private function draftLogo(): ?string
    {
        if (! $this->logoUpload || $this->getErrorBag()->has('logoUpload')) {
            return null;
        }

        $path = $this->logoUpload->getRealPath();
        $info = @getimagesize($path);
        if ($info === false || ! in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$info['mime'].';base64,'.base64_encode((string) file_get_contents($path));
    }

    private function logoRules(): array
    {
        return ['logoUpload' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048']];
    }

    protected function rules(): array
    {
        return $this->logoRules() + [
            'form.business_name' => ['nullable', 'string', 'max:120'],
            'form.address' => ['nullable', 'string', 'max:255'],
            'form.phone' => ['nullable', 'string', 'max:40'],
            'form.email' => ['nullable', 'email', 'max:120'],
            'form.website' => ['nullable', 'string', 'max:120'],
            'form.tax_id' => ['nullable', 'string', 'max:40'],
            'form.document_title' => ['nullable', 'string', 'max:40'],
            'form.status_label' => ['nullable', 'string', 'max:30'],
            'form.intro_text' => ['nullable', 'string', 'max:600'],
            'form.notes_text' => ['nullable', 'string', 'max:1200'],
            'form.footer_text' => ['nullable', 'string', 'max:255'],
            'form.accent' => ['required', 'in:'.implode(',', array_keys(InvoiceTemplateSetting::ACCENTS))],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'logoUpload' => 'logo',
            'form.business_name' => 'nama usaha',
            'form.address' => 'alamat',
            'form.phone' => 'telepon',
            'form.email' => 'email',
            'form.website' => 'website',
            'form.tax_id' => 'NPWP',
            'form.document_title' => 'judul dokumen',
            'form.status_label' => 'label status',
            'form.intro_text' => 'kalimat pembuka',
            'form.notes_text' => 'catatan',
            'form.footer_text' => 'kalimat penutup',
            'form.accent' => 'warna aksen',
        ];
    }
}
