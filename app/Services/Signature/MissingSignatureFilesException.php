<?php

namespace App\Services\Signature;

/** Versi lama yang mau dikembalikan menunjuk ke file yang sudah dihapus (lama tidak tayang). Pesannya aman untuk admin. */
class MissingSignatureFilesException extends \RuntimeException {}
