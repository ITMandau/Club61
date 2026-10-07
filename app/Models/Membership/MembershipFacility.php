<?php

namespace App\Models\Membership;

use App\Services\Membership\MembershipFacilityService;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Fasilitas yang bisa dimasukkan ke paket membership. Fasilitas sistem (PADEL / GYM / SAUNA) terhubung ke mesin
 * booking & check-in yang sudah ada, jadi kode & mode pemakaiannya dikunci. Fasilitas baru dari admin hanya bisa
 * berupa CHECK_IN (kuota kunjungan dipotong saat check-in) atau INFO (benefit tampilan tanpa kuota).
 */
class MembershipFacility extends Model
{
    use HasUlids;

    public const MODE_PADEL_BOOKING = 'PADEL_BOOKING';

    public const MODE_WELLNESS_BOOKING = 'WELLNESS_BOOKING';

    public const MODE_CHECK_IN = 'CHECK_IN';

    public const MODE_INFO = 'INFO';

    /** Mode yang boleh dipilih untuk fasilitas buatan admin. */
    public const CUSTOM_MODES = [
        self::MODE_CHECK_IN => 'Check-in (kuota kunjungan dipotong saat member check-in)',
        self::MODE_INFO => 'Info saja (benefit tampilan, tanpa kuota)',
    ];

    protected $fillable = ['code', 'name', 'badge', 'description', 'usage_mode', 'is_system', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $facility) {
            if ($facility->isDirty('code')) {
                throw new \LogicException('Kode fasilitas tidak boleh diubah — sudah tersimpan di paket & kartu member.');
            }
            if ($facility->getOriginal('is_system') && ($facility->isDirty('usage_mode') || $facility->isDirty('is_system'))) {
                throw new \LogicException('Mode pemakaian fasilitas sistem tidak boleh diubah.');
            }
            // Kuota yang sudah dijual tidak boleh berubah arti (mis. kunjungan → info saja = kuota member hilang).
            if ($facility->isDirty('usage_mode') && $facility->isInUse()) {
                throw new \LogicException('Mode pemakaian tidak bisa diubah: fasilitas sudah dipakai di paket / kartu member.');
            }
        });

        static::deleting(function (self $facility) {
            if ($facility->is_system) {
                throw new \LogicException('Fasilitas sistem tidak boleh dihapus — nonaktifkan saja.');
            }
            if ($facility->isInUse()) {
                throw new \LogicException('Fasilitas masih dipakai di paket / kartu member — nonaktifkan saja.');
            }
        });

        $flush = fn () => DB::afterCommit(fn () => app(MembershipFacilityService::class)->flush());
        static::saved($flush);
        static::deleted($flush);
    }

    /** Sudah dipakai di paket atau di kartu member yang sudah terjual. */
    public function isInUse(): bool
    {
        $code = $this->getOriginal('code') ?? $this->code;

        return MembershipPlanBenefit::where('facility', $code)->exists()
            || UserMembershipBalance::where('facility', $code)->exists();
    }
}
