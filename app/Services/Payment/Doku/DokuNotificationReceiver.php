<?php

namespace App\Services\Payment\Doku;

use App\Models\Payment\DokuNotification;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Notifikasi DOKU format non-SNAP (Client-Id, Request-Id, Request-Timestamp, Signature: HMACSHA256=…). Dipakai:
 *   - DokuNotificationController (DOKU Checkout, Modul 27 §7);
 *   - notifikasi QRIS SNAP — DOKU mengirimnya dengan format ini, bukan format SNAP (dokumentasi "HTTP Notification
 *     Sample - Non SNAP" → QRIS, additional_info.origin.apiFormat = SNAP).
 *
 * Urutan:
 *   1. Verifikasi tanda tangan dari body MENTAH + path route yang dipanggil (bukan URL yang dilihat proxy). Gagal → 401.
 *   2. Simpan ke doku_notifications (unik per Request-Id). Sudah pernah selesai diproses → 200 tanpa proses ulang.
 *   3. Balas 200 SEGERA; pelunasan diproses setelah respons terkirim (DOKU hanya mengirim ulang 3×, jeda 30 menit+).
 *      DokuNotificationProcessor mencocokkan nominal & menanyakan ulang status ke DOKU sebelum melunasi. Kalau
 *      pemrosesan gagal, rekonsiliasi berkala memprosesnya ulang — uang customer tidak bergantung pada 1 request.
 */
class DokuNotificationReceiver
{
    public function __construct(protected DokuClient $client) {}

    public function receive(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $requestId = (string) $request->header('Request-Id', '');
        $target = '/'.ltrim($request->route()->uri(), '/');

        $valid = $this->client->verifyNotification(
            (string) $request->header('Signature', ''),
            (string) $request->header('Client-Id', ''),
            $requestId,
            (string) $request->header('Request-Timestamp', ''),
            $target,
            $rawBody,
        );

        if (! $valid) {
            DokuClient::alert('Notifikasi DOKU DITOLAK: tanda tangan tidak valid'.($this->client->isConfigured() ? '' : ' (kunci DOKU kosong di server)'), [
                'ip' => $request->ip(),
                'request_id' => mb_substr($requestId, 0, 128),
                'path' => $target,
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
