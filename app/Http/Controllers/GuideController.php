<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Staff\GuideResource;
use App\Models\Staff\Guide;
use App\Services\Staff\GuideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Pembaca panduan. Publik: /panduan/{token} tanpa login (seperti layar Digital Signage) — hanya panduan AKTIF, dicek
 * ulang di setiap gambar halaman, jadi menonaktifkan / membuat ulang link langsung memutus pembaca yang sedang membuka.
 * Pratinjau admin: /admin/panduan/{guide}/pratinjau — izin manage_guides, berlaku walau panduan masih nonaktif.
 */
class GuideController extends Controller
{
    /** Header untuk semua jawaban: tidak di-cache browser / proxy, tidak diindeks mesin pencari, tidak boleh di-frame. */
    private const PRIVATE_HEADERS = [
        'Cache-Control' => 'no-store, private, max-age=0',
        'Pragma' => 'no-cache',
        'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        'Referrer-Policy' => 'no-referrer',
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
    ];

    public function __construct(private GuideService $guides) {}

    public function show(Request $request, string $token): Response
    {
        $guide = $this->activeGuide($token);
        if (! $guide) {
            return response()->view('guides.unavailable', [], 404)->withHeaders(self::PRIVATE_HEADERS);
        }

        $this->guides->recordView($guide, $request);

        return response()->view('guides.viewer', [
            'guide' => $guide,
            'pageUrl' => fn (int $page) => route('guide.page', ['token' => $token, 'page' => $page]),
            'statusUrl' => route('guide.status', $token),
            'isPreview' => false,
        ])->withHeaders(self::PRIVATE_HEADERS);
    }

    public function page(Request $request, string $token, int $page): Response
    {
        $guide = $this->activeGuide($token);
        abort_unless($guide !== null, 404);

        return $this->image($guide, $page, 'CLUB 61 · INTERNAL · '.now()->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB · '.$request->ip());
    }

    /** Dicek berkala oleh halaman baca: 404 = panduan dinonaktifkan / link dibuat ulang → isi halaman disembunyikan. */
    public function status(string $token): JsonResponse
    {
        abort_unless($this->activeGuide($token) !== null, 404);

        return response()->json(['active' => true])->withHeaders(self::PRIVATE_HEADERS);
    }

    public function preview(Guide $guide): Response
    {
        $this->authorizeAdmin();

        return response()->view('guides.viewer', [
            'guide' => $guide,
            'pageUrl' => fn (int $page) => route('guide.preview.page', ['guide' => $guide, 'page' => $page]),
            'statusUrl' => null,
            'isPreview' => true,
        ])->withHeaders(self::PRIVATE_HEADERS);
    }

    public function previewPage(Guide $guide, int $page): Response
    {
        $this->authorizeAdmin();

        return $this->image($guide, $page, 'PRATINJAU ADMIN · '.auth()->user()->name.' · '.now()->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB');
    }

    private function image(Guide $guide, int $page, string $mark): Response
    {
        $jpeg = $this->guides->watermarkedPage($guide, $page, $mark);
        abort_unless($jpeg !== null, 404);

        return response($jpeg, 200, self::PRIVATE_HEADERS + [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'inline',
        ]);
    }

    private function activeGuide(string $token): ?Guide
    {
        if (preg_match('/^[A-Za-z0-9]{'.Guide::TOKEN_LENGTH.'}$/', $token) !== 1) {
            return null;
        }

        return Guide::where('token', $token)->where('is_active', true)->where('pages_count', '>', 0)->first();
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->can(GuideResource::MANAGE), 403);
    }
}
