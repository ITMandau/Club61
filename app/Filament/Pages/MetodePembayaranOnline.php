<?php

namespace App\Filament\Pages;

use App\Models\Padel\BookingTimeSetting;
use App\Models\Pos\OnlinePaymentMethod;
use App\Services\Padel\BookingTimeService;
use App\Services\Payment\OnlinePaymentCatalog;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Pengaturan metode pembayaran online (Midtrans Snap) untuk checkout padel, bayar ulang / selisih reschedule,
 * dan membership online. Daftar KODE metode tetap (OnlinePaymentCatalog) — di sini admin hanya mengatur aktif,
 * nama tampilan, keterangan, urutan & batas nominal. Perubahan tercatat otomatis di Log Aktivitas.
 */
class MetodePembayaranOnline extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Metode Pembayaran Online';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Metode Pembayaran Online';

    protected string $view = 'filament.pages.metode-pembayaran-online';

    public function canManage(): bool
    {
        return (bool) auth()->user()?->can('manage_online_payment_methods');
    }

    /** Izin terpisah dari kelola metode: lama tahan slot & batas bayar berlaku untuk SEMUA booking online. */
    public function canManageBookingTimes(): bool
    {
        return (bool) auth()->user()?->can('manage_booking_time_limits');
    }

    protected function authorizeManageBookingTimes(): void
    {
        if (! $this->canManageBookingTimes()) {
            \App\Services\Audit\ActivityLogger::accessDenied('mengubah waktu tahan slot & batas bayar tanpa izin [manage_booking_time_limits]');
            abort(403, 'Akses ditolak: Anda tidak memiliki izin [manage_booking_time_limits].');
        }
    }

    protected function authorizeManage(): void
    {
        if (! $this->canManage()) {
            \App\Services\Audit\ActivityLogger::accessDenied('mengubah metode pembayaran online tanpa izin [manage_online_payment_methods]');
            abort(403, 'Akses ditolak: Anda tidak memiliki izin [manage_online_payment_methods].');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bookingTimes')
                ->label('Atur Batas Waktu')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->visible(fn () => $this->canManageBookingTimes())
                ->modalHeading('Waktu Tahan Slot & Batas Bayar')
                ->modalDescription('Berlaku untuk booking BARU. Booking yang sedang berjalan tetap memakai batas waktunya sendiri.')
                ->modalWidth('lg')
                ->fillForm(fn () => [
                    'slot_hold_minutes' => app(BookingTimeService::class)->holdMinutes(),
                    'payment_window_minutes' => app(BookingTimeService::class)->paymentWindowMinutes(),
                ])
                ->schema([
                    TextInput::make('slot_hold_minutes')->label('Waktu tahan slot (menit)')->required()->integer()
                        ->minValue(BookingTimeService::HOLD_MIN)->maxValue(BookingTimeService::HOLD_MAX)
                        ->helperText('Waktu customer mengisi keranjang & checkout sebelum klik bayar. Lewat dari ini, slot dilepas.'),
                    TextInput::make('payment_window_minutes')->label('Batas waktu bayar online (menit)')->required()->integer()
                        ->minValue(BookingTimeService::PAYMENT_MIN)->maxValue(BookingTimeService::PAYMENT_MAX)
                        ->helperText('Dihitung sejak klik bayar. Dipakai sebagai batas waktu di Midtrans DAN batas pelepasan slot. Ganti metode bayar tidak memperpanjang waktu ini.'),
                ])
                ->action(fn (array $data) => $this->saveBookingTimes($data)),
        ];
    }

    public function saveBookingTimes(array $data): void
    {
        $this->authorizeManageBookingTimes();

        $hold = (int) ($data['slot_hold_minutes'] ?? 0);
        $window = (int) ($data['payment_window_minutes'] ?? 0);
        if ($hold < BookingTimeService::HOLD_MIN || $hold > BookingTimeService::HOLD_MAX
            || $window < BookingTimeService::PAYMENT_MIN || $window > BookingTimeService::PAYMENT_MAX) {
            Notification::make()->title('Batas Waktu Tidak Valid')
                ->body('Waktu tahan slot '.BookingTimeService::HOLD_MIN.'–'.BookingTimeService::HOLD_MAX.' menit, batas bayar '.BookingTimeService::PAYMENT_MIN.'–'.BookingTimeService::PAYMENT_MAX.' menit.')
                ->danger()->send();

            return;
        }

        DB::transaction(function () use ($hold, $window) {
            $setting = BookingTimeSetting::query()->lockForUpdate()->first() ?? new BookingTimeSetting();
            $setting->fill(['slot_hold_minutes' => $hold, 'payment_window_minutes' => $window])->save();
        });

        Notification::make()->title('Batas Waktu Tersimpan')
            ->body("Tahan slot {$hold} menit, batas bayar {$window} menit — berlaku untuk booking baru.")
            ->success()->send();
    }

    public function table(Table $table): Table
    {
        $rupiah = fn ($v) => $v === null ? null : 'Rp '.number_format((float) $v, 0, ',', '.');

        return $table
            ->query(OnlinePaymentMethod::query()->orderBy('sort_order')->orderBy('code'))
            ->paginated(false)
            ->columns([
                TextColumn::make('badge')
                    ->label('')
                    ->badge()
                    ->color(fn (OnlinePaymentMethod $r) => $r->is_active ? 'warning' : 'gray'),
                TextColumn::make('label')
                    ->label('Metode')
                    ->weight('bold')
                    ->description(fn (OnlinePaymentMethod $r) => $r->description)
                    ->wrap(),
                TextColumn::make('code')
                    ->label('Kode')
                    ->fontFamily('mono')
                    ->size('xs')
                    ->color('gray'),
                TextColumn::make('limits')
                    ->label('Batas Nominal')
                    ->state(function (OnlinePaymentMethod $r) use ($rupiah) {
                        return match (true) {
                            $r->min_amount !== null && $r->max_amount !== null => $rupiah($r->min_amount).' – '.$rupiah($r->max_amount),
                            $r->max_amount !== null => 'Maks. '.$rupiah($r->max_amount),
                            $r->min_amount !== null => 'Min. '.$rupiah($r->min_amount),
                            default => 'Tanpa batas',
                        };
                    })
                    ->color(fn (OnlinePaymentMethod $r) => $r->min_amount === null && $r->max_amount === null ? 'gray' : null),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state) => $state ? 'success' : 'gray'),
                // "Bayar Otomatis" di layar kasir (POS Walk-In, F&B, Jual Membership) — hanya metode yang juga Aktif.
                TextColumn::make('show_at_pos')
                    ->label('Di Kasir')
                    ->badge()
                    ->formatStateUsing(fn (bool $state, OnlinePaymentMethod $r) => $state ? ($r->is_active ? 'Tampil' : 'Tampil (nonaktif)') : 'Tidak')
                    ->color(fn (bool $state, OnlinePaymentMethod $r) => $state && $r->is_active ? 'info' : 'gray'),
            ])
            ->recordActions([
                Action::make('moveUp')
                    ->label('')
                    ->tooltip('Naikkan urutan')
                    ->icon('heroicon-o-chevron-up')
                    ->color('gray')
                    ->visible(fn () => $this->canManage())
                    ->action(fn (OnlinePaymentMethod $record) => $this->move($record, -1)),
                Action::make('moveDown')
                    ->label('')
                    ->tooltip('Turunkan urutan')
                    ->icon('heroicon-o-chevron-down')
                    ->color('gray')
                    ->visible(fn () => $this->canManage())
                    ->action(fn (OnlinePaymentMethod $record) => $this->move($record, 1)),
                Action::make('toggle')
                    ->label(fn (OnlinePaymentMethod $r) => $r->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (OnlinePaymentMethod $r) => $r->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color(fn (OnlinePaymentMethod $r) => $r->is_active ? 'danger' : 'success')
                    ->visible(fn () => $this->canManage())
                    ->requiresConfirmation()
                    ->modalHeading(fn (OnlinePaymentMethod $r) => ($r->is_active ? 'Nonaktifkan ' : 'Aktifkan ').$r->label.'?')
                    ->modalDescription(fn (OnlinePaymentMethod $r) => $r->is_active
                        ? 'Customer tidak bisa memilih metode ini lagi di checkout padel, invoice, maupun membership. Sesi pembayaran yang sudah terbuka tetap berlaku sampai kedaluwarsa (15 menit).'
                        : 'Pastikan metode ini SUDAH AKTIF di dashboard Midtrans — kalau belum, customer akan gagal membayar.')
                    ->modalSubmitActionLabel(fn (OnlinePaymentMethod $r) => $r->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan')
                    ->action(fn (OnlinePaymentMethod $record) => $this->toggle($record)),
                Action::make('togglePos')
                    ->label(fn (OnlinePaymentMethod $r) => $r->show_at_pos ? 'Sembunyikan dari Kasir' : 'Tampilkan di Kasir')
                    ->icon('heroicon-o-computer-desktop')
                    ->color(fn (OnlinePaymentMethod $r) => $r->show_at_pos ? 'gray' : 'info')
                    ->visible(fn () => $this->canManage())
                    ->requiresConfirmation()
                    ->modalHeading(fn (OnlinePaymentMethod $r) => ($r->show_at_pos ? 'Sembunyikan ' : 'Tampilkan ').$r->label.' di kasir?')
                    ->modalDescription(fn (OnlinePaymentMethod $r) => $r->show_at_pos
                        ? 'Kasir tidak bisa memilih metode ini lagi di "Bayar Otomatis" (POS Walk-In, F&B, Jual Membership). Checkout online customer tidak terpengaruh.'
                        : 'Kasir bisa memilih metode ini di "Bayar Otomatis": popup pembayaran (QR / nomor VA) tampil di layar kasir dan lunas terkonfirmasi otomatis. Metode juga harus berstatus Aktif.')
                    ->modalSubmitActionLabel(fn (OnlinePaymentMethod $r) => $r->show_at_pos ? 'Ya, Sembunyikan' : 'Ya, Tampilkan')
                    ->action(fn (OnlinePaymentMethod $record) => $this->togglePos($record)),
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->visible(fn () => $this->canManage())
                    ->modalHeading(fn (OnlinePaymentMethod $r) => 'Edit '.$r->code)
                    ->modalWidth('lg')
                    ->fillForm(fn (OnlinePaymentMethod $r) => [
                        'label' => $r->label,
                        'description' => $r->description,
                        'badge' => $r->badge,
                        'min_amount' => $r->min_amount,
                        'max_amount' => $r->max_amount,
                    ])
                    ->schema([
                        TextInput::make('label')->label('Nama tampilan')->required()->maxLength(100),
                        TextInput::make('description')->label('Keterangan untuk customer')->maxLength(255),
                        TextInput::make('badge')->label('Label singkat (badge)')->required()->maxLength(10),
                        Grid::make(2)->schema([
                            TextInput::make('min_amount')->label('Nominal minimal (Rp)')->numeric()->minValue(0)->maxValue(999999999999)->placeholder('Tanpa batas'),
                            TextInput::make('max_amount')->label('Nominal maksimal (Rp)')->numeric()->minValue(1)->maxValue(999999999999)->placeholder('Tanpa batas')
                                ->helperText(fn (OnlinePaymentMethod $r) => $r->code === 'QRIS' ? 'Ketentuan BI: QRIS maksimal Rp10.000.000 per transaksi.' : null),
                        ]),
                    ])
                    ->action(fn (OnlinePaymentMethod $record, array $data) => $this->saveSettings($record, $data)),
            ]);
    }

    public function toggle(OnlinePaymentMethod $record): void
    {
        $this->authorizeManage();

        DB::transaction(function () use ($record) {
            $record = OnlinePaymentMethod::whereKey($record->id)->lockForUpdate()->firstOrFail();

            if ($record->is_active && OnlinePaymentMethod::where('is_active', true)->lockForUpdate()->count() <= 1) {
                Notification::make()->title('Tidak Bisa Dinonaktifkan')
                    ->body('Minimal harus ada satu metode pembayaran online yang aktif — kalau tidak, customer tidak bisa membayar booking & membership online sama sekali.')
                    ->danger()->persistent()->send();

                return;
            }

            $record->update(['is_active' => ! $record->is_active]);

            Notification::make()->title($record->is_active ? 'Metode Diaktifkan' : 'Metode Dinonaktifkan')
                ->body($record->label.($record->is_active ? ' sekarang bisa dipilih customer.' : ' tidak bisa dipilih customer lagi.'))
                ->success()->send();
        });
    }

    public function togglePos(OnlinePaymentMethod $record): void
    {
        $this->authorizeManage();

        $record = OnlinePaymentMethod::findOrFail($record->id);
        $record->update(['show_at_pos' => ! $record->show_at_pos]);

        Notification::make()->title($record->show_at_pos ? 'Tampil di Kasir' : 'Disembunyikan dari Kasir')
            ->body($record->label.($record->show_at_pos ? ' bisa dipilih kasir di "Bayar Otomatis".' : ' tidak muncul lagi di kasir.'))
            ->success()->send();
    }

    public function saveSettings(OnlinePaymentMethod $record, array $data): void
    {
        $this->authorizeManage();

        $min = isset($data['min_amount']) && $data['min_amount'] !== '' ? round((float) $data['min_amount'], 2) : null;
        $max = isset($data['max_amount']) && $data['max_amount'] !== '' ? round((float) $data['max_amount'], 2) : null;

        if ($min !== null && $max !== null && $min > $max) {
            Notification::make()->title('Batas Nominal Tidak Valid')->body('Nominal minimal tidak boleh lebih besar dari nominal maksimal.')->danger()->send();

            return;
        }

        // QRIS dibatasi ketentuan BI — batas di sistem tidak boleh dilonggarkan melebihinya (transaksi pasti ditolak).
        if ($record->code === 'QRIS' && ($max === null || $max > OnlinePaymentCatalog::QRIS_MAX_AMOUNT)) {
            Notification::make()->title('Batas QRIS Tidak Valid')
                ->body('Ketentuan BI: QRIS maksimal Rp10.000.000 per transaksi. Isi nominal maksimal paling besar Rp10.000.000.')
                ->danger()->persistent()->send();

            return;
        }

        $record->update([
            'label' => trim((string) $data['label']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'badge' => strtoupper(trim((string) $data['badge'])),
            'min_amount' => $min,
            'max_amount' => $max,
        ]);

        Notification::make()->title('Pengaturan Tersimpan')->body("{$record->label} diperbarui.")->success()->send();
    }

    public function move(OnlinePaymentMethod $record, int $direction): void
    {
        $this->authorizeManage();

        DB::transaction(function () use ($record, $direction) {
            $ordered = OnlinePaymentMethod::orderBy('sort_order')->orderBy('code')->lockForUpdate()->get()->values();
            $index = $ordered->search(fn ($m) => $m->id === $record->id);
            $target = $index + $direction;
            if ($index === false || $target < 0 || $target >= $ordered->count()) {
                return;
            }

            $items = $ordered->all();
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
            foreach ($items as $position => $method) {
                if ($method->sort_order !== $position) {
                    $method->update(['sort_order' => $position]);
                }
            }
        });
    }
}
