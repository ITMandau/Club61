<?php

namespace App\Models\Coaching;

use App\Services\Coaching\CoachingPricingService;
use App\Services\Coaching\CoachingReminder;
use Illuminate\Database\Eloquent\Model;

/** Satu baris pengaturan coaching: biaya coach tambahan / jam (default Rp0) & WhatsApp admin coaching. */
class CoachingSetting extends Model
{
    protected $fillable = ['extra_coach_fee_per_hour', 'admin_whatsapp'];

    protected function casts(): array
    {
        return ['extra_coach_fee_per_hour' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Pengaturan coaching tidak boleh dihapus.'));
        static::saved(fn () => app(CoachingPricingService::class)->flush());
    }

    public static function current(): self
    {
        return self::query()->first() ?? new self(['extra_coach_fee_per_hour' => 0]);
    }

    /** Tautan wa.me admin coaching (profil coach & link bio); null kalau nomor belum diisi / tidak valid. */
    public static function adminWhatsappUrl(?string $message = null): ?string
    {
        $digits = CoachingReminder::waDigits(rescue(fn () => self::current()->admin_whatsapp, null, false));

        return $digits ? 'https://wa.me/'.$digits.($message ? '?text='.rawurlencode($message) : '') : null;
    }
}
