<?php

namespace App\Models\Coaching;

use App\Services\Coaching\CoachingPricingService;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Aturan tarif setoran coaching: berlaku untuk slot yang JAM MULAI-nya di [start_time, end_time) pada hari `days`
 * (ISO 1=Senin … 7=Minggu). Beberapa aturan cocok → prioritas terkecil dipakai (CoachingPricingService).
 */
class CoachingRate extends Model
{
    use HasUlids;

    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    protected $fillable = ['name', 'days', 'start_time', 'end_time', 'price_per_hour', 'priority', 'is_active'];

    protected function casts(): array
    {
        return ['days' => 'array', 'price_per_hour' => 'decimal:2', 'priority' => 'integer', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(CoachingPricingService::class)->flush());
        static::deleted(fn () => app(CoachingPricingService::class)->flush());
    }

    /** "Sen, Sel, …" — atau "Setiap hari". */
    public function daysLabel(): string
    {
        $days = array_map('intval', (array) $this->days);
        sort($days);

        return $days === [1, 2, 3, 4, 5, 6, 7] ? 'Setiap hari' : implode(', ', array_map(fn ($d) => mb_substr(self::DAYS[$d] ?? '?', 0, 3), $days));
    }
}
