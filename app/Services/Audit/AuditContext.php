<?php

namespace App\Services\Audit;

use Illuminate\Support\Str;

/**
 * State per-request / per-command untuk ActivityLogger (didaftarkan sebagai scoped singleton,
 * jadi otomatis reset di setiap request/job baru).
 */
class AuditContext
{
    public ?string $batchId = null;

    /** Nama command artisan yang sedang berjalan (null = request HTTP). */
    public ?string $consoleCommand = null;

    /** >0 = pencatatan otomatis dimatikan sementara (seeder, migrate). */
    public int $suppressed = 0;

    public function batchId(): string
    {
        return $this->batchId ??= (string) Str::uuid();
    }

    public function newBatch(): string
    {
        return $this->batchId = (string) Str::uuid();
    }
}
