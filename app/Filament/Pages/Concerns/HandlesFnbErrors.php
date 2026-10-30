<?php

namespace App\Filament\Pages\Concerns;

use App\Services\Audit\ActivityLogger;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Halaman Modul 29 (Stok F&B, Pengeluaran F&B): izin dicek di server per aksi, dan penolakan bisnis dari service
 * (abort 422) tampil sebagai notifikasi — bukan layar error Livewire.
 */
trait HandlesFnbErrors
{
    protected function authorizeAction(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            ActivityLogger::accessDenied("mencoba aksi tanpa izin [{$permission}] di ".static::$title);
            abort(403, "Akses ditolak: Anda tidak memiliki izin [{$permission}].");
        }
    }

    protected function attempt(callable $callback): void
    {
        try {
            $callback();
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 403) {
                throw $e;
            }
            Notification::make()->title('Tidak dapat disimpan')->body($e->getMessage())->danger()->send();
        }
    }

    /** Path file Excel sementara untuk diunduh (dihapus setelah terkirim lewat deleteFileAfterSend). */
    protected function tempXlsxPath(string $prefix): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefix.'-'.Str::uuid().'.xlsx';
    }
}
