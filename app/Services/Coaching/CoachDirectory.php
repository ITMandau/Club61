<?php

namespace App\Services\Coaching;

use App\Models\Coaching\Coach;
use App\Models\Coaching\CoachingSetting;

/**
 * Daftar profil coach untuk customer (halaman Coaching portal & API aplikasi) — Modul 24 Opsi B Tahap 2. Informasi
 * saja: customer tidak booking coach sendiri, tombolnya WhatsApp admin coaching. Sengaja hanya kolom publik — no. WA,
 * email, catatan internal coach tidak pernah ikut.
 */
class CoachDirectory
{
    public const WHATSAPP_MESSAGE = 'Halo Admin Coaching Club 61, saya mau tanya jadwal & paket coaching.';

    /**
     * @param  bool  $absoluteUrls  true untuk API (aplikasi butuh URL lengkap), false untuk halaman web (URL relatif).
     * @return list<array{id: string, name: string, title: string, title_label: string, sport: string, academy: ?string, photo_url: ?string, motto: ?string, initials: string, highlights: list<string>}>
     */
    public function coaches(bool $absoluteUrls = false): array
    {
        return Coach::query()->publicProfile()->with('academy:id,name')
            ->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Coach $coach) => [
                'id' => $coach->id,
                'name' => $coach->name,
                'title' => $coach->title,
                'title_label' => $coach->titleLabel(),
                'sport' => $coach->sport ?: 'Padel',
                'academy' => $coach->academy?->name,
                'photo_url' => ($path = $coach->photoUrl()) ? ($absoluteUrls ? url($path) : $path) : null,
                'motto' => Coach::cleanMotto($coach->motto),
                'initials' => $coach->initials(),
                'highlights' => $coach->highlightList(),
            ])->values()->all();
    }

    public function adminWhatsappUrl(?string $coachName = null): ?string
    {
        return CoachingSetting::adminWhatsappUrl($coachName
            ? "Halo Admin Coaching Club 61, saya mau tanya jadwal & paket coaching bersama Coach {$coachName}."
            : self::WHATSAPP_MESSAGE);
    }
}
