<?php

namespace App\Services\Signature;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Signature\SignatureContent;
use App\Models\Signature\SignatureRunningText;
use App\Models\Signature\SignatureScreen;
use App\Models\Signature\SignatureSponsor;
use App\Models\Signature\SignatureTemplate;
use App\Models\Signature\SignatureVersion;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Modul 26 — Signature (layar TV venue).
 *
 * - Yang diedit admin (template, konten, running text, sponsor, pengaturan layar) = DRAFT.
 * - buildPayload() merangkum draft (+ perubahan form yang belum disimpan, untuk Preview) jadi satu JSON tampilan.
 * - publish() menyimpan JSON itu sebagai versi baru; TV hanya menayangkan versi yang ditunjuk layar.
 * - liveData() = data yang berubah terus (monitoring lapangan, jadwal, slot kosong, notifikasi, running text otomatis),
 *   diambil TV tiap ±12 detik. Hanya data yang tampil di layar — tanpa No. HP, email, atau nominal.
 */
class SignatureService
{
    public const TIMEZONE = 'Asia/Jakarta';

    /** Preset dari sketsa owner. Posisi zona dalam persen kanvas 16:9. */
    public const LAYOUTS = [
        'PRESET_A' => [
            'label' => 'A — Konten + Panel Samping + Running Text',
            'zones' => [
                ['key' => 'main', 'kind' => 'playlist', 'x' => 0, 'y' => 0, 'w' => 75, 'h' => 91],
                ['key' => 'side', 'kind' => 'side_panel', 'x' => 75, 'y' => 0, 'w' => 25, 'h' => 91],
                ['key' => 'ticker', 'kind' => 'ticker', 'x' => 0, 'y' => 91, 'w' => 100, 'h' => 9],
            ],
        ],
        'PRESET_B' => [
            'label' => 'B — Full Slider',
            'zones' => [
                ['key' => 'main', 'kind' => 'playlist', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 100],
            ],
        ],
        'PRESET_C' => [
            'label' => 'C — Full + Running Text',
            'zones' => [
                ['key' => 'main', 'kind' => 'playlist', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 91],
                ['key' => 'ticker', 'kind' => 'ticker', 'x' => 0, 'y' => 91, 'w' => 100, 'h' => 9],
            ],
        ],
    ];

    /** Font Google yang terbaca dari jauh (dimuat di halaman TV). */
    public const FONTS = ['Archivo', 'Source Serif 4', 'Montserrat', 'Poppins', 'Bebas Neue', 'JetBrains Mono'];

    public const DEFAULT_THEME = [
        'background' => '#1E120F',
        'panel' => '#2A1A16',
        'accent' => '#662721',
        'text' => '#F7F0DB',
        'font' => 'Archivo',
    ];

    public const DEFAULT_TICKER = [
        'font' => 'Archivo',
        'font_size' => 44,
        'speed' => 120,
        'direction' => 'left',
        'background' => '#662721',
        'color' => '#F7F0DB',
        'separator' => '•',
    ];

    /** Widget sistem yang selalu tersedia di library konten. */
    private const DEFAULT_WIDGETS = [
        'MONITORING' => ['Monitoring Lapangan', 20],
        'SCHEDULE' => ['Jadwal Hari Ini', 15],
        'SPONSOR' => ['Sponsor', 12],
        'CLOCK' => ['Jam & Tanggal', 10],
    ];

    /** Lama notifikasi booking / check-in dianggap "baru" (detik). */
    public const EVENT_WINDOW = 180;

    // ================================================================== DATA AWAL

