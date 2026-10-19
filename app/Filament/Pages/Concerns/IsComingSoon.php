<?php

namespace App\Filament\Pages\Concerns;

use Illuminate\Contracts\Support\Htmlable;

/**
 * Halaman admin yang modulnya belum dibangun: tampil sebagai "Segera Hadir" (partial c61-coming-soon) + label
 * "Segera" di menu. Dulu halaman-halaman ini menampilkan data contoh yang tertulis di kode seolah data asli.
 *
 * Halaman memakai trait ini wajib mengisi comingSoon(): eyebrow, sub, planned, links. Link hanya tampil kalau
 * staf punya akses ke halaman tujuannya.
 */
trait IsComingSoon
{
    /**
     * @return array{eyebrow: string, sub: string, planned: list<string>, links: list<array{label: string, sub: string, page: class-string}>}
     */
    abstract protected static function comingSoon(): array;

    public static function getNavigationBadge(): ?string
    {
        return 'Segera';
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'gray';
    }

    public static function getNavigationBadgeTooltip(): string | Htmlable | null
    {
        return 'Modul belum dibangun';
    }

    public function getView(): string
    {
        return 'filament.partials.c61-coming-soon';
    }

    protected function getViewData(): array
    {
        $data = static::comingSoon();

        $data['title'] = $this->getTitle();
        $data['links'] = collect($data['links'])
            ->filter(fn (array $link) => $link['page']::canAccess())
            ->map(fn (array $link) => ['label' => $link['label'], 'sub' => $link['sub'], 'url' => $link['page']::getUrl()])
            ->values()
            ->all();

        return $data;
    }
}
