<?php

namespace App\Filament\Pages;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Payment;
use App\Services\Padel\PadelBookingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Livewire\WithPagination;
use UnitEnum;

class KelolaPemesanan extends Page
{
    use HasPageShield;
    use WithPagination;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Kelola Pemesanan';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Kelola Pemesanan & Tiket';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.kelola-pemesanan';

    // Filter & Search State
    public string $activeTab = 'ALL';
    public string $search = '';
    public int $perPage = 10;

    // Modal State
    public bool $showRescheduleModal = false;
    public bool $showSettleModal = false;
    public bool $showCancelRefundModal = false;

    // Reschedule Form State
    public ?string $selectedBookingId = null;
    public ?array $selectedBookingData = null;
    public ?string $rescheduleCourtId = null;
    public ?string $rescheduleDate = null;
    public ?string $rescheduleStartTime = null;
    public int $rescheduleDurationHours = 1;
    public array $availableSlots = [];
    public float $rescheduleEstimatedFee = 0.0;
    public float $rescheduleDelta = 0.0;
    public bool $rescheduleIsDeltaPaidNow = true;
    public string $reschedulePaymentMethod = 'CASH';
    public string $rescheduleReason = '';

    // Settle Modal State
    public ?string $settleBookingId = null;
    public ?string $settleBookingCode = null;
    public ?string $settleCustomerName = null;
    public float $settleAmount = 0.0;
    public string $settlePaymentMethod = 'CASH';

    // Cancel & Refund Form State
    public ?string $cancelBookingId = null;
    public ?string $cancelBookingCode = null;
    public ?string $cancelCustomerName = null;
    public float $originalTotalAmount = 0.0;
    public float $refundAmount = 0.0;
    public string $refundMethod = 'TUNAI_KASIR';
    public string $refundCategory = 'SALAH_BAYAR';
    public string $refundNotes = '';

    // Check-In Gate Modal State
    public bool $showCheckInModal = false;
    public string $checkInQuery = '';
    public ?array $checkInResult = null;