    /** Layar utama + 3 template preset + widget + running text otomatis. Aman dipanggil berulang. */
    public function ensureDefaults(): SignatureScreen
    {
        return DB::transaction(function () {
            foreach (self::DEFAULT_WIDGETS as $type => [$name, $duration]) {
                SignatureContent::firstOrCreate(['type' => $type], ['name' => $name, 'duration_seconds' => $duration, 'is_active' => true]);
            }

            if (! SignatureTemplate::exists()) {
                $monitoring = SignatureContent::where('type', 'MONITORING')->first();
                $schedule = SignatureContent::where('type', 'SCHEDULE')->first();
                foreach (['PRESET_A' => 'Monitoring + Panel', 'PRESET_B' => 'Full Slider', 'PRESET_C' => 'Full + Running Text'] as $layout => $name) {
                    $template = SignatureTemplate::create(['name' => $name, 'layout' => $layout, 'theme' => self::DEFAULT_THEME, 'ticker' => self::DEFAULT_TICKER]);
                    $template->playlistItems()->create(['zone_key' => 'main', 'content_id' => $monitoring->id, 'sort_order' => 1]);
                    $template->playlistItems()->create(['zone_key' => 'main', 'content_id' => $schedule->id, 'sort_order' => 2]);
                }
            }

            foreach (SignatureRunningText::DEFAULT_TEXTS as $kind => $text) {
                SignatureRunningText::firstOrCreate(['kind' => $kind], ['text' => $text, 'is_active' => true, 'sort_order' => 100]);
            }

            return SignatureScreen::orderBy('created_at')->first() ?? SignatureScreen::create([
                'name' => 'TV Lobby',
                'token' => $this->newToken(),
                'template_id' => SignatureTemplate::where('layout', 'PRESET_A')->value('id'),
                'name_display' => 'FULL',
                'sound_enabled' => true,
                'operating_hours_only' => true,
            ]);
        });
    }

    public function regenerateToken(SignatureScreen $screen, User $user): string
    {
        $screen->update(['token' => $this->newToken()]);
        ActivityLogger::record(module: 'SIGNATURE', event: 'signature.link_regenerated', description: "Link layar {$screen->name} dibuat ulang (link lama tidak berlaku)", subject: $screen, severity: ActivityLogger::WARNING, causer: $user);

        return $screen->token;
    }

    private function newToken(): string
    {
        return Str::lower(Str::random(48));
    }

    // ================================================================== TAMPILAN (PAYLOAD)

    /**
     * Rangkum tampilan layar jadi satu JSON untuk TV / Preview.
     *
     * @param  array|null  $draft  perubahan yang belum disimpan (Preview): ['screen' => [...], 'template' => [...], 'playlist' => [['content_id', 'duration']]]
     */
    public function buildPayload(SignatureScreen $screen, ?array $draft = null): array
    {
        $screenAttrs = array_merge($screen->only(['name', 'name_display', 'sound_enabled', 'operating_hours_only', 'template_id']), $draft['screen'] ?? []);

        $templateModel = SignatureTemplate::with('playlistItems.content')->find($draft['template']['id'] ?? $screenAttrs['template_id'])
            ?? SignatureTemplate::with('playlistItems.content')->orderBy('created_at')->first();
        $template = array_merge(
            $templateModel ? $templateModel->only(['id', 'name', 'layout', 'zones', 'theme', 'ticker', 'logo_path']) : ['name' => '-', 'layout' => 'PRESET_B'],
            array_filter($draft['template'] ?? [], fn ($v) => $v !== null)
        );

        $layout = isset(self::LAYOUTS[$template['layout'] ?? '']) ? $template['layout'] : 'PRESET_B';
        $zones = self::LAYOUTS[$layout]['zones'];

        if (isset($draft['playlist'])) {
            $contents = SignatureContent::whereIn('id', array_column($draft['playlist'], 'content_id'))->get()->keyBy('id');
            $items = collect($draft['playlist'])->map(fn ($row) => ($c = $contents[$row['content_id']] ?? null) ? [$c, $row['duration'] ?? null] : null)->filter();
        } else {
            $items = collect($templateModel?->playlistItems ?? [])->filter(fn ($i) => $i->content)->map(fn ($i) => [$i->content, $i->duration_seconds]);
        }

        $logoUrl = $template['logo_url'] ?? (! empty($template['logo_path']) ? $this->fileUrl($template['logo_path']) : '/images/identity/logo-cream.png');

        return [
            'generated_at' => now()->toIso8601String(),
            'screen' => [
                'name' => $screenAttrs['name'],
                'name_display' => $screenAttrs['name_display'],
                'sound_enabled' => (bool) $screenAttrs['sound_enabled'],
                'operating_hours_only' => (bool) $screenAttrs['operating_hours_only'],
            ],
            'template' => [
                'name' => $template['name'] ?? '-',
                'layout' => $layout,
                'zones' => $zones,
                'theme' => array_merge(self::DEFAULT_THEME, array_filter((array) ($template['theme'] ?? []), fn ($v) => $v !== null && $v !== '')),
                'ticker' => array_merge(self::DEFAULT_TICKER, array_filter((array) ($template['ticker'] ?? []), fn ($v) => $v !== null && $v !== '')),
                'logo_url' => $logoUrl,
            ],
            'playlists' => [
                'main' => $items->filter(fn ($pair) => $pair[0]->is_active)->map(fn ($pair) => $this->contentPayload($pair[0], $pair[1]))->values()->all(),
            ],
            'running_texts' => SignatureRunningText::where('is_active', true)->orderBy('sort_order')->orderBy('created_at')->get(['kind', 'text'])->map(fn ($t) => ['kind' => $t->kind, 'text' => $t->text])->all(),
            'sponsors' => SignatureSponsor::with('organization:id,name')->orderBy('sort_order')->orderBy('created_at')->get()
                ->filter(fn (SignatureSponsor $s) => $s->isShowing() && $s->displayName() !== '')
                ->map(fn (SignatureSponsor $s) => [
                    'name' => $s->displayName(),
                    'logo_url' => $s->logo_path ? $this->fileUrl($s->logo_path) : null,
                    'duration' => max(3, (int) $s->duration_seconds),
                ])->values()->all(),
            'hours' => $this->operatingHours(),
        ];
    }

