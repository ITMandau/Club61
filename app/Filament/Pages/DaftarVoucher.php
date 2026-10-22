<?php

namespace App\Filament\Pages;

use App\Models\Pos\Order;
use App\Models\Pos\Voucher;
use App\Services\Audit\ActivityLogger;
use App\Services\Finance\LedgerReport;
use App\Services\Finance\PromoVoucherService;
use App\Services\Finance\VoucherService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use UnitEnum;

/**
 * Modul 21 + 22 — semua voucher dalam satu tabel: kode promo (dibuat & diubah di sini, cara sebar A) dan voucher saldo
 * customer (dari refund yang ditolak, terbit otomatis). Jenisnya dibedakan di kolom Kode & Asal. Sisa saldo voucher =
 * uang customer yang masih disimpan klub, jadi ringkasannya tampil di atas tabel. Aturan pemakaian promo dicek di
 * VoucherService::resolve() (online & kasir).
 */
class DaftarVoucher extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Daftar Voucher';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Daftar Voucher';

    protected string $view = 'filament.pages.daftar-voucher';

    public const STATUSES = ['AKTIF' => 'Aktif', 'TERJADWAL' => 'Terjadwal', 'HABIS' => 'Saldo / kuota habis', 'KEDALUWARSA' => 'Kedaluwarsa', 'NONAKTIF' => 'Dinonaktifkan'];

    /** Order yang dihitung "memakai promo" di kolom Dipakai: lunas (penuh / sebagian), dan promonya tidak dikembalikan. */
    private const COUNTED_STATUSES = ['PAID', 'PARTIALLY_PAID'];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:DaftarVoucher');
    }

    /** Membuat / mengubah / mengaktifkan kode promo. */
    public static function canManagePromo(): bool
    {
        return (bool) auth()->user()?->can('manage_vouchers');
    }

    public static function statusOf(Voucher $v): string
    {
        return match (true) {
            ! $v->is_active => 'NONAKTIF',
            $v->valid_until && $v->valid_until->lt(now()) => 'KEDALUWARSA',
            ! $v->isCredit() && $v->valid_from && $v->valid_from->gt(now()) => 'TERJADWAL',
            $v->isCredit() ? (float) $v->balance <= 0 : ($v->quota !== null && $v->quota <= 0) => 'HABIS',
            default => 'AKTIF',
        };
    }

    /** Filter status di SQL — sama dengan statusOf(). */
    public static function applyStatus(Builder $query, ?string $status): Builder
    {
        $notExpired = fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('valid_until')->orWhere('valid_until', '>=', now()));
        $started = fn (Builder $q) => $q->where(fn ($w) => $w->where('discount_type', Voucher::TYPE_CREDIT)
            ->orWhereNull('valid_from')->orWhere('valid_from', '<=', now()));
        $empty = fn (Builder $q) => $q->where(fn ($w) => $w
            ->where(fn ($c) => $c->where('discount_type', Voucher::TYPE_CREDIT)->where('balance', '<=', 0))
            ->orWhere(fn ($p) => $p->where('discount_type', '!=', Voucher::TYPE_CREDIT)->whereNotNull('quota')->where('quota', '<=', 0)));

        return match ($status) {
            'NONAKTIF' => $query->where('is_active', false),
            'KEDALUWARSA' => $query->where('is_active', true)->whereNotNull('valid_until')->where('valid_until', '<', now()),
            'TERJADWAL' => $notExpired($query->where('is_active', true))
                ->where('discount_type', '!=', Voucher::TYPE_CREDIT)->where('valid_from', '>', now()),
            'HABIS' => $empty($started($notExpired($query->where('is_active', true)))),
            'AKTIF' => $started($notExpired($query->where('is_active', true)))->where(fn ($w) => $w
                ->where(fn ($c) => $c->where('discount_type', Voucher::TYPE_CREDIT)->where('balance', '>', 0))
                ->orWhere(fn ($p) => $p->where('discount_type', '!=', Voucher::TYPE_CREDIT)->where(fn ($q) => $q->whereNull('quota')->orWhere('quota', '>', 0)))),
            default => $query,
        };
    }

    protected function getViewData(): array
    {
        $credit = Voucher::where('discount_type', Voucher::TYPE_CREDIT);
        $active = self::applyStatus(clone $credit, 'AKTIF');

        return [
            'summary' => [
                'issued_count' => (clone $credit)->count(),
                'issued_amount' => (float) (clone $credit)->sum('discount_value'),
                'active_count' => (clone $active)->count(),
                'outstanding' => (float) (clone $active)->sum('balance'),
                'used_amount' => (float) (clone $credit)->sum(DB::raw('discount_value - COALESCE(balance, 0)')),
                'expired_balance' => (float) self::applyStatus(clone $credit, 'KEDALUWARSA')->sum('balance'),
            ],
        ];
    }

    public function table(Table $table): Table
    {
        $orders = fn () => Order::query()->whereColumn('orders.voucher_code', 'vouchers.code')
            ->whereIn('payment_status', self::COUNTED_STATUSES)->whereNull('voucher_restored_at');
        $promo = app(PromoVoucherService::class);

        return $table
            ->query(Voucher::query()
                ->select('vouchers.*')
                ->addSelect([
                    'report_discount' => $orders()->selectRaw('COALESCE(SUM(discount_amount), 0)'),
                    'report_orders' => $orders()->selectRaw('COUNT(*)'),
                ])
                ->with(['user:id,name,phone', 'refund:id,order_id,padel_booking_id', 'refund.booking:id,booking_code', 'refund.order:id,order_number']))
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Belum ada voucher pada filter ini')
            ->headerActions([
                Action::make('create')
                    ->label('Buat Promo')
                    ->icon('heroicon-o-plus')
                    ->authorize(fn () => self::canManagePromo())
                    ->modalHeading('Buat promo baru')
                    ->modalDescription('Cara sebar A — satu kode umum untuk semua orang (disebar lewat sosmed, WhatsApp, spanduk). Satu akun hanya bisa memakai promo yang sama sekali; promo hanya memotong sewa lapangan.')
                    ->modalSubmitActionLabel('Simpan promo')
                    ->modalWidth('3xl')
                    ->schema(fn () => $this->promoForm(null))
                    ->fillForm(fn () => $this->formDefaults(null))
                    ->action(fn (array $data) => $this->run(
                        fn () => app(PromoVoucherService::class)->create($data, auth()->user()),
                        fn (Voucher $v) => "Promo {$v->code} dibuat.",
                    )),
            ])
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable(['code', 'name'])
                    ->copyable()
                    ->copyMessage('Kode voucher disalin')
                    ->description(fn (Voucher $v) => $v->isCredit() ? 'Voucher saldo' : ($v->name ?: 'Kode promo')),
                TextColumn::make('user.name')
                    ->label('Pemilik')
                    ->placeholder('Semua customer')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('user', fn ($u) => $u
                        ->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
                    ->description(fn (Voucher $v) => $v->user?->phone),
                TextColumn::make('origin')
                    ->label('Asal')
                    ->state(fn (Voucher $v) => $v->isCredit() ? 'Refund ditolak' : 'Kode promo')
                    ->description(fn (Voucher $v) => $v->isCredit()
                        ? ($v->refund?->booking?->booking_code ?? $v->refund?->order?->order_number)
                        : (Voucher::CHANNELS[$v->channel] ?? null)),
                TextColumn::make('discount_value')
                    ->label('Nilai')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, Voucher $v) => $v->isCredit() ? BukuTransaksi::rupiah($state) : $promo->describe($v))
                    ->description(fn (Voucher $v) => $v->isCredit() ? null
                        : ((float) $v->min_order_amount > 0 ? 'Min. '.BukuTransaksi::rupiah($v->min_order_amount).' · ' : '').'sewa lapangan'),
                TextColumn::make('balance')
                    ->label('Sisa')
                    ->alignEnd()
                    ->weight('bold')
                    ->state(fn (Voucher $v) => $v->isCredit() ? BukuTransaksi::rupiah($v->balance) : ($v->quota === null ? 'Tanpa batas' : $v->quota.' kali')),
                TextColumn::make('used_count')
                    ->label('Dipakai')
                    ->alignCenter()
                    ->suffix('x')
                    ->sortable()
                    ->description(fn (Voucher $v) => ! $v->isCredit() && $v->report_orders > 0
                        ? 'Potongan '.BukuTransaksi::rupiah($v->report_discount)
                        : null),
                TextColumn::make('valid_until')
                    ->label('Berlaku s/d')
                    ->date('d M Y', LedgerReport::TIMEZONE)
                    ->placeholder('Tanpa batas')
                    ->sortable()
                    ->description(fn (Voucher $v) => ! $v->isCredit() && $v->valid_from
                        ? 'Mulai '.$v->valid_from->timezone(LedgerReport::TIMEZONE)->format('d M Y')
                        : null),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Voucher $v) => self::STATUSES[self::statusOf($v)])
                    ->color(fn (Voucher $v) => match (self::statusOf($v)) {
                        'AKTIF' => 'success',
                        'TERJADWAL' => 'info',
                        'HABIS' => 'gray',
                        'KEDALUWARSA' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('created_at')->label('Diterbitkan')->dateTime('d M Y, H:i', LedgerReport::TIMEZONE)->sortable(),
            ])
            ->filters([
                SelectFilter::make('jenis')
                    ->label('Jenis')
                    ->options(['CREDIT' => 'Voucher saldo (refund)', 'PROMO' => 'Kode promo'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'CREDIT' => $query->where('discount_type', Voucher::TYPE_CREDIT),
                        'PROMO' => $query->where('discount_type', '!=', Voucher::TYPE_CREDIT),
                        default => $query,
                    }),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::STATUSES)
                    ->query(fn (Builder $query, array $data) => self::applyStatus($query, $data['value'] ?? null)),
            ])
            ->recordActions([ActionGroup::make([
                Action::make('usage')
                    ->label('Pemakaian')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->modalHeading(fn (Voucher $v) => 'Pemakaian voucher '.$v->code)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (Voucher $v) => view('filament.pages.partials.voucher-usage', [
                        'voucher' => $v,
                        'orders' => Order::where('voucher_code', $v->code)->with('user:id,name')->latest()->limit(50)->get(),
                        'available' => app(VoucherService::class)->availableBalance($v),
                    ])),

                // ---- Kode promo ----
                Action::make('edit')
                    ->label('Ubah')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (Voucher $v) => ! $v->isCredit())
                    ->authorize(fn () => self::canManagePromo())
                    ->modalHeading(fn (Voucher $v) => 'Ubah promo '.$v->code)
                    ->modalDescription(fn (Voucher $v) => app(PromoVoucherService::class)->hasBeenUsed($v)
                        ? 'Promo ini sudah dipakai — kode, potongan, dan kategori dikunci. Periode, kuota, kanal, dan aturan gabung masih bisa diubah.'
                        : null)
                    ->modalSubmitActionLabel('Simpan perubahan')
                    ->modalWidth('3xl')
                    ->schema(fn (Voucher $record) => $this->promoForm($record))
                    ->fillForm(fn (Voucher $record) => $this->formDefaults($record))
                    ->action(fn (Voucher $record, array $data) => $this->run(
                        // Isian yang dikunci (disabled) tidak ikut terkirim — pakai nilai yang tersimpan.
                        fn () => app(PromoVoucherService::class)->update($record, $data + $this->formDefaults($record), auth()->user()),
                        fn (Voucher $v) => "Promo {$v->code} diperbarui.",
                    )),
                Action::make('togglePromo')
                    ->label(fn (Voucher $v) => $v->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (Voucher $v) => $v->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-play-circle')
                    ->color(fn (Voucher $v) => $v->is_active ? 'danger' : 'success')
                    ->visible(fn (Voucher $v) => ! $v->isCredit())
                    ->authorize(fn () => self::canManagePromo())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Voucher $v) => ($v->is_active ? 'Nonaktifkan' : 'Aktifkan').' promo '.$v->code.'?')
                    ->modalDescription(fn (Voucher $v) => $v->is_active
                        ? 'Kode tidak bisa dipakai lagi di checkout online maupun kasir. Pesanan yang sudah memakainya tidak berubah. Tercatat di Log Aktivitas.'
                        : 'Kode bisa dipakai lagi selama masih dalam periode dan kuota.')
                    ->action(fn (Voucher $record) => $this->run(
                        fn () => app(PromoVoucherService::class)->setActive($record, ! $record->is_active, auth()->user()),
                        fn (Voucher $v) => 'Promo '.$v->code.($v->is_active ? ' diaktifkan.' : ' dinonaktifkan.'),
                    )),

                // ---- Voucher saldo customer ----
                Action::make('deactivate')
                    ->label('Nonaktifkan')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Voucher $v) => $v->isCredit() && $v->is_active)
                    ->authorize(fn () => auth()->user()?->can('process_refund_queue') ?? false)
                    ->requiresConfirmation()
                    ->modalHeading(fn (Voucher $v) => 'Nonaktifkan voucher '.$v->code)
                    ->modalDescription(fn (Voucher $v) => 'Sisa saldo '.BukuTransaksi::rupiah($v->balance).' tidak bisa dipakai lagi oleh customer. Gunakan hanya untuk voucher yang salah terbit. Tercatat di Log Aktivitas (kritis).')
                    ->schema([
                        Textarea::make('reason')->label('Alasan')->required()->minLength(5)->maxLength(500)->rows(2),
                    ])
                    ->action(function (Voucher $record, array $data) {
                        $record->update(['is_active' => false]);

                        ActivityLogger::record(
                            module: 'FINANCE',
                            event: 'voucher.deactivated',
                            description: 'Menonaktifkan voucher '.$record->code.' (sisa saldo '.ActivityLogger::rupiah((float) $record->balance).'): '.$data['reason'],
                            subject: $record,
                            meta: ['voucher' => $record->code, 'sisa_saldo' => (float) $record->balance, 'alasan' => $data['reason']],
                            severity: ActivityLogger::CRITICAL,
                        );

                        Notification::make()->title('Voucher '.$record->code.' dinonaktifkan.')->success()->send();
                    }),
            ])->tooltip('Aksi voucher')]);
    }

    /** Isian promo (PRD Modul 22 §4.2). Isian yang mengubah arti promo dikunci setelah promo pernah dipakai. */
    private function promoForm(?Voucher $record): array
    {
        $locked = $record && app(PromoVoucherService::class)->hasBeenUsed($record);

        return [
            Section::make('Promo')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')->label('Nama promo')->required()->maxLength(100)->placeholder('Promo Pembukaan November'),
                    TextInput::make('code')->label('Kode')
                        ->placeholder('Kosongkan = dibuat otomatis')
                        ->helperText('Huruf, angka, tanda hubung (4–30). Customer mengetik kode ini di checkout / menyebutkannya ke kasir.')
                        ->maxLength(30)
                        ->disabled($locked)
                        ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                ]),
                Radio::make('distribution')->label('Cara sebar')
                    ->options([
                        Voucher::DISTRIBUTION_CODE => 'A. Kode umum — satu kode untuk semua orang',
                        'UNIQUE' => 'B. Kode unik massal (menyusul)',
                        'CLAIM' => 'C. Klaim ke akun (menyusul)',
                        'AUTO' => 'D. Otomatis (menyusul)',
                    ])
                    ->disableOptionWhen(fn (string $value) => $value !== Voucher::DISTRIBUTION_CODE)
                    ->default(Voucher::DISTRIBUTION_CODE)
                    ->dehydrated(false),
            ]),
            Section::make('Potongan')->schema([
                Grid::make(3)->schema([
                    Radio::make('discount_type')->label('Jenis')
                        ->options([Voucher::TYPE_PERCENT => 'Persen (%)', Voucher::TYPE_FIXED => 'Rupiah (Rp)'])
                        ->required()->live()->disabled($locked),
                    TextInput::make('discount_value')
                        ->label(fn (Get $get) => $get('discount_type') === Voucher::TYPE_FIXED ? 'Potongan (Rp)' : 'Potongan (%)')
                        ->numeric()->required()->minValue(0)->disabled($locked),
                    TextInput::make('max_discount_amount')->label('Maksimal potongan (Rp)')
                        ->numeric()->minValue(0)->placeholder('Tanpa batas')
                        ->visible(fn (Get $get) => $get('discount_type') === Voucher::TYPE_PERCENT)->disabled($locked),
                ]),
                Grid::make(2)->schema([
                    TextInput::make('min_order_amount')->label('Minimal transaksi (Rp)')->numeric()->minValue(0)->placeholder('Tanpa minimal')
                        ->helperText('Dihitung dari nilai kategori yang dicentang (mis. sewa lapangan saja).'),
                    CheckboxList::make('categories')->label('Berlaku untuk')
                        ->options(Voucher::PROMO_CATEGORIES)->required()->disabled($locked)
                        ->helperText('Sewa lapangan saja — raket & bola tidak ikut dipotong.'),
                ]),
            ]),
            Section::make('Periode, kuota & aturan')->schema([
                Grid::make(3)->schema([
                    DatePicker::make('valid_from')->label('Mulai')->native(false)->displayFormat('d M Y')->placeholder('Langsung berlaku'),
                    DatePicker::make('valid_until')->label('Berakhir')->native(false)->displayFormat('d M Y')->required(),
                    TextInput::make('quota')->label('Kuota pemakaian')->numeric()->minValue(1)->placeholder('Tanpa batas')
                        ->helperText($record ? 'Total kuota, termasuk yang sudah dipakai ('.$record->used_count.').' : 'Jumlah order lunas yang boleh memakai promo ini.'),
                ]),
                Grid::make(3)->schema([
                    Radio::make('channel')->label('Kanal')->options(Voucher::CHANNELS)->required(),
                    Toggle::make('stack_with_membership')->label('Boleh digabung benefit member')
                        ->helperText('Mati = promo ditolak kalau booking memakai kuota / diskon member.'),
                    Toggle::make('is_active')->label('Aktif'),
                ]),
            ]),
        ];
    }

    private function formDefaults(?Voucher $record): array
    {
        if (! $record) {
            return [
                'distribution' => Voucher::DISTRIBUTION_CODE,
                'discount_type' => Voucher::TYPE_PERCENT,
                'categories' => ['PADEL'],
                'channel' => Voucher::CHANNEL_ALL,
                'stack_with_membership' => false,
                'is_active' => true,
            ];
        }

        return [
            'name' => $record->name ?? $record->code,
            'code' => $record->code,
            'distribution' => $record->distribution ?: Voucher::DISTRIBUTION_CODE,
            'discount_type' => $record->discount_type,
            'discount_value' => (float) $record->discount_value,
            'max_discount_amount' => $record->max_discount_amount !== null ? (float) $record->max_discount_amount : null,
            'min_order_amount' => (float) $record->min_order_amount ?: null,
            'categories' => $record->promoCategories(),
            'channel' => $record->channel ?: Voucher::CHANNEL_ALL,
            'stack_with_membership' => (bool) $record->stack_with_membership,
            'quota' => $record->totalQuota(),
            'valid_from' => $record->valid_from?->timezone(LedgerReport::TIMEZONE)->toDateString(),
            'valid_until' => $record->valid_until?->timezone(LedgerReport::TIMEZONE)->toDateString(),
            'is_active' => (bool) $record->is_active,
        ];
    }

    private function run(\Closure $callback, \Closure $success): void
    {
        try {
            $result = $callback();
            Notification::make()->title($success($result))->success()->send();
        } catch (HttpException $e) {
            Notification::make()->title('Promo tidak disimpan')->body($e->getMessage())->danger()->send();
        }
    }
}
