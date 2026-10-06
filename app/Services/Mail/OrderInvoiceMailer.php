<?php

namespace App\Services\Mail;

use App\Mail\OrderInvoiceMail;
use App\Models\Membership\UserMembership;
use App\Models\Padel\PadelBooking;
use App\Models\Pos\ClubFinanceSetting;
use App\Models\Pos\Order;
use App\Models\Setting\CompanyProfileSetting;
use App\Services\Finance\LedgerReport;
use App\Services\Finance\LedgerTransactionPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Kirim invoice lunas (PDF terlampir) ke email akun customer lewat akun billing@. Dipanggil setelah transaksi
 * pelunasan selesai (PaymentOrchestratorService::markOrderAsPaid) — webhook Midtrans, cek status berkala, maupun kasir.
 */
class OrderInvoiceMailer
{
    /** Booking lapangan online & membership (online maupun dijual di kasir). Walk-in & F&B cukup struk kasir. */
    public const ORDER_TYPES = ['ONLINE_BOOKING', 'MEMBERSHIP'];

    /** Pesan error SMTP terakhir dari send() (ditampilkan perintah invoice:send). */
    public ?string $lastError = null;

    /** Logo di email & PDF invoice. Ganti file ini untuk ganti logo; kalau tidak ada, header tampil teks "CLUB 61". */
    public const LOGO_FILE = 'images/brand/invoice-logo.png';

    public static function logoPath(): ?string
    {
        $path = public_path(self::LOGO_FILE);

        return is_file($path) ? $path : null;
    }

    public function shouldSend(Order $order): bool
    {
        return in_array($order->order_type, self::ORDER_TYPES, true) && $order->user_id !== null;
    }

    /**
     * Sekali per order: notifikasi Midtrans bisa datang berulang, jadi order "diklaim" dulu lewat invoice_emailed_at.
     * Gagal kirim tidak pernah menggagalkan pelunasan — klaimnya dilepas & dicatat di log supaya bisa dikirim ulang.
     */
    public function send(string $orderId): bool
    {
        $order = Order::with(['user', 'items', 'payments'])->find($orderId);
        $email = $order?->user?->email;

        if (! $order || $order->payment_status !== 'PAID' || ! $this->shouldSend($order) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $claimed = Order::whereKey($order->id)->whereNull('invoice_emailed_at')->update(['invoice_emailed_at' => now()]);
        if ($claimed === 0) {
            return false;
        }

        try {
            Mail::mailer('billing')->to($email, $order->user->name)->send(new OrderInvoiceMail($this->invoiceData($order)));

            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Order::whereKey($order->id)->update(['invoice_emailed_at' => null]);
            Log::error('Invoice email gagal dikirim', ['order_number' => $order->order_number, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** @return array<string, mixed> */
    public function invoiceData(Order $order): array
    {
        $tz = LedgerReport::TIMEZONE;
        $payment = $order->payments->where('status', 'SUCCESS')->sortByDesc('created_at')->first();
        $log = is_array($payment?->payload_log) ? $payment->payload_log : [];
        $finance = ClubFinanceSetting::getSettings();

        $bookings = PadelBooking::with('court')
            ->where('order_id', $order->id)
            ->orderBy('booking_date')->orderBy('start_time')
            ->get()
            ->map(fn (PadelBooking $b) => [
                'court' => $b->court?->name ?? 'Lapangan',
                'date' => Carbon::parse($b->booking_date)->locale('id')->translatedFormat('l, d F Y'),
                'time' => Carbon::parse($b->start_time)->timezone($tz)->format('H:i').' – '.Carbon::parse($b->end_time)->timezone($tz)->format('H:i').' WIB',
                'code' => $b->booking_code,
            ])->all();

        $membership = UserMembership::with('plan')->where('order_id', $order->id)->first();

        return [
            'number' => $order->order_number,
            'type' => $order->order_type === 'MEMBERSHIP' ? 'Membership' : 'Booking Lapangan Padel',
            'paid_at' => ($payment?->updated_at ?? now())->timezone($tz)->locale('id')->translatedFormat('d F Y, H:i').' WIB',
            'customer' => [
                'name' => $order->user?->name ?? $order->customer_name ?? '-',
                'email' => $order->user?->email,
                'phone' => $order->user?->phone,
            ],
            'lines' => $order->items->map(fn ($item) => [
                'name' => $item->item_name,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
            ])->values()->all(),
            'bookings' => $bookings,
            'membership' => $membership ? [
                'plan' => $membership->plan?->name,
                'code' => $membership->membership_code,
                'period' => $membership->start_date && $membership->end_date
                    ? Carbon::parse($membership->start_date)->locale('id')->translatedFormat('d F Y').' – '.Carbon::parse($membership->end_date)->locale('id')->translatedFormat('d F Y')
                    : null,
            ] : null,
            'subtotal' => (float) $order->subtotal,
            'discount' => (float) $order->discount_amount,
            'voucher_code' => $order->voucher_code,
            'service_charge' => (float) $order->service_charge,
            'service_charge_name' => $finance->admin_fee_name ?: 'Biaya Layanan',
            'tax' => (float) $order->tax_amount,
            'tax_name' => $finance->tax_name ?: 'Pajak',
            'grand_total' => (float) $order->grand_total,
            'method' => $payment ? app(LedgerTransactionPresenter::class)->methodLabel($payment) : '-',
            'va_number' => $log['va_numbers'][0]['va_number'] ?? $log['permata_va_number'] ?? null,
            'company_address' => CompanyProfileSetting::receiptAddress(),
            'invoice_url' => rtrim((string) config('app.url'), '/').'/invoice',
        ];
    }
}
