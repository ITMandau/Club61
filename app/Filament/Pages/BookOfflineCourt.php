<?php

namespace App\Filament\Pages;

use App\Exceptions\SlotConflictException;
use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\PosCashierShift;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class BookOfflineCourt extends Page
{
    use HasPageShield;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'POS Walk-In Booking';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Walk-In Offline Booking & Frontdesk POS';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.book-offline-court';

    // Sesi Shift Kasir POS
    public bool $showOpenShiftModal = false;
    public float $startingCashInput = 0.00;
    public string $openingNotes = '';

    public bool $showCloseShiftModal = false;
    public ?float $actualCashInput = null;
    public string $closingNotes = '';
    public ?array $closingShiftSummary = null;

    public bool $showShiftReportModal = false;
    public ?array $reportShiftData = null;

    // Tanggal Booking Aktif
    public string $bookingDate;

    // Slot yang Dipilih Kasir: [$slotKey => ['court_id', 'court_name', 'start_time', 'end_time', 'time_label', 'price']]
    public array $selectedSlots = [];

    // Mode Pelanggan: 'quick_create' atau 'search'
    public string $customerMode = 'quick_create';

    public string $customerSearch = '';

    // Hasil pencarian customer — diisi lewat updatedCustomerSearch(), BUKAN di-query ulang di getViewData()
    // supaya query DB-nya cuma jalan pas teks pencarian beneran berubah, bukan di setiap render/klik lain.
    public array $searchResults = [];

    public ?string $selectedCustomerId = null;

    public ?string $selectedCustomerName = null;

    public ?string $selectedCustomerPhone = null;

    public ?array $activeMembershipInfo = null;

    // Toggle kasir: pakai/tidak pakai benefit membership customer ini untuk transaksi sekarang.
    public bool $useMembershipBenefit = true;

    // Form Walk-In Cepat
    public string $walkInName = '';

    public string $walkInPhone = '';

    public string $walkInEmail = '';

    // Add-On Sewa Alat: [$equipmentId => $quantity]
    public array $rentalQuantities = [];

    // Step Alur Terminal Kasir: 'selection' (Jadwal), 'payment' (Layar Bayar), 'receipt' (Struk)
    public string $posStep = 'selection';

    // Metode Pembayaran Kasir: 'CASH', 'DEBIT_CARD', 'CREDIT_CARD', 'QRIS'
    public string $paymentMethod = 'CASH';

    // Rincian Pembayaran Mesin EDC (Kartu Debit & Kredit)
    public string $edcTerminal = 'EDC_BCA'; // EDC_BCA, EDC_MANDIRI, EDC_LAINNYA
    public string $edcCardType = 'DEBIT'; // DEBIT, CREDIT
    public string $edcCardNetwork = 'GPN'; // GPN, VISA, MASTERCARD, JCB, AMEX, LAINNYA
    public string $edcBank = 'BCA'; // BCA, MANDIRI, BNI, BRI, CIMB, PERMATA, DANAMON, OVERSEAS, LAINNYA
    public string $edcLast4 = '';
    public string $edcApprovalCode = '';
    public string $edcTraceNumber = '';

    // Rincian Pembayaran QRIS
    public string $qrisProvider = 'BCA_QRIS'; // BCA_QRIS, MANDIRI_QRIS, GOPAY_QRIS, LAINNYA
    public string $qrisRrn = '';
    public string $qrisSenderName = '';

    // Rincian Pembayaran Tunai
    public ?float $cashReceived = null;
    public float $cashChange = 0.00;

    // Auto-Recovery Draf Transaksi POS
    public bool $hasPendingDraft = false;
    public ?array $pendingDraftSummary = null;

    // Opsi Software Auto Check-In
    public bool $isAutoCheckIn = false;

    // Modal Sukses & Struk POS
    public bool $showSuccessModal = false;

    public ?array $completedOrderData = null;

    public function mount(): void
    {
        $this->bookingDate = now()->format('Y-m-d');
        $this->initializeEquipmentQuantities();
        $this->checkPendingDraft();
    }

    protected function initializeEquipmentQuantities(): void
    {
        $equipments = CourtEquipment::where('is_active', true)->get();
        foreach ($equipments as $eq) {
            if (! isset($this->rentalQuantities[$eq->id])) {
                $this->rentalQuantities[$eq->id] = 0;
            }
        }
    }

    public function setDate(string $date): void
    {
        $this->bookingDate = $date;
        $this->selectedSlots = [];
    }

    public function prevDay(): void
    {
        $this->bookingDate = Carbon::parse($this->bookingDate)->subDay()->format('Y-m-d');
        $this->selectedSlots = [];
    }

    public function nextDay(): void
    {
        $this->bookingDate = Carbon::parse($this->bookingDate)->addDay()->format('Y-m-d');
        $this->selectedSlots = [];
    }

    public function today(): void
    {
        $this->bookingDate = now()->format('Y-m-d');
        $this->selectedSlots = [];
    }

    public function toggleSlot(string $courtId, string $courtName, string $startTime, string $endTime, float $price): void
    {
        $slotKey = "{$courtId}_{$startTime}";

        if (isset($this->selectedSlots[$slotKey])) {
            unset($this->selectedSlots[$slotKey]);
        } else {
            $court = PadelCourt::find($courtId);
            if ($court) {
                $cOpen = (int) substr($court->open_time ?: '06:00', 0, 2);
                $cCloseVal = $court->close_time ?: '23:00';
                $cClose = ($cCloseVal === '00:00' || $cCloseVal === '24:00') ? 24 : (int) substr($cCloseVal, 0, 2);
                $slotH = (int) substr($startTime, 0, 2);

                if ($slotH < $cOpen || $slotH >= $cClose) {
                    Notification::make()
                        ->title('Lapangan Tutup')
                        ->body("Jam {$startTime} berada di luar jam operasional {$court->name} ({$court->open_time} - {$court->close_time} WIB).")
                        ->warning()
                        ->send();
                    return;
                }
            }

            $this->selectedSlots[$slotKey] = [
                'court_id' => $courtId,
                'court_name' => $courtName,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'time_label' => substr($startTime, 0, 5) . ' - ' . substr($endTime, 0, 5),
                'price' => $price,
            ];
        }

        if (empty($this->selectedSlots)) {
            Cache::forget($this->getDraftCacheKey());
            $this->hasPendingDraft = false;
            $this->pendingDraftSummary = null;
        } else {
            $this->saveDraft();
        }
    }

    public function removeSlot(string $slotKey): void
    {
        unset($this->selectedSlots[$slotKey]);

        if (empty($this->selectedSlots)) {
            Cache::forget($this->getDraftCacheKey());
            $this->hasPendingDraft = false;
            $this->pendingDraftSummary = null;
        } else {
            $this->saveDraft();
        }
    }

    public function clearSelectedSlots(): void
    {
        $this->selectedSlots = [];
        Cache::forget($this->getDraftCacheKey());
        $this->hasPendingDraft = false;
        $this->pendingDraftSummary = null;
    }

    public function incrementEquipment(string $equipmentId, int $maxStock): void
    {
        $current = $this->rentalQuantities[$equipmentId] ?? 0;
        if ($current < $maxStock) {
            $this->rentalQuantities[$equipmentId] = $current + 1;
            $this->saveDraft();
        }
    }

    public function decrementEquipment(string $equipmentId): void
    {
        $current = $this->rentalQuantities[$equipmentId] ?? 0;
        if ($current > 0) {
            $this->rentalQuantities[$equipmentId] = $current - 1;
            $this->saveDraft();
        }
    }

    public function setCustomerMode(string $mode): void
    {
        $this->customerMode = $mode;
        $this->saveDraft();
    }

    public function selectCustomer(string $userId): void
    {
        $user = User::find($userId);
        if ($user) {
            $this->selectedCustomerId = $user->id;
            $this->selectedCustomerName = $user->name;
            $this->selectedCustomerPhone = $user->phone;
            $this->customerSearch = '';

            // Cek keanggotaan aktif dan benefit fasilitas Padel
            // Tie-break: kartu yang paling cepat kedaluwarsa dipakai lebih dulu, konsisten dengan
            // auto-detect balance di ManagesCheckoutAndPayments::applyMembershipBenefitToCourtBookings().
            $activeMbr = \App\Models\Membership\UserMembership::with(['plan', 'balances'])
                ->where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
                })
                ->orderByRaw('end_date IS NULL, end_date ASC')
                ->first();

            if ($activeMbr) {
                $padelBalance = $activeMbr->balanceFor('PADEL');
                $this->activeMembershipInfo = [
                    'membership_code' => $activeMbr->membership_code,
                    'plan_name' => $activeMbr->plan->name,
                    'facility' => 'PADEL',
                    'quota_type' => $padelBalance?->quota_type ?? 'NONE',
                    'remaining_quota' => $padelBalance ? (float) $padelBalance->remaining_quota : 0.00,
                    'discount_percent' => $padelBalance ? (float) $padelBalance->discount_percent : 0.00,
                    'balance_id' => $padelBalance?->id,
                ];
            } else {
                $this->activeMembershipInfo = null;
            }

            // Reset toggle ke default (ON) setiap ganti customer, biar gak kebawa state customer sebelumnya.
            $this->useMembershipBenefit = true;

            $this->saveDraft();
        }
    }

    public function toggleMembershipBenefit(): void
    {
        $this->useMembershipBenefit = ! $this->useMembershipBenefit;
        $this->saveDraft();
    }

    public function clearSelectedCustomer(): void
    {
        $this->selectedCustomerId = null;
        $this->selectedCustomerName = null;
        $this->selectedCustomerPhone = null;
        $this->useMembershipBenefit = true;
        $this->activeMembershipInfo = null;
        $this->customerSearch = '';
        $this->saveDraft();
    }

    public function updatedWalkInName(): void
    {
        $this->saveDraft();
    }

    public function updatedWalkInPhone(): void
    {
        $this->saveDraft();
    }

    public function updatedWalkInEmail(): void
    {
        $this->saveDraft();
    }

    public function updatedCustomerSearch(): void
    {
        $term = trim($this->customerSearch);

        if (strlen($term) < 2) {
            $this->searchResults = [];
            return;
        }

        $this->searchResults = User::where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        })
            ->limit(5)
            ->get()
            ->all();
    }

    // Memoisasi per-request buat rantai kalkulasi harga (courtTotal -> membershipDiscountAmount ->
    // equipmentTotal -> subtotal -> financeCalculation -> grandTotal/taxAmount/adminFeeAmount/dst).
    // Properti Filament Page pakai magic __get biasa (BUKAN #[Computed] Livewire yang otomatis
    // di-memoize) — tanpa cache manual ini, tiap `$this->grandTotal` dipanggil di Blade akan
    // mengeksekusi ULANG seluruh rantai dari nol, termasuk query DB di getEquipmentTotalProperty()
    // dan getFinanceCalculationProperty(). Karena properti-properti cache ini `protected` (bukan
    // `public`), Livewire TIDAK menyertakannya saat hydrate/dehydrate antar request — otomatis
    // "kosong lagi" di setiap request baru, jadi tidak ada resiko data basi nyangkut ke transaksi lain.
    protected ?float $courtTotalCache = null;
    protected ?float $equipmentTotalCache = null;
    protected ?float $membershipDiscountAmountCache = null;
    protected ?float $subtotalCache = null;
    protected ?array $financeCalculationCache = null;

    public function getCourtTotalProperty(): float
    {
        return $this->courtTotalCache ??= (float) array_sum(array_column($this->selectedSlots, 'price'));
    }

    public function getEquipmentTotalProperty(): float
    {
        if ($this->equipmentTotalCache !== null) {
            return $this->equipmentTotalCache;
        }

        $selectedIds = array_keys(array_filter($this->rentalQuantities, fn ($qty) => $qty > 0));
        if (empty($selectedIds)) {
            return $this->equipmentTotalCache = 0.0;
        }

        // Satu query batch (bukan find() di dalam loop) — menghindari N+1 per macam alat yang disewa.
        $equipmentsById = CourtEquipment::whereIn('id', $selectedIds)->where('is_active', true)->get()->keyBy('id');

        $total = 0.0;
        foreach ($this->rentalQuantities as $eqId => $qty) {
            if ($qty > 0 && isset($equipmentsById[$eqId])) {
                $total += ((float) $equipmentsById[$eqId]->rental_price * $qty);
            }
        }

        return $this->equipmentTotalCache = $total;
    }

    /**
     * Preview (read-only) potongan membership terhadap slot yang lagi dipilih — dihitung pakai aturan
     * yang SAMA persis dengan ManagesCheckoutAndPayments::applyMembershipBenefitToCourtBookings() (kuota
     * jam menutup penuh biaya lapangan sampai kuota habis, sisanya/kalau NONE pakai diskon persen flat)
     * supaya angka yang keliatan di layar kasir gak pernah beda sama yang beneran dipotong pas checkout.
     */
    public function getMembershipDiscountAmountProperty(): float
    {
        if ($this->membershipDiscountAmountCache !== null) {
            return $this->membershipDiscountAmountCache;
        }

        if (! $this->useMembershipBenefit || ! $this->activeMembershipInfo) {
            return $this->membershipDiscountAmountCache = 0.0;
        }

        $info = $this->activeMembershipInfo;
        $discountTotal = 0.0;

        if ($info['quota_type'] === 'HOURS') {
            $remaining = (float) $info['remaining_quota'];
            foreach ($this->selectedSlots as $slot) {
                $durationMinutes = \Carbon\Carbon::parse($slot['start_time'])->diffInMinutes(\Carbon\Carbon::parse($slot['end_time']));
                $hoursNeeded = max(0.5, round($durationMinutes / 60, 2));
                if ($remaining >= $hoursNeeded) {
                    $discountTotal += (float) $slot['price'];
                    $remaining -= $hoursNeeded;
                }
            }
        } elseif ((float) $info['discount_percent'] > 0) {
            $discountTotal = round($this->courtTotal * ((float) $info['discount_percent'] / 100), 2);
        }

        return $this->membershipDiscountAmountCache = min($discountTotal, $this->courtTotal);
    }

    public function getSubtotalProperty(): float
    {
        return $this->subtotalCache ??= max(0, $this->courtTotal - $this->membershipDiscountAmount) + $this->equipmentTotal;
    }

    public function getFinanceCalculationProperty(): array
    {
        return $this->financeCalculationCache ??= app(\App\Services\Finance\TaxAndFeeService::class)->calculate(
            subtotal: $this->subtotal,
            discountAmount: 0,
            channel: 'POS_WALKIN',
            module: 'PADEL'
        );
    }

    public function getTaxAmountProperty(): int
    {
        return $this->financeCalculation['tax_amount'];
    }

    public function getTaxNameProperty(): string
    {
        return $this->financeCalculation['tax_name'] ?: 'PB1 Pajak Daerah / PPh';
    }

    public function getIsTaxEnabledProperty(): bool
    {
        return $this->financeCalculation['tax_enabled'];
    }

    public function getAdminFeeAmountProperty(): int
    {
        return $this->financeCalculation['admin_fee_amount'];
    }

    public function getAdminFeeNameProperty(): string
    {
        return $this->financeCalculation['admin_fee_name'] ?: 'Biaya Layanan';
    }

    public function getIsAdminFeeEnabledProperty(): bool
    {
        return $this->financeCalculation['admin_fee_enabled'];
    }

    public function getGrandTotalProperty(): float
    {
        return (float) $this->financeCalculation['grand_total'];
    }

    public function getActiveShiftProperty(): ?PosCashierShift
    {
        return PosCashierShift::getActiveShift('PADEL_FRONTDESK');
    }

    public function openShiftModal(): void
    {
        $this->startingCashInput = 0.00;
        $this->openingNotes = '';
        $this->showOpenShiftModal = true;
    }

    public function executeOpenShift(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->hasAnyRole(['super_admin', 'admin']) || $user->can('open_pos_shift')),
            403,
            'Akses ditolak: Anda tidak memiliki izin [open_pos_shift] untuk membuka sesi shift kasir.'
        );

        // Anti-race: bungkus cek+create shift dengan distributed lock (pola sama persis dengan
        // padel_lock di ManagesScheduleAndSlots). Tanpa ini, 2 admin (atau 1 admin yang double-klik
        // saat koneksi lemot) bisa sama-sama lolos cek "belum ada shift aktif" di bawah dan membentuk
        // 2 shift OPEN sekaligus untuk counter yang sama — tidak ada unique constraint DB yang menahan
        // ini (pos_cashier_shifts cuma unique di shift_number, bukan di kombinasi counter+status).
        $lock = Cache::lock('pos_open_shift:PADEL_FRONTDESK', 10);
        if (! $lock->get()) {
            Notification::make()
                ->title('Sedang Diproses')
                ->body('Ada permintaan buka shift lain yang sedang diproses. Silakan coba lagi sesaat.')
                ->warning()
                ->send();
            return;
        }

        try {
            if ($this->activeShift) {
                Notification::make()
                    ->title('Shift Sudah Terbuka')
                    ->body('Loket Padel Frontdesk sudah memiliki sesi shift yang aktif.')
                    ->warning()
                    ->send();
                $this->showOpenShiftModal = false;
                return;
            }

            $shiftNumber = PosCashierShift::generateShiftNumber('PADEL_FRONTDESK');

            PosCashierShift::create([
                'shift_number' => $shiftNumber,
                'counter' => 'PADEL_FRONTDESK',
                'status' => 'OPEN',
                'opened_by_id' => $user->id,
                'opened_at' => Carbon::now('Asia/Jakarta'),
                'starting_cash' => (float) $this->startingCashInput,
                'expected_cash' => (float) $this->startingCashInput,
                'opening_notes' => trim($this->openingNotes) ?: null,
            ]);

            Notification::make()
                ->title('Shift Kasir Berhasil Dibuka')
                ->body("Sesi {$shiftNumber} aktif. Modal awal kas: Rp " . number_format((float) $this->startingCashInput, 0, ',', '.'))
                ->success()
                ->send();

            $this->showOpenShiftModal = false;
        } finally {
            $lock->release();
        }
    }

    public function prepareCloseShift(): void
    {
        $shift = $this->activeShift;
        if (! $shift) {
            Notification::make()
                ->title('Tidak Ada Shift Aktif')
                ->body('Belum ada shift kasir yang terbuka saat ini.')
                ->warning()
                ->send();
            return;
        }

        $summary = $shift->calculateSummary();
        $this->closingShiftSummary = $summary;
        $this->actualCashInput = null;
        $this->closingNotes = '';
        $this->showCloseShiftModal = true;
    }

    public function executeCloseShift(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->hasAnyRole(['super_admin', 'admin']) || $user->can('close_pos_shift')),
            403,
            'Akses ditolak: Anda tidak memiliki izin [close_pos_shift] untuk menutup sesi shift kasir.'
        );

        $shift = $this->activeShift;

        if (! $shift) {
            $this->showCloseShiftModal = false;
            return;
        }

        if ($this->actualCashInput === null || $this->actualCashInput === '') {
            Notification::make()
                ->title('Hitung Fisik Kas Wajib Diisi')
                ->body('Silakan masukkan total fisik uang tunai di laci kasir (Blind Cash Count).')
                ->danger()
                ->send();
            return;
        }

        $summary = $shift->calculateSummary();
        $actualCash = (float) $this->actualCashInput;
        $cashDifference = $actualCash - $summary['expected_cash'];

        $shift->update([
            'status' => 'CLOSED',
            'closed_by_id' => $user?->id,
            'closed_at' => Carbon::now('Asia/Jakarta'),
            'expected_cash' => $summary['expected_cash'],
            'actual_cash' => $actualCash,
            'cash_difference' => $cashDifference,
            'total_cash_sales' => $summary['total_cash_sales'],
            'total_edc_bca_sales' => $summary['total_edc_bca_sales'],
            'total_edc_mandiri_sales' => $summary['total_edc_mandiri_sales'],
            'total_qris_sales' => $summary['total_qris_sales'],
            'total_other_sales' => $summary['total_other_sales'],
            'total_sales' => $summary['total_sales'],
            'total_transactions' => $summary['total_transactions'],
            'closing_notes' => trim($this->closingNotes) ?: null,
        ]);

        $this->reportShiftData = [
            'shift_number' => $shift->shift_number,
            'counter' => $shift->counter,
            'opened_by' => $shift->openedBy?->name ?? 'Kasir',
            'closed_by' => $user?->name ?? 'Kasir',
            'opened_at' => $shift->opened_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'closed_at' => Carbon::now('Asia/Jakarta')->format('d/m/Y H:i'),
            'starting_cash' => (float) $shift->starting_cash,
            'total_cash_sales' => $summary['total_cash_sales'],
            'total_edc_bca_sales' => $summary['total_edc_bca_sales'],
            'total_edc_mandiri_sales' => $summary['total_edc_mandiri_sales'],
            'total_qris_sales' => $summary['total_qris_sales'],
            'total_other_sales' => $summary['total_other_sales'],
            'total_sales' => $summary['total_sales'],
            'total_transactions' => $summary['total_transactions'],
            'expected_cash' => $summary['expected_cash'],
            'actual_cash' => $actualCash,
            'cash_difference' => $cashDifference,
            'closing_notes' => $shift->closing_notes,
        ];

        $this->showCloseShiftModal = false;
        $this->showShiftReportModal = true;

        Notification::make()
            ->title('Shift Kasir Berhasil Ditutup')
            ->body("Sesi {$shift->shift_number} resmi ditutup. Rekapitulasi kas selesai.")
            ->success()
            ->send();
    }

    public function closeShiftReportModal(): void
    {
        $this->showShiftReportModal = false;
        $this->reportShiftData = null;
    }

    protected function getDraftCacheKey(): string
    {
        $userId = auth()->id() ?? 'guest';
        return "pos_walkin_draft:{$userId}";
    }

    public function checkPendingDraft(): void
    {
        $key = $this->getDraftCacheKey();
        $draft = Cache::get($key);

        if ($draft && ! empty($draft['selectedSlots'])) {
            $this->hasPendingDraft = true;
            $this->pendingDraftSummary = [
                'bookingDate' => $draft['bookingDate'] ?? now()->format('Y-m-d'),
                'slotsCount' => count($draft['selectedSlots']),
                'customerName' => ! empty($draft['walkInName']) ? $draft['walkInName'] : (! empty($draft['selectedCustomerName']) ? $draft['selectedCustomerName'] : 'Pelanggan Walk-In'),
                'savedAt' => $draft['savedAt'] ?? now('Asia/Jakarta')->format('H:i'),
                'grandTotal' => $draft['grandTotal'] ?? 0,
            ];
        } else {
            $this->hasPendingDraft = false;
            $this->pendingDraftSummary = null;
        }
    }

    public function saveDraft(): void
    {
        if (empty($this->selectedSlots)) {
            return;
        }

        $key = $this->getDraftCacheKey();
        Cache::put($key, [
            'bookingDate' => $this->bookingDate,
            'selectedSlots' => $this->selectedSlots,
            'customerMode' => $this->customerMode,
            'selectedCustomerId' => $this->selectedCustomerId,
            'selectedCustomerName' => $this->selectedCustomerName,
            'selectedCustomerPhone' => $this->selectedCustomerPhone,
            'walkInName' => $this->walkInName,
            'walkInPhone' => $this->walkInPhone,
            'walkInEmail' => $this->walkInEmail,
            'rentalQuantities' => $this->rentalQuantities,
            'paymentMethod' => $this->paymentMethod,
            'edcTerminal' => $this->edcTerminal,
            'edcCardType' => $this->edcCardType,
            'edcCardNetwork' => $this->edcCardNetwork,
            'edcBank' => $this->edcBank,
            'edcLast4' => $this->edcLast4,
            'edcApprovalCode' => $this->edcApprovalCode,
            'edcTraceNumber' => $this->edcTraceNumber,
            'qrisProvider' => $this->qrisProvider,
            'qrisRrn' => $this->qrisRrn,
            'qrisSenderName' => $this->qrisSenderName,
            'isAutoCheckIn' => $this->isAutoCheckIn,
            'grandTotal' => $this->grandTotal,
            'savedAt' => now('Asia/Jakarta')->format('H:i'),
        ], 7200);
    }

    public function resumeDraft(): void
    {
        $key = $this->getDraftCacheKey();
        $draft = Cache::get($key);

        if (! $draft) {
            $this->hasPendingDraft = false;
            $this->pendingDraftSummary = null;
            return;
        }

        $this->bookingDate = $draft['bookingDate'] ?? now()->format('Y-m-d');
        $this->customerMode = $draft['customerMode'] ?? 'quick_create';
        $this->selectedCustomerId = $draft['selectedCustomerId'] ?? null;
        $this->selectedCustomerName = $draft['selectedCustomerName'] ?? null;
        $this->selectedCustomerPhone = $draft['selectedCustomerPhone'] ?? null;
        $this->walkInName = $draft['walkInName'] ?? '';
        $this->walkInPhone = $draft['walkInPhone'] ?? '';
        $this->walkInEmail = $draft['walkInEmail'] ?? '';
        $this->rentalQuantities = $draft['rentalQuantities'] ?? [];
        $this->paymentMethod = $draft['paymentMethod'] ?? 'CASH';
        $this->edcTerminal = $draft['edcTerminal'] ?? 'EDC_BCA';
        $this->edcCardType = $draft['edcCardType'] ?? 'DEBIT';
        $this->edcCardNetwork = $draft['edcCardNetwork'] ?? 'GPN';
        $this->edcBank = $draft['edcBank'] ?? 'BCA';
        $this->edcLast4 = $draft['edcLast4'] ?? '';
        $this->edcApprovalCode = $draft['edcApprovalCode'] ?? '';
        $this->edcTraceNumber = $draft['edcTraceNumber'] ?? '';
        $this->qrisProvider = $draft['qrisProvider'] ?? 'BCA_QRIS';
        $this->qrisRrn = $draft['qrisRrn'] ?? '';
        $this->qrisSenderName = $draft['qrisSenderName'] ?? '';
        $this->isAutoCheckIn = $draft['isAutoCheckIn'] ?? false;

        $draftSlots = $draft['selectedSlots'] ?? [];
        $conflicted = false;
        $validSlots = [];

        $isToday = $this->bookingDate === now()->format('Y-m-d');
        $currentHour = (int) now()->format('H');

        foreach ($draftSlots as $keySlot => $slot) {
            $startDt = "{$this->bookingDate} {$slot['start_time']}";
            $endDt = "{$this->bookingDate} {$slot['end_time']}";
            $slotHour = (int) substr($slot['start_time'], 0, 2);

            if ($isToday && $slotHour < $currentHour) {
                $conflicted = true;
                continue;
            }

            $exists = PadelBooking::where('court_id', $slot['court_id'])
                ->whereDate('booking_date', $this->bookingDate)
                ->whereIn('status', ['LOCKED', 'PENDING_PAYMENT', 'PENDING', 'PAID', 'CHECKED_IN', 'COMPLETED'])
                ->where(function ($q) use ($startDt, $endDt) {
                    $q->where('start_time', '<', $endDt)
                        ->where('end_time', '>', $startDt);
                })
                ->exists();

            if ($exists) {
                $conflicted = true;
            } else {
                $validSlots[$keySlot] = $slot;
            }
        }

        $this->selectedSlots = $validSlots;
        $this->hasPendingDraft = false;
        $this->pendingDraftSummary = null;

        if ($conflicted || empty($validSlots)) {
            $this->posStep = 'selection';
            Notification::make()
                ->title('Beberapa Slot Tidak Tersedia')
                ->body('Satu atau lebih slot draf sebelumnya telah terisi atau kedaluwarsa. Data pelanggan telah dipulihkan, silakan sesuaikan slot lapangan pada jadwal.')
                ->warning()
                ->send();
        } else {
            $this->posStep = 'payment';
            $this->cashReceived = $this->grandTotal;
            $this->calculateCashChange();
            Notification::make()
                ->title('Draf Transaksi Dipulihkan')
                ->body('Layar pembayaran berhasil dipulihkan dari transaksi sebelumnya.')
                ->success()
                ->send();
        }
    }

    public function discardDraft(): void
    {
        Cache::forget($this->getDraftCacheKey());
        $this->hasPendingDraft = false;
        $this->pendingDraftSummary = null;
        $this->clearSelectedSlots();
        $this->walkInName = '';
        $this->walkInPhone = '';
        $this->walkInEmail = '';
        $this->clearSelectedCustomer();
        $this->initializeEquipmentQuantities();
        $this->posStep = 'selection';

        Notification::make()
            ->title('Draf Dihapus')
            ->body('Draf transaksi kasir telah dibersihkan.')
            ->info()
            ->send();
    }

    public function proceedToPayment(): void
    {
        // 0. Guard Shift Kasir Aktif PADEL_FRONTDESK
        $activeShift = $this->activeShift;
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser && method_exists($currentUser, 'hasRole') && $currentUser->hasRole('super_admin');

        if (! $activeShift && ! $isSuperAdmin) {
            Notification::make()
                ->title('Shift Kasir Belum Dibuka')
                ->body('Silakan buka sesi shift kasir terlebih dahulu sebelum melanjutkan ke pembayaran.')
                ->danger()
                ->send();
            return;
        }

        // 1. Validasi slot
        if (empty($this->selectedSlots)) {
            Notification::make()
                ->title('Slot Belum Dipilih')
                ->body('Silakan pilih minimal satu slot jam bermain pada timetable grid sebelum melanjutkan.')
                ->warning()
                ->send();
            return;
        }

        // 2. Validasi Customer
        if ($this->customerMode === 'search') {
            if (empty($this->selectedCustomerId)) {
                Notification::make()
                    ->title('Customer Belum Dipilih')
                    ->body('Silakan cari dan pilih pelanggan terdaftar, atau beralih ke form Walk-In Baru.')
                    ->warning()
                    ->send();
                return;
            }
        } else {
            $name = trim($this->walkInName);
            $phone = trim($this->walkInPhone);

            if (empty($name)) {
                Notification::make()
                    ->title('Nama Wajib Diisi')
                    ->body('Silakan masukkan nama pelanggan walk-in.')
                    ->warning()
                    ->send();
                return;
            }

            if (empty($phone) || strlen(preg_replace('/[^0-9]/', '', $phone)) < 8) {
                Notification::make()
                    ->title('Nomor Telepon Tidak Valid')
                    ->body('Silakan masukkan nomor WhatsApp / telepon aktif minimal 8 digit.')
                    ->warning()
                    ->send();
                return;
            }
        }

        // Default Tunai
        if ($this->paymentMethod === 'CASH') {
            if ($this->cashReceived === null || $this->cashReceived < $this->grandTotal) {
                $this->cashReceived = $this->grandTotal;
            }
            $this->calculateCashChange();
        }

        $this->saveDraft();
        $this->posStep = 'payment';
    }

    public function backToSelection(): void
    {
        $this->saveDraft();
        $this->posStep = 'selection';
    }

    public function startNewTransaction(): void
    {
        Cache::forget($this->getDraftCacheKey());
        $this->hasPendingDraft = false;
        $this->pendingDraftSummary = null;
        $this->selectedSlots = [];
        $this->initializeEquipmentQuantities();
        $this->walkInName = '';
        $this->walkInPhone = '';
        $this->walkInEmail = '';
        $this->selectedCustomerId = null;
        $this->selectedCustomerName = null;
        $this->selectedCustomerPhone = null;
        $this->paymentMethod = 'CASH';
        $this->cashReceived = null;
        $this->cashChange = 0.00;
        $this->edcLast4 = '';
        $this->edcApprovalCode = '';
        $this->edcTraceNumber = '';
        $this->qrisRrn = '';
        $this->qrisSenderName = '';
        $this->showSuccessModal = false;
        $this->completedOrderData = null;
        $this->posStep = 'selection';
    }

    public function setPaymentMethod(string $method): void
    {
        $this->paymentMethod = $method;

        if ($method === 'CASH') {
            if ($this->cashReceived === null || $this->cashReceived < $this->grandTotal) {
                $this->cashReceived = $this->grandTotal;
            }
            $this->calculateCashChange();
        } elseif ($method === 'DEBIT_CARD' || $method === 'DEBIT') {
            $this->edcCardType = 'DEBIT';
            if (! in_array($this->edcCardNetwork, ['GPN', 'MASTERCARD', 'VISA'])) {
                $this->edcCardNetwork = 'GPN';
            }
        } elseif ($method === 'CREDIT_CARD' || $method === 'CREDIT') {
            $this->edcCardType = 'CREDIT';
            if (! in_array($this->edcCardNetwork, ['VISA', 'MASTERCARD', 'JCB', 'AMEX', 'UNIONPAY'])) {
                $this->edcCardNetwork = 'VISA';
            }
        }

        $this->saveDraft();
    }

    public function setQuickCash(float $amount): void
    {
        $this->cashReceived = $amount;
        $this->calculateCashChange();
    }

    public function updatedCashReceived(): void
    {
        $this->calculateCashChange();
    }

    public function calculateCashChange(): void
    {
        $total = $this->grandTotal;
        $received = (float) ($this->cashReceived ?? 0);
        $this->cashChange = max(0, $received - $total);
    }

    public function submitWalkInBooking(PadelBookingService $service): void
    {
        // 0. Guard Permission
        abort_unless(auth()->user() && auth()->user()->can('process_walkin_booking'), 403, 'Akses ditolak: Anda tidak memiliki izin untuk memproses transaksi walk-in.');

        // 0.1 Guard Shift Kasir Aktif
        $activeShift = $this->activeShift;
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser && method_exists($currentUser, 'hasRole') && $currentUser->hasRole('super_admin');

        if (! $activeShift && ! $isSuperAdmin) {
            Notification::make()
                ->title('Shift Kasir Belum Dibuka')
                ->body('Silakan buka sesi shift kasir terlebih dahulu sebelum melayani transaksi walk-in.')
                ->danger()
                ->send();
            return;
        }

        // 1. Validasi slot
        if (empty($this->selectedSlots)) {
            Notification::make()
                ->title('Slot Belum Dipilih')
                ->body('Silakan pilih minimal satu slot jam bermain pada timetable grid sebelum melanjutkan.')
                ->warning()
                ->send();
            return;
        }

        // 2. Identifikasi Customer
        $customer = null;
        if ($this->customerMode === 'search') {
            if (empty($this->selectedCustomerId)) {
                Notification::make()
                    ->title('Customer Belum Dipilih')
                    ->body('Silakan cari dan pilih pelanggan terdaftar, atau beralih ke form Walk-In Cepat.')
                    ->warning()
                    ->send();
                return;
            }
            $customer = User::find($this->selectedCustomerId);
            if (! $customer) {
                Notification::make()
                    ->title('Customer Tidak Ditemukan')
                    ->body('Data pelanggan terdaftar tidak valid atau telah dihapus.')
                    ->danger()
                    ->send();
                return;
            }
        } else {
            $name = trim($this->walkInName);
            $phone = trim($this->walkInPhone);

            if (empty($name)) {
                Notification::make()
                    ->title('Nama Wajib Diisi')
                    ->body('Silakan masukkan nama pelanggan walk-in.')
                    ->warning()
                    ->send();
                return;
            }

            if (empty($phone) || strlen(preg_replace('/[^0-9]/', '', $phone)) < 8) {
                Notification::make()
                    ->title('Nomor Telepon Tidak Valid')
                    ->body('Silakan masukkan nomor WhatsApp / telepon aktif minimal 8 digit.')
                    ->warning()
                    ->send();
                return;
            }

            $customer = $service->findOrCreateWalkInCustomer(
                name: $name,
                phone: $phone,
                email: ! empty($this->walkInEmail) ? trim($this->walkInEmail) : null
            );
        }

        // 2.1 Validasi Ketat Metode Pembayaran
        $method = strtoupper($this->paymentMethod);
        $grandTotal = $this->grandTotal;
        $paymentMeta = [];

        if ($method === 'CASH') {
            if ($this->cashReceived === null || (float) $this->cashReceived < (float) $grandTotal) {
                Notification::make()
                    ->title('Nominal Tunai Kurang')
                    ->body('Uang tunai yang diterima (Rp ' . number_format((float) ($this->cashReceived ?? 0), 0, ',', '.') . ') kurang dari total tagihan (Rp ' . number_format($grandTotal, 0, ',', '.') . ').')
                    ->danger()
                    ->send();
                return;
            }
            $this->calculateCashChange();
            $paymentMeta = [
                'cash_received' => (float) $this->cashReceived,
                'cash_change' => (float) $this->cashChange,
            ];
        } elseif (in_array($method, ['DEBIT_CARD', 'CREDIT_CARD', 'EDC_BCA', 'EDC_MANDIRI', 'DEBIT', 'CREDIT'])) {
            $last4 = trim($this->edcLast4);
            $approvalCode = trim($this->edcApprovalCode);
            $traceNumber = trim($this->edcTraceNumber);

            if (! preg_match('/^[0-9]{4}$/', $last4)) {
                Notification::make()
                    ->title('4 Digit Kartu Tidak Valid')
                    ->body('Silakan masukkan tepat 4 digit angka terakhir dari kartu debit/kredit pelanggan.')
                    ->danger()
                    ->send();
                return;
            }

            if (empty($approvalCode) || strlen($approvalCode) < 3) {
                Notification::make()
                    ->title('Approval Code Wajib Diisi')
                    ->body('Silakan masukkan nomor otorisasi/approval code dari slip transaksi mesin EDC.')
                    ->danger()
                    ->send();
                return;
            }

            if (empty($traceNumber) || strlen($traceNumber) < 3) {
                Notification::make()
                    ->title('Trace Number Wajib Diisi')
                    ->body('Silakan masukkan nomor trace / audit number dari slip transaksi mesin EDC.')
                    ->danger()
                    ->send();
                return;
            }

            $cardType = (str_contains($method, 'CREDIT') || $this->edcCardType === 'CREDIT') ? 'CREDIT' : 'DEBIT';
            $terminal = ! empty($this->edcTerminal) ? $this->edcTerminal : ($method === 'EDC_MANDIRI' ? 'EDC_MANDIRI' : 'EDC_BCA');

            $paymentMeta = [
                'terminal' => $terminal,
                'card_type' => $cardType,
                'card_network' => $this->edcCardNetwork,
                'card_issuer' => $this->edcBank,
                'card_last_4' => $last4,
                'approval_code' => $approvalCode,
                'trace_number' => $traceNumber,
                'charged_amount' => (float) $grandTotal,
            ];
        } elseif (in_array($method, ['QRIS_STATIS', 'QRIS'])) {
            $rrn = trim($this->qrisRrn);

            if (empty($rrn) || strlen($rrn) < 6) {
                Notification::make()
                    ->title('Nomor RRN QRIS Wajib Diisi')
                    ->body('Silakan masukkan nomor RRN (Retrieval Reference Number) minimal 6 digit dari bukti bayar customer.')
                    ->danger()
                    ->send();
                return;
            }

            $paymentMeta = [
                'qris_provider' => $this->qrisProvider,
                'qris_rrn' => $rrn,
                'qris_sender_name' => trim($this->qrisSenderName) ?: null,
            ];
        }

        // 3. Susun array slots untuk service
        $slotsPayload = array_values(array_map(function ($slot) {
            return [
                'court_id' => $slot['court_id'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
            ];
        }, $this->selectedSlots));

        // 4. Susun array equipments
        $equipmentsPayload = [];
        foreach ($this->rentalQuantities as $eqId => $qty) {
            if ($qty > 0) {
                $equipmentsPayload[] = [
                    'equipment_id' => $eqId,
                    'quantity' => (int) $qty,
                ];
            }
        }

        // 5. Kasir bertugas
        $cashier = auth()->user() ?? User::role(['cashier', 'admin', 'super_admin'])->first();

        // 6. Eksekusi transaksi dengan penanganan SlotConflictException
        try {
            $result = $service->processWalkInCheckout(
                customer: $customer,
                slots: $slotsPayload,
                bookingDate: $this->bookingDate,
                equipments: $equipmentsPayload,
                paymentMethod: $this->paymentMethod,
                cashier: $cashier,
                autoCheckIn: $this->isAutoCheckIn,
                paymentMeta: $paymentMeta,
                // 'NONE' kalau kasir sengaja matiin toggle benefit membership untuk transaksi ini —
                // konsisten dengan guard yang sama dipakai di jalur online checkout.
                membershipBalanceId: $this->useMembershipBenefit ? ($this->activeMembershipInfo['balance_id'] ?? null) : 'NONE'
            );

            // Siapkan data struk POS thermal
            $this->completedOrderData = [
                'order_number' => $result['order']->order_number,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'cashier_name' => $cashier->name,
                'booking_date' => Carbon::parse($this->bookingDate)->translatedFormat('d F Y'),
                'payment_method' => $service->formatPaymentMethodLabel($this->paymentMethod),
                'subtotal' => $result['order']->subtotal,
                'tax_amount' => $result['order']->tax_amount,
                'tax_name' => $this->taxName,
                'service_charge' => $result['order']->service_charge,
                'admin_fee_name' => $this->adminFeeName,
                'grand_total' => $result['grand_total'],
                'auto_checked_in' => $result['auto_checked_in'],
                'created_at' => now()->format('d/m/Y H:i:s'),
                'payment_meta' => $paymentMeta,
                'bookings' => $result['bookings']->map(function ($b) {
                    return [
                        'booking_code' => $b->booking_code,
                        'court_name' => $b->court?->name ?? 'Lapangan Padel',
                        'time_label' => $b->start_time->format('H:i') . ' - ' . $b->end_time->format('H:i'),
                        'court_fee' => (float) $b->court_fee,
                        'status' => $b->status,
                        'qr_code_hash' => $b->qr_code_hash,
                    ];
                })->toArray(),
                'equipments' => array_values(array_filter(array_map(function ($item) {
                    $eq = CourtEquipment::find($item['equipment_id']);
                    return ($eq && $eq->is_active) ? [
                        'name' => $eq->name,
                        'quantity' => $item['quantity'],
                        'price' => (float) $eq->rental_price * $item['quantity'],
                    ] : null;
                }, $equipmentsPayload))),
            ];

            // Hapus Draf Kasir setelah transaksi berhasil diselesaikan
            Cache::forget($this->getDraftCacheKey());
            $this->hasPendingDraft = false;
            $this->pendingDraftSummary = null;

            // Transisi ke layar Struk Kasir (In-Page)
            $this->posStep = 'receipt';
            $this->showSuccessModal = false;

            Notification::make()
                ->title('Pemesanan Walk-In Berhasil!')
                ->body("Order #{$result['order']->order_number} berhasil dibayar lunas dan e-tiket telah aktif.")
                ->success()
                ->send();

            // Reset seleksi keranjang untuk transaksi berikutnya
            $this->selectedSlots = [];
            $this->initializeEquipmentQuantities();
            $this->walkInName = '';
            $this->walkInPhone = '';
            $this->walkInEmail = '';
            $this->selectedCustomerId = null;
            $this->selectedCustomerName = null;
            $this->selectedCustomerPhone = null;

        } catch (SlotConflictException $e) {
            Notification::make()
                ->title('Slot Tidak Tersedia')
                ->body($e->getMessage() ?: 'Slot jam tersebut baru saja diambil customer online atau sedang di-hold. Silakan pilih slot lain.')
                ->warning()
                ->send();
            $this->posStep = 'selection';
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Memproses Pemesanan Walk-In')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function closeSuccessModal(): void
    {
        $this->showSuccessModal = false;
        $this->completedOrderData = null;
        $this->posStep = 'selection';
    }

    protected function getViewData(): array
    {
        // WAJIB reset cache kalkulasi harga tepat sebelum render — menjamin angka yang ditampilkan SELALU
        // dihitung ulang dari state TERBARU (misal setelah selectedSlots di-reset pasca pembayaran sukses
        // di request yang sama), bukan angka basi yang keburu ke-cache dari pembacaan lebih awal di action
        // method (misal validasi cashReceived saat checkout). Memoisasi di getter-getter-nya tetap berlaku
        // SELAMA render ini berlangsung (jadi tetap cuma dihitung sekali walau dipanggil ~18x di Blade).
        $this->courtTotalCache = null;
        $this->equipmentTotalCache = null;
        $this->membershipDiscountAmountCache = null;
        $this->subtotalCache = null;
        $this->financeCalculationCache = null;

        // Fail-safe sinkronisasi kedaluwarsa — di-throttle max 1x per 15 detik (bukan tiap render/klik).
        // Cache::add() atomic: cuma proses PERTAMA dalam window 15 detik yang benar-benar menjalankan sync,
        // proses lain di window yang sama otomatis skip. Ini murni fail-safe cepat; penegakan expiry yang
        // sebenarnya sudah dijamin scheduled command terpisah, jadi telat beberapa detik di sini aman.
        if (Cache::add('padel_offline_sync_throttle', true, 15)) {
            app(PadelBookingService::class)->syncExpiredAndCompletedBookings();
        }

        $courts = PadelCourt::where('is_active', true)->orderBy('name')->get();
        $isWeekend = Carbon::parse($this->bookingDate)->isWeekend();
        $isToday = $this->bookingDate === now()->format('Y-m-d');
        $currentHour = (int) now()->format('H');

        // Tarik seluruh booking pada tanggal aktif untuk evaluasi cepat
        $existingBookings = PadelBooking::with(['court', 'user'])
            ->whereDate('booking_date', $this->bookingDate)
            ->whereIn('status', ['LOCKED', 'PENDING_PAYMENT', 'PENDING', 'PAID', 'CHECKED_IN', 'COMPLETED'])
            ->get();

        // Susun grid jam operasional dinamis sesuai jam buka & jam tutup lapangan
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
                'start_time' => sprintf('%02d:00:00', $h),
                'end_time' => sprintf('%02d:00:00', $h + 1),
                'label' => sprintf('%02d:00', $h),
                'full_label' => sprintf('%02d:00 - %02d:00', $h, $h + 1),
            ];
        }

        // Ambil SEMUA kemungkinan cache lock key sekaligus (1 query batch), bukan Cache::has() satu-satu
        // per slot di dalam loop di bawah — dengan CACHE_STORE=database, itu berarti puluhan query terpisah
        // per render kalau tidak di-batch (misal 5 lapangan x 17 jam operasional = 85+ query Cache::has()).
        $allLockKeys = [];
        foreach ($courts as $court) {
            foreach ($operationalHours as $oh) {
                $allLockKeys[] = "padel_lock:{$court->id}:{$this->bookingDate}:" . sprintf('%02d00', $oh['hour']);
            }
        }
        $lockValues = ! empty($allLockKeys) ? Cache::many($allLockKeys) : [];

        $gridData = [];
        foreach ($courts as $court) {
            $courtRow = [
                'court' => $court,
                'slots' => [],
            ];

            $courtOpen = (int) substr($court->open_time ?: '06:00', 0, 2);
            $courtCloseVal = $court->close_time ?: '23:00';
            $courtClose = ($courtCloseVal === '00:00' || $courtCloseVal === '24:00') ? 24 : (int) substr($courtCloseVal, 0, 2);

            foreach ($operationalHours as $oh) {
                $h = $oh['hour'];
                $startTimeStr = $oh['start_time'];
                $endTimeStr = $oh['end_time'];
                $slotKey = "{$court->id}_{$startTimeStr}";

                $isPrime = $isWeekend || $h >= 17;
                $rate = $isPrime ? (float) $court->hourly_rate_prime : (float) $court->hourly_rate_regular;

                // Tentukan status slot
                $status = 'AVAILABLE';
                $bookingDetail = null;

                if ($h < $courtOpen || $h >= $courtClose) {
                    $status = 'CLOSED';
                } elseif (isset($this->selectedSlots[$slotKey])) {
                    $status = 'SELECTED';
                } elseif ($isToday && $h < $currentHour) {
                    $status = 'PAST';
                } else {
                    $slotStartDt = "{$this->bookingDate} {$startTimeStr}";
                    $slotEndDt = "{$this->bookingDate} {$endTimeStr}";

                    // Cari booking database yang overlap
                    $matchedBooking = $existingBookings->first(function ($b) use ($court, $slotStartDt, $slotEndDt) {
                        return $b->court_id === $court->id
                            && $b->start_time->format('Y-m-d H:i:s') < $slotEndDt
                            && $b->end_time->format('Y-m-d H:i:s') > $slotStartDt;
                    });

                    if ($matchedBooking) {
                        if (in_array($matchedBooking->status, ['PAID', 'CHECKED_IN', 'COMPLETED'])) {
                            $status = 'BOOKED';
                            $bookingDetail = [
                                'code' => $matchedBooking->booking_code,
                                'player' => $matchedBooking->user?->name ?? 'Pemain',
                                'status' => $matchedBooking->status,
                            ];
                        } else {
                            $status = 'LOCKED';
                            $bookingDetail = [
                                'code' => $matchedBooking->booking_code,
                                'player' => 'Checkout...',
                                'status' => 'LOCKED',
                            ];
                        }
                    } else {
                        // Cek Cache Lock (Hold transaksi online) — dari batch $lockValues yang sudah diambil
                        // sekaligus di atas, bukan query Cache::has() baru per slot.
                        $lockKey = "padel_lock:{$court->id}:{$this->bookingDate}:" . sprintf('%02d00', $h);
                        if (! empty($lockValues[$lockKey])) {
                            $status = 'LOCKED';
                            $bookingDetail = [
                                'code' => 'HOLD',
                                'player' => 'Sedang Dipilih',
                                'status' => 'LOCKED',
                            ];
                        }
                    }
                }

                $courtRow['slots'][] = [
                    'slot_key' => $slotKey,
                    'hour' => $h,
                    'start_time' => $startTimeStr,
                    'end_time' => $endTimeStr,
                    'label' => $oh['label'],
                    'full_label' => $oh['full_label'],
                    'rate' => $rate,
                    'is_prime' => $isPrime,
                    'status' => $status,
                    'booking' => $bookingDetail,
                ];
            }

            $gridData[] = $courtRow;
        }

        // Ringkasan okupansi tanggal yang sedang ditampilkan di grid
        $totalSlotsAll = 0;
        $bookedSlotsAll = 0;
        foreach ($gridData as $courtRow) {
            foreach ($courtRow['slots'] as $slot) {
                if ($slot['status'] === 'PAST') {
                    continue;
                }
                $totalSlotsAll++;
                if (in_array($slot['status'], ['BOOKED', 'LOCKED', 'SELECTED'], true)) {
                    $bookedSlotsAll++;
                }
            }
        }

        // Statistik & riwayat transaksi walk-in HARI INI — di-cache 10 detik. Angka ini cuma buat
        // ditampilin di pojok layar (bukan input keputusan transaksi), jadi selisih beberapa detik
        // gak masalah, tapi query-nya sendiri gak perlu jalan ulang di SETIAP klik slot/tombol lain.
        $walkInStatsCacheKey = 'pos_walkin_stats_today:' . now()->format('Y-m-d');
        $walkInStatsToday = Cache::remember($walkInStatsCacheKey, 10, function () {
            $query = \App\Models\Pos\Order::where('order_type', 'WALK_IN')->whereDate('created_at', now());

            return [
                'count' => (clone $query)->count(),
                'revenue' => (float) (clone $query)->sum('grand_total'),
            ];
        });

        $recentWalkInOrdersCacheKey = 'pos_walkin_recent_orders:' . now()->format('Y-m-d');
        $recentWalkInOrderIds = Cache::remember($recentWalkInOrdersCacheKey, 10, function () {
            return \App\Models\Pos\Order::where('order_type', 'WALK_IN')
                ->whereDate('created_at', now())
                ->latest()
                ->limit(8)
                ->pluck('id');
        });
        // Eager-load relasi tetap dijalankan tiap render (bukan ikut di-cache) supaya data user/booking
        // yang ditampilkan selalu representasi terbaru, hanya DAFTAR ID order-nya yang di-throttle.
        $recentWalkInOrders = \App\Models\Pos\Order::whereIn('id', $recentWalkInOrderIds)
            ->with(['user', 'padelBookings.court'])
            ->latest()
            ->get();

        $equipments = CourtEquipment::where('is_active', true)->orderBy('type')->orderBy('name')->get();

        // Hasil pencarian customer sekarang diisi via updatedCustomerSearch() (lifecycle hook), bukan
        // di-query ulang di sini setiap render — lihat method updatedCustomerSearch().
        $searchResults = $this->searchResults;

        return [
            'gridData' => $gridData,
            'operationalHours' => $operationalHours,
            'equipments' => $equipments,
            'searchResults' => $searchResults,
            'isToday' => $isToday,
            'totalSlotsAll' => $totalSlotsAll,
            'bookedSlotsAll' => $bookedSlotsAll,
            'walkInStatsToday' => $walkInStatsToday,
            'recentWalkInOrders' => $recentWalkInOrders,
            'activeShift' => $this->activeShift,
        ];
    }
}
