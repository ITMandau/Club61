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
}
