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
    ];

    protected function casts(): array
    {
        return [
            'num_persons' => 'integer',
            'total_amount' => 'decimal:2',
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
}
