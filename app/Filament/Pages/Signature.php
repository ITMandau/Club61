<?php

namespace App\Filament\Pages;

use App\Models\Signature\SignatureContent;
use App\Models\Signature\SignaturePlaylistItem;
use App\Models\Signature\SignatureRunningText;
use App\Models\Signature\SignatureScreen;
use App\Models\Signature\SignatureSponsor;
use App\Models\Signature\SignatureTemplate;
use App\Models\Signature\SignatureVersion;
use App\Models\Sponsor\SponsorOrganization;
use App\Services\Media\SecureImageUploader;
use App\Services\Signature\SignatureService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;
use UnitEnum;

/**
 * Modul 26 — Signature: atur layar TV venue (template, konten, running text, sponsor). Semua perubahan = draft;
 * Preview menampilkan hasilnya (termasuk yang belum disimpan); Tayangkan mengirim versi baru ke TV.
 */
class Signature extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tv';

    protected static ?string $navigationLabel = 'Digital Signage';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing & Event';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Signature — Layar TV';

    protected string $view = 'filament.pages.signature';

    public const TABS = ['screen' => 'Layar & Tayangan', 'templates' => 'Template', 'contents' => 'Konten', 'texts' => 'Running Text', 'sponsors' => 'Sponsor'];

    public string $activeTab = 'screen';

    /** URL halaman Preview yang sedang dibuka (modal). */
    public ?string $previewUrl = null;

    // --- Layar ---
    public ?string $screenId = null;

    public string $screenName = '';

    public ?string $screenTemplateId = null;

    public string $screenNameDisplay = 'FULL';

    public bool $screenSound = true;

    public bool $screenHoursOnly = true;

    // --- Template ---
    public bool $showTemplateModal = false;

    public ?string $editingTemplateId = null;

    public string $tplName = '';

    public string $tplLayout = 'PRESET_A';

    public array $tplTheme = [];

    public array $tplTicker = [];

    public $tplLogoUpload = null;

    public ?string $tplLogoPath = null;

    public bool $tplRemoveLogo = false;

    /** @var array<int, array{content_id: string, duration: int|string|null}> */
    public array $tplPlaylist = [];

    public string $tplAddContentId = '';

    // --- Konten ---
    public bool $showContentModal = false;

    public ?string $editingContentId = null;

    public string $contentName = '';

    public string $contentType = 'IMAGE';

    public $contentUpload = null;

    public ?string $contentFilePath = null;

    public int|string $contentDuration = 15;

    public bool $contentSound = false;

    public int|string $contentVolume = 80;

    public bool $contentActive = true;

    // --- Running text ---
    public bool $showTextModal = false;

    public ?string $editingTextId = null;

    public string $textKind = 'MANUAL';

    public string $textBody = '';

    public bool $textActive = true;

    // --- Sponsor ---
    public bool $showSponsorModal = false;

    public ?string $editingSponsorId = null;

    public string $sponsorKind = 'BRAND';

    public string $sponsorName = '';

    public ?string $sponsorOrgId = null;

    public $sponsorLogoUpload = null;

    public ?string $sponsorLogoPath = null;

    public int|string $sponsorDuration = 8;

    public bool $sponsorActive = true;

    public ?string $sponsorStarts = null;

    public ?string $sponsorEnds = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:Signature');
    }

    public function mount(): void
    {
        $this->loadScreen(app(SignatureService::class)->ensureDefaults());
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = array_key_exists($tab, self::TABS) ? $tab : 'screen';
    }

    // ================================================================== LAYAR & TAYANGAN

    protected function loadScreen(SignatureScreen $screen): void
    {
        $this->screenId = $screen->id;
        $this->screenName = $screen->name;
        $this->screenTemplateId = $screen->template_id;
        $this->screenNameDisplay = $screen->name_display;
        $this->screenSound = $screen->sound_enabled;
        $this->screenHoursOnly = $screen->operating_hours_only;
    }

    public function getScreenProperty(): SignatureScreen
    {
        return SignatureScreen::with(['template', 'publishedVersion.publishedBy'])->findOrFail($this->screenId);
    }

    public function getPlayerUrlProperty(): string
    {
        return route('signature.player', SignatureScreen::whereKey($this->screenId)->value('token'));
    }

    public function getVersionsProperty()
    {
        return SignatureVersion::with('publishedBy:id,name')->where('screen_id', $this->screenId)->orderByDesc('version')->limit(10)->get(['id', 'screen_id', 'version', 'note', 'published_by_id', 'created_at']);
    }

    public function saveScreen(bool $notify = true): bool
    {
        $this->authorizeManage();
        $this->validate([
            'screenName' => ['required', 'string', 'max:80'],
            'screenTemplateId' => ['required', 'exists:signature_templates,id'],
            'screenNameDisplay' => ['required', Rule::in(array_keys(SignatureScreen::NAME_DISPLAYS))],
        ], ['screenTemplateId.required' => 'Pilih template yang ditayangkan.']);

        $this->screen->update([
            'name' => trim($this->screenName),
            'template_id' => $this->screenTemplateId,
            'name_display' => $this->screenNameDisplay,
            'sound_enabled' => $this->screenSound,
            'operating_hours_only' => $this->screenHoursOnly,
        ]);

        if ($notify) {
            Notification::make()->title('Pengaturan layar disimpan')->body('Belum tampil di TV — tekan Tayangkan untuk mengirim ke TV.')->success()->send();
        }

        return true;
    }

    public function previewScreen(): void
    {
        $this->authorizeView();
        $this->openPreview(app(SignatureService::class)->buildPayload($this->screen, ['screen' => [
            'name' => $this->screenName,
            'template_id' => $this->screenTemplateId,
            'name_display' => $this->screenNameDisplay,
            'sound_enabled' => $this->screenSound,
            'operating_hours_only' => $this->screenHoursOnly,
        ]]));
    }

    public function publish(): void
    {
        $this->authorizePublish();
        $this->saveScreen(notify: false);
        $version = app(SignatureService::class)->publish($this->screen, auth()->user());

        Notification::make()->title("Versi {$version->version} ditayangkan")->body('TV mengambil tampilan baru dalam ±15 detik.')->success()->send();
    }

    public function restoreVersion(string $versionId): void
    {
        $this->authorizePublish();
        $version = SignatureVersion::where('screen_id', $this->screenId)->findOrFail($versionId);
        $new = app(SignatureService::class)->restore($version, auth()->user());

        Notification::make()->title("Versi {$version->version} ditayangkan ulang")->body("Tersimpan sebagai versi {$new->version}.")->success()->send();
    }

    public function regenerateToken(): void
    {
        $this->authorizePublish();
        app(SignatureService::class)->regenerateToken($this->screen, auth()->user());

        Notification::make()->title('Link layar dibuat ulang')->body('Link lama tidak berlaku lagi — buka link baru di TV.')->warning()->send();
    }

    // ================================================================== TEMPLATE

    public function getTemplatesProperty()
    {
        return SignatureTemplate::withCount('playlistItems')->orderBy('created_at')->get();
    }

    public function openCreateTemplate(): void
    {
        $this->authorizeManage();
        $this->resetTemplateForm();
        $this->tplName = 'Template Baru';
        $this->showTemplateModal = true;
    }

    public function openEditTemplate(string $templateId): void
    {
        $this->authorizeManage();
        $template = SignatureTemplate::with('playlistItems')->findOrFail($templateId);
        $this->resetTemplateForm();
        $this->editingTemplateId = $template->id;
        $this->tplName = $template->name;
        $this->tplLayout = isset(SignatureService::LAYOUTS[$template->layout]) ? $template->layout : 'PRESET_A';
        $this->tplTheme = array_merge(SignatureService::DEFAULT_THEME, $template->theme ?? []);
        $this->tplTicker = array_merge(SignatureService::DEFAULT_TICKER, $template->ticker ?? []);
        $this->tplLogoPath = $template->logo_path;
        $this->tplPlaylist = $template->playlistItems->where('zone_key', 'main')->map(fn ($i) => ['content_id' => $i->content_id, 'duration' => $i->duration_seconds])->values()->all();
        $this->showTemplateModal = true;
    }

    public function duplicateTemplate(string $templateId): void
    {
        $this->authorizeManage();
        $source = SignatureTemplate::with('playlistItems')->findOrFail($templateId);

        DB::transaction(function () use ($source) {
            $copy = SignatureTemplate::create([
                'name' => mb_substr($source->name.' (salinan)', 0, 80),
                'layout' => $source->layout,
                'zones' => $source->zones,
                'theme' => $source->theme,
                'ticker' => $source->ticker,
            ]);
            foreach ($source->playlistItems as $item) {
                $copy->playlistItems()->create($item->only(['zone_key', 'content_id', 'sort_order', 'duration_seconds']));
            }
        });

        Notification::make()->title('Template diduplikat')->success()->send();
    }

    public function deleteTemplate(string $templateId): void
    {
        $this->authorizeManage();
        if (SignatureScreen::where('template_id', $templateId)->exists()) {
            Notification::make()->title('Template sedang dipakai layar')->body('Pilih template lain di tab Layar & Tayangan dulu.')->danger()->send();

            return;
        }
        SignatureTemplate::findOrFail($templateId)->delete();
        Notification::make()->title('Template dihapus')->success()->send();
    }

    public function addPlaylistItem(): void
    {
        if ($this->tplAddContentId !== '' && SignatureContent::whereKey($this->tplAddContentId)->exists()) {
            $this->tplPlaylist[] = ['content_id' => $this->tplAddContentId, 'duration' => null];
        }
        $this->tplAddContentId = '';
    }

    public function movePlaylistItem(int $index, int $direction): void
    {
        $target = $index + $direction;
        if (! isset($this->tplPlaylist[$index], $this->tplPlaylist[$target])) {
            return;
        }
        [$this->tplPlaylist[$index], $this->tplPlaylist[$target]] = [$this->tplPlaylist[$target], $this->tplPlaylist[$index]];
    }

    public function removePlaylistItem(int $index): void
    {
        unset($this->tplPlaylist[$index]);
        $this->tplPlaylist = array_values($this->tplPlaylist);
    }

    public function saveTemplate(): void
    {
        $this->authorizeManage();
        $this->validateTemplate();

        DB::transaction(function () {
            $logo = $this->tplRemoveLogo ? null : $this->tplLogoPath;
            if ($this->tplLogoUpload) {
                $logo = SecureImageUploader::store($this->tplLogoUpload, 'signature/logos');
            }

            $data = [
                'name' => trim($this->tplName),
                'layout' => $this->tplLayout,
                'theme' => $this->cleanTheme(),
                'ticker' => $this->cleanTicker(),
                'logo_path' => $logo,
            ];
            $template = $this->editingTemplateId
                ? tap(SignatureTemplate::findOrFail($this->editingTemplateId))->update($data)
                : SignatureTemplate::create($data);

            $template->playlistItems()->where('zone_key', 'main')->delete();
            foreach (array_values($this->tplPlaylist) as $order => $row) {
                SignaturePlaylistItem::create([
                    'template_id' => $template->id,
                    'zone_key' => 'main',
                    'content_id' => $row['content_id'],
                    'sort_order' => $order + 1,
                    'duration_seconds' => ($row['duration'] ?? '') === '' || $row['duration'] === null ? null : (int) $row['duration'],
                ]);
            }
        });

        $this->showTemplateModal = false;
        Notification::make()->title('Template disimpan')->body('Tekan Tayangkan di tab Layar & Tayangan untuk mengirim ke TV.')->success()->send();
    }

    /** Preview template dengan isi form saat ini — termasuk yang belum disimpan. */
    public function previewTemplate(): void
    {
        $this->authorizeView();
        $this->validateTemplate();

        $template = [
            'id' => $this->editingTemplateId,
            'name' => $this->tplName,
            'layout' => $this->tplLayout,
            'theme' => $this->cleanTheme(),
            'ticker' => $this->cleanTicker(),
            'logo_path' => $this->tplRemoveLogo ? null : $this->tplLogoPath,
        ];
        if ($this->tplLogoUpload) {
            $template['logo_url'] = $this->tplLogoUpload->temporaryUrl();
        }

        $this->openPreview(app(SignatureService::class)->buildPayload($this->screen, [
            'template' => $template,
            'playlist' => array_map(fn ($row) => ['content_id' => $row['content_id'], 'duration' => ($row['duration'] ?? '') === '' ? null : $row['duration']], array_values($this->tplPlaylist)),
        ]));
    }

    public function closeTemplateModal(): void
    {
        $this->showTemplateModal = false;
    }

    protected function validateTemplate(): void
    {
        $color = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $this->validate([
            'tplName' => ['required', 'string', 'max:80'],
            'tplLayout' => ['required', Rule::in(array_keys(SignatureService::LAYOUTS))],
            'tplTheme.background' => $color,
            'tplTheme.panel' => $color,
            'tplTheme.accent' => $color,
            'tplTheme.text' => $color,
            'tplTheme.font' => ['required', Rule::in(SignatureService::FONTS)],
            'tplTicker.font' => ['required', Rule::in(SignatureService::FONTS)],
            'tplTicker.font_size' => ['required', 'integer', 'min:20', 'max:120'],
            'tplTicker.speed' => ['required', 'integer', 'min:20', 'max:600'],
            'tplTicker.direction' => ['required', Rule::in(['left', 'right'])],
            'tplTicker.background' => $color,
            'tplTicker.color' => $color,
            'tplTicker.separator' => ['nullable', 'string', 'max:5'],
            'tplLogoUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'tplPlaylist' => ['array', 'max:50'],
            'tplPlaylist.*.content_id' => ['required', 'exists:signature_contents,id'],
            'tplPlaylist.*.duration' => ['nullable', 'integer', 'min:3', 'max:3600'],
        ], [
            'tplName.required' => 'Nama template wajib diisi.',
            'tplTheme.*.regex' => 'Warna harus format #RRGGBB.',
            'tplTicker.background.regex' => 'Warna harus format #RRGGBB.',
            'tplTicker.color.regex' => 'Warna harus format #RRGGBB.',
            'tplPlaylist.*.duration.min' => 'Durasi minimal 3 detik.',
        ]);
    }

    protected function resetTemplateForm(): void
    {
        $this->resetValidation();
        $this->editingTemplateId = null;
        $this->tplName = '';
        $this->tplLayout = 'PRESET_A';
        $this->tplTheme = SignatureService::DEFAULT_THEME;
        $this->tplTicker = SignatureService::DEFAULT_TICKER;
        $this->tplLogoUpload = null;
        $this->tplLogoPath = null;
        $this->tplRemoveLogo = false;
        $this->tplPlaylist = [];
        $this->tplAddContentId = '';
    }

    protected function cleanTheme(): array
    {
        return array_intersect_key(array_merge(SignatureService::DEFAULT_THEME, $this->tplTheme), SignatureService::DEFAULT_THEME);
    }

    protected function cleanTicker(): array
    {
        $ticker = array_intersect_key(array_merge(SignatureService::DEFAULT_TICKER, $this->tplTicker), SignatureService::DEFAULT_TICKER);
        $ticker['font_size'] = (int) $ticker['font_size'];
        $ticker['speed'] = (int) $ticker['speed'];

        return $ticker;
    }

    // ================================================================== KONTEN

    public function getContentsProperty()
    {
        return SignatureContent::withCount('playlistItems')->orderByRaw("CASE WHEN type IN ('IMAGE','VIDEO') THEN 1 ELSE 0 END")->orderBy('name')->get();
    }

    public function openCreateContent(): void
    {
        $this->authorizeManage();
        $this->resetContentForm();
        $this->showContentModal = true;
    }

    public function openEditContent(string $contentId): void
    {
        $this->authorizeManage();
        $content = SignatureContent::findOrFail($contentId);
        $this->resetContentForm();
        $this->editingContentId = $content->id;
        $this->contentName = $content->name;
        $this->contentType = $content->type;
        $this->contentFilePath = $content->file_path;
        $this->contentDuration = $content->duration_seconds;
        $this->contentSound = $content->sound;
        $this->contentVolume = $content->volume;
        $this->contentActive = $content->is_active;
        $this->showContentModal = true;
    }

    public function saveContent(): void
    {
        $this->authorizeManage();
        $editing = $this->editingContentId ? SignatureContent::findOrFail($this->editingContentId) : null;
        $type = $editing?->type ?? $this->contentType;
        $needsFile = in_array($type, SignatureContent::FILE_TYPES, true) && ! $this->contentFilePath;

        $this->validate([
            'contentName' => ['required', 'string', 'max:100'],
            'contentType' => ['required', Rule::in(SignatureContent::FILE_TYPES)],
            'contentUpload' => $type === 'VIDEO'
                ? [$needsFile ? 'required' : 'nullable', 'file', 'max:204800', function ($attribute, $file, $fail) {
                    // Dicek dari isi file. Pustaka deteksi membaca MP4 sebagai video/mp4 atau application/mp4.
                    $mime = strtolower((string) $file->getMimeType());
                    $extension = strtolower((string) $file->getClientOriginalExtension());
                    if (! in_array($mime, ['video/mp4', 'application/mp4', 'video/x-m4v'], true) || ! in_array($extension, ['mp4', 'm4v'], true)) {
                        $fail('Video harus format MP4.');
                    }
                }]
                : [$type === 'IMAGE' && $needsFile ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'contentDuration' => ['required', 'integer', 'min:3', 'max:3600'],
            'contentVolume' => ['required', 'integer', 'min:0', 'max:100'],
        ], [
            'contentName.required' => 'Nama konten wajib diisi.',
            'contentUpload.required' => $type === 'VIDEO' ? 'Pilih file video MP4.' : 'Pilih file gambar.',
            'contentUpload.max' => $type === 'VIDEO' ? 'Ukuran video maksimal 200 MB.' : 'Ukuran gambar maksimal 10 MB.',
            'contentDuration.min' => 'Durasi minimal 3 detik.',
        ]);

        $path = $this->contentFilePath;
        if ($this->contentUpload) {
            $path = $type === 'VIDEO'
                ? $this->contentUpload->store('signature/videos', 'public')
                : SecureImageUploader::store($this->contentUpload, 'signature/images');
        }

        $data = [
            'name' => trim($this->contentName),
            'duration_seconds' => (int) $this->contentDuration,
            'sound' => $type === 'VIDEO' && $this->contentSound,
            'volume' => (int) $this->contentVolume,
            'is_active' => $this->contentActive,
            'file_path' => $path,
        ];
        $editing ? $editing->update($data) : SignatureContent::create($data + ['type' => $type]);

        $this->showContentModal = false;
        Notification::make()->title('Konten disimpan')->success()->send();
    }

    /** Widget sistem (monitoring, jadwal, sponsor, jam) hanya bisa diubah nama, durasi, dan aktif / nonaktif. */
    public function saveWidget(): void
    {
        $this->authorizeManage();
        $content = SignatureContent::findOrFail($this->editingContentId);
        $this->validate([
            'contentName' => ['required', 'string', 'max:100'],
            'contentDuration' => ['required', 'integer', 'min:3', 'max:3600'],
        ]);
        $content->update(['name' => trim($this->contentName), 'duration_seconds' => (int) $this->contentDuration, 'is_active' => $this->contentActive]);
        $this->showContentModal = false;
        Notification::make()->title('Widget disimpan')->success()->send();
    }

    public function deleteContent(string $contentId): void
    {
        $this->authorizeManage();
        $content = SignatureContent::findOrFail($contentId);
        if (! in_array($content->type, SignatureContent::FILE_TYPES, true)) {
            Notification::make()->title('Widget sistem tidak bisa dihapus')->body('Nonaktifkan saja kalau tidak dipakai.')->warning()->send();

            return;
        }
        $content->delete();
        Notification::make()->title('Konten dihapus')->body('Dihapus juga dari semua playlist template (draft).')->success()->send();
    }

    public function closeContentModal(): void
    {
        $this->showContentModal = false;
    }

    protected function resetContentForm(): void
    {
        $this->resetValidation();
        $this->editingContentId = null;
        $this->contentName = '';
        $this->contentType = 'IMAGE';
        $this->contentUpload = null;
        $this->contentFilePath = null;
        $this->contentDuration = 15;
        $this->contentSound = false;
        $this->contentVolume = 80;
        $this->contentActive = true;
    }

    // ================================================================== RUNNING TEXT

    public function getTextsProperty()
    {
        return SignatureRunningText::orderByRaw("CASE WHEN kind = 'MANUAL' THEN 0 ELSE 1 END")->orderBy('sort_order')->orderBy('created_at')->get();
    }

    public function openCreateText(): void
    {
        $this->authorizeManage();
        $this->resetValidation();
        $this->editingTextId = null;
        $this->textKind = 'MANUAL';
        $this->textBody = '';
        $this->textActive = true;
        $this->showTextModal = true;
    }

    public function openEditText(string $textId): void
    {
        $this->authorizeManage();
        $text = SignatureRunningText::findOrFail($textId);
        $this->resetValidation();
        $this->editingTextId = $text->id;
        $this->textKind = $text->kind;
        $this->textBody = $text->text;
        $this->textActive = $text->is_active;
        $this->showTextModal = true;
    }

    public function saveText(): void
    {
        $this->authorizeManage();
        $this->validate(['textBody' => ['required', 'string', 'max:300']], ['textBody.required' => 'Teks wajib diisi.']);

        if ($this->editingTextId) {
            SignatureRunningText::findOrFail($this->editingTextId)->update(['text' => trim($this->textBody), 'is_active' => $this->textActive]);
        } else {
            SignatureRunningText::create([
                'kind' => 'MANUAL',
                'text' => trim($this->textBody),
                'is_active' => $this->textActive,
                'sort_order' => (int) SignatureRunningText::where('kind', 'MANUAL')->max('sort_order') + 1,
            ]);
        }

        $this->showTextModal = false;
        Notification::make()->title('Running text disimpan')->success()->send();
    }

    public function toggleText(string $textId): void
    {
        $this->authorizeManage();
        $text = SignatureRunningText::findOrFail($textId);
        $text->update(['is_active' => ! $text->is_active]);
    }

    public function deleteText(string $textId): void
    {
        $this->authorizeManage();
        $text = SignatureRunningText::findOrFail($textId);
        if ($text->kind !== 'MANUAL') {
            Notification::make()->title('Teks otomatis tidak bisa dihapus')->body('Matikan saja kalau tidak dipakai.')->warning()->send();

            return;
        }
        $text->delete();
        Notification::make()->title('Running text dihapus')->success()->send();
    }

    public function closeTextModal(): void
    {
        $this->showTextModal = false;
    }

    // ================================================================== SPONSOR

    public function getSponsorsProperty()
    {
        return SignatureSponsor::with('organization:id,name')->orderBy('sort_order')->orderBy('created_at')->get();
    }

    public function getOrganizationsProperty()
    {
        return SponsorOrganization::orderBy('name')->get(['id', 'name']);
    }

    public function openCreateSponsor(): void
    {
        $this->authorizeManage();
        $this->resetSponsorForm();
        $this->showSponsorModal = true;
    }

    public function openEditSponsor(string $sponsorId): void
    {
        $this->authorizeManage();
        $sponsor = SignatureSponsor::findOrFail($sponsorId);
        $this->resetSponsorForm();
        $this->editingSponsorId = $sponsor->id;
        $this->sponsorKind = $sponsor->kind;
        $this->sponsorName = (string) $sponsor->name;
        $this->sponsorOrgId = $sponsor->sponsor_organization_id;
        $this->sponsorLogoPath = $sponsor->logo_path;
        $this->sponsorDuration = $sponsor->duration_seconds;
        $this->sponsorActive = $sponsor->is_active;
        $this->sponsorStarts = $sponsor->starts_on?->toDateString();
        $this->sponsorEnds = $sponsor->ends_on?->toDateString();
        $this->showSponsorModal = true;
    }

    public function saveSponsor(): void
    {
        $this->authorizeManage();
        $this->sponsorStarts = $this->sponsorStarts ?: null;
        $this->sponsorEnds = $this->sponsorEnds ?: null;
        $this->validate([
            'sponsorKind' => ['required', Rule::in(array_keys(SignatureSponsor::KINDS))],
            'sponsorName' => [$this->sponsorKind === 'BRAND' ? 'required' : 'nullable', 'string', 'max:100'],
            'sponsorOrgId' => [$this->sponsorKind === 'CORPORATE' ? 'required' : 'nullable', 'exists:sponsor_organizations,id'],
            'sponsorLogoUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'sponsorDuration' => ['required', 'integer', 'min:3', 'max:600'],
            'sponsorStarts' => ['nullable', 'date'],
            'sponsorEnds' => ['nullable', 'date', 'after_or_equal:sponsorStarts'],
        ], [
            'sponsorName.required' => 'Nama sponsor wajib diisi.',
            'sponsorOrgId.required' => 'Pilih perusahaan Sponsor Corporate.',
            'sponsorEnds.after_or_equal' => 'Tanggal selesai harus setelah tanggal mulai.',
        ]);

        $logo = $this->sponsorLogoPath;
        if ($this->sponsorLogoUpload) {
            $logo = SecureImageUploader::store($this->sponsorLogoUpload, 'signature/sponsors');
        }

        $data = [
            'kind' => $this->sponsorKind,
            'name' => $this->sponsorKind === 'BRAND' ? trim($this->sponsorName) : null,
            'sponsor_organization_id' => $this->sponsorKind === 'CORPORATE' ? $this->sponsorOrgId : null,
            'logo_path' => $logo,
            'duration_seconds' => (int) $this->sponsorDuration,
            'is_active' => $this->sponsorActive,
            'starts_on' => $this->sponsorStarts,
            'ends_on' => $this->sponsorEnds,
        ];
        if ($this->editingSponsorId) {
            SignatureSponsor::findOrFail($this->editingSponsorId)->update($data);
        } else {
            SignatureSponsor::create($data + ['sort_order' => (int) SignatureSponsor::max('sort_order') + 1]);
        }

        $this->showSponsorModal = false;
        Notification::make()->title('Sponsor disimpan')->success()->send();
    }

    public function deleteSponsor(string $sponsorId): void
    {
        $this->authorizeManage();
        SignatureSponsor::findOrFail($sponsorId)->delete();
        Notification::make()->title('Sponsor dihapus')->success()->send();
    }

    public function closeSponsorModal(): void
    {
        $this->showSponsorModal = false;
    }

    protected function resetSponsorForm(): void
    {
        $this->resetValidation();
        $this->editingSponsorId = null;
        $this->sponsorKind = 'BRAND';
        $this->sponsorName = '';
        $this->sponsorOrgId = null;
        $this->sponsorLogoUpload = null;
        $this->sponsorLogoPath = null;
        $this->sponsorDuration = 8;
        $this->sponsorActive = true;
        $this->sponsorStarts = null;
        $this->sponsorEnds = null;
    }

    // ================================================================== PREVIEW & AKSES

    protected function openPreview(array $payload): void
    {
        $this->previewUrl = route('signature.preview', app(SignatureService::class)->storePreview($payload));
    }

    public function closePreview(): void
    {
        $this->previewUrl = null;
    }

    public function getCanManageProperty(): bool
    {
        return (bool) auth()->user()?->can('manage_signature');
    }

    public function getCanPublishProperty(): bool
    {
        return (bool) auth()->user()?->can('publish_signature');
    }

    protected function authorizeView(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('manage_signature'), 403, 'Akses ditolak: Anda tidak memiliki izin [manage_signature].');
    }

    protected function authorizePublish(): void
    {
        abort_unless(auth()->user()?->can('publish_signature'), 403, 'Akses ditolak: Anda tidak memiliki izin [publish_signature].');
    }
}
