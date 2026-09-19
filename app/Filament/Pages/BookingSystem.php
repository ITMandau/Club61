<?php

namespace App\Filament\Pages;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Services\Padel\PadelBookingService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class BookingSystem extends Page
{
    use HasPageShield;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-tv';

    protected static ?string $navigationLabel = 'Monitoring Lapangan';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Monitoring Lapangan & Jadwal Padel';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.booking-system';

    // Filter & Kalender Navigasi
    public string $selectedDate;

    public string $courtFilter = 'all'; // 'all', 'indoor', 'outdoor'

    public string $statusFilter = 'all'; // 'all', 'playing', 'paid', 'available'

    // Check-In Modal State
    public bool $showCheckInModal = false;

    public string $checkInQuery = '';

    public ?array $checkInResult = null;

    // Quick Inspector Drawer State
    public bool $showInspectorDrawer = false;

    public ?array $inspectData = null;

    public function mount(): void
    {
        $this->selectedDate = now()->format('Y-m-d');
    }

    public function setDate(string $date): void
    {
        $this->selectedDate = $date;
        $this->closeInspector();
    }

    public function prevDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->format('Y-m-d');
        $this->closeInspector();
    }

    public function nextDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->format('Y-m-d');
        $this->closeInspector();
    }

    public function today(): void
    {
        $this->selectedDate = now()->format('Y-m-d');
        $this->closeInspector();
    }

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
            $staffUser = auth()->user() ?? \App\Models\User::role(['cashier', 'admin', 'super_admin'])->first();
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

    public function inspectBooking(string $bookingId): void
    {
        $booking = PadelBooking::with(['court', 'user', 'order.payments', 'equipments.equipment'])
            ->find($bookingId);

        if (! $booking) {
            Notification::make()
                ->title('Data Tidak Ditemukan')
                ->body('Sesi pemesanan tidak ditemukan atau telah dihapus.')
                ->warning()
                ->send();
            return;
        }

        $equipmentsList = [];
        if ($booking->equipments) {
            foreach ($booking->equipments as $eq) {
                $equipmentsList[] = [
                    'name' => $eq->equipment?->name ?? 'Padel Equipment',
                    'quantity' => $eq->quantity,
                    'price' => (float) $eq->price_at_rental,
                ];
            }
        }

        $this->inspectData = [
            'type' => 'booking',
            'id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'qr_code_hash' => $booking->qr_code_hash,
            'status' => $booking->status,
            'court_id' => $booking->court_id,
            'court_name' => $booking->court?->name ?? 'Lapangan',
            'court_type' => $booking->court?->type ?? 'Indoor',
            'booking_date' => $booking->booking_date->format('Y-m-d'),
            'date_formatted' => $booking->booking_date->translatedFormat('l, d F Y'),
            'start_time' => $booking->start_time->format('H:i'),
            'end_time' => $booking->end_time->format('H:i'),
            'customer_name' => $booking->user?->name ?? 'Guest User',
            'customer_phone' => $booking->user?->phone ?? '-',
            'customer_email' => $booking->user?->email ?? '-',
            'court_fee' => (float) $booking->court_fee,
            'total_amount' => (float) $booking->total_amount,
            'payment_status' => $booking->order?->payment_status ?? ($booking->status === 'PAID' ? 'PAID' : 'UNPAID'),
            'equipments' => $equipmentsList,
            'is_checked_in' => $booking->status === 'CHECKED_IN',
            'checked_in_at' => $booking->checked_in_at ? Carbon::parse($booking->checked_in_at)->format('H:i WIB') : null,
        ];

        $this->showInspectorDrawer = true;
    }

    public function inspectEmptySlot(string $courtId, string $hour): void
    {
        $court = PadelCourt::find($courtId);
        if (! $court) return;

        $cOpen = (int) substr($court->open_time ?: '06:00', 0, 2);
        $cCloseVal = $court->close_time ?: '23:00';
        $cClose = ($cCloseVal === '00:00' || $cCloseVal === '24:00') ? 24 : (int) substr($cCloseVal, 0, 2);
        $h = (int) $hour;

        if ($h < $cOpen || $h >= $cClose) {
            Notification::make()
                ->title('Lapangan Tutup')
                ->body("Jam {$hour}:00 berada di luar jam operasional {$court->name} ({$court->open_time} - {$court->close_time}).")
                ->warning()
                ->send();
            return;
        }

        $startFormatted = sprintf('%02d:00', $h);
        $endFormatted = sprintf('%02d:00', $h + 1);

        $isWeekend = Carbon::parse($this->selectedDate)->isWeekend();
        $isPrime = $isWeekend || $h >= 17;
        $rate = $isPrime ? (float) $court->hourly_rate_prime : (float) $court->hourly_rate_regular;

        $this->inspectData = [
            'type' => 'available',
            'court_id' => $court->id,
            'court_name' => $court->name,
            'court_type' => $court->type ?? 'INDOOR',
            'rate' => $rate,
            'is_prime_time' => $isPrime,
            'booking_date' => $this->selectedDate,
            'date_formatted' => Carbon::parse($this->selectedDate)->translatedFormat('l, d F Y'),
            'start_time' => $startFormatted,
            'end_time' => $endFormatted,
            'time_label' => "{$startFormatted} - {$endFormatted} WIB",
        ];

        $this->showInspectorDrawer = true;
    }

    public function closeInspector(): void
    {
        $this->showInspectorDrawer = false;
        $this->inspectData = null;
    }

    public function quickCheckInFromInspector(string $bookingId, PadelBookingService $service): void
    {
        try {
            $staffUser = auth()->user() ?? \App\Models\User::role(['cashier', 'admin', 'super_admin'])->first();
            $result = $service->checkIn($bookingId, $staffUser);

            Notification::make()
                ->title('Check-In Berhasil!')
                ->body($result['message'])
                ->success()
                ->send();

            $this->inspectBooking($bookingId);
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
            $staffUser = auth()->user() ?? \App\Models\User::role(['cashier', 'admin', 'super_admin'])->first();
            $booking = $service->completeBooking($bookingId, $staffUser);

            Notification::make()
                ->title('Sesi Bermain Selesai')
                ->body("Sesi lapangan tiket {$booking->booking_code} telah ditandai COMPLETED.")
                ->success()
                ->send();

            $this->closeInspector();
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
        app(PadelBookingService::class)->syncExpiredAndCompletedBookings();

        $now = now();
        $targetDate = Carbon::parse($this->selectedDate);
        $isToday = $this->selectedDate === $now->format('Y-m-d');
        $currentHour = (int) $now->format('H');

        // Query Lapangan
        $courtQuery = PadelCourt::where('is_active', true);
        if ($this->courtFilter === 'indoor') {
            $courtQuery->where('type', 'INDOOR');
        } elseif ($this->courtFilter === 'outdoor') {
            $courtQuery->where('type', 'OUTDOOR');
        }
        $courts = $courtQuery->orderBy('name')->get();

        // Jam Operasional Dinamis (Mengikuti konfigurasi lapangan di Master Data)
        $minOpenHour = 6;
        $maxCloseHour = 23;

        if ($courts->isNotEmpty()) {
            $minOpenHour = $courts->min(function ($c) {
                return (int) substr($c->open_time ?: '06:00', 0, 2);
            }) ?? 6;

            $maxCloseHour = $courts->max(function ($c) {
                $val = $c->close_time ?: '23:00';
                return ($val === '00:00' || $val === '24:00') ? 24 : (int) substr($val, 0, 2);
            }) ?? 23;

            $minOpenHour = max(0, min($minOpenHour, 23));
            $maxCloseHour = max($minOpenHour + 1, min($maxCloseHour, 24));
        }

        $operationalHours = [];
        for ($h = $minOpenHour; $h < $maxCloseHour; $h++) {
            $operationalHours[] = [
                'hour' => $h,
                'label' => sprintf('%02d:00', $h),
                'next_label' => sprintf('%02d:00', $h + 1),
                'is_current' => $isToday && ($currentHour === $h),
                'is_past' => $isToday ? ($h < $currentHour) : $targetDate->isPast(),
            ];
        }

        // Ambil Seluruh Booking pada Tanggal Terpilih
        $bookings = PadelBooking::with(['court', 'user', 'equipments.equipment'])
            ->whereDate('booking_date', $this->selectedDate)
            ->whereIn('status', ['LOCKED', 'PENDING_PAYMENT', 'PENDING', 'PAID', 'CHECKED_IN', 'COMPLETED'])
            ->get();

        // Bangun Matrix Schedule
        $matrix = [];
        $totalSlotsCount = count($courts) * count($operationalHours);
        $occupiedSlotsCount = 0;
        $playingCount = 0;
        $paidUpcomingCount = 0;
        $completedCount = 0;

        foreach ($courts as $court) {
            $courtRow = [
                'court' => $court,
                'slots' => [],
            ];

            $courtOpen = (int) substr($court->open_time ?: '06:00', 0, 2);
            $courtCloseVal = $court->close_time ?: '23:00';
            $courtClose = ($courtCloseVal === '00:00' || $courtCloseVal === '24:00') ? 24 : (int) substr($courtCloseVal, 0, 2);

            foreach ($operationalHours as $opHour) {
                $h = $opHour['hour'];
                $isOpenForCourt = ($h >= $courtOpen && $h < $courtClose);
                $slotTimeStr = sprintf('%02d:00:00', $h);
                $slotCarbon = Carbon::parse("{$this->selectedDate} {$slotTimeStr}");

                // Cari booking yang mencakup jam ini
                $matchedBooking = $bookings->first(function ($b) use ($court, $slotCarbon) {
                    if ($b->court_id !== $court->id) return false;
                    return $slotCarbon->gte($b->start_time) && $slotCarbon->lt($b->end_time);
                });

                if ($matchedBooking) {
                    $occupiedSlotsCount++;
                    $status = $matchedBooking->status;

                    if ($status === 'CHECKED_IN') {
                        $playingCount++;
                    } elseif ($status === 'PAID') {
                        $paidUpcomingCount++;
                    } elseif ($status === 'COMPLETED') {
                        $completedCount++;
                    }

                    $courtRow['slots'][$h] = [
                        'type' => 'booked',
                        'booking' => $matchedBooking,
                        'status' => $status,
                        'is_playing' => $status === 'CHECKED_IN',
                        'is_paid' => $status === 'PAID',
                        'is_pending' => in_array($status, ['PENDING_PAYMENT', 'PENDING', 'LOCKED']),
                        'is_completed' => $status === 'COMPLETED',
                        'player_name' => $matchedBooking->user?->name ?? 'Guest',
                        'booking_code' => $matchedBooking->booking_code,
                        'equipment_count' => $matchedBooking->equipments ? $matchedBooking->equipments->sum('quantity') : 0,
                    ];
                } elseif (! $isOpenForCourt) {
                    $courtRow['slots'][$h] = [
                        'type' => 'closed',
                        'court_id' => $court->id,
                        'hour' => $h,
                        'is_past' => $opHour['is_past'],
                        'is_prime_time' => false,
                        'price' => 0,
                    ];
                } else {
                    $isWeekend = $targetDate->isWeekend();
                    $isPrime = $isWeekend || (int) $h >= 17;
                    $slotPrice = $isPrime ? (float) $court->hourly_rate_prime : (float) $court->hourly_rate_regular;

                    $courtRow['slots'][$h] = [
                        'type' => 'available',
                        'court_id' => $court->id,
                        'hour' => $h,
                        'is_past' => $opHour['is_past'],
                        'is_prime_time' => $isPrime,
                        'price' => $slotPrice,
                    ];
                }
            }

            $matrix[] = $courtRow;
        }

        $freeSlotsCount = max(0, $totalSlotsCount - $occupiedSlotsCount);
        $occupancyRate = $totalSlotsCount > 0 ? round(($occupiedSlotsCount / $totalSlotsCount) * 100, 1) : 0;

        // Hitung lapangan yang sedang aktif detik ini
        $activeCourtsNow = 0;
        if ($isToday) {
            foreach ($matrix as $row) {
                if (isset($row['slots'][$currentHour]) && ($row['slots'][$currentHour]['status'] ?? '') === 'CHECKED_IN') {
                    $activeCourtsNow++;
                }
            }
        }

        return [
            'matrix' => $matrix,
            'operationalHours' => $operationalHours,
            'isToday' => $isToday,
            'currentHour' => $currentHour,
            'totalSlotsCount' => $totalSlotsCount,
            'occupiedSlotsCount' => $occupiedSlotsCount,
            'freeSlotsCount' => $freeSlotsCount,
            'occupancyRate' => $occupancyRate,
            'activeCourtsNow' => $activeCourtsNow,
            'playingCount' => $playingCount,
            'paidUpcomingCount' => $paidUpcomingCount,
            'completedCount' => $completedCount,
            'totalCourtsCount' => count($courts),
        ];
    }
}
