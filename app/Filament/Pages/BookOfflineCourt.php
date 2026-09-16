<?php

namespace App\Filament\Pages;

use App\Exceptions\SlotConflictException;
use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
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

    protected static ?string $navigationLabel = 'Walk-In Booking';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Walk-In Offline Booking & Frontdesk POS';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.book-offline-court';

    // Tanggal Booking Aktif
    public string $bookingDate;

    // Slot yang Dipilih Kasir: [$slotKey => ['court_id', 'court_name', 'start_time', 'end_time', 'time_label', 'price']]
    public array $selectedSlots = [];

    // Mode Pelanggan: 'quick_create' atau 'search'
    public string $customerMode = 'quick_create';

    public string $customerSearch = '';

    public ?string $selectedCustomerId = null;

    public ?string $selectedCustomerName = null;

    public ?string $selectedCustomerPhone = null;

    // Form Walk-In Cepat
    public string $walkInName = '';

    public string $walkInPhone = '';

    public string $walkInEmail = '';

    // Add-On Sewa Alat: [$equipmentId => $quantity]
    public array $rentalQuantities = [];

    // Metode Pembayaran Kasir: 'CASH', 'EDC_BCA', 'EDC_MANDIRI', 'QRIS_STATIS'
    public string $paymentMethod = 'CASH';

    // Opsi Software Auto Check-In
    public bool $isAutoCheckIn = false;

    // Modal Sukses & Struk POS
    public bool $showSuccessModal = false;

    public ?array $completedOrderData = null;

    public function mount(): void
    {
        $this->bookingDate = now()->format('Y-m-d');
        $this->initializeEquipmentQuantities();
    }

    protected function initializeEquipmentQuantities(): void
    {
        $equipments = CourtEquipment::all();
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
            $this->selectedSlots[$slotKey] = [
                'court_id' => $courtId,
                'court_name' => $courtName,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'time_label' => substr($startTime, 0, 5) . ' - ' . substr($endTime, 0, 5),
                'price' => $price,
            ];
        }
    }

    public function removeSlot(string $slotKey): void
    {
        unset($this->selectedSlots[$slotKey]);
    }

    public function clearSelectedSlots(): void
    {
        $this->selectedSlots = [];
    }

    public function incrementEquipment(string $equipmentId, int $maxStock): void
    {
        $current = $this->rentalQuantities[$equipmentId] ?? 0;
        if ($current < $maxStock) {
            $this->rentalQuantities[$equipmentId] = $current + 1;
        }
    }

    public function decrementEquipment(string $equipmentId): void
    {
        $current = $this->rentalQuantities[$equipmentId] ?? 0;
        if ($current > 0) {
            $this->rentalQuantities[$equipmentId] = $current - 1;
        }
    }

    public function setCustomerMode(string $mode): void
    {
        $this->customerMode = $mode;
    }

    public function selectCustomer(string $userId): void
    {
        $user = User::find($userId);
        if ($user) {
            $this->selectedCustomerId = $user->id;
            $this->selectedCustomerName = $user->name;
            $this->selectedCustomerPhone = $user->phone;
            $this->customerSearch = '';
        }
    }

    public function clearSelectedCustomer(): void
    {
        $this->selectedCustomerId = null;
        $this->selectedCustomerName = null;
        $this->selectedCustomerPhone = null;
        $this->customerSearch = '';
    }

    public function getCourtTotalProperty(): float
    {
        return (float) array_sum(array_column($this->selectedSlots, 'price'));
    }

    public function getEquipmentTotalProperty(): float
    {
        $total = 0;
        foreach ($this->rentalQuantities as $eqId => $qty) {
            if ($qty > 0) {
                $eq = CourtEquipment::find($eqId);
                if ($eq) {
                    $total += ((float) $eq->rental_price * $qty);
                }
            }
        }

        return (float) $total;
    }

    public function getGrandTotalProperty(): float
    {
        return $this->courtTotal + $this->equipmentTotal;
    }

    public function submitWalkInBooking(PadelBookingService $service): void
    {
        // 0. Guard Permission
        abort_unless(auth()->user() && auth()->user()->can('process_walkin_booking'), 403, 'Akses ditolak: Anda tidak memiliki izin untuk memproses transaksi walk-in.');

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
                autoCheckIn: $this->isAutoCheckIn
            );

            // Siapkan data struk POS thermal
            $this->completedOrderData = [
                'order_number' => $result['order']->order_number,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'cashier_name' => $cashier->name,
                'booking_date' => Carbon::parse($this->bookingDate)->translatedFormat('d F Y'),
                'payment_method' => $service->formatPaymentMethodLabel($this->paymentMethod),
                'grand_total' => $result['grand_total'],
                'auto_checked_in' => $result['auto_checked_in'],
                'created_at' => now()->format('d/m/Y H:i:s'),
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
                    return $eq ? [
                        'name' => $eq->name,
                        'quantity' => $item['quantity'],
                        'price' => (float) $eq->rental_price * $item['quantity'],
                    ] : null;
                }, $equipmentsPayload))),
            ];

            $this->showSuccessModal = true;

            Notification::make()
                ->title('Pemesanan Walk-In Berhasil!')
                ->body("Order #{$result['order']->order_number} berhasil dibayar lunas dan e-tiket telah aktif.")
                ->success()
                ->send();

            // Reset seleksi keranjang
            $this->selectedSlots = [];
            $this->initializeEquipmentQuantities();
            $this->walkInName = '';
            $this->walkInPhone = '';
            $this->walkInEmail = '';
            $this->selectedCustomerId = null;
            $this->selectedCustomerName = null;
            $this->selectedCustomerPhone = null;

        } catch (SlotConflictException $e) {
            // Skenario Tabrakan Slot (PRD Section 6): Catch SlotConflictException dan tampilkan notifikasi ramah
            Notification::make()
                ->title('Slot Tidak Tersedia')
                ->body($e->getMessage() ?: 'Slot jam tersebut baru saja diambil customer online atau sedang di-hold. Silakan pilih slot lain.')
                ->warning()
                ->send();
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
    }

    protected function getViewData(): array
    {
        // Fail-safe sinkronisasi kedaluwarsa
        app(PadelBookingService::class)->syncExpiredAndCompletedBookings();

        $courts = PadelCourt::where('is_active', true)->orderBy('name')->get();
        $isWeekend = Carbon::parse($this->bookingDate)->isWeekend();
        $isToday = $this->bookingDate === now()->format('Y-m-d');
        $currentHour = (int) now()->format('H');

        // Tarik seluruh booking pada tanggal aktif untuk evaluasi cepat
        $existingBookings = PadelBooking::with(['court', 'user'])
            ->whereDate('booking_date', $this->bookingDate)
            ->whereIn('status', ['LOCKED', 'PENDING_PAYMENT', 'PENDING', 'PAID', 'CHECKED_IN', 'COMPLETED'])
            ->get();

        // Susun grid jam operasional: 06:00 - 23:00 (17 slot per lapangan)
        $operationalHours = [];
        for ($h = 6; $h <= 22; $h++) {
            $operationalHours[] = [
                'hour' => $h,
                'start_time' => sprintf('%02d:00:00', $h),
                'end_time' => sprintf('%02d:00:00', $h + 1),
                'label' => sprintf('%02d:00', $h),
                'full_label' => sprintf('%02d:00 - %02d:00', $h, $h + 1),
            ];
        }

        $gridData = [];
        foreach ($courts as $court) {
            $courtRow = [
                'court' => $court,
                'slots' => [],
            ];

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

                if (isset($this->selectedSlots[$slotKey])) {
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
                        // Cek Cache Lock (Hold transaksi online)
                        $lockKey = "padel_lock:{$court->id}:{$this->bookingDate}:" . sprintf('%02d00', $h);
                        if (Cache::has($lockKey)) {
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

        $equipments = CourtEquipment::orderBy('type')->orderBy('name')->get();

        $searchResults = [];
        if (strlen(trim($this->customerSearch)) >= 2) {
            $term = trim($this->customerSearch);
            $searchResults = User::where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->limit(5)
            ->get();
        }

        return [
            'gridData' => $gridData,
            'operationalHours' => $operationalHours,
            'equipments' => $equipments,
            'searchResults' => $searchResults,
            'isToday' => $isToday,
        ];
    }
}
