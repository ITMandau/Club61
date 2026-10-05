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

    protected static string | UnitEnum | null $navigationGroup = 'Operasional Harian';

    protected static ?string $title = 'Kelola Pemesanan & Tiket';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.kelola-pemesanan';

    // Filter & Search State
    public string $activeTab = 'ALL';
    public string $search = '';
    public int $perPage = 10;

    // Modal State
    public bool $showRescheduleModal = false;
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
    /** Pilihan customer untuk membayar selisih: CASHIER (POS Walk-In) / ONLINE (Midtrans via invoice). */
    public string $rescheduleDeltaChannel = 'CASHIER';
    public string $rescheduleReason = '';
    /** Rincian selisih slot terpilih (hasil PadelBookingService::quoteReschedule, sama persis dengan yang ditagih). */
    public array $rescheduleQuote = [];
    // Cancel & Refund Form State
    public ?string $cancelBookingId = null;
    public ?string $cancelBookingCode = null;
    public ?string $cancelCustomerName = null;
    public float $originalTotalAmount = 0.0;
    /** Ditampilkan saja (tidak bisa diubah): seluruh uang yang masuk untuk booking ini, tanpa potongan (Modul 21). */
    public float $refundAmount = 0.0;
    public string $refundCategory = 'PERMINTAAN_CUSTOMER';
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

    public function getCanRescheduleProperty(): bool
    {
        return (bool) auth()->user()?->can('reschedule_padel_booking');
    }

    public function getCanRefundProperty(): bool
    {
        return (bool) auth()->user()?->can('request_refund_padel');
    }

    /**
     * REFUND_PENDING dari pengajuan customer lewat web (fitur lama, sebelum Modul 21) yang belum punya catatan refund —
     * staf perlu memasukkannya ke Antrian Refund, kalau tidak booking itu tertahan selamanya.
     */
    public static function isLegacyRefundRequest(PadelBooking $booking): bool
    {
        if ($booking->status !== 'REFUND_PENDING') {
            return false;
        }

        $refunds = $booking->relationLoaded('order') && $booking->order?->relationLoaded('refunds')
            ? $booking->order->refunds
            : \App\Models\Pos\Refund::where('padel_booking_id', $booking->id)->get();

        return ! $refunds->contains(fn ($r) => $r->padel_booking_id === $booking->id && $r->status === 'PENDING');
    }

    /** Tombol "Ajukan Pembatalan & Refund": booking aktif yang jam mainnya belum mulai, atau pengajuan lama customer. */
    public function canRequestRefundFor(PadelBooking $booking): bool
    {
        return $this->canRefund && (
            (in_array($booking->status, ['PAID', 'LOCKED'], true) && $booking->start_time->gt(now()))
            || self::isLegacyRefundRequest($booking)
        );
    }

    public function getCanSettleProperty(): bool
    {
        return (bool) auth()->user()?->can('settle_unpaid_booking');
    }

    public function getCanOpenPosProperty(): bool
    {
        return \App\Filament\Pages\BookOfflineCourt::canAccess();
    }

    public function getCanCheckInProperty(): bool
    {
        return (bool) auth()->user()?->can('checkin_padel_ticket');
    }

    /** true = aksi ditolak (notifikasi sudah dikirim); tombolnya juga disembunyikan di Blade. */
    protected function deniedWithout(string $permission): bool
    {
        if (auth()->user()?->can($permission)) {
            return false;
        }

        \App\Services\Audit\ActivityLogger::accessDenied("mencoba aksi tanpa izin [{$permission}] di Kelola Pemesanan");

        Notification::make()
            ->title('Akses Ditolak')
            ->body("Anda tidak memiliki izin [{$permission}] untuk aksi ini.")
            ->danger()
            ->send();

        return true;
    }

    public function openRescheduleModal(string $bookingId, PadelBookingService $service): void
    {
        if ($this->deniedWithout('reschedule_padel_booking')) {
            return;
        }

        $booking = PadelBooking::with(['court', 'user', 'order'])->findOrFail($bookingId);

        if ($booking->status !== 'PAID') {
            Notification::make()
                ->title('Tidak Bisa Dipindah')
                ->body($booking->status === 'LOCKED' && $booking->reschedule_count > 0
                    ? 'Booking ini masih punya tagihan selisih reschedule yang belum lunas. Lunasi dulu di POS Walk-In (klik slot "Bayar" di grid jadwal).'
                    : "Hanya booking yang sudah lunas yang bisa dipindah jadwalnya (status saat ini: {$booking->status}).")
                ->warning()
                ->send();

            return;
        }

        if (now()->gte(PadelBookingService::rescheduleDeadline($booking))) {
            Notification::make()
                ->title('Tidak Bisa Dipindah')
                ->body('Reschedule paling lambat '.PadelBookingService::RESCHEDULE_CUTOFF_HOURS.' jam sebelum jam main ('.PadelBookingService::rescheduleDeadline($booking)->format('d M Y H:i').' WIB).')
                ->warning()
                ->send();

            return;
        }

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
            'original_benefit' => (float) $booking->member_discount_court + (float) $booking->sponsor_discount_court,
            'duration_hours' => $this->rescheduleDurationHours,
        ];

        $this->rescheduleReason = '';
        $this->rescheduleDeltaChannel = 'CASHIER';
        $this->rescheduleQuote = [];

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
            $this->rescheduleQuote = [];
            return;
        }

        $chosenSlot = collect($this->availableSlots)->firstWhere('start_time', $this->rescheduleStartTime);
        if ($chosenSlot) {
            $this->rescheduleEstimatedFee = (float) $chosenSlot['estimated_fee'];
            $this->rescheduleDelta = (float) $chosenSlot['delta'];
            $this->rescheduleQuote = $chosenSlot;
        }
    }

    public function executeReschedule(PadelBookingService $service): void
    {
        if ($this->deniedWithout('reschedule_padel_booking')) {
            return;
        }

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

            $result = $service->adminRescheduleBooking(
                bookingId: $this->selectedBookingId,
                newCourtId: $this->rescheduleCourtId,
                newDate: $this->rescheduleDate,
                newStartTimeStr: $this->rescheduleStartTime,
                reason: $this->rescheduleReason ?: 'Permintaan Reschedule via Admin',
                adminUser: $adminUser,
                isDeltaPaid: false, // pembayaran dieksekusi di POS Walk-In / Midtrans, bukan di sini
                deltaPaymentChannel: $this->rescheduleDeltaChannel === 'ONLINE' ? 'ONLINE' : 'CASHIER',
            );

            Cache::forget('kelola_pemesanan_tab_counts');

            $rupiah = fn (float $v) => 'Rp '.number_format($v, 0, ',', '.');
            Notification::make()
                ->title('Reschedule Berhasil')
                ->body((! empty($result['benefit_dropped_reason']) ? 'Benefit gugur: '.$result['benefit_dropped_reason'].' ' : '').match (true) {
                    ($result['total_charge'] ?? 0) > 0 => 'Jadwal dipindahkan & slot ditahan. Selisih '.$rupiah($result['total_charge']).($this->rescheduleDeltaChannel === 'ONLINE'
                        ? ' dibayar customer via Midtrans dari halaman invoice-nya.'
                        : ' dilunasi di POS Walk-In (klik slot "Bayar" di grid jadwal) saat customer datang.').' QR & check-in terkunci sampai lunas.',
                    ($result['forfeited'] ?? 0) > 0 => 'Jadwal dipindahkan ke jam lebih murah. Selisih '.$rupiah($result['forfeited']).' hangus sesuai kebijakan (tidak dikembalikan).',
                    default => 'Jadwal reservasi berhasil dipindahkan tanpa selisih biaya.',
                })
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

    /** Customer bilang "sudah bayar tapi masih pending": tanya langsung ke Midtrans. */
    public function checkMidtransPayment(string $bookingId): void
    {
        if ($this->deniedWithout('settle_unpaid_booking')) {
            return;
        }

        $booking = PadelBooking::findOrFail($bookingId);
        $order = $booking->order_id ? \App\Models\Pos\Order::find($booking->order_id) : null;

        if (! $order) {
            Notification::make()->title('Tidak Ada Transaksi Online')->body("Tiket {$booking->booking_code} tidak punya order pembayaran Midtrans.")->warning()->send();

            return;
        }

        $result = app(\App\Services\Payment\MidtransReconciliationService::class)->reconcileOrder($order);
        Cache::forget('kelola_pemesanan_tab_counts');

        $booking->refresh();
        $service = app(PadelBookingService::class);
        $openBill = $service->pendingBillForBooking($booking);
        if ($result === \App\Services\Payment\MidtransReconciliationService::NOT_PAID && $openBill) {
            Notification::make()->title('Tagihan Belum Dibayar')
                ->body("Belum ada pembayaran untuk tagihan {$booking->booking_code} Rp ".number_format((float) $openBill->amount, 0, ',', '.').'. Tagihan masih terbuka: dibayar di POS Walk-In atau via invoice customer.')
                ->warning()->send();

            return;
        }

        [$title, $body, $color] = match ($result) {
            \App\Services\Payment\MidtransReconciliationService::PAID => ['Pembayaran Terkonfirmasi', "Midtrans mencatat order {$order->order_number} LUNAS. Tiket {$booking->booking_code} ".($booking->status === 'LOCKED' ? 'masih menunggu tagihan lain.' : 'sudah diaktifkan.'), 'success'],
            \App\Services\Payment\MidtransReconciliationService::PENDING => ['Belum Dibayar', "Midtrans masih menunggu pembayaran order {$order->order_number}.", 'warning'],
            \App\Services\Payment\MidtransReconciliationService::NOT_PAID => ['Tidak Ada Pembayaran', "Midtrans tidak mencatat pembayaran lunas untuk order {$order->order_number} (kedaluwarsa / dibatalkan / tidak ditemukan).", 'danger'],
            default => ['Gagal Menghubungi Midtrans', 'Coba lagi beberapa saat, atau cek langsung di dashboard Midtrans.', 'danger'],
        };

        Notification::make()->title($title)->body($body)->color($color)->send();
    }

    /**
     * Modul 21: staf (kasir / resepsionis / admin) mengajukan pembatalan + refund. Booking langsung batal, uangnya
     * menunggu persetujuan di Antrian Refund — halaman ini tidak pernah mengeluarkan uang.
     */
    public function openCancelRefundModal(string $bookingId): void
    {
        if ($this->deniedWithout('request_refund_padel')) {
            return;
        }

        $booking = PadelBooking::with(['user', 'order'])->findOrFail($bookingId);

        if (! self::isLegacyRefundRequest($booking) && $booking->start_time->lte(now())) {
            Notification::make()
                ->title('Tidak Bisa Dibatalkan')
                ->body('Jam main booking ini sudah dimulai atau lewat. Booking yang hangus tidak bisa dibatalkan, di-refund, maupun di-reschedule.')
                ->warning()
                ->send();

            return;
        }

        // Nominal = uang yang benar-benar sudah dibayar untuk booking ini (tanpa potongan).
        $refundable = app(PadelBookingService::class)->refundableAmountForBooking($booking);
        if ($refundable <= 0 && $booking->status === 'PAID' && (! $booking->order_id || ! \App\Models\Pos\Payment::where('order_id', $booking->order_id)->exists())) {
            $refundable = (float) $booking->total_amount; // data legacy tanpa catatan pembayaran
        }

        $this->cancelBookingId = $booking->id;
        $this->cancelBookingCode = $booking->booking_code;
        $this->cancelCustomerName = $booking->user?->name ?? 'Guest';
        $this->originalTotalAmount = $refundable;
        $this->refundAmount = $refundable;
        $this->refundCategory = 'PERMINTAAN_CUSTOMER';
        $this->refundNotes = '';

        $this->showCancelRefundModal = true;
    }

    public function executeCancelRefund(PadelBookingService $service): void
    {
        if ($this->deniedWithout('request_refund_padel')) {
            return;
        }

        if (! in_array($this->refundCategory, ['PERMINTAAN_CUSTOMER', 'FORCE_MAJEURE', 'SALAH_BAYAR', 'KESALAHAN_VENUE'], true)) {
            $this->refundCategory = 'PERMINTAAN_CUSTOMER';
        }

        if (mb_strlen(trim($this->refundNotes)) < 5) {
            Notification::make()->title('Alasan Wajib Diisi')->body('Tulis alasan pembatalan (minimal 5 karakter) untuk pemeriksa di Antrian Refund.')->danger()->send();

            return;
        }

        try {
            $result = $service->requestCancelAndRefund(
                bookingId: (string) $this->cancelBookingId,
                reasonCategory: $this->refundCategory,
                notes: mb_substr(trim($this->refundNotes), 0, 500),
                requester: auth()->user(),
            );

            Cache::forget('kelola_pemesanan_tab_counts');

            Notification::make()
                ->title('Reservasi Dibatalkan')
                ->body($result['message'].' Tiket QR sudah dinonaktifkan dan slot dilepas.')
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
        if ($this->deniedWithout('checkin_padel_ticket')) {
            return;
        }

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
        if ($this->deniedWithout('checkin_padel_ticket')) {
            return;
        }

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
        if ($this->deniedWithout('checkin_padel_ticket')) {
            return;
        }

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
            $query->whereIn('status', ['CANCELLED', 'REFUND_PENDING', 'REFUNDED', 'EXPIRED']);
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
                    SUM(CASE WHEN status IN ('CANCELLED', 'REFUND_PENDING', 'REFUNDED', 'EXPIRED') THEN 1 ELSE 0 END) as cancelled
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
