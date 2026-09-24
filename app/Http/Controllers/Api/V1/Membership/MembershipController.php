<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Http\Controllers\Controller;
use App\Models\Membership\FacilityCheckin;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipUsageLog;
use App\Models\Membership\UserMembership;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\User;
use App\Services\Finance\TaxAndFeeService;
use App\Services\Membership\MembershipBalanceService;
use App\Services\Payment\PaymentManager;
use App\Services\Payment\PaymentOrchestratorService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembershipController extends Controller
{
    public function __construct(
        protected MembershipBalanceService $balanceService,
        protected TaxAndFeeService $taxAndFeeService,
        protected PaymentManager $paymentManager,
        protected PaymentOrchestratorService $orchestrator
    ) {}

    /**
     * Daftar paket membership aktif beserta matrix benefit per fasilitas.
     */
    public function plans(): JsonResponse
    {
        $plans = MembershipPlan::with('benefits')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar paket membership aktif.',
            'data' => $plans,
        ]);
    }

    /**
     * Checkout pembelian paket membership oleh customer secara online.
     * Status membership awal adalah PENDING_PAYMENT sampai pelunasan berhasil.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|string|exists:membership_plans,id',
            'payment_method' => 'required|string|max:50',
        ]);

        // 100% Cashless: pembayaran tunai tidak diperbolehkan sama sekali di jalur checkout online.
        if (in_array(strtoupper($validated['payment_method']), ['CASH', 'TUNAI'])) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran tunai (CASH) tidak diperbolehkan. Venue Club 61 beroperasi 100% Cashless.',
            ], 422);
        }

        $user = $request->user();
        $plan = MembershipPlan::with('benefits')->findOrFail($validated['plan_id']);

        if (! $plan->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Paket membership ini sedang tidak aktif.',
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($user, $plan, $validated) {
                $orderNumber = 'ORD-MBR-' . strtoupper(Str::random(10));
                $price = (float) $plan->price;

                // Hitung pajak dan biaya layanan
                $calc = $this->taxAndFeeService->calculate(
                    subtotal: $price,
                    discountAmount: 0,
                    channel: 'ONLINE',
                    module: 'MEMBERSHIP'
                );

                $grandTotal = (float) $calc['grand_total'];

                // Buat Order resmi
                $order = Order::create([
                    'order_number' => $orderNumber,
                    'user_id' => $user->id,
                    'order_type' => 'MEMBERSHIP',
                    'subtotal' => $calc['subtotal'],
                    'discount_amount' => 0.00,
                    'voucher_code' => null,
                    'tax_amount' => $calc['tax_amount'],
                    'service_charge' => $calc['admin_fee_amount'],
                    'grand_total' => $grandTotal,
                    'payment_status' => 'UNPAID',
                ]);

                $order->items()->create([
                    'item_type' => 'MEMBERSHIP',
                    'reference_id' => $plan->id,
                    'item_name' => 'Membership: ' . $plan->name,
                    'quantity' => 1,
                    'unit_price' => $price,
                    'subtotal' => $price,
                ]);

                // Anti-race: kunci row user ini DULU sebelum cek existing membership. Ini krusial khusus
                // untuk pelanggan yang BELUM punya UserMembership sama sekali — lockForUpdate() di query
                // existingActive di bawah tidak mengunci apa pun kalau belum ada row yang match, jadi tanpa
                // baris ini 2 request checkout bersamaan (double-klik / 2 tab) bisa sama-sama lolos "belum
                // ada yang aktif" dan menghasilkan 2 kartu ACTIVE + kuota ke-top-up dua kali padahal uang
                // yang masuk cuma sekali. Locking row user memaksa request kedua menunggu commit pertama.
                User::where('id', $user->id)->lockForUpdate()->first();

                // Cek apakah customer sudah punya membership AKTIF (row-lock: cegah 2 kartu ganda kalau
                // customer klik beli 2x hampir bersamaan, sama seperti guard di POS JualMembership.php).
                $existingActive = UserMembership::where('user_id', $user->id)
                    ->where('status', 'ACTIVE')
                    ->where(function ($q) {
                        $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
                    })
                    ->lockForUpdate()
                    ->first();

                $membershipOptions = ['order_id' => $order->id, 'status' => 'PENDING_PAYMENT'];

                if ($existingActive && $existingActive->plan_id === $plan->id) {
                    // Paket SAMA yang masih aktif -> RENEWAL, kuota digabung ke kartu lama saat lunas
                    // (ditangani MembershipFulfillmentHandler::fulfillRenewal() saat webhook lunas masuk).
                    $membershipOptions['renewal_of_id'] = $existingActive->id;
                }
                // Catatan: kasus "beli paket BEDA sementara kartu lama masih aktif" (upgrade) sengaja belum
                // ditangani di jalur online self-checkout ini — upgrade butuh rollover kuota yang baru aman
                // dieksekusi SETELAH pembayaran benar-benar lunas (async webhook), beda dengan alur kasir POS
                // yang pembayarannya instan tunai. Untuk sekarang tetap jadi pembelian baru independen
                // (perilaku lama, bukan regresi) — upgrade online menyusul sebagai pekerjaan terpisah.

                // Buat kartu membership dengan status PENDING_PAYMENT (kuota remaining 0.00)
                $membership = $this->balanceService->purchasePlan($user, $plan, $membershipOptions);

                // Panggil Payment Gateway Manager
                $paymentMethod = $validated['payment_method'];
                $paymentResult = $this->paymentManager->createPayment([
                    'order_id' => $orderNumber,
                    'gross_amount' => (int) $grandTotal,
                    'item_details' => [
                        [
                            'id' => substr($plan->id, 0, 50),
                            'price' => (int) $price,
                            'quantity' => 1,
                            'name' => substr($plan->name, 0, 50),
                        ],
                    ],
                    'customer_details' => [
                        'first_name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone ?? '081261617233',
                    ],
                    'payment_method' => $paymentMethod,
                ]);

                $isMock = $paymentResult['is_mock'] ?? false;

                if ($isMock) {
                    // Jika simulator aktif, tandai lunas seketika melalui orchestrator
                    $this->orchestrator->markOrderAsPaid($order, [
                        'payment_gateway' => 'MOCK',
                        'counter' => 'ONLINE_PORTAL',
                        'transaction_id' => $orderNumber,
                        'payment_method' => strtoupper($paymentMethod),
                        'amount' => $grandTotal,
                        'user' => $user,
                        'payload_log' => $paymentResult['raw'] ?? null,
                    ]);
                } else {
                    Payment::create([
                        'order_id' => $order->id,
                        'payment_gateway' => 'MIDTRANS',
                        'transaction_id' => $orderNumber,
                        'snap_token' => $paymentResult['snap_token'] ?? null,
                        'payment_url' => $paymentResult['payment_url'] ?? $paymentResult['redirect_url'] ?? null,
                        'amount' => $grandTotal,
                        'payment_method' => strtoupper($paymentMethod),
                        'status' => 'PENDING',
                        'payload_log' => $paymentResult['raw'] ?? null,
                    ]);
                }

                return [
                    'order' => $order->fresh(),
                    'membership' => $membership->fresh(['balances', 'plan']),
                    'payment' => $paymentResult,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Pesanan membership berhasil dibuat.',
                'data' => $result,
            ], 201);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Ambil data membership aktif milik user yang sedang login beserta saldo kuota per fasilitas.
     */
    public function myMembership(Request $request): JsonResponse
    {
        $user = $request->user();

        $membership = UserMembership::with(['plan', 'balances'])
            ->where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
            })
            ->latest('start_date')
            ->first();

        return response()->json([
            'success' => true,
            'message' => $membership ? 'Membership aktif ditemukan.' : 'Tidak ada membership aktif.',
            'data' => $membership,
        ]);
    }

    /**
     * Riwayat mutasi kuota membership user (audit log).
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        $logs = MembershipUsageLog::whereHas('balance.membership', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->with(['balance.membership.plan'])
            ->latest('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat penggunaan membership.',
            'data' => $logs,
        ]);
    }

    /**
     * Check-in fasilitas Gym menggunakan kuota / akses membership aktif.
     */
    public function checkinGym(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'balance_id' => 'nullable|string|exists:user_membership_balances,id',
        ]);

        $user = $request->user();
        $balanceId = $validated['balance_id'] ?? null;

        if (! $balanceId) {
            $membership = UserMembership::where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
                })
                ->whereHas('balances', function ($q) {
                    $q->where('facility', 'GYM');
                })
                ->first();

            if (! $membership) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ditemukan keanggotaan aktif untuk akses Gym.',
                ], 422);
            }

            $gymBalance = $membership->balanceFor('GYM');
            $balanceId = $gymBalance?->id;
        }

        try {
            $checkin = $this->balanceService->recordCheckin(
                balanceId: $balanceId,
                userId: $user->id,
                staffId: null
            );

            return response()->json([
                'success' => true,
                'message' => 'Check-in Gym berhasil. Selamat berlatih di Club 61.',
                'data' => $checkin->load('balance.membership'),
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