    public function mount(): void
    {
        $this->rescheduleDate = now()->format('Y-m-d');
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function openRescheduleModal(string $bookingId, PadelBookingService $service): void
    {
        $booking = PadelBooking::with(['court', 'user', 'order'])->findOrFail($bookingId);

        $this->selectedBookingId = $booking->id;
        $this->rescheduleCourtId = $booking->court_id;

        // Anti-tanggal lampau: jika tanggal booking sudah lewat, gunakan hari ini
        $isPastDate = $booking->booking_date->isPast() && ! $booking->booking_date->isToday();
        $this->rescheduleDate = $isPastDate ? now()->format('Y-m-d') : $booking->booking_date->format('Y-m-d');

        // Kunci durasi multi-jam (Anti-Jebakan Durasi)
        $duration = (int) $booking->start_time->diffInHours($booking->end_time);
        $this->rescheduleDurationHours = max(1, $duration);

        $this->selectedBookingData = [
            'id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'customer_name' => $booking->user?->name ?? 'Guest',
            'court_name' => $booking->court?->name ?? '-',
            'original_date' => $booking->booking_date->format('d M Y'),
            'original_time' => $booking->start_time->format('H:i') . ' - ' . $booking->end_time->format('H:i') . ' WIB',
            'original_court_fee' => (float) $booking->court_fee,
            'duration_hours' => $this->rescheduleDurationHours,
        ];

        $this->rescheduleReason = '';
        $this->rescheduleIsDeltaPaidNow = true;
        $this->reschedulePaymentMethod = 'CASH';

        $this->loadAvailableSlots($service);
        $this->showRescheduleModal = true;
    }

    public function updatedRescheduleCourtId(): void
    {
        $this->loadAvailableSlots(app(PadelBookingService::class));
    }

    public function updatedRescheduleDate(): void
    {
        // Validasi anti-tanggal lampau pada input Livewire
        if ($this->rescheduleDate) {
            $parsed = Carbon::parse($this->rescheduleDate)->startOfDay();
            if ($parsed->isPast() && ! $parsed->isToday()) {
                $this->rescheduleDate = now()->format('Y-m-d');
                Notification::make()
                    ->title('Tanggal Lampau Ditolak')
                    ->body('Jadwal hanya dapat dipindahkan ke hari ini atau masa depan.')
                    ->warning()
                    ->send();
            }
        }

        $this->loadAvailableSlots(app(PadelBookingService::class));
    }

    public function updatedRescheduleStartTime(): void
    {
        $this->updatePriceDelta();
    }

    public function loadAvailableSlots(PadelBookingService $service): void
    {
        if (! $this->selectedBookingId || ! $this->rescheduleCourtId || ! $this->rescheduleDate) {
            $this->availableSlots = [];
            return;
        }

        try {
            $result = $service->getAvailableRescheduleSlots(
                $this->selectedBookingId,
                $this->rescheduleCourtId,
                $this->rescheduleDate
            );

            $this->availableSlots = $result['slots'];

            if (! empty($this->availableSlots)) {
                // Pilih slot pertama secara default jika pilihan sebelumnya tidak lagi tersedia
                $exists = collect($this->availableSlots)->contains('start_time', $this->rescheduleStartTime);
                if (! $exists) {
                    $this->rescheduleStartTime = $this->availableSlots[0]['start_time'];
                }
            } else {
                $this->rescheduleStartTime = null;
            }

            $this->updatePriceDelta();
        } catch (\Throwable $e) {
            $this->availableSlots = [];
            $this->rescheduleStartTime = null;
            $this->rescheduleEstimatedFee = 0.0;
            $this->rescheduleDelta = 0.0;
        }
    }

    protected function updatePriceDelta(): void
    {
        if (empty($this->availableSlots) || ! $this->rescheduleStartTime) {
            $this->rescheduleEstimatedFee = 0.0;
            $this->rescheduleDelta = 0.0;
            return;
        }

        $chosenSlot = collect($this->availableSlots)->firstWhere('start_time', $this->rescheduleStartTime);
        if ($chosenSlot) {
            $this->rescheduleEstimatedFee = (float) $chosenSlot['estimated_fee'];
            $this->rescheduleDelta = (float) $chosenSlot['delta'];
        }
    }

    public function executeReschedule(PadelBookingService $service): void
    {
        if (! $this->rescheduleStartTime) {
            Notification::make()
                ->title('Pilih Jam Main')
                ->body('Silakan pilih salah satu slot jam main yang tersedia.')
                ->danger()
                ->send();
            return;
        }

        try {
            $adminUser = auth()->user() ?? \App\Models\User::role(['admin', 'super_admin'])->first();

            $service->adminRescheduleBooking(
                bookingId: $this->selectedBookingId,
                newCourtId: $this->rescheduleCourtId,
                newDate: $this->rescheduleDate,
                newStartTimeStr: $this->rescheduleStartTime,
                reason: $this->rescheduleReason ?: 'Permintaan Reschedule via Admin',
                adminUser: $adminUser,
                paymentMethod: $this->reschedulePaymentMethod,
                isDeltaPaid: $this->rescheduleIsDeltaPaidNow
            );

            Cache::forget('kelola_pemesanan_tab_counts');

            Notification::make()
                ->title('Reschedule Berhasil')
                ->body('Jadwal reservasi berhasil dipindahkan dan dicatat dalam pembukuan.')
                ->success()
                ->send();

            $this->showRescheduleModal = false;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Reschedule')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function openSettleModal(string $bookingId): void
    {
        $booking = PadelBooking::with(['user', 'order.payments'])->findOrFail($bookingId);

        $pendingPayment = Payment::where('order_id', $booking->order_id)
            ->where('status', 'PENDING')
            ->latest()
            ->first();

        $this->settleBookingId = $booking->id;
        $this->settleBookingCode = $booking->booking_code;
        $this->settleCustomerName = $booking->user?->name ?? 'Guest';
        $this->settleAmount = $pendingPayment 
            ? (float) $pendingPayment->amount 
            : (float) ($booking->order?->grand_total ?: $booking->total_amount);
        $this->settlePaymentMethod = 'CASH';

        $this->showSettleModal = true;
    }

    public function executeSettleSupplemental(PadelBookingService $service): void
    {
        try {
            $adminUser = auth()->user() ?? \App\Models\User::role(['admin', 'super_admin'])->first();

            $service->adminSettleCashierPayment(
                bookingId: $this->settleBookingId,
                paymentMethod: $this->settlePaymentMethod,
                amountReceived: (float) $this->settleAmount,
                cashierUser: $adminUser
            );

            Cache::forget('kelola_pemesanan_tab_counts');

            Notification::make()
                ->title('Pelunasan Berhasil')
                ->body('Pelunasan kasir berhasil diverifikasi dan QR Tiket aktif.')
                ->success()
                ->send();

            $this->showSettleModal = false;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Melunasi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function openCancelRefundModal(string $bookingId): void
    {
        if (! auth()->user()->can('cancel_refund_padel') && ! auth()->user()->can('cancel_padel_booking') && ! auth()->user()->isAdmin()) {
            Notification::make()->title('Akses Ditolak: Anda tidak memiliki izin membatalkan pesanan.')->danger()->send();
            return;
        }

        $booking = PadelBooking::with(['user'])->findOrFail($bookingId);

        $this->cancelBookingId = $booking->id;
        $this->cancelBookingCode = $booking->booking_code;
        $this->cancelCustomerName = $booking->user?->name ?? 'Guest';
        $this->originalTotalAmount = (float) $booking->total_amount;
        $this->refundAmount = (float) $booking->total_amount;
        $this->refundMethod = 'TUNAI_KASIR';
        $this->refundCategory = 'SALAH_BAYAR';
        $this->refundNotes = '';

        $this->showCancelRefundModal = true;
    }

    public function executeCancelRefund(PadelBookingService $service): void
    {
        if (! auth()->user()->can('cancel_refund_padel') && ! auth()->user()->can('cancel_padel_booking') && ! auth()->user()->isAdmin()) {
            Notification::make()->title('Akses Ditolak: Anda tidak memiliki izin membatalkan pesanan.')->danger()->send();
            return;
        }

        try {
            $adminUser = auth()->user() ?? \App\Models\User::role(['admin', 'super_admin'])->first();

            $service->adminCancelAndRefund(
                bookingId: $this->cancelBookingId,
                refundAmount: (float) $this->refundAmount,
                refundMethod: $this->refundMethod,
                reasonCategory: $this->refundCategory,
                notes: $this->refundNotes ?: 'Pembatalan & Refund via Frontdesk Admin',
                adminUser: $adminUser
            );

            Cache::forget('kelola_pemesanan_tab_counts');

            Notification::make()
                ->title('Reservasi Dibatalkan')
                ->body('Tiket QR telah dinonaktifkan dan pengembalian dana tercatat di database.')
                ->success()
                ->send();

            $this->showCancelRefundModal = false;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Membatalkan')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
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
            $staffUser = auth()->user() ?? \App\Models\User::role(['admin', 'super_admin'])->first();
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
            $staffUser = auth()->user() ?? \App\Models\User::role(['admin', 'super_admin'])->first();
            $booking = $service->completeBooking($bookingId, $staffUser);

            Notification::make()
                ->title('Sesi Bermain Selesai')
                ->body("Sesi untuk tiket {$booking->booking_code} telah ditandai COMPLETED.")
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

    public function executeReturnEquipment(string $bookingId, PadelBookingService $service): void
    {
        try {
            $staffUser = auth()->user() ?? \App\Models\User::role(['admin', 'super_admin'])->first();
            $result = $service->returnEquipment($bookingId, $staffUser);

            $summary = collect($result['items'])->map(fn ($i) => "{$i['quantity']}x {$i['name']}")->join(', ');

            Notification::make()
                ->title('Alat Sewa Dikembalikan')
                ->body("Tiket {$result['booking_code']}: {$summary} telah di-restock ke stok alat.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Memproses Retur Alat')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getViewData(): array
    {
        // REAKTIF FAIL-SAFE: Otomatis sinkronkan tiket kedaluwarsa & selesai setiap halaman dibuka
        app(PadelBookingService::class)->syncExpiredAndCompletedBookings();

        $query = PadelBooking::with(['user', 'court', 'order.payments', 'order.refunds', 'equipments.equipment'])
            ->latest('start_time');

        if ($this->activeTab === 'CONFIRMED') {
            $query->where('status', 'PAID');
        } elseif ($this->activeTab === 'LOCKED_PENDING') {
            $query->where('status', 'LOCKED');
        } elseif ($this->activeTab === 'COMPLETED') {
            $query->whereIn('status', ['CHECKED_IN', 'COMPLETED']);
        } elseif ($this->activeTab === 'CANCELLED') {
            $query->whereIn('status', ['CANCELLED', 'REFUNDED', 'EXPIRED']);
        }

        if (trim($this->search) !== '') {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('booking_code', 'like', "%{$term}%")
                    ->orWhereHas('user', function ($u) use ($term) {
                        $u->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                    })
                    ->orWhereHas('court', function ($c) use ($term) {
                        $c->where('name', 'like', "%{$term}%");
                    });
            });
        }

        $bookings = $query->paginate($this->perPage);

        $counts = Cache::remember('kelola_pemesanan_tab_counts', 60, function () {
            $rawCounts = DB::table('padel_bookings')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'LOCKED' THEN 1 ELSE 0 END) as locked,
                    SUM(CASE WHEN status IN ('CHECKED_IN', 'COMPLETED') THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status IN ('CANCELLED', 'REFUNDED', 'EXPIRED') THEN 1 ELSE 0 END) as cancelled
                ")
                ->first();

            return [
                'ALL' => (int) ($rawCounts->total ?? 0),
                'CONFIRMED' => (int) ($rawCounts->confirmed ?? 0),
                'LOCKED_PENDING' => (int) ($rawCounts->locked ?? 0),
                'COMPLETED' => (int) ($rawCounts->completed ?? 0),
                'CANCELLED' => (int) ($rawCounts->cancelled ?? 0),
            ];
        });

        $courts = Cache::remember('active_padel_courts_list', 120, function () {
            return PadelCourt::where('is_active', true)->orderBy('name')->get();
        });

        return [
            'bookings' => $bookings,
            'counts' => $counts,
            'courts' => $courts,
        ];
    }
}
