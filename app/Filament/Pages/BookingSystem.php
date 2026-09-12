<?php

namespace App\Filament\Pages;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Services\Padel\PadelBookingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class BookingSystem extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Booking System';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Booking System Lapangan';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.booking-system';

    // Check-In Modal State
    public bool $showCheckInModal = false;
    public string $checkInQuery = '';
    public ?array $checkInResult = null;

    public function openCheckInModal(?string $code = null): void
    {
        $this->checkInQuery = $code ?? '';
        $this->checkInResult = null;
        $this->showCheckInModal = true;
    }

    public function closeCheckInModal(): void
    {
        $this->showCheckInModal = false;
        $this->checkInQuery = '';
        $this->checkInResult = null;
    }

    public function executeCheckIn(PadelBookingService $service): void
    {
        $code = trim($this->checkInQuery);
        if (empty($code)) {
            Notification::make()
                ->title('Input Kosong')
                ->body('Silakan scan barcode atau masukkan Kode Booking / Hash QR.')
                ->warning()
                ->send();
            return;
        }

        try {
            $staffUser = auth()->user() ?? \App\Models\User::where('role', 'ADMIN')->first();
            $result = $service->checkIn($code, $staffUser);
            $this->checkInResult = $result;

            Notification::make()
                ->title($result['already_checked_in'] ? 'Sudah Pernah Check-In' : 'Check-In Berhasil!')
                ->body($result['message'])
                ->color($result['already_checked_in'] ? 'warning' : 'success')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Check-In')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function executeComplete(string $bookingId, PadelBookingService $service): void
    {
        try {
            $staffUser = auth()->user() ?? \App\Models\User::where('role', 'ADMIN')->first();
            $booking = $service->completeBooking($bookingId, $staffUser);

            Notification::make()
                ->title('Sesi Bermain Selesai')
                ->body("Sesi lapangan tiket {$booking->booking_code} telah ditandai COMPLETED.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Menyelesaikan Sesi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getViewData(): array
    {
        // 🔄 REAKTIF FAIL-SAFE: Sinkronkan tiket kedaluwarsa & selesai
        app(PadelBookingService::class)->syncExpiredAndCompletedBookings();

        $now = now();
        $today = $now->format('Y-m-d');

        $courts = PadelCourt::where('is_active', true)->orderBy('name')->get();

        $courtStatuses = [];
        foreach ($courts as $court) {
            // Cari sesi yang sedang aktif main sekarang
            $activeBooking = PadelBooking::with(['user', 'equipments.equipment'])
                ->where('court_id', $court->id)
                ->where('booking_date', $today)
                ->where('status', 'CHECKED_IN')
                ->where('start_time', '<=', $now)
                ->where('end_time', '>=', $now)
                ->first();

            // Atau sesi checked-in hari ini yang belum selesai
            if (! $activeBooking) {
                $activeBooking = PadelBooking::with(['user', 'equipments.equipment'])
                    ->where('court_id', $court->id)
                    ->where('booking_date', $today)
                    ->where('status', 'CHECKED_IN')
                    ->latest('start_time')
                    ->first();
            }

            // Jika tidak ada yang sedang main, cari sesi terjadwal berikutnya hari ini
            $upcomingBooking = null;
            if (! $activeBooking) {
                $upcomingBooking = PadelBooking::with(['user', 'equipments.equipment'])
                    ->where('court_id', $court->id)
                    ->where('booking_date', $today)
                    ->where('status', 'PAID')
                    ->where('end_time', '>=', $now)
                    ->orderBy('start_time')
                    ->first();
            }

            $courtStatuses[] = [
                'court' => $court,
                'active_booking' => $activeBooking,
                'upcoming_booking' => $upcomingBooking,
            ];
        }

        // 10 Jadwal hari ini
        $todayBookings = PadelBooking::with(['court', 'user'])
            ->where('booking_date', $today)
            ->whereIn('status', ['PAID', 'CHECKED_IN', 'COMPLETED'])
            ->orderBy('start_time')
            ->take(10)
            ->get();

        return [
            'courtStatuses' => $courtStatuses,
            'todayBookings' => $todayBookings,
        ];
    }
}
