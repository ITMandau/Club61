<?php

namespace App\Models\Fnb;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FnbMenu extends Model
{
    use HasUlids;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'image_url',
        'base_price',
        'station_id',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    /**
     * Foto lama dibuang dari disk begitu diganti foto baru atau menunya dihapus — supaya
     * storage tidak menumpuk file yatim tiap kali staf ganti foto menu (pola yang sama dengan
     * CompanyProfileFacility di modul Company Profile).
     */
    protected static function booted(): void
    {
        // File lama baru dibuang SETELAH perubahan benar-benar tersimpan (updated / deleted, lalu menunggu commit kalau
        // ada transaksi). Dulu dibuang saat saving / deleting: kalau simpan gagal, atau hapus ditolak database (menu
        // masih dipakai riwayat order), fotonya sudah hilang padahal menunya masih menunjuk ke file itu.
        static::updated(function (self $menu) {
            $oldPath = $menu->getOriginal('image_url');
            if ($menu->wasChanged('image_url') && $oldPath) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($oldPath));
            }
        });

        static::deleted(function (self $menu) {
            $path = $menu->image_url;
            if ($path) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($path));
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(FnbCategory::class, 'category_id');
    }

    /** Stasiun yang membuat menu ini; null = dibuat langsung di kasir (tanpa slip). */
    public function station()
    {
        return $this->belongsTo(FnbStation::class, 'station_id');
    }

    public function recipes()
    {
        return $this->hasMany(RecipeBom::class, 'menu_id');
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(FnbModifierGroup::class, 'fnb_menu_modifier_group', 'menu_id', 'group_id');
    }
}
