<?php

namespace App\Filament\Resources\Padel\Pages;

use App\Filament\Resources\Padel\PadelRecurringScheduleResource;
use App\Models\Padel\PadelRecurringSchedule;
use App\Services\Padel\BookingTimeService;
use App\Services\Padel\RecurringScheduleService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Grid;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ManagePadelRecurringSchedules extends ManageRecords
{
    protected static string $resource = PadelRecurringScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Batas reschedule GLOBAL (semua booking, bukan hanya jadwal tetap) — izin sama dengan waktu tahan slot.
            Action::make('rescheduleCutoff')
                ->label(fn () => 'Batas Reschedule: H-'.BookingTimeService::formatCutoff(app(BookingTimeService::class)->rescheduleCutoffHours()))
                ->icon('heroicon-o-clock')->color('gray')
                ->visible(fn () => (bool) auth()->user()?->can('manage_booking_time_limits'))
                ->modalHeading('Atur Batas Reschedule')
                ->modalDescription('Berlaku untuk SEMUA booking (customer, member, corporate, jadwal tetap) dan langsung aktif. Lewat batas ini tombol Reschedule di Kelola Pemesanan nonaktif dan server menolak. Contoh: 3 hari = H-3. Maks. 30 hari.')
                ->modalWidth('md')
                ->modalSubmitActionLabel('Simpan')
                ->fillForm(function () {
                    $hours = app(BookingTimeService::class)->rescheduleCutoffHours();

                    return $hours % 24 === 0
                        ? ['value' => intdiv($hours, 24), 'unit' => 'DAYS']
                        : ['value' => $hours, 'unit' => 'HOURS'];
                })
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('value')->label('Paling lambat')->required()->integer()->minValue(1)->maxValue(720),
                        Select::make('unit')->label('Satuan')->required()->native(false)
                            ->options(['HOURS' => 'jam sebelum main', 'DAYS' => 'hari sebelum main']),
                    ]),
                ])
                ->action(function (array $data) {
                    abort_unless(auth()->user()?->can('manage_booking_time_limits'), 403);
                    $hours = (int) $data['value'] * (($data['unit'] ?? 'HOURS') === 'DAYS' ? 24 : 1);

                    try {
                        app(BookingTimeService::class)->setRescheduleCutoffHours($hours);
                        Notification::make()->title('Batas reschedule tersimpan')
                            ->body('Reschedule paling lambat '.BookingTimeService::formatCutoff($hours).' sebelum main, untuk semua booking.')
                            ->success()->send();
                    } catch (HttpException $e) {
                        Notification::make()->title('Belum bisa disimpan')->body($e->getMessage())->danger()->send();
                    }
                }),
            CreateAction::make()->label('Tambah Jadwal Tetap')->modalWidth('3xl')
                ->mutateDataUsing(fn (array $data, CreateAction $action) => PadelRecurringScheduleResource::normalizeOrHalt($data, $action) + [
                    'status' => PadelRecurringSchedule::STATUS_ACTIVE,
                    'created_by_id' => auth()->id(),
                ])
                // Langsung buatkan booking minggu-minggu dalam jangkauan.
                ->after(fn (PadelRecurringSchedule $record) => PadelRecurringScheduleResource::notifyGenerated(
                    app(RecurringScheduleService::class)->generate($record, auth()->user())
                )),
        ];
    }
}
