<?php

namespace App\Filament\Pages;

use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelBookingEquipment;
use App\Models\Padel\PadelCourt;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class MasterData extends Page
{
    use HasPageShield;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Master Data & Tarif';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Master Data, Tarif & Add-ons';

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.pages.master-data';

    // Tab Aktif: 'courts' atau 'equipments'
    public string $activeTab = 'courts';

    // State Modal Lapangan
    public bool $showCourtModal = false;
    public ?string $editingCourtId = null;
    public string $courtName = '';
    public string $courtType = 'INDOOR';
    public ?string $courtDescription = '';
    public int|float|string|null $hourlyRateRegular = 300000;
    public int|float|string|null $hourlyRatePrime = 450000;
    public bool $courtIsActive = true;

    // State Modal Add-on / Equipment
    public bool $showEquipmentModal = false;
    public ?string $editingEquipmentId = null;
    public string $equipmentName = '';
    public string $equipmentType = 'RACKET';
    public int|float|string|null $equipmentRentalPrice = 50000;
    public int|float|string|null $equipmentStock = 20;
    public bool $equipmentIsActive = true;

    // Filter status tampilan Add-ons ('ALL', 'ACTIVE', 'INACTIVE')
    public string $equipmentFilter = 'ALL';

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['courts', 'equipments']) ? $tab : 'courts';
    }

    public function setEquipmentFilter(string $filter): void
    {
        $this->equipmentFilter = in_array($filter, ['ALL', 'ACTIVE', 'INACTIVE']) ? $filter : 'ALL';
    }

    // ==========================================
    // LOGIKA TAB 1: LAPANGAN & TARIF
    // ==========================================

    public function openCreateCourtModal(): void
    {
        $this->editingCourtId = null;
        $this->courtName = '';
        $this->courtType = 'INDOOR';
        $this->courtDescription = 'Indoor • Central AC';
        $this->hourlyRateRegular = 300000;
        $this->hourlyRatePrime = 450000;
        $this->courtIsActive = true;
        $this->showCourtModal = true;
    }

    public function openEditCourtModal(string $courtId): void
    {
        $court = PadelCourt::find($courtId);
        if (! $court) {
            Notification::make()->title('Lapangan tidak ditemukan')->danger()->send();
            return;
        }

        $this->editingCourtId = $court->id;
        $this->courtName = (string) $court->name;
        $this->courtType = (string) ($court->type ?: 'INDOOR');
        $this->courtDescription = (string) ($court->description ?: ($court->type === 'INDOOR' ? 'Indoor • Central AC' : 'Outdoor • Open Air Court'));
        $this->hourlyRateRegular = (float) $court->hourly_rate_regular;
        $this->hourlyRatePrime = (float) $court->hourly_rate_prime;
        $this->courtIsActive = (bool) $court->is_active;
        $this->showCourtModal = true;
    }

    public function closeCourtModal(): void
    {
        $this->showCourtModal = false;
        $this->editingCourtId = null;
        $this->courtDescription = '';
    }

    public function saveCourt(): void
    {
        $this->authorizeAdminAction();

        $this->validate([
            'courtName' => ['required', 'string', 'max:50'],
            'courtType' => ['required', 'in:INDOOR,OUTDOOR'],
            'courtDescription' => ['nullable', 'string', 'max:100'],
            'hourlyRateRegular' => ['required', 'numeric', 'min:0'],
            'hourlyRatePrime' => ['required', 'numeric', 'min:0'],
        ], [
            'courtName.required' => 'Nama lapangan wajib diisi.',
            'hourlyRateRegular.required' => 'Tarif reguler wajib diisi.',
            'hourlyRatePrime.required' => 'Tarif prime time wajib diisi.',
        ]);

        $regular = max(0, (float) ($this->hourlyRateRegular ?: 0));
        $prime = max(0, (float) ($this->hourlyRatePrime ?: 0));

        if ($this->editingCourtId) {
            $court = PadelCourt::find($this->editingCourtId);
            if (! $court) {
                Notification::make()->title('Lapangan tidak ditemukan')->danger()->send();
                return;
            }

            $court->update([
                'name' => trim($this->courtName),
                'type' => $this->courtType,
                'description' => trim($this->courtDescription ?? '') ?: null,
                'hourly_rate_regular' => $regular,
                'hourly_rate_prime' => $prime,
                'is_active' => $this->courtIsActive,
            ]);

            Notification::make()
                ->title('Tarif Lapangan Berhasil Diperbarui')
                ->body("Konfigurasi untuk {$court->name} telah aktif dan tersinkronisasi ke seluruh jadwal.")
                ->success()
                ->send();
        } else {
            $court = PadelCourt::create([
                'name' => trim($this->courtName),
                'type' => $this->courtType,
                'description' => trim($this->courtDescription ?? '') ?: null,
                'hourly_rate_regular' => $regular,
                'hourly_rate_prime' => $prime,
                'is_active' => $this->courtIsActive,
            ]);

            Notification::make()
                ->title('Lapangan Baru Berhasil Ditambahkan')
                ->body("Lapangan {$court->name} telah tersedia di sistem.")
                ->success()
                ->send();
        }

        $this->closeCourtModal();
    }

    public function toggleCourtStatus(string $courtId): void
    {
        $this->authorizeAdminAction();

        $court = PadelCourt::find($courtId);
        if (! $court) {
            Notification::make()->title('Lapangan tidak ditemukan')->danger()->send();
            return;
        }

        $court->is_active = ! $court->is_active;
        $court->save();

        $statusText = $court->is_active ? 'diaktifkan' : 'dinonaktifkan';
        Notification::make()
            ->title("Status Lapangan Diperbarui")
            ->body("Lapangan {$court->name} kini telah {$statusText}.")
            ->success()
            ->send();
    }

    // ==========================================
    // LOGIKA TAB 2: ADD-ONS & PERALATAN
    // ==========================================

    public function openCreateEquipmentModal(): void
    {
        $this->editingEquipmentId = null;
        $this->equipmentName = '';
        $this->equipmentType = 'RACKET';
        $this->equipmentRentalPrice = 50000;
        $this->equipmentStock = 20;
        $this->equipmentIsActive = true;
        $this->showEquipmentModal = true;
    }

    public function openEditEquipmentModal(string $equipmentId): void
    {
        $equipment = CourtEquipment::find($equipmentId);
        if (! $equipment) {
            Notification::make()->title('Add-on tidak ditemukan')->danger()->send();
            return;
        }

        $this->editingEquipmentId = $equipment->id;
        $this->equipmentName = (string) $equipment->name;
        $this->equipmentType = (string) ($equipment->type ?: 'RACKET');
        $this->equipmentRentalPrice = (float) $equipment->rental_price;
        $this->equipmentStock = (int) $equipment->stock_quantity;
        $this->equipmentIsActive = (bool) $equipment->is_active;
        $this->showEquipmentModal = true;
    }

    public function closeEquipmentModal(): void
    {
        $this->showEquipmentModal = false;
        $this->editingEquipmentId = null;
    }

    public function saveEquipment(): void
    {
        $this->authorizeAdminAction();

        $this->validate([
            'equipmentName' => ['required', 'string', 'max:100'],
            'equipmentType' => ['required', 'in:RACKET,BALL,TOWEL,COACH,OTHER'],
            'equipmentRentalPrice' => ['required', 'numeric', 'min:0'],
            'equipmentStock' => ['required', 'integer', 'min:0'],
        ], [
            'equipmentName.required' => 'Nama add-on wajib diisi.',
            'equipmentRentalPrice.required' => 'Tarif sewa wajib diisi.',
            'equipmentStock.required' => 'Jumlah stok wajib diisi.',
        ]);

        $price = max(0, (float) ($this->equipmentRentalPrice ?: 0));
        $stock = max(0, (int) ($this->equipmentStock ?: 0));

        if ($this->editingEquipmentId) {
            $equipment = CourtEquipment::find($this->editingEquipmentId);
            if (! $equipment) {
                Notification::make()->title('Add-on tidak ditemukan')->danger()->send();
                return;
            }

            $equipment->update([
                'name' => trim($this->equipmentName),
                'type' => $this->equipmentType,
                'rental_price' => $price,
                'stock_quantity' => $stock,
                'is_active' => $this->equipmentIsActive,
            ]);

            Notification::make()
                ->title('Add-on Berhasil Diperbarui')
                ->body("Data {$equipment->name} telah diperbarui di katalog sewa.")
                ->success()
                ->send();
        } else {
            $equipment = CourtEquipment::create([
                'name' => trim($this->equipmentName),
                'type' => $this->equipmentType,
                'rental_price' => $price,
                'stock_quantity' => $stock,
                'is_active' => $this->equipmentIsActive,
            ]);

            Notification::make()
                ->title('Add-on Baru Berhasil Ditambahkan')
                ->body("Item {$equipment->name} telah siap disewakan.")
                ->success()
                ->send();
        }

        $this->closeEquipmentModal();
    }

    public function toggleEquipmentStatus(string $equipmentId): void
    {
        $this->authorizeAdminAction();

        $equipment = CourtEquipment::find($equipmentId);
        if (! $equipment) {
            Notification::make()->title('Add-on tidak ditemukan')->danger()->send();
            return;
        }

        $equipment->is_active = ! $equipment->is_active;
        $equipment->save();

        $statusText = $equipment->is_active ? 'diaktifkan' : 'dinonaktifkan';
        Notification::make()
            ->title("Status Add-on Diperbarui")
            ->body("Item {$equipment->name} kini telah {$statusText}.")
            ->success()
            ->send();
    }

    /**
     * Hapus Pintar dengan Proteksi Integritas Transaksi Relasional (ACID Safe).
     * Jika item sudah pernah disewa, item hanya dinonaktifkan (is_active = false)
     * agar riwayat invoice masa lalu tidak terhapus oleh foreign key cascadeOnDelete.
     */
    public function deleteEquipment(string $equipmentId): void
    {
        $this->authorizeAdminAction();

        $actionTaken = DB::transaction(function () use ($equipmentId) {
            $equipment = CourtEquipment::where('id', $equipmentId)->lockForUpdate()->first();
            if (! $equipment) {
                return 'NOT_FOUND';
            }

            $hasHistory = PadelBookingEquipment::where('equipment_id', $equipmentId)->exists();
            if ($hasHistory) {
                $equipment->update(['is_active' => false]);
                return 'DEACTIVATED';
            } else {
                $equipment->delete();
                return 'DELETED';
            }
        });

        if ($actionTaken === 'NOT_FOUND') {
            Notification::make()->title('Add-on tidak ditemukan')->danger()->send();
        } elseif ($actionTaken === 'DEACTIVATED') {
            Notification::make()
                ->title('Add-on Dinonaktifkan (Proteksi Integritas Data)')
                ->body('Item ini memiliki riwayat penyewaan masa lalu, sehingga otomatis dialihkan ke status NONAKTIF agar seluruh struk dan invoice historis tetap utuh 100%.')
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title('Add-on Berhasil Dihapus Permanen')
                ->body('Item belum pernah disewa sama sekali sehingga telah dihapus bersih dari sistem.')
                ->success()
                ->send();
        }
    }

    // ==========================================
    // DATA COMPUTED PROPERTIES UNTUK BLADE
    // ==========================================

    public function getCourtsProperty(): Collection
    {
        return PadelCourt::orderBy('name')->get();
    }

    public function getEquipmentsProperty(): Collection
    {
        $query = CourtEquipment::query();

        if ($this->equipmentFilter === 'ACTIVE') {
            $query->where('is_active', true);
        } elseif ($this->equipmentFilter === 'INACTIVE') {
            $query->where('is_active', false);
        }

        $equipments = $query->orderBy('type')->orderBy('name')->get();

        // Prefetch count riwayat rental untuk setiap equipment secara efisien
        $counts = PadelBookingEquipment::select('equipment_id', DB::raw('count(*) as total_rentals'))
            ->whereIn('equipment_id', $equipments->pluck('id'))
            ->groupBy('equipment_id')
            ->pluck('total_rentals', 'equipment_id');

        return $equipments->map(function ($eq) use ($counts) {
            $eq->setAttribute('historical_rentals_count', $counts[$eq->id] ?? 0);
            return $eq;
        });
    }

    protected function authorizeAdminAction(): void
    {
        abort_unless(
            auth()->user() && (
                auth()->user()->hasAnyRole(['super_admin', 'admin']) ||
                auth()->user()->can('manage_court_pricing') ||
                auth()->user()->can('manage_court_equipment')
            ),
            403,
            'Akses ditolak: Anda tidak memiliki izin untuk mengelola master data dan tarif.'
        );
    }
}
