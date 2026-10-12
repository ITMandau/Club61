<?php

namespace App\Services\Import;

use RuntimeException;

/** Pembatalan import ditolak: ada transaksi dari batch itu yang sudah dipakai operasional. */
class SalesImportCancelBlocked extends RuntimeException
{
    /** @param  array<int, string>  $reasons */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct('Import tidak bisa dibatalkan: '.implode(' ', array_slice($reasons, 0, 5)).(count($reasons) > 5 ? ' (+'.(count($reasons) - 5).' lainnya)' : ''));
    }
}
