<?php

namespace App\Models\Wellness;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class WellnessBooking extends Model
{
    use HasUlids;

    protected $fillable = [
        'booking_code',
        'user_id',
        'slot_id',
        'num_persons',
        'total_amount',
        'status',
        'qr_code_hash',
        'membership_balance_id',
        'member_discount_amount',
        'member_sessions_consumed',
    ];

    protected function casts(): array
    {
        return [
            'num_persons' => 'integer',
            'total_amount' => 'decimal:2',
            'member_discount_amount' => 'decimal:2',
            'member_sessions_consumed' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function slot()
    {
        return $this->belongsTo(WellnessSlot::class, 'slot_id');
    }

    public function membershipBalance()
    {
        return $this->belongsTo(\App\Models\Membership\UserMembershipBalance::class, 'membership_balance_id');
    }
}
