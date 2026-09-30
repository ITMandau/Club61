<?php

namespace App\Models\Audit;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Satu baris jejak audit. IMMUTABLE: tidak bisa diubah atau dihapus lewat Eloquent — termasuk oleh
 * super_admin. Satu-satunya jalur tulis adalah App\Services\Audit\ActivityLogger, dan satu-satunya
 * jalur hapus adalah command retensi `audit:prune` (lihat ActivityLog::pruneOlderThan()).
 */
class ActivityLog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'activity_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'changes' => 'array',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Log aktivitas tidak boleh diubah.');
        });

        static::deleting(function () {
            throw new LogicException('Log aktivitas tidak boleh dihapus.');
        });
    }

    public function causer()
    {
        return $this->belongsTo(User::class, 'causer_id')->withTrashed();
    }

    public function scopeInBatch(Builder $query, ?string $batchId): Builder
    {
        return $query->where('batch_id', $batchId ?? '__none__');
    }

    /**
     * Jalur hapus satu-satunya (retensi). Query builder langsung (bukan model event) supaya
     * guard `deleting` di atas tetap memblokir penghapusan per-baris dari kode aplikasi.
     */
    public static function pruneOlderThan(\DateTimeInterface $cutoff): int
    {
        $deleted = 0;

        do {
            $ids = static::query()->where('created_at', '<', $cutoff)->limit(1000)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $deleted += static::query()->toBase()->whereIn('id', $ids)->delete();
        } while ($ids->count() === 1000);

        return $deleted;
    }
}
