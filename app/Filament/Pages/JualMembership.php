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

    protected static ?int $navigationSort = 7;

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
    public float $manualDiscountPercent = 0.00;
    public string $manualDiscountReason = '';

    // Pembayaran
    public string $paymentMethod = 'CASH'; // CASH | DEBIT_CARD | CREDIT_CARD | QRIS
    public ?float $cashReceived = null;

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

    public function getDiscountAmountProperty(): float
    {
        if (! $this->selectedPlan || $this->manualDiscountPercent <= 0) {
            return 0.00;
        }

        return round($this->subtotal * ($this->manualDiscountPercent / 100), 2);
    }

    public function getFinanceCalculationProperty(): array
    {
        return app(TaxAndFeeService::class)->calculate(
            subtotal: $this->subtotal,
            discountAmount: $this->discountAmount,
            channel: 'POS_WALKIN',
            module: 'MEMBERSHIP'
        );
    }

    public function getGrandTotalProperty(): float
    {
        return (float) $this->financeCalculation['grand_total'];
    }

    public function getCashChangeProperty(): float
    {
        if ($this->paymentMethod !== 'CASH' || ! $this->cashReceived) {
            return 0.00;
        }

        return max(0, $this->cashReceived - $this->grandTotal);
    }

    public function submitSale(): void
    {
        if (! $this->selectedPlan) {
            Notification::make()->title('Silakan pilih paket membership terlebih dahulu.')->danger()->send();
            return;
        }

        if ($this->manualDiscountPercent > 0 && empty(trim($this->manualDiscountReason))) {
            Notification::make()->title('Alasan diskon manual wajib diisi.')->danger()->send();
            return;
        }

        if ($this->paymentMethod === 'CASH' && ($this->cashReceived ?? 0) < $this->grandTotal) {
            Notification::make()->title('Uang tunai yang diterima kurang dari total tagihan.')->danger()->send();
            return;
        }

        $balanceService = app(MembershipBalanceService::class);
        $orchestrator = app(PaymentOrchestratorService::class);
        $cashier = auth()->user() ?? User::role(['cashier', 'admin', 'super_admin'])->first();

        try {
            DB::transaction(function () use ($balanceService, $orchestrator, $cashier) {
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

                // 2. Buat Order POS
                $order = Order::create([
                    'order_number' => $orderNumber,
                    'user_id' => $customer->id,
                    'cashier_id' => $cashier->id,
                    'order_type' => 'WALK_IN',
                    'subtotal' => $this->financeCalculation['subtotal'],
                    'discount_amount' => $this->discountAmount,
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

                // 3. Buat UserMembership dengan initial quota 0.00
                $membership = $balanceService->purchasePlan($customer, $plan, [
                    'order_id' => $order->id,
                    'sold_by_admin_id' => $cashier->id,
                    'manual_discount_percent' => $this->manualDiscountPercent,
                    'manual_discount_reason' => $this->manualDiscountReason,
                    'status' => 'PENDING_PAYMENT',
                ]);

                // 4. Mark Order As Paid (eksekusi pelunasan kasir)
                $orchestrator->markOrderAsPaid($order, [
                    'payment_gateway' => 'CASHIER_POS',
                    'counter' => 'MEMBERSHIP_DESK',
                    'transaction_id' => $orderNumber,
                    'payment_method' => strtoupper($this->paymentMethod),
                    'amount' => $this->grandTotal,
                    'cashier_id' => $cashier->id,
                    'payload_log' => [
                        'cashier_name' => $cashier->name,
                        'payment_method' => $this->paymentMethod,
                        'cash_received' => $this->cashReceived,
                        'cash_change' => $this->cashChange,
                        'manual_discount_percent' => $this->manualDiscountPercent,
                        'manual_discount_reason' => $this->manualDiscountReason,
                    ],
                ]);

                // 5. Data Struk Sukses
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
                    'cash_received' => $this->cashReceived,
                    'cash_change' => $this->cashChange,
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
        $this->manualDiscountPercent = 0.00;
        $this->manualDiscountReason = '';
        $this->cashReceived = null;
    }
}
