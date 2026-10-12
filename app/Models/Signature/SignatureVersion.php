<?php

namespace App\Models\Signature;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Snapshot tampilan yang ditayangkan ke TV (Tayangkan / Kembalikan versi). */
class SignatureVersion extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['screen_id', 'version', 'payload', 'note', 'published_by_id', 'created_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'version' => 'integer', 'created_at' => 'datetime'];
    }

    public function screen()
    {
        return $this->belongsTo(SignatureScreen::class, 'screen_id');
    }

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by_id');
    }
}
