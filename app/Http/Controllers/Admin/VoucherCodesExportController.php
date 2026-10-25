<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Pages\LogAktivitas;
use App\Http\Controllers\Controller;
use App\Models\Pos\Order;
use App\Models\Pos\Voucher;
use App\Services\Audit\ActivityLogger;
use App\Services\Finance\LedgerReport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Modul 22 tahap 3 — unduh CSV kode unik kampanye (cara sebar B) beserta statusnya, untuk dibagikan satu per satu.
 * Kode = nilai uang (siapa pun yang memegangnya bisa memakai), jadi hanya pengelola promo & tercatat di Log Aktivitas.
 */
class VoucherCodesExportController extends Controller
{
    public function __invoke(Request $request, Voucher $voucher): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && $user->can('View:DaftarVoucher') && $user->can('manage_vouchers'), 403,
            'Akses ditolak: butuh izin [manage_vouchers] untuk mengunduh kode promo.');
        abort_unless($voucher->isCampaign(), 404);

        $codes = Voucher::where('campaign_id', $voucher->id)->orderBy('code')->get(['code', 'used_count', 'is_active']);
        // Order terakhir per kode (lunas / menunggu bayar) untuk kolom status & pemakai.
        $orders = Order::whereIn('voucher_code', $codes->pluck('code'))
            ->whereIn('payment_status', ['PAID', 'PARTIALLY_PAID', 'UNPAID', 'PENDING_PAYMENT'])
            ->whereNull('voucher_restored_at')
            ->with('user:id,name')->latest()->get()->unique('voucher_code')->keyBy('voucher_code');

        ActivityLogger::record(
            module: 'MARKETING',
            event: 'promo.codes_exported',
            description: "Mengunduh {$codes->count()} kode unik promo {$voucher->name}",
            subject: $voucher,
            meta: ['jumlah_kode' => $codes->count()],
            severity: ActivityLogger::WARNING,
        );

        $filename = 'kode-promo-'.Str::slug($voucher->name ?: 'kampanye').'-'.now(LedgerReport::TIMEZONE)->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($codes, $orders) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8
            fputcsv($out, ['Kode', 'Status', 'Dipakai oleh', 'No. order', 'Waktu']);
            foreach ($codes as $code) {
                $order = $orders[$code->code] ?? null;
                $status = match (true) {
                    $code->used_count > 0 => 'Sudah dipakai',
                    $order !== null => 'Sedang dipesan (belum lunas)',
                    ! $code->is_active => 'Nonaktif',
                    default => 'Belum dipakai',
                };
                fputcsv($out, array_map([LogAktivitas::class, 'csvSafe'], [
                    $code->code,
                    $status,
                    $order?->user?->name,
                    $order?->order_number,
                    $order?->created_at?->timezone(LedgerReport::TIMEZONE)->format('d M Y H:i'),
                ]));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
