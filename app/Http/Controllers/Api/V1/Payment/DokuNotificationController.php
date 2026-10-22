<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment\DokuNotification;
use App\Services\Payment\Doku\DokuClient;
use App\Services\Payment\Doku\DokuNotificationProcessor;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Notifikasi DOKU (Modul 27 §7). Urutan:
 *   1. Verifikasi tanda tangan dari body MENTAH + path route kita (bukan URL yang dilihat proxy). Gagal → 401.
 *   2. Simpan ke doku_notifications (unik per Request-Id). Sudah pernah selesai diproses → 200 tanpa proses ulang.
 *   3. Balas 200 SEGERA; pelunasan diproses setelah respons terkirim (DOKU hanya mengirim ulang 3×, jeda 30 menit+).
 *      Kalau pemrosesan gagal, rekonsiliasi berkala memprosesnya ulang — uang customer tidak bergantung pada 1 request.
 */
class DokuNotificationController extends Controller
{
    public function __invoke(Request $request, DokuClient $client): JsonResponse
    {
        $rawBody = $request->getContent();
        $requestId = (string) $request->header('Request-Id', '');
        $target = '/'.ltrim($request->route()->uri(), '/');

        $valid = $client->verifyNotification(
            (string) $request->header('Signature', ''),
            (string) $request->header('Client-Id', ''),
            $requestId,
            (string) $request->header('Request-Timestamp', ''),
            $target,
            $rawBody,
        );

        if (! $valid) {
            DokuClient::alert('Notifikasi DOKU DITOLAK: tanda tangan tidak valid'.($client->isConfigured() ? '' : ' (kunci DOKU kosong di server)'), [
                'ip' => $request->ip(),
                'request_id' => mb_substr($requestId, 0, 128),
            ]);

            return response()->json(['success' => false, 'message' => 'Invalid signature.'], 401);
        }

        $data = json_decode($rawBody, true);
        if (! is_array($data) || $requestId === '' || strlen($requestId) > 128) {
            DokuClient::alert('Notifikasi DOKU bertanda tangan sah tapi isinya tidak bisa dibaca', ['request_id' => mb_substr($requestId, 0, 128)]);

            return response()->json(['success' => false, 'message' => 'Invalid payload.'], 400);
        }

        $notification = $this->store($requestId, $data);

        if (DokuNotification::isFinal($notification->result)) {
            return response()->json(['success' => true, 'message' => 'Already processed.']);
        }

        $id = $notification->id;
        dispatch(fn () => app(DokuNotificationProcessor::class)->process($id))->afterResponse();

        return response()->json(['success' => true, 'message' => 'Received.']);
    }

    /** Insert sekali per Request-Id (notifikasi dobel / dikirim bersamaan → baris yang sama). */
    private function store(string $requestId, array $data): DokuNotification
    {
        $amount = $data['order']['amount'] ?? null;

        try {
            return DokuNotification::firstOrCreate(['request_id' => $requestId], [
                'invoice_number' => is_string($data['order']['invoice_number'] ?? null) ? mb_substr($data['order']['invoice_number'], 0, 64) : null,
                'status' => is_string($data['transaction']['status'] ?? null) ? mb_substr(strtoupper($data['transaction']['status']), 0, 20) : null,
                'amount' => is_numeric($amount) ? (float) $amount : null,
                // Data pribadi customer dari DOKU (nama, email, telepon) tidak disimpan.
                'payload' => Arr::except($data, ['customer']),
                'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Dua notifikasi identik masuk bersamaan: yang kalah balapan membaca baris yang sudah dibuat.
            return DokuNotification::where('request_id', $requestId)->firstOrFail();
        }
    }
}
