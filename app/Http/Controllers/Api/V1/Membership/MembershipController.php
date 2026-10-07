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
use App\Services\Membership\MembershipOnlinePaymentService;
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
        protected PaymentOrchestratorService $orchestrator,
        protected MembershipOnlinePaymentService $onlinePayments,
    ) {}

    /**
     * Daftar paket membership aktif beserta matrix benefit per fasilitas.
     */
    public function plans(): JsonResponse
    {
        $facilities = app(\App\Services\Membership\MembershipFacilityService::class);
        $plans = MembershipPlan::with('benefits')
            ->where('is_active', true)
            ->get()
            // Kartu benefit siap tampil (nama & deskripsi dari Master Fasilitas) — aplikasi mobile tidak perlu
            // menulis teks benefit sendiri.
            ->each(fn (MembershipPlan $p) => $p->setAttribute('benefit_cards', $facilities->presentPlan($p)));

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
            // Dulu teks apa saja diterima & tersimpan apa adanya. Kode wajib dari katalog resmi; aktif/nonaktif &
            // batas nominal dicek setelah total (termasuk pajak) diketahui.
            'payment_method' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(\App\Services\Payment\OnlinePaymentCatalog::all()))],
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
                // Anti-race: kunci row user ini DULU sebelum cek pesanan pending / existing membership. Ini krusial
                // khusus untuk pelanggan yang BELUM punya UserMembership sama sekali — lockForUpdate() di query
                // existingActive di bawah tidak mengunci apa pun kalau belum ada row yang match, jadi tanpa baris
                // ini 2 request checkout bersamaan (double-klik / 2 tab) bisa sama-sama lolos "belum ada" dan
                // menghasilkan 2 order / 2 kartu. Locking row user memaksa request kedua menunggu commit pertama.
                User::where('id', $user->id)->lockForUpdate()->first();

                // Masih ada pesanan online yang belum dibayar → jangan buat order baru (dulu setiap klik "Bayar"
                // menambah order + kartu PENDING). Dilanjutkan / ditolak di luar transaksi ini.
                if ($pending = $this->onlinePayments->pendingPurchase($user)) {
                    return ['pending' => $pending];
                }

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

                // Sebelum order dibuat: metode harus aktif & sesuai batas nominal (mis. QRIS maks Rp10 juta —
                // paket Corporate Rp25 juta dulu tetap menawarkan QRIS lalu gagal di Midtrans).
                $paymentMethod = app(\App\Services\Payment\OnlinePaymentMethodService::class)->assertSelectable($validated['payment_method'], $grandTotal);

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

                // Item Midtrans WAJIB berjumlah sama dengan gross_amount. Dulu hanya harga paket yang dikirim padahal
                // total sudah termasuk pajak + biaya layanan → begitu pajak membership diaktifkan, MidtransService
                // menolak (jumlah item ≠ total) dan customer mendapat error 500.
                $itemDetails = MembershipOnlinePaymentService::itemDetails($order, $plan->name, $calc['tax_name'] ?? null, $calc['admin_fee_name'] ?? null);

                // Panggil Payment Gateway Manager
                $paymentResult = $this->paymentManager->createPayment([
                    'order_id' => $orderNumber,
                    'gross_amount' => (int) $grandTotal,
                    'item_details' => $itemDetails,
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

            if (isset($result['pending'])) {
                return $this->continuePendingPurchase($result['pending'], $plan, $validated['payment_method'], $user);
            }

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
        } catch (\InvalidArgumentException $e) {
            // Data transaksi ke Midtrans tidak konsisten (bug sisi kita) — jangan bocorkan detail teknis ke customer.
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Pembayaran belum bisa diproses. Silakan coba lagi beberapa saat atau hubungi frontdesk.',
            ], 503);
        }
    }

    /**
     * Checkout saat masih ada pesanan online yang belum dibayar: paket SAMA → lanjutkan bayar pesanan itu
     * (sesi Midtrans baru, order yang sama); paket BEDA → tolak, customer memilih lanjut bayar atau batalkan dulu.
     */
    private function continuePendingPurchase(UserMembership $pending, MembershipPlan $plan, string $paymentMethod, User $user): JsonResponse
    {
        if ($pending->plan_id !== $plan->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda masih punya pesanan paket '.($pending->plan?->name ?? 'membership').' yang belum dibayar. Lanjutkan pembayarannya atau batalkan dulu sebelum memilih paket lain.',
                'data' => ['pending_purchase' => $this->pendingSummary($pending)],
            ], 409);
        }

        $result = $this->onlinePayments->resume($pending, $paymentMethod, $user);

        return response()->json([
            'success' => true,
            'message' => $result['already_paid']
                ? 'Pembayaran pesanan ini sudah kami terima. Membership Anda aktif.'
                : 'Melanjutkan pembayaran pesanan membership Anda yang sudah ada.',
            'data' => $result + ['resumed' => true],
        ]);
    }

    /** Lanjutkan bayar pesanan membership online yang belum dibayar (halaman Invoice / aplikasi). */
    public function payPurchase(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(\App\Services\Payment\OnlinePaymentCatalog::all()))],
        ]);

        $membership = UserMembership::with(['plan', 'order'])->where('user_id', $request->user()->id)->findOrFail($id);
        $result = $this->onlinePayments->resume($membership, $validated['payment_method'], $request->user());

        return response()->json([
            'success' => true,
            'message' => $result['already_paid']
                ? 'Pembayaran pesanan ini sudah kami terima. Membership Anda aktif.'
                : 'Sesi pembayaran baru berhasil dibuat.',
            'data' => $result,
        ]);
    }

    /** Batalkan pesanan membership online yang belum dibayar (mis. ingin ganti paket). */
    public function cancelPurchase(string $id, Request $request): JsonResponse
    {
        $membership = UserMembership::with(['plan', 'order'])->where('user_id', $request->user()->id)->findOrFail($id);
        $this->onlinePayments->cancel($membership, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Pesanan membership dibatalkan. Anda bisa memilih paket lain.',
            'data' => ['id' => $membership->id, 'status' => 'CANCELLED'],
        ]);
    }

    private function pendingSummary(UserMembership $m): array
    {
        return [
            'id' => $m->id,
            'plan_id' => $m->plan_id,
            'plan_name' => $m->plan?->name,
            'order_number' => $m->order?->order_number,
            'grand_total' => (float) ($m->order?->grand_total ?? 0),
            'created_at' => $m->created_at,
        ];
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
     * Riwayat SEMUA pembelian paket membership user (bukan cuma yang aktif) beserta detail
     * pembayarannya — dipakai halaman "Invoice & Digital E-Ticket" supaya pembelian membership
     * ikut muncul di sana, tidak cuma booking lapangan padel.
     */
    public function myPurchases(Request $request): JsonResponse
    {
        $user = $request->user();

        // Polling halaman invoice setelah bayar: tanya Midtrans juga, supaya kartu tetap aktif walau webhook tidak sampai.
        if ($request->boolean('verify_payment') && ($pending = $this->onlinePayments->pendingPurchase($user)) && $pending->order) {
            app(\App\Services\Payment\MidtransReconciliationService::class)->reconcileOrder($pending->order, cacheSeconds: 10);
        }

        $purchases = UserMembership::with(['plan', 'order.payments' => fn ($q) => $q->latest()])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (UserMembership $m) {
                $order = $m->order;
                $latestPayment = $order?->payments->first();

                return [
                    'id' => $m->id,
                    'type' => 'MEMBERSHIP',
                    'membership_code' => $m->membership_code,
                    'plan_name' => $m->plan->name ?? '-',
                    'owner_type' => $m->owner_type,
                    'status' => $m->status,
                    'start_date' => $m->start_date,
                    'end_date' => $m->end_date,
                    'qr_pass_hash' => $m->qr_pass_hash,
                    'order_id' => $order?->id,
                    'order_number' => $order?->order_number,
                    'payment_status' => $order?->payment_status,
                    // Pesanan online yang belum dibayar → tombol "Lanjutkan Pembayaran" (penjualan kasir dibayar di kasir).
                    'can_pay_online' => $m->status === 'PENDING_PAYMENT' && ! $m->sold_by_admin_id
                        && $order?->order_type === 'MEMBERSHIP' && $order?->payment_status === 'UNPAID',
                    'payment_method' => $latestPayment?->payment_method,
                    'subtotal' => (float) ($order?->subtotal ?? $m->purchase_price_snapshot ?? 0),
                    'discount_amount' => (float) ($order?->discount_amount ?? 0),
                    'tax_amount' => (float) ($order?->tax_amount ?? 0),
                    'service_charge' => (float) ($order?->service_charge ?? 0),
                    'grand_total' => (float) ($order?->grand_total ?? $m->purchase_price_snapshot ?? 0),
                    'created_at' => $m->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pembelian membership saya berhasil diambil.',
            'data' => $purchases,
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
     * Check-in fasilitas Gym (endpoint lama, dipertahankan untuk aplikasi yang sudah ada).
     */
    public function checkinGym(Request $request): JsonResponse
    {
        return $this->checkinFacility($request, 'GYM');
    }

    /**
     * Check-in fasilitas bermode CHECK_IN (Gym atau fasilitas baru dari Master Fasilitas).
     */
    public function checkin(Request $request): JsonResponse
    {
        $validated = $request->validate(['facility' => 'required|string|max:10']);

        return $this->checkinFacility($request, strtoupper($validated['facility']));
    }

    private function checkinFacility(Request $request, string $facility): JsonResponse
    {
        $validated = $request->validate([
            'balance_id' => 'nullable|string|exists:user_membership_balances,id',
        ]);

        $user = $request->user();
        $balanceId = $validated['balance_id'] ?? null;
        $facilityName = app(\App\Services\Membership\MembershipFacilityService::class)->name($facility);

        if ($balanceId && \App\Models\Membership\UserMembershipBalance::whereKey($balanceId)->value('facility') !== $facility) {
            return response()->json(['success' => false, 'message' => "Kuota yang dipilih bukan untuk {$facilityName}."], 422);
        }

        if (! $balanceId) {
            // Member bisa punya beberapa kartu: utamakan kuota yang BISA dipakai (unlimited / masih ada sisa),
            // yang paling cepat berakhir. Dulu kartu pertama yang ditemukan — bisa kartu diskon saja → ditolak.
            $memberships = UserMembership::with('balances')
                ->where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
                })
                ->whereHas('balances', function ($q) use ($facility) {
                    $q->where('facility', $facility);
                })
                ->orderByRaw('end_date IS NULL')
                ->orderBy('end_date')
                ->get();
            $membership = $memberships->first(function (UserMembership $m) use ($facility) {
                $b = $m->balanceFor($facility);

                return $b && $b->quota_type === 'VISITS' && ($b->initial_quota === null || (float) $b->remaining_quota >= 1);
            }) ?? $memberships->first();

            if (! $membership) {
                return response()->json([
                    'success' => false,
                    'message' => "Tidak ditemukan keanggotaan aktif untuk akses {$facilityName}.",
                ], 422);
            }

            $balanceId = $membership->balanceFor($facility)?->id;
        }

        try {
            $checkin = $this->balanceService->recordCheckin(
                balanceId: $balanceId,
                userId: $user->id,
                staffId: null
            );

            return response()->json([
                'success' => true,
                'message' => "Check-in {$facilityName} berhasil. Selamat menikmati fasilitas Club 61.",
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
