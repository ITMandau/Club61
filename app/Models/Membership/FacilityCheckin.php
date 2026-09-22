<?php

namespace App\Models\Membership;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityCheckin extends Model
{
    use HasUlids;

    protected $fillable = [
        'facility',
        'balance_id',
        'user_id',
        'staff_id',
        'checkin_at',
    ];

    protected $casts = [
        'checkin_at' => 'datetime',
    ];

    public function balance(): BelongsTo
    {
        return $this->belongsTo(UserMembershipBalance::class, 'balance_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
