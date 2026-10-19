<?php

namespace App\Console\Commands;

use App\Services\Audit\ActivityLogger;
use App\Services\Signature\SignatureFileJanitor;
use Illuminate\Console\Command;

/**
 * Digital Signage: hapus video / gambar / logo yang tidak dipakai library dan tidak tayang di TV mana pun selama
 * SignatureFileJanitor::KEEP_AFTER_AIRING_DAYS hari. File yang masih tayang tidak pernah dihapus.
 */
class CleanSignatureFiles extends Command
{
    protected $signature = 'signature:clean-files';

    protected $description = 'Hapus file Digital Signage yang tidak dipakai & tidak tayang selama 7 hari';

    public function handle(SignatureFileJanitor $janitor): int
    {
        $result = $janitor->sweep();
        $size = number_format($result['bytes'] / 1048576, 1, ',', '.').' MB';

        if ($result['deleted'] > 0) {
            ActivityLogger::record(
                module: 'SIGNATURE',
                event: 'signature.files_cleaned',
                description: "Sistem menghapus {$result['deleted']} file Digital Signage yang tidak tayang lebih dari ".SignatureFileJanitor::KEEP_AFTER_AIRING_DAYS." hari ({$size})",
                meta: ['jumlah_file' => $result['deleted'], 'ukuran_dibebaskan' => $size],
                asSystem: true,
            );
        }

        $this->info("{$result['deleted']} file dihapus ({$size} dibebaskan).");

        return self::SUCCESS;
    }
}
