<?php

namespace App\Filament\Pages;

use App\Models\Sponsor\SponsorOrganization;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Tampilan read-only dashboard PIC Sponsor Team di dalam panel staf — pengganti membuka
 * portal customer (/corporate) yang membawa navigasi customer. Aksi PIC (rilis voucher,
 * ubah roster) tetap hanya bisa dilakukan PIC asli lewat portalnya sendiri.
 */
class SponsorDashboard extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static ?string $navigationLabel = 'Dashboard Sponsor';

    protected static string|UnitEnum|null $navigationGroup = 'Sponsor';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Dashboard Sponsor Team (PIC)';

    protected string $view = 'filament.pages.sponsor-dashboard';

    #[Url(as: 'organization')]
    public ?string $organizationId = null;

    public function mount(): void
    {
        if (! $this->organizationId || ! $this->organizations->has($this->organizationId)) {
            $this->organizationId = $this->organizations->keys()->first();
        }
    }

    /** @return Collection<string, string> [id => "Nama Perusahaan — PIC"] */
    public function getOrganizationsProperty(): Collection
    {
        return SponsorOrganization::with('sponsorAdmin:id,name')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (SponsorOrganization $o) => [$o->id => $o->name.' — PIC: '.($o->sponsorAdmin?->name ?? '-')]);
    }
}
