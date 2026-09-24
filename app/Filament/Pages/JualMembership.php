<?php

namespace App\Filament\Pages;

use App\Models\Membership\MembershipPlan;
use App\Models\Membership\UserMembership;
use App\Models\Pos\Order;
use App\Models\User;
use App\Services\Finance\TaxAndFeeService;
use App\Services\Membership\MembershipBalanceService;
use App\Services\Payment\PaymentOrchestratorService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use UnitEnum;

class JualMembership extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'POS Jual Membership';

    protected static string|UnitEnum|null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'POS Penjualan Membership Frontdesk';

    protected static ?int $navigationSort = 5;

    // Disembunyikan dari sidebar — sekarang diakses lewat tab "POS Jual Membership" di halaman
    // Walk-In Booking (lihat resources/views/filament/partials/pos-subnav.blade.php). Halaman &
    // route-nya tetap aktif penuh (HasPageShield/permission tidak berubah), cuma gak dobel muncul
    // di sidebar lagi.
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.jual-membership';

    // Pelanggan
    public string $customerMode = 'quick_create'; // quick_create | search
    public string $customerSearch = '';
    public ?string $selectedCustomerId = null;
    public ?string $selectedCustomerName = null;
    public ?string $selectedCustomerPhone = null;

    // Quick create customer
    public string $walkInName = '';
    public string $walkInPhone = '';
    public string $walkInEmail = '';

    // Paket Membership
    public ?string $selectedPlanId = null;

    // Step Alur Terminal Kasir: 'selection' (Pilih Pelanggan & Paket) atau 'payment' (Layar Bayar) —
    // sama persis dengan alur BookOfflineCourt (selection/payment/receipt), minus langkah struk
    // in-page karena struk membership sudah ditampilkan lewat modal ($showSuccessModal).
    public string $posStep = 'selection';

    // Pembayaran (100% Cashless) — properti & markup form disamakan manual dengan POS Walk-In
    // Booking (BookOfflineCourt.php / book-offline-court.blade.php) supaya kedua loket konsisten.
    public string $paymentMethod = 'QRIS'; // DEBIT_CARD | CREDIT_CARD | QRIS

    public string $edcTerminal = 'EDC_BCA'; // EDC_BCA, EDC_MANDIRI, EDC_LAINNYA
    public string $edcCardType = 'DEBIT'; // DEBIT, CREDIT
    public string $edcCardNetwork = 'GPN'; // GPN, VISA, MASTERCARD, JCB, AMEX, LAINNYA
    public string $edcBank = 'BCA'; // BCA, MANDIRI, BNI, BRI, CIMB, PERMATA, DANAMON, OVERSEAS, LAINNYA
    public string $edcLast4 = '';
    public string $edcApprovalCode = '';
    public string $edcTraceNumber = '';

    public string $qrisProvider = 'BCA_QRIS'; // BCA_QRIS, MANDIRI_QRIS, GOPAY_QRIS, LAINNYA
    public string $qrisRrn = '';
    public string $qrisSenderName = '';

    // Modal Sukses & Struk
    public bool $showSuccessModal = false;
    public ?array $completedMembershipData = null;

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

    public function clearCustomer(): void
    {
        $this->selectedCustomerId = null;
        $this->selectedCustomerName = null;
        $this->selectedCustomerPhone = null;
        $this->customerSearch = '';
    }

    public function selectPlan(string $planId): void
    {
        $this->selectedPlanId = $planId;
    }

    // Sama persis dengan BookOfflineCourt::setPaymentMethod() — markup form pembayaran di
    // jual-membership.blade.php ditulis manual meniru book-offline-court.blade.php.
    public function setPaymentMethod(string $method): void
    {
        $this->paymentMethod = $method;

        if ($method === 'DEBIT_CARD' || $method === 'DEBIT') {
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
    }

    public function getSelectedPlanProperty(): ?MembershipPlan
    {
        return $this->selectedPlanId
            ? MembershipPlan::with('benefits')->find($this->selectedPlanId)
            : null;
    }

    public function getPlansProperty()
    {
        return MembershipPlan::with('benefits')
            ->where('is_active', true)
            ->get();
    }

    public function getSearchResultsProperty(): array
    {
        $term = trim($this->customerSearch);
        if (strlen($term) < 2) {
            return [];
        }

        return User::where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->limit(8)
            ->get(['id', 'name', 'phone', 'email'])
            ->toArray();
    }

    public function getSubtotalProperty(): float
    {
        return $this->selectedPlan ? (float) $this->selectedPlan->price : 0.00;
    }

    public function getFinanceCalculationProperty(): array
    {
        return app(TaxAndFeeService::class)->calculate(
            subtotal: $this->subtotal,
            discountAmount: 0,
            channel: 'POS_WALKIN',
            module: 'MEMBERSHIP'
        );
    }

    public function getGrandTotalProperty(): float
    {
        return (float) $this->financeCalculation['grand_total'];
    }

    // Sama persis dengan BookOfflineCourt::proceedToPayment() — validasi paket & data pelanggan
    // dulu sebelum pindah ke layar pembayaran khusus (posStep 'payment').
    public function proceedToPayment(): void
    {
        if (! $this->selectedPlan) {
            Notification::make()->title('Paket Belum Dipilih')->body('Silakan pilih salah satu paket membership terlebih dahulu.')->warning()->send();
            return;
        }

        if ($this->customerMode === 'search') {
            if (empty($this->selectedCustomerId)) {
                Notification::make()->title('Customer Belum Dipilih')->body('Silakan cari dan pilih pelanggan terdaftar, atau beralih ke form Walk-In Baru.')->warning()->send();
                return;
            }
        } else {
            $name = trim($this->walkInName);
            $phone = trim($this->walkInPhone);

            if (empty($name)) {
                Notification::make()->title('Nama Wajib Diisi')->body('Silakan masukkan nama pelanggan walk-in.')->warning()->send();
                return;
            }

            if (empty($phone) || strlen(preg_replace('/[^0-9]/', '', $phone)) < 8) {
                Notification::make()->title('Nomor Telepon Tidak Valid')->body('Silakan masukkan nomor WhatsApp / telepon aktif minimal 8 digit.')->warning()->send();
                return;
            }
        }

        $this->posStep = 'payment';
    }

    public function backToSelection(): void
    {
        $this->posStep = 'selection';
    }

    public function submitSale(): void
    {
        if (! $this->selectedPlan) {
            Notification::make()->title('Silakan pilih paket membership terlebih dahulu.')->danger()->send();
            return;
        }

        // Validasi metode pembayaran — sama persis dengan BookOfflineCourt::submitWalkInBooking()
        // supaya rincian slip EDC/QRIS yang tercatat konsisten di kedua loket POS.
        $method = strtoupper($this->paymentMethod);
        $paymentMeta = [];

        if (in_array($method, ['CASH', 'TUNAI'])) {
            Notification::make()->title('Pembayaran tunai (CASH) tidak diperbolehkan. Venue Club 61 beroperasi 100% Cashless.')->danger()->send();
            return;
        } elseif (in_array($method, ['DEBIT_CARD', 'CREDIT_CARD', 'EDC_BCA', 'EDC_MANDIRI', 'DEBIT', 'CREDIT'])) {
            $last4 = trim($this->edcLast4);
            $approvalCode = trim($this->edcApprovalCode);
            $traceNumber = trim($this->edcTraceNumber);

            if (! preg_match('/^[0-9]{4}$/', $last4)) {
                Notification::make()->title('4 Digit Kartu Tidak Valid')->body('Silakan masukkan tepat 4 digit angka terakhir dari kartu debit/kredit pelanggan.')->danger()->send();
                return;
            }

            if (empty($approvalCode) || strlen($approvalCode) < 3) {
                Notification::make()->title('Approval Code Wajib Diisi')->body('Silakan masukkan nomor otorisasi/approval code dari slip transaksi mesin EDC.')->danger()->send();
                return;
            }

            if (empty($traceNumber) || strlen($traceNumber) < 3) {
                Notification::make()->title('Trace Number Wajib Diisi')->body('Silakan masukkan nomor trace / audit number dari slip transaksi mesin EDC.')->danger()->send();
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
                'charged_amount' => (float) $this->grandTotal,
            ];
        } elseif (in_array($method, ['QRIS_STATIS', 'QRIS'])) {
            $rrn = trim($this->qrisRrn);

            if (empty($rrn) || strlen($rrn) < 6) {
                Notification::make()->title('Nomor RRN QRIS Wajib Diisi')->body('Silakan masukkan nomor RRN (Retrieval Reference Number) minimal 6 digit dari bukti bayar customer.')->danger()->send();
                return;
            }

            $paymentMeta = [
                'qris_provider' => $this->qrisProvider,
                'qris_rrn' => $rrn,
                'qris_sender_name' => trim($this->qrisSenderName) ?: null,
            ];
        }

        $balanceService = app(MembershipBalanceService::class);
        $orchestrator = app(PaymentOrchestratorService::class);
        $cashier = auth()->user() ?? User::role(['cashier', 'admin', 'super_admin'])->first();

        try {
            DB::transaction(function () use ($balanceService, $orchestrator, $cashier, $paymentMeta) {
                // 1. Resolve customer
                if ($this->selectedCustomerId) {
                    $customer = User::findOrFail($this->selectedCustomerId);
                } else {
                    $phone = trim($this->walkInPhone);
                    if (empty($phone)) {
                        throw new \DomainException('Nomor WhatsApp customer walk-in wajib diisi.');
                    }

                    $customer = User::where('phone', $phone)->first();
                    if (! $customer) {
                        $name = trim($this->walkInName) ?: 'Member Walk-In';
                        $email = trim($this->walkInEmail) ?: ('mbr_' . preg_replace('/\D/', '', $phone) . '@club61.id');

                        $customer = User::create([
                            'name' => $name,
                            'phone' => $phone,
                            'email' => $email,
                            'password' => Hash::make(Str::random(12)),
                            'is_active' => true,
                        ]);

                        $customer->assignRole('customer');
                    }
                }

                $plan = $this->selectedPlan;
                $orderNumber = 'ORD-POS-MBR-' . strtoupper(Str::random(8));

                // 2. Buat Order POS — order_type 'MEMBERSHIP' (BUKAN 'WALK_IN'), sama seperti jalur
                // pembelian membership online di MembershipController.php. Statistik & daftar
                // "Transaksi Walk-In Terakhir" di halaman Walk-In Booking cuma nge-query order_type
                // 'WALK_IN' (khusus booking lapangan) — kalau di sini ikut 'WALK_IN', omzet & histori
                // penjualan membership akan nyasar tercampur ke situ.
                $order = Order::create([
                    'order_number' => $orderNumber,
                    'user_id' => $customer->id,
                    'cashier_id' => $cashier->id,
                    'order_type' => 'MEMBERSHIP',
                    'subtotal' => $this->financeCalculation['subtotal'],
                    'discount_amount' => 0.00,
                    'voucher_code' => null,
                    'tax_amount' => $this->financeCalculation['tax_amount'],
                    'service_charge' => $this->financeCalculation['admin_fee_amount'],
                    'grand_total' => $this->grandTotal,
                    'payment_status' => 'UNPAID',
                ]);

                $order->items()->create([
                    'item_type' => 'MEMBERSHIP',
                    'reference_id' => $plan->id,
                    'item_name' => 'Membership: ' . $plan->name,
                    'quantity' => 1,
                    'unit_price' => $plan->price,
                    'subtotal' => $plan->price,
                ]);

                // Anti-race: kunci row customer ini DULU sebelum cek existing membership. Krusial khusus
                // buat customer yang BELUM punya UserMembership sama sekali — lockForUpdate() di query
                // existingActive di bawah tidak mengunci apa pun kalau belum ada row yang match, jadi tanpa
                // baris ini 2 kasir yang submit hampir bersamaan buat customer yang sama bisa sama-sama
                // lolos "belum ada yang aktif" dan bikin 2 kartu ACTIVE + kuota ke-top-up dua kali padahal
                // uang yang masuk kasir cuma sekali. Locking row user memaksa transaksi kedua menunggu
                // commit yang pertama selesai.
                User::where('id', $customer->id)->lockForUpdate()->first();

                // 2b. Cek apakah customer ini sudah punya membership AKTIF (row-lock: cegah 2 transaksi
                // kasir bersamaan buat customer yang sama menghasilkan 2 kartu ganda -> lihat invarian 5.1/5.2 PRD Modul 05)
                $existingActive = UserMembership::where('user_id', $customer->id)
                    ->where('status', 'ACTIVE')
                    ->where(function ($q) {
                        $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
                    })
                    ->lockForUpdate()
                    ->first();

                $membershipOptions = [
                    'order_id' => $order->id,
                    'sold_by_admin_id' => $cashier->id,
                ];

                if ($existingActive && $existingActive->plan_id === $plan->id) {
                    // 3a. Paket SAMA yang masih aktif -> RENEWAL. Kuota digabung ke kartu LAMA (bukan kartu baru
                    // yang berdiri sendiri), lewat renewal_of_id -> ditangani MembershipFulfillmentHandler saat lunas.
                    $membershipOptions['status'] = 'PENDING_PAYMENT';
                    $membershipOptions['renewal_of_id'] = $existingActive->id;
                    $membership = $balanceService->purchasePlan($customer, $plan, $membershipOptions);
                } elseif ($existingActive) {
                    // 3b. Paket BEDA sementara kartu lama masih aktif -> UPGRADE. Sisa kuota lama di-rollover
                    // (ROLLOVER_OUT/ROLLOVER_IN, bukan hangus diam-diam), kartu lama ditandai UPGRADED.
                    // Aktivasi instan karena kasir sudah terima pembayaran tunai/EDC/QRIS di tempat.
                    $membershipOptions['activate_now'] = true;
                    $membership = $balanceService->upgradeMembership($existingActive, $plan, $membershipOptions);
                } else {
                    // 3c. Belum punya membership aktif sama sekali -> pembelian baru murni.
                    $membershipOptions['status'] = 'PENDING_PAYMENT';
                    $membership = $balanceService->purchasePlan($customer, $plan, $membershipOptions);
                }

                // 4. Susun payload_log audit finansial — rincian slip EDC/QRIS ikut tercatat sama
                // seperti transaksi Walk-In (lihat ManagesCheckoutAndPayments::processWalkInCheckout()).
                $payloadLog = [
                    'cashier_name' => $cashier->name,
                    'payment_method' => $this->paymentMethod,
                ];

                if (in_array(strtoupper($this->paymentMethod), ['DEBIT_CARD', 'CREDIT_CARD', 'EDC_BCA', 'EDC_MANDIRI', 'DEBIT', 'CREDIT'])) {
                    $payloadLog['edc_details'] = $paymentMeta;
                } elseif (in_array(strtoupper($this->paymentMethod), ['QRIS_STATIS', 'QRIS'])) {
                    $payloadLog['qris_details'] = $paymentMeta;
                }

                // 5. Mark Order As Paid (eksekusi pelunasan kasir)
                $orchestrator->markOrderAsPaid($order, [
                    'payment_gateway' => 'CASHIER_POS',
                    'counter' => 'MEMBERSHIP_DESK',
                    'transaction_id' => $orderNumber,
                    'payment_method' => strtoupper($this->paymentMethod),
                    'amount' => $this->grandTotal,
                    'cashier_id' => $cashier->id,
                    'payload_log' => $payloadLog,
                ]);

                // 6. Data Struk Sukses
                $membership->refresh();
                $this->completedMembershipData = [
                    'order_number' => $orderNumber,
                    'membership_code' => $membership->membership_code,
                    'plan_name' => $plan->name,
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                    'cashier_name' => $cashier->name,
                    'start_date' => $membership->start_date,
                    'end_date' => $membership->end_date,
                    'grand_total' => $this->grandTotal,
                    'payment_method' => $this->paymentMethod,
                    'balances' => $membership->balances->map(fn ($b) => [
                        'facility' => $b->facility,
                        'quota_type' => $b->quota_type,
                        'remaining_quota' => $b->remaining_quota,
                        'discount_percent' => $b->discount_percent,
                    ])->toArray(),
                ];

                $this->showSuccessModal = true;
            });

            Notification::make()
                ->title('Membership berhasil diterbitkan dan langsung aktif!')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Transaksi Gagal: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resetSale(): void
    {
        $this->showSuccessModal = false;
        $this->completedMembershipData = null;
        $this->selectedPlanId = null;
        $this->selectedCustomerId = null;
        $this->selectedCustomerName = null;
        $this->selectedCustomerPhone = null;
        $this->walkInName = '';
        $this->walkInPhone = '';
        $this->walkInEmail = '';
        $this->paymentMethod = 'QRIS';
        $this->edcLast4 = '';
        $this->edcApprovalCode = '';
        $this->edcTraceNumber = '';
        $this->qrisRrn = '';
        $this->qrisSenderName = '';
        $this->posStep = 'selection';
    }
}
