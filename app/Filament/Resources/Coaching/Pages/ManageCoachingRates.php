<?php

namespace App\Filament\Resources\Coaching\Pages;

use App\Filament\Resources\Coaching\CoachingRateResource;
use App\Models\Coaching\CoachingSetting;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;

class ManageCoachingRates extends ManageRecords
{
    protected static string $resource = CoachingRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Tarif')->modalWidth('2xl'),
            Action::make('coachingSettings')
                ->label('Pengaturan Coaching')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->visible(fn () => auth()->user()?->can('manage_coaching_rates') ?? false)
                ->authorize(fn () => auth()->user()?->can('manage_coaching_rates') ?? false)
                ->modalHeading('Pengaturan Coaching')
                ->modalDescription('Berlaku untuk booking coaching BARU & reschedule. Booking yang sudah dibayar tidak berubah.')
                ->modalWidth('lg')
                ->fillForm(fn () => [
                    'extra_coach_fee_per_hour' => (float) CoachingSetting::current()->extra_coach_fee_per_hour,
                    'admin_whatsapp' => CoachingSetting::current()->admin_whatsapp,
                ])
                ->schema([
                    TextInput::make('extra_coach_fee_per_hour')->label('Biaya coach tambahan / jam')->numeric()->prefix('Rp')->required()
                        ->minValue(0)->maxValue(100000000)
                        ->helperText('Ditambahkan per jam untuk tiap coach tambahan di sesi yang sama. Rp0 = setoran tetap per jam lapangan.'),
                    TextInput::make('admin_whatsapp')->label('No. WhatsApp admin coaching')->tel()->maxLength(20),
                ])
                ->action(fn (array $data) => $this->saveSettings($data)),
        ];
    }

    public function saveSettings(array $data): void
    {
        // Validasi ulang di server (aksi Livewire bisa dipanggil langsung).
        abort_unless(auth()->user()?->can('manage_coaching_rates'), 403, 'Akses ditolak: Anda tidak memiliki izin [manage_coaching_rates].');

        $fee = (float) ($data['extra_coach_fee_per_hour'] ?? -1);
        if ($fee < 0 || $fee > 100000000) {
            Notification::make()->title('Biaya Tidak Valid')->body('Biaya coach tambahan Rp0 – Rp100.000.000 per jam.')->danger()->send();

            return;
        }
        $phone = trim((string) ($data['admin_whatsapp'] ?? ''));
        $phone = $phone === '' ? null : (PhoneNumber::normalize($phone) ?? mb_substr($phone, 0, 20));

        DB::transaction(function () use ($fee, $phone) {
            $setting = CoachingSetting::query()->lockForUpdate()->first() ?? new CoachingSetting;
            $setting->fill(['extra_coach_fee_per_hour' => round($fee, 2), 'admin_whatsapp' => $phone])->save();
        });

        Notification::make()->title('Pengaturan Coaching Tersimpan')->success()->send();
    }
}
