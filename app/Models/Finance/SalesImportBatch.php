<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Satu kali Import Penjualan dari Excel (App\Services\Import\SalesImportService): ringkasan, file asli, status batal. */
class SalesImportBatch extends Model
{
    use HasUlids;

    public const STATUS_IMPORTED = 'IMPORTED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'batch_code',
        'file_name',
        'file_path',
        'imported_by_id',
        'padel_transactions',
        'padel_bookings',
        'padel_total',
        'fnb_transactions',
        'fnb_total',
        'new_customer_ids',
        'status',
        'cancelled_by_id',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'padel_total' => 'decimal:2',
            'fnb_total' => 'decimal:2',
            'new_customer_ids' => 'array',
            'cancelled_at' => 'datetime',
        ];
    }

    public function importedBy()
    {
        return $this->belongsTo(User::class, 'imported_by_id');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }
}
