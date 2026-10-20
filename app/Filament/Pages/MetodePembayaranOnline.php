<?php

namespace App\Filament\Pages;

use App\Models\Padel\BookingTimeSetting;
use App\Models\Pos\OnlinePaymentMethod;
use App\Models\Pos\PaymentGatewaySetting;
use App\Services\Padel\BookingTimeService;
use App\Services\Payment\OnlineGatewayRouter;
use App\Services\Payment\OnlinePaymentCatalog;
use App\Services\Payment\OnlinePaymentMethodService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Pengaturan metode pembayaran online untuk checkout padel, bayar ulang / selisih reschedule, dan membership online
 * (Modul 27). Metode bawaan (OnlinePaymentCatalog) kodenya tetap; admin mengatur aktif, nama tampilan, keterangan,
 * urutan & batas nominal, MENAMBAH metode QRIS / VA dengan kanal dari daftar kode resmi gateway, dan memilih gateway
 * untuk tagihan baru (online customer & QR kasir terpisah). Perubahan tercatat otomatis di Log Aktivitas.
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

    /** Izin terpisah: gateway menentukan ke akun mana uang customer masuk. */
    public function canManageGateway(): bool
    {
        return (bool) auth()->user()?->can('manage_payment_gateway');
    }

    protected function authorizeManageGateway(): void
    {
        if (! $this->canManageGateway()) {
            \App\Services\Audit\ActivityLogger::accessDenied('mengubah gateway pembayaran tanpa izin [manage_payment_gateway]');
            abort(403, 'Akses ditolak: Anda tidak memiliki izin [manage_payment_gateway].');
        }
    }

    /** Gateway yang dipakai sekarang + yang belum bisa dipilih beserta alasannya (untuk kartu info halaman). */
    public function gatewayStatus(): array
    {
        $router = app(OnlineGatewayRouter::class);

        return [
            'online' => OnlineGatewayRouter::label($router->onlineGateway()),
            'pos' => OnlineGatewayRouter::label($router->posQrisGateway()),
            'unavailable' => collect(OnlineGatewayRouter::labels())
                ->mapWithKeys(fn (string $label, string $code) => [$label => $router->unavailableReason($code) ?? $router->unavailableReason($code, forPos: true)])
                ->filter()
                ->all(),
        ];
    }

    protected function getHeaderActions(): array
    {
        $router = app(OnlineGatewayRouter::class);
        $gatewayOptions = OnlineGatewayRouter::labels();
        $note = fn (bool $forPos) => collect($gatewayOptions)
            ->map(fn (string $label, string $code) => ($reason = $router->unavailableReason($code, $forPos)) ? "{$label}: {$reason}" : null)
            ->filter()
            ->implode(' ');

        return [
            Action::make('addMethod')
                ->label('Tambah Metode')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->visible(fn () => $this->canManage())
                ->modalHeading('Tambah Metode Pembayaran Online')
                ->modalDescription('Hanya QRIS & Virtual Account. Kode kanal dipilih dari daftar resmi Midtrans / DOKU. Metode baru dibuat NONAKTIF — aktifkan setelah kanalnya aktif di dashboard gateway.')
                ->modalWidth('2xl')
                ->fillForm(fn () => ['kind' => 'VA', 'midtrans_channels' => [], 'doku_channels' => [], 'show_at_pos' => false])
                ->schema($this->methodSchema(null))
                ->action(fn (array $data) => $this->createMethod($data)),
            Action::make('gatewaySettings')
                ->label('Gateway Pembayaran')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->visible(fn () => $this->canManageGateway())
                ->modalHeading('Gateway Pembayaran')
                ->modalDescription('Berlaku untuk tagihan BARU. Tagihan yang sudah dibuat tetap dibayar & dicek lewat gateway yang membuatnya. Sebelum memilih DOKU: aktifkan "Order Cancellation" (Settings → Checkout Appearance → Order Settings) dan isi URL notifikasi di SETIAP kanal QRIS / VA pada dashboard DOKU.')
                ->modalWidth('lg')
                ->fillForm(fn () => $router->savedSettings())
                ->schema([
                    Select::make('online_gateway')->label('Pembayaran online customer')->required()
                        ->options($gatewayOptions)
                        ->disableOptionWhen(fn (string $value) => ! $router->isUsable($value))
                        ->helperText(trim('Checkout booking padel, bayar ulang / selisih di invoice, dan membership online. '.$note(false))),
                    Select::make('pos_qris_gateway')->label('QR otomatis di kasir')->required()
                        ->options($gatewayOptions)
                        ->disableOptionWhen(fn (string $value) => ! $router->isUsable($value, forPos: true))
                        ->helperText(trim('"Bayar Otomatis" di POS Walk-In, F&B & Jual Membership. Mode Manual (QRIS statis / EDC) tidak terpengaruh. '.$note(true))),
                ])
                ->action(fn (array $data) => $this->saveGatewaySettings($data)),
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
                    ->color('gray')
                    ->description(fn (OnlinePaymentMethod $r) => $r->is_custom ? 'Ditambah admin' : null),
                // Kanal tiap gateway — metode tanpa kanal di gateway yang dipakai otomatis disembunyikan dari customer.
                TextColumn::make('channels')
                    ->label('Kanal Gateway')
                    ->state(function (OnlinePaymentMethod $r) {
                        $methods = app(OnlinePaymentMethodService::class);

                        return collect(OnlineGatewayRouter::labels())
                            ->map(fn (string $label, string $gateway) => $label.': '.(implode(', ', $methods->channels($r->code, $gateway) ?? []) ?: '—'))
                            ->values()
                            ->all();
                    })
                    ->listWithLineBreaks()
                    ->fontFamily('mono')
                    ->size('xs')
                    ->color('gray')
                    ->wrap(),
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
                    ->modalWidth(fn (OnlinePaymentMethod $r) => $r->is_custom ? '2xl' : 'lg')
                    ->fillForm(fn (OnlinePaymentMethod $r) => [
                        'label' => $r->label,
                        'description' => $r->description,
                        'badge' => $r->badge,
                        'kind' => $this->kindOf($r),
                        'midtrans_channels' => (array) ($r->midtrans_channels ?? []),
                        'doku_channels' => (array) ($r->doku_channels ?? []),
                        'min_amount' => $r->min_amount,
                        'max_amount' => $r->max_amount,
                    ])
                    ->schema(fn (OnlinePaymentMethod $record) => $this->methodSchema($record))
                    ->action(fn (OnlinePaymentMethod $record, array $data) => $this->saveSettings($record, $data)),
            ]);
    }

    /**
     * Form tambah / edit metode. Kanal hanya bisa dipilih untuk metode baru & metode tambahan admin — kanal metode
     * bawaan tetap dari katalog (transaksi lama tetap terpetakan benar).
     */
    private function methodSchema(?OnlinePaymentMethod $record): array
    {
        $isNew = $record === null;
        $fields = [
            TextInput::make('label')->label('Nama tampilan')->required()->maxLength(100),
            TextInput::make('description')->label('Keterangan untuk customer')->maxLength(255),
            TextInput::make('badge')->label('Label singkat (badge)')->required()->maxLength(10),
        ];

        if ($isNew || $record->is_custom) {
            $fields[] = Select::make('kind')->label('Jenis')->required()
                ->options(OnlinePaymentCatalog::ADDABLE_KINDS)
                ->disabled(! $isNew)
                ->dehydrated($isNew)
                ->live()
                ->afterStateUpdated(function (Set $set) {
                    $set('midtrans_channels', []);
                    $set('doku_channels', []);
                })
                ->helperText($isNew ? 'Kartu & e-wallet langsung belum bisa ditambahkan (tidak bisa dibatalkan saat customer ganti metode).' : null);

            foreach ([OnlineGatewayRouter::MIDTRANS => 'midtrans_channels', OnlineGatewayRouter::DOKU => 'doku_channels'] as $gateway => $field) {
                $used = $this->channelsUsedByOthers($gateway, $record?->code);
                $label = OnlineGatewayRouter::label($gateway);
                $fields[] = CheckboxList::make($field)->label("Kanal {$label}")
                    ->options(fn (Get $get) => OnlinePaymentCatalog::officialChannels($gateway, (string) ($get('kind') ?: 'VA')))
                    ->disableOptionWhen(fn (string $value) => in_array($value, $used, true))
                    ->columns(2)
                    ->helperText("Kosongkan kalau metode ini tidak dibayar lewat {$label} (metode disembunyikan saat {$label} dipakai). Kanal yang sudah dipakai metode lain tidak bisa dipilih.");
            }
        }

        $fields[] = Grid::make(2)->schema([
            TextInput::make('min_amount')->label('Nominal minimal (Rp)')->numeric()->minValue(0)->maxValue(999999999999)->placeholder('Tanpa batas'),
            TextInput::make('max_amount')->label('Nominal maksimal (Rp)')->numeric()->minValue(1)->maxValue(999999999999)->placeholder('Tanpa batas')
                ->helperText(fn (Get $get) => strtoupper((string) $get('kind')) === 'QRIS' ? 'Ketentuan BI: QRIS maksimal Rp10.000.000 per transaksi.' : null),
        ]);

        if ($isNew) {
            $fields[] = Toggle::make('show_at_pos')->label('Tampil di kasir ("Bayar Otomatis")');
        }

        return $fields;
    }

    /** Jenis metode: bawaan dari katalog, tambahan dari kolom `kind`. */
    private function kindOf(OnlinePaymentMethod $record): ?string
    {
        return app(OnlinePaymentMethodService::class)->all()[$record->code]['kind'] ?? $record->kind;
    }

    /** Kode kanal gateway yang sudah dipakai metode lain (satu kanal = satu metode, supaya label pembayaran tidak rancu). */
    private function channelsUsedByOthers(string $gateway, ?string $exceptCode): array
    {
        $methods = app(OnlinePaymentMethodService::class);
        $used = [];
        foreach (array_keys($methods->all()) as $code) {
            if ($code !== $exceptCode) {
                array_push($used, ...($methods->channels($code, $gateway) ?? []));
            }
        }

        return array_values(array_unique($used));
    }

    /**
     * Validasi server untuk tambah / edit metode (isi form bisa direkayasa).
     *
     * @return array{0: ?array, 1: ?array{0: string, 1: string}} [data bersih, [judul, pesan] kalau ditolak]
     */
    private function normalizeMethodData(array $data, ?OnlinePaymentMethod $record): array
    {
        $min = isset($data['min_amount']) && $data['min_amount'] !== '' ? round((float) $data['min_amount'], 2) : null;
        $max = isset($data['max_amount']) && $data['max_amount'] !== '' ? round((float) $data['max_amount'], 2) : null;
        $kind = strtoupper((string) ($record ? $this->kindOf($record) : ($data['kind'] ?? '')));

        if ($min !== null && $max !== null && $min > $max) {
            return [null, ['Batas Nominal Tidak Valid', 'Nominal minimal tidak boleh lebih besar dari nominal maksimal.']];
        }

        // QRIS dibatasi ketentuan BI — batas di sistem tidak boleh dilonggarkan melebihinya (transaksi pasti ditolak).
        if ($kind === 'QRIS' && ($max === null || $max > OnlinePaymentCatalog::QRIS_MAX_AMOUNT)) {
            return [null, ['Batas QRIS Tidak Valid', 'Ketentuan BI: QRIS maksimal Rp10.000.000 per transaksi. Isi nominal maksimal paling besar Rp10.000.000.']];
        }

        $clean = [
            'label' => trim((string) ($data['label'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'badge' => strtoupper(trim((string) ($data['badge'] ?? ''))),
            'min_amount' => $min,
            'max_amount' => $max,
        ];

        if ($clean['label'] === '' || mb_strlen($clean['label']) > 100 || $clean['badge'] === '' || mb_strlen($clean['badge']) > 10
            || ($clean['description'] !== null && mb_strlen($clean['description']) > 255)) {
            return [null, ['Data Tidak Valid', 'Nama tampilan (maks. 100 karakter) dan label singkat (maks. 10 karakter) wajib diisi.']];
        }

        if ($record !== null && ! $record->is_custom) {
            return [$clean, null];
        }

        if (! array_key_exists($kind, OnlinePaymentCatalog::ADDABLE_KINDS)) {
            return [null, ['Jenis Tidak Didukung', 'Metode yang bisa ditambahkan hanya QRIS atau Virtual Account.']];
        }

        $anyChannel = false;
        foreach ([OnlineGatewayRouter::MIDTRANS => 'midtrans_channels', OnlineGatewayRouter::DOKU => 'doku_channels'] as $gateway => $field) {
            $label = OnlineGatewayRouter::label($gateway);
            $selected = array_values(array_unique(array_map('strval', (array) ($data[$field] ?? []))));

            if ($unknown = array_diff($selected, array_keys(OnlinePaymentCatalog::officialChannels($gateway, $kind)))) {
                return [null, ['Kanal Tidak Dikenal', "Kode kanal {$label} ".implode(', ', $unknown).' tidak ada di daftar resmi untuk jenis ini.']];
            }
            if ($taken = array_intersect($selected, $this->channelsUsedByOthers($gateway, $record?->code))) {
                return [null, ['Kanal Sudah Dipakai', "Kanal {$label} ".implode(', ', $taken).' sudah dipakai metode lain. Satu kanal hanya untuk satu metode.']];
            }

            $clean[$field] = $selected;
            $anyChannel = $anyChannel || $selected !== [];
        }

        if (! $anyChannel) {
            return [null, ['Kanal Belum Dipilih', 'Pilih minimal satu kanal Midtrans atau DOKU — tanpa kanal, metode ini tidak bisa dibayar.']];
        }

        if ($record === null) {
            $clean['kind'] = $kind;
        }

        return [$clean, null];
    }

    public function createMethod(array $data): void
    {
        $this->authorizeManage();

        [$clean, $error] = $this->normalizeMethodData($data, null);
        if ($error) {
            Notification::make()->title($error[0])->body($error[1])->danger()->persistent()->send();

            return;
        }

        $method = DB::transaction(fn () => OnlinePaymentMethod::create($clean + [
            'code' => $this->newMethodCode($clean['label'], $clean['kind']),
            'is_custom' => true,
            // Nonaktif dulu: kanal yang belum aktif di dashboard gateway membuat customer gagal membayar.
            'is_active' => false,
            'show_at_pos' => (bool) ($data['show_at_pos'] ?? false),
            'sort_order' => (int) OnlinePaymentMethod::query()->lockForUpdate()->max('sort_order') + 1,
        ]));

        Notification::make()->title('Metode Ditambahkan')
            ->body("{$method->label} ({$method->code}) dibuat NONAKTIF. Aktifkan setelah kanalnya aktif di dashboard gateway.")
            ->success()->send();
    }

    /**
     * Kode metode baru dari nama tampilan, mis. "Danamon" (VA) → DANAMON_VA. Tidak boleh sama dengan metode bawaan /
     * metode kasir (CASH, QRIS_STATIS, DEBIT_CARD, …) karena laporan kasir mengelompokkan pembayaran dari kode ini.
     */
    private function newMethodCode(string $label, string $kind): string
    {
        $base = trim(substr(strtoupper(Str::slug($label, '_')), 0, 22), '_') ?: $kind;
        if ($base !== $kind && ! str_starts_with($base, $kind.'_') && ! str_ends_with($base, '_'.$kind)) {
            $base .= '_'.$kind;
        }

        $reserved = array_merge(array_keys(OnlinePaymentCatalog::all()), [
            'CASH', 'QRIS_STATIS', 'DEBIT_CARD', 'CREDIT_CARD', 'DEBIT', 'CREDIT', 'EDC_BCA', 'EDC_MANDIRI', 'TRANSFER', 'MIDTRANS', 'DOKU',
        ]);

        $code = $base;
        for ($n = 2; in_array($code, $reserved, true) || OnlinePaymentMethod::where('code', $code)->exists(); $n++) {
            $code = $base.'_'.$n;
        }

        return $code;
    }

    public function saveGatewaySettings(array $data): void
    {
        $this->authorizeManageGateway();

        $router = app(OnlineGatewayRouter::class);
        $online = strtoupper((string) ($data['online_gateway'] ?? ''));
        $pos = strtoupper((string) ($data['pos_qris_gateway'] ?? ''));

        foreach ([[$online, false], [$pos, true]] as [$gateway, $forPos]) {
            if ($reason = $router->unavailableReason($gateway, $forPos)) {
                Notification::make()->title('Gateway Tidak Bisa Dipilih')->body(OnlineGatewayRouter::label($gateway).': '.$reason)->danger()->persistent()->send();

                return;
            }
        }

        // Jangan sampai customer tidak punya satu pun metode yang bisa dibayar lewat gateway baru.
        if (app(OnlinePaymentMethodService::class)->available(null, $online) === []) {
            Notification::make()->title('Tidak Ada Metode Aktif')
                ->body('Belum ada metode aktif yang punya kanal '.OnlineGatewayRouter::label($online).'. Tambahkan / aktifkan metodenya dulu, supaya customer tetap bisa membayar.')
                ->danger()->persistent()->send();

            return;
        }

        // QR kasir lewat gateway yang tidak punya metode "Tampil di Kasir" = tombol Bayar Otomatis hilang diam-diam.
        $posMethods = array_filter(app(OnlinePaymentMethodService::class)->available(null, $pos), fn (array $m) => $m['show_at_pos'] ?? false);
        if ($posMethods === []) {
            Notification::make()->title('Tidak Ada Metode Kasir')
                ->body('Belum ada metode aktif "Tampil di Kasir" yang punya kanal '.OnlineGatewayRouter::label($pos).'. Aktifkan metodenya dulu (mis. QRIS), supaya Bayar Otomatis di kasir tetap bisa dipakai.')
                ->danger()->persistent()->send();

            return;
        }

        DB::transaction(function () use ($online, $pos) {
            $setting = PaymentGatewaySetting::query()->lockForUpdate()->first() ?? new PaymentGatewaySetting();
            $setting->fill(['online_gateway' => $online, 'pos_qris_gateway' => $pos])->save();
        });

        Notification::make()->title('Gateway Tersimpan')
            ->body('Online: '.OnlineGatewayRouter::label($online).', QR kasir: '.OnlineGatewayRouter::label($pos).' — berlaku untuk tagihan baru.')
            ->success()->send();
    }

    public function toggle(OnlinePaymentMethod $record): void
    {
        $this->authorizeManage();

        DB::transaction(function () use ($record) {
            $record = OnlinePaymentMethod::whereKey($record->id)->lockForUpdate()->firstOrFail();

            // Metode lain yang masih bisa dibayar lewat gateway online yang dipakai (metode tanpa kanalnya tidak dihitung).
            $methods = app(OnlinePaymentMethodService::class);
            $gateway = app(OnlineGatewayRouter::class)->onlineGateway();
            $otherPayable = OnlinePaymentMethod::where('is_active', true)->whereKeyNot($record->id)->lockForUpdate()->get()
                ->contains(fn (OnlinePaymentMethod $m) => ! empty($methods->channels($m->code, $gateway)));

            if ($record->is_active && ! $otherPayable) {
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

        $record = OnlinePaymentMethod::findOrFail($record->id);
        [$clean, $error] = $this->normalizeMethodData($data, $record);
        if ($error) {
            Notification::make()->title($error[0])->body($error[1])->danger()->persistent()->send();

            return;
        }

        $record->update($clean);

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
