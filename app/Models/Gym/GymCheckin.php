<?php

namespace App\Models\Gym;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class GymCheckin extends Model
{
    use HasUlids;

    protected $fillable = [
        'membership_id',
        'user_id',
        'staff_id',
        'checkin_at',
    ];

    protected function casts(): array
    {
        return [
            'checkin_at' => 'datetime',
        ];
    }

    public function membership()
    {
        return $this->belongsTo(GymMembership::class, 'membership_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
