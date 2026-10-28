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

    public function prunable(): Builder
    {
        return static::where('viewed_at', '<', now()->subDays(self::KEEP_DAYS));
    }
}
