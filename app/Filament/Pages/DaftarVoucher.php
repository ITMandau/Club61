<?php

namespace App\Filament\Pages;

use App\Models\Pos\Order;
use App\Models\Pos\Voucher;
use App\Services\Audit\ActivityLogger;
use App\Services\Finance\LedgerReport;
use App\Services\Finance\VoucherService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Modul 21 — semua voucher dalam satu tabel: voucher saldo customer (dari refund yang ditolak) dan kode promo.
 * Sisa saldo voucher = uang customer yang masih disimpan klub, jadi ringkasannya tampil di atas tabel.
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

    public const STATUSES = ['AKTIF' => 'Aktif', 'HABIS' => 'Saldo / kuota habis', 'KEDALUWARSA' => 'Kedaluwarsa', 'NONAKTIF' => 'Dinonaktifkan'];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:DaftarVoucher');
    }

    public static function statusOf(Voucher $v): string
    {
        return match (true) {
            ! $v->is_active => 'NONAKTIF',
            $v->valid_until && $v->valid_until->lt(now()) => 'KEDALUWARSA',
            $v->isCredit() ? (float) $v->balance <= 0 : ($v->quota !== null && $v->quota <= 0) => 'HABIS',
            default => 'AKTIF',
        };
    }

    /** Filter status di SQL — sama dengan statusOf(). */
    public static function applyStatus(Builder $query, ?string $status): Builder
    {
        $notExpired = fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('valid_until')->orWhere('valid_until', '>=', now()));
        $empty = fn (Builder $q) => $q->where(fn ($w) => $w
            ->where(fn ($c) => $c->where('discount_type', Voucher::TYPE_CREDIT)->where('balance', '<=', 0))
            ->orWhere(fn ($p) => $p->where('discount_type', '!=', Voucher::TYPE_CREDIT)->whereNotNull('quota')->where('quota', '<=', 0)));

        return match ($status) {
            'NONAKTIF' => $query->where('is_active', false),
            'KEDALUWARSA' => $query->where('is_active', true)->whereNotNull('valid_until')->where('valid_until', '<', now()),
            'HABIS' => $empty($notExpired($query->where('is_active', true))),
            'AKTIF' => $notExpired($query->where('is_active', true))->where(fn ($w) => $w
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
        return $table
            ->query(Voucher::query()->with(['user:id,name,phone', 'refund:id,order_id,padel_booking_id', 'refund.booking:id,booking_code', 'refund.order:id,order_number']))
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Belum ada voucher pada filter ini')
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Kode voucher disalin')
                    ->description(fn (Voucher $v) => match ($v->discount_type) {
                        Voucher::TYPE_CREDIT => 'Voucher saldo',
                        'PERCENT' => 'Promo '.rtrim(rtrim(number_format((float) $v->discount_value, 2, ',', '.'), '0'), ',').'%',
                        default => 'Promo potongan tetap',
                    }),
                TextColumn::make('user.name')
                    ->label('Pemilik')
                    ->placeholder('Semua customer')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('user', fn ($u) => $u
                        ->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
                    ->description(fn (Voucher $v) => $v->user?->phone),
                TextColumn::make('refund_id')
                    ->label('Asal')
                    ->formatStateUsing(fn ($state, Voucher $v) => 'Refund ditolak')
                    ->description(fn (Voucher $v) => $v->refund?->booking?->booking_code ?? $v->refund?->order?->order_number)
                    ->placeholder('Dibuat manual'),
                TextColumn::make('discount_value')
                    ->label('Nilai')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, Voucher $v) => $v->discount_type === 'PERCENT'
                        ? rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',').'%'
                        : BukuTransaksi::rupiah($state)),
                TextColumn::make('balance')
                    ->label('Sisa')
                    ->alignEnd()
                    ->weight('bold')
                    ->state(fn (Voucher $v) => $v->isCredit() ? BukuTransaksi::rupiah($v->balance) : ($v->quota === null ? 'Tanpa batas' : $v->quota.' kali')),
                TextColumn::make('used_count')->label('Dipakai')->alignCenter()->suffix('x')->sortable(),
                TextColumn::make('valid_until')
                    ->label('Berlaku s/d')
                    ->date('d M Y', LedgerReport::TIMEZONE)
                    ->placeholder('Tanpa batas')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Voucher $v) => self::STATUSES[self::statusOf($v)])
                    ->color(fn (Voucher $v) => match (self::statusOf($v)) {
                        'AKTIF' => 'success',
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
            ->recordActions([
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
                Action::make('deactivate')
                    ->label('Nonaktifkan')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Voucher $v) => $v->is_active)
                    ->authorize(fn () => auth()->user()?->can('process_refund_queue') ?? false)
                    ->requiresConfirmation()
                    ->modalHeading(fn (Voucher $v) => 'Nonaktifkan voucher '.$v->code)
                    ->modalDescription(fn (Voucher $v) => $v->isCredit()
                        ? 'Sisa saldo '.BukuTransaksi::rupiah($v->balance).' tidak bisa dipakai lagi oleh customer. Gunakan hanya untuk voucher yang salah terbit. Tercatat di Log Aktivitas (kritis).'
                        : 'Kode promo ini tidak bisa dipakai lagi. Tercatat di Log Aktivitas.')
                    ->schema([
                        Textarea::make('reason')->label('Alasan')->required()->minLength(5)->maxLength(500)->rows(2),
                    ])
                    ->action(function (Voucher $record, array $data) {
                        $record->update(['is_active' => false]);

                        ActivityLogger::record(
                            module: 'FINANCE',
                            event: 'voucher.deactivated',
                            description: 'Menonaktifkan voucher '.$record->code.($record->isCredit() ? ' (sisa saldo '.ActivityLogger::rupiah((float) $record->balance).')' : '').': '.$data['reason'],
                            subject: $record,
                            meta: ['voucher' => $record->code, 'sisa_saldo' => $record->isCredit() ? (float) $record->balance : null, 'alasan' => $data['reason']],
                            severity: $record->isCredit() ? ActivityLogger::CRITICAL : ActivityLogger::WARNING,
                        );

                        Notification::make()->title('Voucher '.$record->code.' dinonaktifkan.')->success()->send();
                    }),
            ]);
    }
}
