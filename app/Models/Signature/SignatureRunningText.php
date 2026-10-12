<?php

namespace App\Models\Signature;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Teks berjalan: manual, atau kalimat template yang diisi otomatis dari sistem. */
class SignatureRunningText extends Model
{
    use HasUlids;

    public const KINDS = [
        'MANUAL' => 'Manual',
        'AUTO_CHECKIN' => 'Otomatis — saat check-in',
        'AUTO_BOOKING' => 'Otomatis — booking baru',
        'AUTO_SLOTS' => 'Otomatis — slot kosong hari ini',
        'AUTO_SPONSOR' => 'Otomatis — daftar sponsor',
    ];

    /** Isian yang boleh dipakai di kalimat otomatis. */
    public const TOKENS = [
        'AUTO_CHECKIN' => ['{nama}', '{lapangan}', '{jam}'],
        'AUTO_BOOKING' => ['{nama}', '{lapangan}', '{jam}'],
        'AUTO_SLOTS' => ['{daftar_slot}'],
        'AUTO_SPONSOR' => ['{sponsor}'],
    ];

    public const DEFAULT_TEXTS = [
        'AUTO_CHECKIN' => 'Selamat bermain {nama} di {lapangan}!',
        'AUTO_BOOKING' => 'Booking baru: {lapangan} jam {jam}.',
        'AUTO_SLOTS' => 'Masih tersedia hari ini: {daftar_slot}. Booking sekarang di club61.id',
        'AUTO_SPONSOR' => 'Didukung oleh {sponsor}.',
    ];

    protected $fillable = ['kind', 'text', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}
