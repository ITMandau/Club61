<?php

namespace App\Models\Wellness;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class WellnessWaitlist extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'slot_id',
        'queue_number',
        'status',
        'priority_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'queue_number' => 'integer',
            'priority_expires_at' => 'datetime',
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
