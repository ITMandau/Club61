<?php

namespace App\Models\Fnb;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class TableQrCode extends Model
{
    use HasUlids;

    protected $fillable = ['table_number', 'qr_hash', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