    private function contentPayload(SignatureContent $content, ?int $duration): array
    {
        return [
            'id' => $content->id,
            'type' => $content->type,
            'name' => $content->name,
            'url' => $content->file_path ? $this->fileUrl($content->file_path) : null,
            'duration' => max(3, (int) ($duration ?: $content->duration_seconds)),
            'sound' => $content->type === 'VIDEO' && $content->sound,
            'volume' => max(0, min(100, (int) $content->volume)),
        ];
    }

    /**
     * URL file upload TANPA nama host (/storage/...): snapshot yang ditayangkan tetap jalan walau APP_URL berbeda dari
     * alamat yang dibuka TV (mis. IP lokal vs domain).
     */
    private function fileUrl(string $path): string
    {
        $url = Storage::disk('public')->url($path);

        return parse_url($url, PHP_URL_PATH) ?: $url;
    }

    /** Jam buka paling awal & tutup paling akhir dari lapangan aktif. */
    private function operatingHours(): array
    {
        $courts = PadelCourt::where('is_active', true)->get(['open_time', 'close_time']);

        return [
            'open' => $courts->pluck('open_time')->filter()->min() ?: '06:00',
            'close' => $courts->pluck('close_time')->filter()->max() ?: '23:00',
        ];
    }

    // ================================================================== TAYANGKAN & VERSI

    public function publish(SignatureScreen $screen, User $user, ?string $note = null): SignatureVersion
    {
        return $this->storeVersion($screen, $this->buildPayload($screen), $user, $note ?: 'Tayangkan');
    }

    /** Tayangkan ulang isi versi lama sebagai versi baru (riwayat tetap utuh). */
    public function restore(SignatureVersion $version, User $user): SignatureVersion
    {
        return $this->storeVersion($version->screen, $version->payload, $user, "Kembalikan ke versi {$version->version}");
    }

    private function storeVersion(SignatureScreen $screen, array $payload, User $user, string $note): SignatureVersion
    {
        return DB::transaction(function () use ($screen, $payload, $user, $note) {
            $screen = SignatureScreen::whereKey($screen->id)->lockForUpdate()->firstOrFail();
            $number = (int) SignatureVersion::where('screen_id', $screen->id)->max('version') + 1;
            $payload['version'] = $number;

            $version = SignatureVersion::create([
                'screen_id' => $screen->id,
                'version' => $number,
                'payload' => $payload,
                'note' => mb_substr($note, 0, 120),
                'published_by_id' => $user->id,
                'created_at' => now(),
            ]);
            $screen->update(['published_version_id' => $version->id]);

            ActivityLogger::record(
                module: 'SIGNATURE',
                event: 'signature.published',
                description: "Layar {$screen->name} menayangkan versi {$number} ({$note})",
                subject: $screen,
                meta: ['versi' => $number, 'template' => $payload['template']['name'] ?? '-', 'jumlah_konten' => count($payload['playlists']['main'] ?? [])],
                causer: $user,
            );

            return $version;
        });
    }

    // ================================================================== PREVIEW

    /** Simpan tampilan sementara untuk Preview (belum ditayangkan) → kunci acak untuk halaman preview. */
    public function storePreview(array $payload): string
    {
        $key = Str::lower(Str::random(40));
        $payload['version'] = 'preview';
        Cache::put("signature:preview:{$key}", $payload, now()->addMinutes(30));

        return $key;
    }

