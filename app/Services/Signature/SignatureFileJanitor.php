<?php

namespace App\Services\Signature;

use App\Models\Signature\SignatureContent;
use App\Models\Signature\SignatureScreen;
use App\Models\Signature\SignatureSponsor;
use App\Models\Signature\SignatureTemplate;
use App\Models\Signature\SignatureVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Siklus hidup file Digital Signage (video, gambar, logo template & sponsor).
 *
 * TV menayangkan SNAPSHOT (signature_versions) yang menunjuk ke file. Dulu file lama langsung dihapus saat konten
 * diganti / dihapus di library — TV yang masih menayangkan versi itu kehilangan videonya, dan "Kembalikan versi"
 * memunculkan slide kosong. Sekarang:
 *   - file yang masih dipakai library, sedang tayang, atau berhenti tayang < KEEP_AFTER_AIRING_DAYS hari → disimpan;
 *   - selain itu langsung dihapus saat diganti / dihapus, dan disapu tiap malam (`signature:clean-files`).
 * Masa tayang tiap versi dicatat di signature_versions.aired_until (diisi saat tanda Tayang pindah ke versi lain).
 */
class SignatureFileJanitor
{
    public const KEEP_AFTER_AIRING_DAYS = 7;

    /** Folder upload Signature di disk `public` — hanya folder ini yang disapu. */
    public const DIRECTORIES = ['signature/videos', 'signature/images', 'signature/logos', 'signature/sponsors'];

    /** File yang baru di-upload bisa belum tercatat di database (form belum selesai disimpan) — jangan disapu dulu. */
    private const MIN_FILE_AGE_MINUTES = 60;

    /**
     * File lama dari konten / logo yang diganti atau dihapus. Dijalankan setelah transaksi selesai: kalau perubahan
     * database-nya batal, file tetap ada.
     */
    public function release(?string $path): void
    {
        if (! $path || ! $this->isSignatureFile($path)) {
            return;
        }

        DB::afterCommit(function () use ($path) {
            if (! isset($this->libraryPaths()[$path]) && ! isset($this->airedPaths()[$path])) {
                Storage::disk('public')->delete($path);
            }
        });
    }

    /**
     * Sapu harian: hapus file di folder Signature yang tidak dipakai library dan tidak tayang dalam
     * KEEP_AFTER_AIRING_DAYS hari terakhir.
     *
     * @return array{deleted: int, bytes: int}
     */
    public function sweep(): array
    {
        $disk = Storage::disk('public');
        $keep = $this->libraryPaths() + $this->airedPaths();
        $freshAfter = now()->subMinutes(self::MIN_FILE_AGE_MINUTES)->getTimestamp();
        $deleted = 0;
        $bytes = 0;

        foreach (self::DIRECTORIES as $directory) {
            foreach ($disk->files($directory) as $path) {
                if (isset($keep[$path]) || $disk->lastModified($path) > $freshAfter) {
                    continue;
                }
                $size = (int) $disk->size($path);
                if ($disk->delete($path)) {
                    $deleted++;
                    $bytes += $size;
                }
            }
        }

        return ['deleted' => $deleted, 'bytes' => $bytes];
    }

    /**
     * Pemakaian storage folder Signature untuk halaman admin.
     *
     * @return array{total: int, pending: int, files: array<string, int>} pending = file lama yang sudah tidak ada di
     *                                                                         library tapi masih ditahan (tayang / < 7 hari)
     */
    public function usage(): array
    {
        $disk = Storage::disk('public');
        $library = $this->libraryPaths();
        $files = [];
        $pending = 0;

        foreach (self::DIRECTORIES as $directory) {
            foreach ($disk->files($directory) as $path) {
                $files[$path] = (int) $disk->size($path);
                if (! isset($library[$path])) {
                    $pending += $files[$path];
                }
            }
        }

        return ['total' => array_sum($files), 'pending' => $pending, 'files' => $files];
    }

    /**
     * File di snapshot ini yang sudah tidak ada (dihapus karena lama tidak tayang). Dipakai sebelum "Kembalikan versi".
     *
     * @return list<string>
     */
    public function missingFiles(array $payload): array
    {
        $disk = Storage::disk('public');

        return array_values(array_filter(array_keys($this->pathsIn($payload)), fn (string $path) => ! $disk->exists($path)));
    }

    /** @return array<string, true> file yang dipakai library: konten, logo template, logo sponsor */
    private function libraryPaths(): array
    {
        $paths = SignatureContent::whereNotNull('file_path')->pluck('file_path')
            ->merge(SignatureTemplate::whereNotNull('logo_path')->pluck('logo_path'))
            ->merge(SignatureSponsor::whereNotNull('logo_path')->pluck('logo_path'));

        return $paths->filter()->mapWithKeys(fn (string $path) => [$path => true])->all();
    }

    /**
     * File yang sedang tayang atau berhenti tayang kurang dari KEEP_AFTER_AIRING_DAYS hari lalu: versi yang ditunjuk
     * layar, versi yang berhenti tayang (aired_until) sejak batas itu, dan — pengaman — versi yang dibuat sejak batas itu.
     *
     * @return array<string, true>
     */
    private function airedPaths(): array
    {
        $since = now()->subDays(self::KEEP_AFTER_AIRING_DAYS);

        $versions = SignatureVersion::query()
            ->where(fn ($query) => $query->whereIn('id', SignatureScreen::whereNotNull('published_version_id')->select('published_version_id'))
                ->orWhere('aired_until', '>=', $since)
                ->orWhere('created_at', '>=', $since))
            ->get(['id', 'payload']);

        $paths = [];
        foreach ($versions as $version) {
            $paths += $this->pathsIn((array) $version->payload);
        }

        return $paths;
    }

    /**
     * Semua file Signature yang dirujuk snapshot. Snapshot menyimpan URL tanpa host (/storage/signature/...), jadi
     * seluruh isi payload ditelusuri — tidak bergantung pada susunan kuncinya.
     *
     * @return array<string, true>
     */
    private function pathsIn(array $payload): array
    {
        $prefix = $this->publicUrlPrefix();
        $paths = [];

        array_walk_recursive($payload, function ($value) use ($prefix, &$paths) {
            if (! is_string($value) || $value === '') {
                return;
            }
            $urlPath = (string) (parse_url($value, PHP_URL_PATH) ?: $value);
            if (str_starts_with($urlPath, $prefix)) {
                $path = rawurldecode(substr($urlPath, strlen($prefix)));
                if ($this->isSignatureFile($path)) {
                    $paths[$path] = true;
                }
            }
        });

        return $paths;
    }

    /** Awalan URL disk `public` tanpa host, mis. "/storage/". */
    private function publicUrlPrefix(): string
    {
        $sample = (string) parse_url(Storage::disk('public')->url('signature'), PHP_URL_PATH);

        return substr($sample, 0, -strlen('signature'));
    }

    private function isSignatureFile(string $path): bool
    {
        foreach (self::DIRECTORIES as $directory) {
            if (str_starts_with($path, $directory.'/') && ! str_contains($path, '..')) {
                return true;
            }
        }

        return false;
    }
}
