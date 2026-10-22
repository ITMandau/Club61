<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Nomor VA DOKU SNAP → invoice (dicatat saat VA dibuat). Dipakai mencari tagihan untuk notifikasi VA tanpa trxId.
 */
class DokuSnapVaNumber extends Model
{
    use MassPrunable;

    public const KEEP_DAYS = 90;

    public const UPDATED_AT = null;

    protected $fillable = ['va_number', 'invoice_number'];

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }
}