    public function previewPayload(string $key): ?array
    {
        return Cache::get("signature:preview:{$key}");
    }

    // ================================================================== DATA LIVE

    /**
     * Data yang berubah terus untuk TV: status tiap lapangan, jadwal hari ini, slot kosong, notifikasi booking / check-in,
     * dan running text (manual + otomatis). Nama customer mengikuti pengaturan layar.
     */
    public function liveData(array $payload, ?SignatureScreen $screen = null): array
    {
        $now = now();
        $wib = now(self::TIMEZONE);
        $today = $wib->toDateString();
        $nameDisplay = $payload['screen']['name_display'] ?? 'FULL';
        $texts = collect($payload['running_texts'] ?? []);
        $autoText = fn (string $kind) => $texts->firstWhere('kind', $kind)['text'] ?? null;

        $courts = PadelCourt::where('is_active', true)->orderBy('name')->get();
        $bookings = PadelBooking::with('user:id,name')
            ->whereDate('booking_date', $today)
            ->whereIn('status', ['PAID', 'CHECKED_IN', 'COMPLETED'])
            ->orderBy('start_time')
            ->get()
            ->groupBy('court_id');

        $dayStart = Carbon::parse($today);
        $courtRows = [];
        $schedule = [];
        $freeSlots = [];
        foreach ($courts as $court) {
            /** @var Collection $list */
            $list = $bookings[$court->id] ?? collect();
            $live = $list->where('status', '!=', 'COMPLETED');
            $current = $live->first(fn ($b) => $b->start_time->lte($now) && $b->end_time->gt($now));
            $next = $live->first(fn ($b) => $b->start_time->gt($now));

            $courtRows[] = [
                'name' => $court->name,
                'status' => $current ? (($current->status === 'CHECKED_IN' || $current->checked_in_at) ? 'PLAYING' : 'WAITING') : 'FREE',
                'current' => $current ? [
                    'name' => $this->displayName($current->user?->name, $nameDisplay),
                    'start' => $current->start_time->format('H:i'),
                    'end' => $current->end_time->format('H:i'),
                    'remaining_seconds' => (int) max(0, $now->diffInSeconds($current->end_time, false)),
                ] : null,
                'next' => $next ? [
                    'name' => $this->displayName($next->user?->name, $nameDisplay),
                    'start' => $next->start_time->format('H:i'),
                    'end' => $next->end_time->format('H:i'),
                ] : null,
            ];

            $open = (int) substr($court->open_time ?: '06:00', 0, 2);
            $close = $court->close_time === '24:00' ? 24 : (int) substr($court->close_time ?: '23:00', 0, 2);
            $hours = [];
            for ($h = $open; $h < $close; $h++) {
                $slotStart = Carbon::parse("{$today} ".sprintf('%02d:00:00', $h));
                $slotEnd = $slotStart->copy()->addHour();
                $taken = $list->first(fn ($b) => $b->start_time->lt($slotEnd) && $b->end_time->gt($slotStart));
                $state = $taken ? 'BOOKED' : ($slotEnd->lte($now) ? 'PAST' : 'FREE');
                $hours[] = ['time' => sprintf('%02d:00', $h), 'state' => $state];
                if ($state === 'FREE' && $slotStart->gte($now)) {
                    $freeSlots[] = "{$court->name} ".sprintf('%02d:00', $h);
                }
            }
            $schedule[] = [
                'name' => $court->name,
                'open_minutes' => $open * 60,
                'close_minutes' => $close * 60,
                'hours' => $hours,
                // Blok booking untuk tampilan timeline: nama sesuai pengaturan layar, menit dari 00:00 hari ini.
                'bookings' => $list->map(fn ($b) => [
                    'name' => $this->displayName($b->user?->name, $nameDisplay),
                    'start' => $b->start_time->format('H:i'),
                    'end' => $b->end_time->format('H:i'),
                    'start_minutes' => (int) max(0, $dayStart->diffInMinutes($b->start_time, false)),
                    'end_minutes' => (int) min(24 * 60, $dayStart->diffInMinutes($b->end_time, false)),
                    'status' => $b->status === 'COMPLETED' || $b->end_time->lte($now) ? 'DONE'
                        : ($b->start_time->lte($now) ? (($b->status === 'CHECKED_IN' || $b->checked_in_at) ? 'PLAYING' : 'WAITING') : 'UPCOMING'),
                ])->values()->all(),
            ];
        }

        $events = $this->recentEvents($now, $nameDisplay, $autoText('AUTO_CHECKIN'), $autoText('AUTO_BOOKING'));

        $ticker = collect($events)->filter(fn ($e) => $e['age_seconds'] <= 600)->pluck('text')->all();
        foreach ($texts as $text) {
            if ($text['kind'] === 'MANUAL') {
                $ticker[] = $text['text'];
            } elseif ($text['kind'] === 'AUTO_SLOTS' && $freeSlots) {
                $ticker[] = str_replace('{daftar_slot}', implode(', ', array_slice($freeSlots, 0, 8)), $text['text']);
            } elseif ($text['kind'] === 'AUTO_SPONSOR' && ! empty($payload['sponsors'])) {
                $ticker[] = str_replace('{sponsor}', implode(', ', array_column($payload['sponsors'], 'name')), $text['text']);
            }
        }

        $hours = $payload['hours'] ?? ['open' => '06:00', 'close' => '23:00'];
        $clock = $wib->format('H:i');

        return [
            'server_time' => $wib->toIso8601String(),
            'version' => $screen?->publishedVersion?->version ?? ($payload['version'] ?? null),
            'is_open' => ! ($payload['screen']['operating_hours_only'] ?? true) || ($clock >= $hours['open'] && ($hours['close'] === '24:00' || $clock < $hours['close'])),
            'courts' => $courtRows,
            'schedule' => $schedule,
            // Posisi garis "sekarang" di timeline, satu basis waktu dengan jam booking.
            'now_minutes' => (int) $dayStart->diffInMinutes($now, false),
            'events' => array_values(array_filter($events, fn ($e) => $e['age_seconds'] <= self::EVENT_WINDOW)),
            'ticker' => array_values(array_filter($ticker, fn ($t) => trim((string) $t) !== '')),
        ];
    }

