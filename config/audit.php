<?php

return [
    /*
    | Lama log aktivitas disimpan sebelum dibersihkan otomatis oleh `audit:prune` (bulan).
    | Default 24 bulan: cukup untuk audit tahunan + perbandingan dengan tahun sebelumnya.
    */
    'retention_months' => (int) env('AUDIT_RETENTION_MONTHS', 24),

    /* Batas baris sekali export CSV — mencegah satu klik menarik jutaan baris & membebani server. */
    'export_max_rows' => (int) env('AUDIT_EXPORT_MAX_ROWS', 50000),
];
