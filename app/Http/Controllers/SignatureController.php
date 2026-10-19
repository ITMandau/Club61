<?php

namespace App\Http\Controllers;

use App\Models\Signature\SignatureScreen;
use App\Services\Signature\SignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Halaman TV Signature (tanpa login, dibuka lewat link rahasia layar) + Preview admin (wajib login & izin
 * View:Signature). Keduanya memakai pemutar yang sama (resources/views/signature/player.blade.php).
 */
class SignatureController extends Controller
{
    public function __construct(private SignatureService $signature) {}

    public function player(string $token): View
    {
        $screen = $this->screen($token);

        return view('signature.player', [
            'title' => $screen->name,
            'payload' => $screen->publishedVersion?->payload,
            'payloadUrl' => route('signature.payload', $token),
            'liveUrl' => route('signature.live', $token),
            'isPreview' => false,
        ]);
    }

    /** Versi yang sedang ditayangkan (TV mengambil ulang saat nomor versinya berubah). */
    public function payload(string $token): Response
    {
        $screen = $this->screen($token);
        $payload = $screen->publishedVersion?->payload;

        // Belum pernah ditayangkan → null (bukan {}), pemutar menampilkan "Belum ada tampilan".
        return response($payload === null ? 'null' : json_encode($payload), 200, ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']);
    }

    public function live(string $token): JsonResponse
    {
        $screen = $this->screen($token);

        // TV melapor "masih hidup" (status Online / Offline di admin) — paling sering sekali per 50 detik.
        if (! $screen->last_seen_at || $screen->last_seen_at->lt(now()->subSeconds(50))) {
            $screen->forceFill(['last_seen_at' => now()])->saveQuietly();
            app(\App\Services\Realtime\RealtimeSignal::class)->signatureScreenSeen($screen->id, $screen->last_seen_at);
        }

        $payload = $screen->publishedVersion?->payload;

        return response()->json($payload ? $this->signature->liveData($payload, $screen) : ['version' => null])->header('Cache-Control', 'no-store');
    }

    public function preview(string $key): View
    {
        $this->authorizePreview();
        $payload = $this->signature->previewPayload($key);
        abort_unless($payload, 404, 'This preview has expired — press Preview again.');

        return view('signature.player', [
            'title' => 'Preview — '.($payload['screen']['name'] ?? 'Signature'),
            'payload' => $payload,
            'payloadUrl' => null,
            'liveUrl' => route('signature.preview.live', $key),
            'isPreview' => true,
        ]);
    }

    public function previewLive(string $key): JsonResponse
    {
        $this->authorizePreview();
        $payload = $this->signature->previewPayload($key);
        abort_unless($payload, 404);

        return response()->json($this->signature->liveData($payload))->header('Cache-Control', 'no-store');
    }

    private function screen(string $token): SignatureScreen
    {
        // Kode pendek baru (10 karakter, SignatureService::TOKEN_LENGTH) & link lama 48 karakter sama-sama berlaku.
        abort_unless(preg_match('/^[a-z0-9]{'.SignatureService::TOKEN_LENGTH.',64}$/', $token) === 1, 404);

        return SignatureScreen::with('publishedVersion')->where('token', $token)->firstOrFail();
    }

    private function authorizePreview(): void
    {
        abort_unless(auth()->user()?->can('View:Signature'), 403);
    }
}
