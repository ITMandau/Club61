<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Notifikasi DOKU yang lolos verifikasi tanda tangan (lihat DokuNotificationController). Satu baris per Request-Id.
 */
class DokuNotification extends Model
{
    use MassPrunable;

    public const RECEIVED = 'received';

    public const PROCESSED = 'processed';

    public const IGNORED = 'ignored';

    public const REJECTED = 'rejected';

    /** Belum bisa diputuskan (status DOKU belum sinkron / DOKU tidak bisa dihubungi) → diproses ulang rekonsiliasi. */
    public const RETRY = 'retry';

    /** Tagihan pemilik invoice belum ditemukan (mis. notifikasi datang sebelum sesi tersimpan) → diproses ulang. */
    public const UNMATCHED = 'unmatched';

    public const KEEP_DAYS = 90;

    /** Diproses ulang paling lama sejauh ini & sebanyak ini (setelahnya cukup rekonsiliasi status). */
    public const RETRY_HOURS = 48;

    public const MAX_ATTEMPTS = 30;

    protected $fillable = ['request_id', 'invoice_number', 'status', 'amount', 'result', 'result_note', 'attempts', 'payload', 'received_at', 'processed_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'amount' => 'decimal:2',
            'attempts' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::where('received_at', '<', now()->subDays(self::KEEP_DAYS));
    }
}