    /** Booking baru (baru lunas) & check-in beberapa menit terakhir, dengan kalimat dari running text otomatis. */
    private function recentEvents(Carbon $now, string $nameDisplay, ?string $checkinText, ?string $bookingText): array
    {
        $since = $now->copy()->subMinutes(10);
        $events = [];

        if ($checkinText) {
            foreach (PadelBooking::with(['user:id,name', 'court:id,name'])->where('checked_in_at', '>=', $since)->orderByDesc('checked_in_at')->limit(10)->get() as $b) {
                $events[] = $this->event('CHECKIN', $b, $b->checked_in_at, $now, $checkinText, $nameDisplay);
            }
        }
        if ($bookingText) {
            // "Booking baru" = booking yang baru jadi lunas (kasir / online), belum check-in.
            foreach (PadelBooking::with(['user:id,name', 'court:id,name'])->where('status', 'PAID')->whereNull('checked_in_at')->where('updated_at', '>=', $since)->orderByDesc('updated_at')->limit(10)->get() as $b) {
                $events[] = $this->event('BOOKING', $b, $b->updated_at, $now, $bookingText, $nameDisplay);
            }
        }

        usort($events, fn ($a, $b) => $a['age_seconds'] <=> $b['age_seconds']);

        return $events;
    }

    private function event(string $type, PadelBooking $b, Carbon $at, Carbon $now, string $template, string $nameDisplay): array
    {
        $name = $this->displayName($b->user?->name, $nameDisplay) ?? 'Customer';

        return [
            'id' => strtolower($type).':'.$b->id.':'.$at->timestamp,
            'type' => $type,
            'age_seconds' => (int) max(0, $at->diffInSeconds($now, false)),
            'text' => strtr($template, [
                '{nama}' => $name,
                '{lapangan}' => $b->court?->name ?? 'lapangan',
                '{jam}' => $b->start_time->format('H:i'),
            ]),
        ];
    }

    /** Nama customer sesuai pengaturan layar. Tidak pernah No. HP / email. */
    public function displayName(?string $name, string $mode): ?string
    {
        $name = trim((string) $name);
        if ($name === '' || $mode === 'BOOKED') {
            return null;
        }
        if ($mode === 'INITIALS') {
            return collect(preg_split('/\s+/u', preg_replace('/\([^)]*\)/u', '', $name)))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)).'.')->take(3)->implode(' ');
        }

        return $name;
    }
}
