<?php

namespace App\Models\Pos;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PosCashierShift extends Model
{
    use HasUlids;

    protected $table = 'pos_cashier_shifts';

    protected $fillable = [
        'shift_number',
        'counter',
        'status',
        'opened_by_id',
        'closed_by_id',
        'opened_at',
        'closed_at',
        'starting_cash',
        'expected_cash',
        'actual_cash',
        'cash_difference',
        'total_cash_sales',
        'total_edc_bca_sales',
        'total_edc_mandiri_sales',
        'total_qris_sales',
        'total_other_sales',
        'total_sales',
        'total_transactions',
        'opening_notes',
        'closing_notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'starting_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'cash_difference' => 'decimal:2',
            'total_cash_sales' => 'decimal:2',
            'total_edc_bca_sales' => 'decimal:2',
            'total_edc_mandiri_sales' => 'decimal:2',
            'total_qris_sales' => 'decimal:2',
            'total_other_sales' => 'decimal:2',
            'total_sales' => 'decimal:2',
            'total_transactions' => 'integer',
        ];
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by_id');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'pos_shift_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'pos_shift_id');
    }

    public static function getActiveShift(string $counter = 'PADEL_FRONTDESK'): ?self
    {
        return static::where('counter', $counter)
            ->where('status', 'OPEN')
            ->latest('opened_at')
            ->first();
    }

    public static function generateShiftNumber(string $counter = 'PADEL_FRONTDESK'): string
    {
        $prefixCode = match ($counter) {
            'PADEL_FRONTDESK' => 'PADEL',
            'FNB_COUNTER' => 'FNB',
            default => strtoupper(substr(str_replace('_', '', $counter), 0, 5)),
        };

        $date = Carbon::now('Asia/Jakarta')->format('Ymd');
        $prefix = "SFT-{$prefixCode}-{$date}-";

        $countToday = static::where('counter', $counter)
            ->whereDate('opened_at', Carbon::now('Asia/Jakarta')->toDateString())
            ->count();

        $sequence = str_pad((string) ($countToday + 1), 4, '0', STR_PAD_LEFT);

        while (static::where('shift_number', $prefix . $sequence)->exists()) {
            $countToday++;
            $sequence = str_pad((string) ($countToday + 1), 4, '0', STR_PAD_LEFT);
        }

        return $prefix . $sequence;
    }

    public function calculateSummary(): array
    {
        $payments = $this->payments()->where('status', 'SUCCESS')->get();

        $cashSales = (float) $payments->where('payment_method', 'CASH')->sum('amount');
        $debitSales = (float) $payments->whereIn('payment_method', ['DEBIT_CARD', 'DEBIT'])->sum('amount');
        $creditSales = (float) $payments->whereIn('payment_method', ['CREDIT_CARD', 'CREDIT'])->sum('amount');
        $edcBcaSales = (float) $payments->where('payment_method', 'EDC_BCA')->sum('amount');
        $edcMandiriSales = (float) $payments->where('payment_method', 'EDC_MANDIRI')->sum('amount');
        $qrisSales = (float) $payments->whereIn('payment_method', ['QRIS', 'QRIS_STATIS'])->sum('amount');
        $otherSales = (float) $payments->whereNotIn('payment_method', ['CASH', 'DEBIT_CARD', 'DEBIT', 'CREDIT_CARD', 'CREDIT', 'EDC_BCA', 'EDC_MANDIRI', 'QRIS', 'QRIS_STATIS'])->sum('amount');
        $totalSales = $cashSales + $debitSales + $creditSales + $edcBcaSales + $edcMandiriSales + $qrisSales + $otherSales;
        $totalTransactions = $payments->count();
        $expectedCash = (float) $this->starting_cash + $cashSales;

        return [
            'total_cash_sales' => $cashSales,
            'total_debit_sales' => $debitSales,
            'total_credit_sales' => $creditSales,
            'total_edc_bca_sales' => $edcBcaSales,
            'total_edc_mandiri_sales' => $edcMandiriSales,
            'total_qris_sales' => $qrisSales,
            'total_other_sales' => $otherSales,
            'total_sales' => $totalSales,
            'total_transactions' => $totalTransactions,
            'expected_cash' => $expectedCash,
        ];
    }
}
