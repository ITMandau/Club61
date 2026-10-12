<?php

namespace App\Services\Import;

use RuntimeException;

/** Import dibatalkan karena file masih berisi error — tidak ada data yang ditulis. */
class SalesImportFailed extends RuntimeException
{
    /** @param  array<int, array{sheet: string, row: int|null, ref: string|null, message: string}>  $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(count($errors).' baris masih error — import dibatalkan, tidak ada data yang masuk.');
    }
}
