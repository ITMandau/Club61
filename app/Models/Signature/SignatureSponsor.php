<?php

namespace App\Models\Signature;

use App\Models\Sponsor\SponsorOrganization;
use App\Services\Signature\SignatureFileJanitor;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Sponsor yang tampil di TV: brand (input manual) atau dari Sponsor Corporate. */
class SignatureSponsor extends Model
{
    use HasUlids;

    public const KINDS = ['BRAND' => 'Sponsor brand (manual)', 'CORPORATE' => 'Dari Sponsor Corporate'];

    protected $fillable = ['kind', 'name', 'sponsor_organization_id', 'logo_path', 'duration_seconds', 'sort_order', 'is_active', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['duration_seconds' => 'integer', 'sort_order' => 'integer', 'is_active' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    protected static function booted(): void
    {
        // Logo lama dibuang saat diganti / sponsornya dihapus — kecuali masih / baru saja tayang di TV.
        static::updated(function (self $sponsor) {
            if ($sponsor->wasChanged('logo_path')) {
                app(SignatureFileJanitor::class)->release($sponsor->getOriginal('logo_path'));
            }
        });
        static::deleted(fn (self $sponsor) => app(SignatureFileJanitor::class)->release($sponsor->logo_path));
    }

    public function organization()
    {
        return $this->belongsTo(SponsorOrganization::class, 'sponsor_organization_id');
    }

    /** Nama yang tampil: nama perusahaan (Corporate) atau nama yang diketik (brand). */
    public function displayName(): string
    {
        return (string) ($this->kind === 'CORPORATE' ? ($this->organization?->name ?? $this->name) : $this->name);
    }

    /** Aktif dan (kalau diisi) masih dalam periode tayang. */
    public function isShowing(?\Carbon\CarbonInterface $today = null): bool
    {
        $today ??= now('Asia/Jakarta')->startOfDay();

        return $this->is_active
            && (! $this->starts_on || $this->starts_on->lte($today))
            && (! $this->ends_on || $this->ends_on->gte($today));
    }
}
