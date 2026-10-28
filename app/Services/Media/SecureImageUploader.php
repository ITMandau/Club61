<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Satu-satunya jalur upload gambar staf di seluruh aplikasi ini (dipakai
 * KelolaKontenWebsite untuk foto fasilitas & FnbMenuResource untuk foto menu). Gambar SELALU
 * di-decode ulang lewat GD dan di-render ke file JPEG baru — bukan sekadar dipindah — supaya
 * metadata EXIF (termasuk lokasi GPS) dan payload tersembunyi apa pun yang menumpang di file
 * asli ikut terhapus. Tipe file divalidasi dari ISI file (getimagesize), bukan dari
 * ekstensi/nama file semata, sesuai checklist keamanan proyek ini soal upload gambar.
 */
class SecureImageUploader
{
    public const MAX_WIDTH = 1920;

    public const JPEG_QUALITY = 82;

    public static function store(TemporaryUploadedFile|UploadedFile $upload, string $directory, string $disk = 'public'): string
    {
        $realPath = $upload->getRealPath();
        $info = @getimagesize($realPath);

        abort_unless($info !== false, 422, 'File yang diunggah bukan gambar yang valid.');

        $mime = $info['mime'];
        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($realPath),
            'image/png' => @imagecreatefrompng($realPath),
            'image/webp' => @imagecreatefromwebp($realPath),
            default => null,
        };

        abort_unless($source !== null && $source !== false, 422, 'Format gambar tidak didukung. Gunakan JPG, PNG, atau WEBP.');

        // Batasi lebar maksimum (foto marketing/menu tidak perlu resolusi lebih dari itu),
        // sekalian menekan ukuran file.
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width > self::MAX_WIDTH) {
            $newHeight = (int) round($height * (self::MAX_WIDTH / $width));
            $resized = imagecreatetruecolor(self::MAX_WIDTH, $newHeight);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, self::MAX_WIDTH, $newHeight, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        Storage::disk($disk)->makeDirectory($directory);
        $filename = $directory.'/'.Str::uuid().'.jpg';
        $absolutePath = Storage::disk($disk)->path($filename);

        imagejpeg($source, $absolutePath, self::JPEG_QUALITY);
        imagedestroy($source);

        return $filename;
    }

    /**
     * Sama dengan store() (divalidasi dari isi file, dibuat ulang lewat GD, metadata terbuang), tapi hasilnya PNG
     * dengan transparansi utuh — untuk logo: JPG mengubah latar transparan jadi hitam.
     */
    public static function storePng(TemporaryUploadedFile|UploadedFile $upload, string $directory, int $maxWidth = 1200, string $disk = 'public'): string
    {
        $realPath = $upload->getRealPath();
        $info = @getimagesize($realPath);

        abort_unless($info !== false, 422, 'File yang diunggah bukan gambar yang valid.');

        $source = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($realPath),
            'image/png' => @imagecreatefrompng($realPath),
            'image/webp' => @imagecreatefromwebp($realPath),
            default => null,
        };

        abort_unless($source !== null && $source !== false, 422, 'Format gambar tidak didukung. Gunakan JPG, PNG, atau WEBP.');

        $width = imagesx($source);
        $height = imagesy($source);
        $newWidth = min($width, $maxWidth);
        $newHeight = (int) max(1, round($height * ($newWidth / $width)));

        // Kanvas baru yang transparan, lalu gambar asli disalin — sekaligus membuang apa pun di luar data piksel.
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($source);

        Storage::disk($disk)->makeDirectory($directory);
        $filename = $directory.'/'.Str::uuid().'.png';
        imagepng($canvas, Storage::disk($disk)->path($filename), 6);
        imagedestroy($canvas);

        return $filename;
    }
}
