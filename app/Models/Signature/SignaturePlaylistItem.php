<?php

namespace App\Models\Signature;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Satu konten di playlist zona sebuah template, dengan urutan & durasi (kosong = durasi konten). */
class SignaturePlaylistItem extends Model
{
    use HasUlids;

    protected $fillable = ['template_id', 'zone_key', 'content_id', 'sort_order', 'duration_seconds'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'duration_seconds' => 'integer'];
    }

    public function template()
    {
        return $this->belongsTo(SignatureTemplate::class, 'template_id');
    }

    public function content()
    {
        return $this->belongsTo(SignatureContent::class, 'content_id');
    }
}
