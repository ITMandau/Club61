<?php

namespace App\Services\Staff;

use App\Models\Staff\Guide;
use App\Models\Staff\GuideAccessLog;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Panduan karyawan: unggah PDF → gambar per halaman (GuidePdfRenderer) di disk private; file unggahan langsung dihapus
 * setelah diubah, jadi PDF aslinya tidak tersimpan di server maupun terkirim ke browser. Setiap halaman yang dikirim
 * ke pembaca diberi watermark (tanggal-jam + IP) yang menempel di gambarnya — bukan lapisan HTML yang bisa dihapus.
 */
class GuideService
{
    private const FONT = 'vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf';

    public function __construct(private GuidePdfRenderer $renderer) {}

    public static function newToken(): string
    {
        return Str::random(Guide::TOKEN_LENGTH);
    }

    /** Panduan baru selalu NONAKTIF dulu: admin cek lewat Pratinjau, baru diaktifkan. */
    public function create(array $data, string $uploadPath, ?string $sourceName, User $actor): Guide
    {
        [$dir, $pages] = $this->renderUpload($uploadPath);

        try {
            return Guide::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'version' => $data['version'] ?? null,
                'token' => self::newToken(),
                'is_active' => false,
                'pages_path' => $dir,
                'pages_count' => $pages,
                'source_name' => $sourceName ? mb_substr($sourceName, 0, 255) : null,
                'created_by_id' => $actor->id,
            ]);
        } catch (\Throwable $e) {
            Storage::disk(Guide::DISK)->deleteDirectory($dir);
            throw $e;
        }
    }

    /** Ubah detail; kalau ada PDF baru, halamannya diganti (link & status aktif tetap). */
    public function update(Guide $guide, array $data, ?string $uploadPath, ?string $sourceName): Guide
    {
        $changes = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'version' => $data['version'] ?? null,
        ];

        $oldDir = null;
        if ($uploadPath) {
            [$dir, $pages] = $this->renderUpload($uploadPath);
            $oldDir = $guide->pages_path;
            $changes += ['pages_path' => $dir, 'pages_count' => $pages, 'source_name' => $sourceName ? mb_substr($sourceName, 0, 255) : null];
        }

        try {
            $guide->update($changes);
        } catch (\Throwable $e) {
            if (isset($dir)) {
                Storage::disk(Guide::DISK)->deleteDirectory($dir);
            }
            throw $e;
        }

        if ($oldDir && $oldDir !== $guide->pages_path) {
            Storage::disk(Guide::DISK)->deleteDirectory($oldDir);
        }

        return $guide;
    }

    public function setActive(Guide $guide, bool $active): void
    {
        $guide->update(['is_active' => $active]);
    }

    /** Link lama langsung mati permanen (token diganti). Token tidak pernah ditulis ke log. */
    public function regenerateToken(Guide $guide, User $actor): void
    {
        $guide->forceFill(['token' => self::newToken()])->save();

        ActivityLogger::record(
            module: 'STAFF',
            event: 'guide.link_regenerated',
            description: "Link panduan \"{$guide->title}\" dibuat ulang — link lama tidak berlaku lagi",
            subject: $guide,
            causer: $actor,
        );
    }

    /** Catat pembukaan link — paling sering sekali per IP per 10 menit (muat ulang halaman tidak membanjiri log). */
    public function recordView(Guide $guide, Request $request): void
    {
        $ip = (string) $request->ip();
        if (! Cache::add('guide-view:'.$guide->id.':'.sha1($ip), 1, 600)) {
            return;
        }

        GuideAccessLog::create([
            'guide_id' => $guide->id,
            'ip' => mb_substr($ip, 0, 45),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
            'viewed_at' => now(),
        ]);
    }

    /**
     * JPEG halaman ke-n dengan watermark diagonal berulang. Null = halaman tidak ada.
     */
    public function watermarkedPage(Guide $guide, int $page, string $mark): ?string
    {
        $path = $guide->pagePath($page);
        $disk = Storage::disk(Guide::DISK);
        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        $image = @imagecreatefromjpeg($disk->path($path));
        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        // Tipis & jarang: abu-abu hangat ±10% terlihat, isi halaman tetap nyaman dibaca, tapi tetap ikut di setiap foto layar.
        $color = imagecolorallocatealpha($image, 110, 96, 90, 114);
        $font = base_path(self::FONT);

        if (function_exists('imagettftext') && is_file($font)) {
            $size = max(11, (int) round($width / 80));
            $box = imagettfbbox($size, 0, $font, $mark);
            $textWidth = abs($box[2] - $box[0]);
            for ($y = 0; $y < $height + $width; $y += $size * 24) {
                for ($x = -$width; $x < $width; $x += $textWidth + $size * 14) {
                    imagettftext($image, $size, 30, $x, $y, $color, $font, $mark);
                }
            }
        } else {
            // Cadangan tanpa FreeType: teks mendatar berulang.
            for ($y = 40; $y < $height; $y += 220) {
                for ($x = ($y / 60 % 2) * 120; $x < $width; $x += strlen($mark) * imagefontwidth(5) + 80) {
                    imagestring($image, 5, (int) $x, $y, $mark, $color);
                }
            }
        }

        ob_start();
        imagejpeg($image, null, 80);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /** @return array{0: string, 1: int} folder halaman & jumlah halaman */
    private function renderUpload(string $uploadPath): array
    {
        $disk = Storage::disk(Guide::DISK);

        try {
            if (! $disk->exists($uploadPath)) {
                throw new HttpException(422, 'File unggahan tidak ditemukan. Unggah ulang PDF-nya.');
            }
            // Dicek dari isi file, bukan nama / ekstensi.
            $handle = fopen($disk->path($uploadPath), 'rb');
            $magic = $handle ? (string) fread($handle, 5) : '';
            if ($handle) {
                fclose($handle);
            }
            if ($magic !== '%PDF-') {
                throw new HttpException(422, 'File bukan PDF. Untuk PowerPoint: File → Save As → PDF, lalu unggah PDF-nya.');
            }

            $dir = 'guides/'.Str::lower((string) Str::ulid());
            $disk->makeDirectory($dir);
            try {
                $pages = $this->renderer->render($disk->path($uploadPath), $disk->path($dir));
            } catch (\Throwable $e) {
                $disk->deleteDirectory($dir);
                throw $e;
            }

            return [$dir, $pages];
        } finally {
            // PDF asli tidak disimpan: yang tersisa hanya gambar halaman.
            $disk->delete($uploadPath);
        }
    }
}
