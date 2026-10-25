<?php

namespace App\Support;

use Filament\Forms\Components\BaseFileUpload;

/**
 * URL file di disk "public" berdasarkan host yang sedang dibuka browser (url('/storage/…')), BUKAN
 * Storage::disk('public')->url() — yang itu memakai APP_URL (mis. "http://localhost" tanpa port) sehingga gambar gagal
 * dimuat kalau admin dibuka lewat host / port lain (alasan yang sama dengan CompanyProfileFacility::photoUrl()).
 */
class PublicStorageUrl
{
    public static function for(?string $path): ?string
    {
        return filled($path) ? url('/storage/'.ltrim((string) $path, '/')) : null;
    }

    /** Pratinjau file yang sudah tersimpan di FileUpload Filament (dipakai lewat ->getUploadedFileUsing()). */
    public static function uploadedFile(BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array
    {
        $data = $component->getUploadedFile($file, $storedFileNames);

        return $data ? ['url' => self::for($file)] + $data : null;
    }
}
