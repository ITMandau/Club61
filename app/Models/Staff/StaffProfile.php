<?php

namespace App\Models\Staff;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'profession',
        'bio',
        'hourly_rate',
        'commission_rate',
        'is_available',
    ];

    // hourly_rate/commission_rate itu data payroll internal — jangan pernah ikut ke-serialize
    // ke JSON kalau model ini (atau relasi 'user') dikembalikan langsung dari sebuah endpoint.
    protected $hidden = [
        'hourly_rate',
        'commission_rate',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function schedules()
    {
        return $this->hasMany(StaffSchedule::class, 'staff_id');
    }
}
