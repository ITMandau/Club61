<?php

namespace App\Models\Gym;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GymMembership extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'membership_code',
        'user_id',
        'package_id',
        'start_date',
        'end_date',
        'remaining_visits',
        'status',
        'qr_pass_hash',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'remaining_visits' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(GymPackage::class, 'package_id');
    }

    public function checkins()
    {
        return $this->hasMany(GymCheckin::class, 'membership_id');
    }
}
