<?php

namespace App\Models\Staff;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu kali panduan dibuka lewat link (dicatat paling sering sekali per IP per 10 menit). Disimpan 180 hari. */
class GuideAccessLog extends Model
{
    use MassPrunable;

    public const KEEP_DAYS = 180;

    public $timestamps = false;

    protected $fillable = ['guide_id', 'ip', 'user_agent', 'viewed_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    /**
     * Perangkat yang mudah dibaca dari user agent, mis. ['HP', 'Chrome · Android'].
     *
     * @return array{0: string, 1: string} [jenis, browser · sistem]
     */
    public function device(): array
    {
        $ua = (string) $this->user_agent;
        if ($ua === '') {
            return ['Tidak diketahui', '—'];
        }

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Browser lain',
        };
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };
        $type = match (true) {
            $os === 'iPad' || ($os === 'Android' && ! str_contains($ua, 'Mobile')) => 'Tablet',
            $os === 'iPhone' || str_contains($ua, 'Mobile') => 'HP',
            default => 'Komputer',
        };

        return [$type, $os ? $browser.' · '.$os : $browser];
    }

    public function prunable(): Builder
    {
        return static::where('viewed_at', '<', now()->subDays(self::KEEP_DAYS));
    }
}
