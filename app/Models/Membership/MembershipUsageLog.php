<?php

namespace App\Models\Membership;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipUsageLog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'balance_id',
        'change_type',
        'quantity',
        'related_type',
        'related_id',
        'performed_by',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function balance(): BelongsTo
    {
        return $this->belongsTo(UserMembershipBalance::class, 'balance_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
