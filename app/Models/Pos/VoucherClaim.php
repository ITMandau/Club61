<?php

namespace App\Models\Pos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Akun yang sudah mengklaim promo cara sebar C (Modul 22 tahap 3). Satu akun satu klaim per promo. */
class VoucherClaim extends Model
{
    protected $fillable = ['voucher_id', 'user_id', 'claimed_at'];

    protected function casts(): array
    {
        return ['claimed_at' => 'datetime'];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
