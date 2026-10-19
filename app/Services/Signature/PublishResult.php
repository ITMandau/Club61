<?php

namespace App\Services\Signature;

use App\Models\Signature\SignatureVersion;

/** Hasil Tayangkan / Kembalikan: versi yang sekarang tayang dan apa yang terjadi. */
final class PublishResult
{
    /** Layar pindah ke template lain dengan tampilan yang belum pernah ada → versi baru dibuat. */
    public const CREATED = 'CREATED';

    /** Template sama dengan yang sedang tayang, isinya berubah → versi itu diperbarui (revision naik). */
    public const UPDATED = 'UPDATED';

    /** Tampilan sama dengan versi lama di riwayat → versi itu ditayangkan lagi, tanpa versi baru. */
    public const REUSED = 'REUSED';

    /** Tampilan sama dengan yang sedang tayang → tidak ada yang berubah. */
    public const UNCHANGED = 'UNCHANGED';

    public function __construct(
        public readonly SignatureVersion $version,
        public readonly string $outcome,
    ) {}
}
