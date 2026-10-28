<?php

namespace App\Services\Staff;

use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\ExecutableFinder;

/**
 * PDF → gambar JPEG per halaman ({folder}/1.jpg, 2.jpg, …) memakai pdftoppm (paket poppler-utils di server:
 * `apt install poppler-utils`). Sisi terpanjang 1600px: tajam di layar, tidak cukup besar untuk dicetak ulang.
 */
class GuidePdfRenderer
{
    public const MAX_PAGES = 150;

    /** PHP-FPM kadang berjalan dengan PATH pendek — folder biasa poppler ikut dicari. */
    private const EXTRA_DIRS = ['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin'];

    public function available(): bool
    {
        return $this->binary() !== null;
    }

    /** @return int jumlah halaman */
    public function render(string $pdfPath, string $outputDir): int
    {
        $binary = $this->binary();
        if ($binary === null) {
            throw new HttpException(422, 'Server belum bisa membaca PDF. Pasang dulu poppler-utils di server (apt install poppler-utils), lalu unggah lagi.');
        }

        $result = Process::timeout(180)->run([
            $binary, '-jpeg', '-jpegopt', 'quality=82', '-scale-to', '1600', '-l', (string) self::MAX_PAGES,
            $pdfPath, $outputDir.'/raw',
        ]);
        if (! $result->successful()) {
            throw new HttpException(422, 'PDF tidak bisa dibaca (rusak atau dikunci password). Simpan ulang PDF-nya lalu unggah lagi.');
        }

        // pdftoppm menamai raw-1.jpg / raw-01.jpg / raw-001.jpg tergantung jumlah halaman → urutkan alami lalu beri nomor 1..n.
        $files = glob($outputDir.'/raw-*.jpg') ?: [];
        natsort($files);
        $count = 0;
        foreach ($files as $file) {
            rename($file, $outputDir.'/'.(++$count).'.jpg');
        }
        if ($count === 0) {
            throw new HttpException(422, 'PDF ini tidak punya halaman yang bisa ditampilkan.');
        }

        return $count;
    }

    protected function binary(): ?string
    {
        return (new ExecutableFinder)->find('pdftoppm', null, self::EXTRA_DIRS);
    }
}
