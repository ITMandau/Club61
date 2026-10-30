<?php

namespace App\Services\Fnb;

use App\Models\Finance\LedgerEntry;
use App\Models\Fnb\FnbCashEntry;
use App\Models\Fnb\FnbMenu;
use App\Models\Fnb\FnbStockMovement;
use App\Models\Pos\OrderItem;
use App\Services\Finance\LedgerReport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Modul 29 — angka Laporan Keuangan F&B. Hari kalender WIB (sama dengan Buku Transaksi).
 *  - Pemasukan = penjualan kasir F&B saja: Buku Transaksi kategori FNB (angka sama dengan Analytics / Buku Transaksi).
 *  - HPP: snapshot order_items.unit_cost dari pesanan yang pembayarannya masuk di periode itu.
 *  - Kerugian stok: pengurangan Penyesuaian (kecuali Salah Input / Dikembalikan ke Penitip) & selisih minus Opname × HPP.
 *  - Pengeluaran F&B: kategori "Pembelian Persediaan / Bahan" hanya mengurangi Arus Kas, tidak dipotong lagi di Laba
 *    (sudah diwakili HPP).
 */
class FnbReportService
{
    /** @return array<string, mixed> */
    public function summary(?string $from, ?string $until): array
    {
        $ledger = LedgerReport::applyDateRange(LedgerEntry::query()->where('category', 'FNB'), $from, $until);
        $sales = (float) (clone $ledger)->sum('net_amount');
        $moneyIn = (float) (clone $ledger)->sum('total_amount');

        $menus = $this->menuBreakdown($ledger);
        $hpp = (float) $menus->sum('hpp');

        $stockLoss = (float) $this->movementsBetween($from, $until)
            ->whereIn('type', [FnbStockMovement::ADJUSTMENT, FnbStockMovement::OPNAME])
            ->where('qty_change', '<', 0)
            ->where(fn (Builder $q) => $q->whereNull('reason')->orWhereNotIn('reason', FnbStockMovement::NOT_A_LOSS))
            ->selectRaw('COALESCE(SUM(-qty_change * unit_cost), 0) as total')->value('total');

        $out = FnbCashEntry::query()->active()->with('category:id,name,is_stock_purchase')
            ->when($from, fn (Builder $q) => $q->whereDate('entry_date', '>=', $from))
            ->when($until, fn (Builder $q) => $q->whereDate('entry_date', '<=', $until))
            ->get();
        $stockPurchases = (float) $out->filter(fn ($e) => $e->category?->is_stock_purchase)->sum('amount');
        $operational = (float) $out->reject(fn ($e) => $e->category?->is_stock_purchase)->sum('amount');

        $grossProfit = $sales - $hpp;
        $moneyOut = $stockPurchases + $operational;

        return [
            'sales' => $sales,
            'hpp' => $hpp,
            'gross_profit' => $grossProfit,
            'stock_loss' => $stockLoss,
            'operational' => $operational,
            'net_profit' => $grossProfit - $stockLoss - $operational,
            'money_in' => $moneyIn,
            'stock_purchases' => $stockPurchases,
            'money_out' => $moneyOut,
            'cash_flow' => $moneyIn - $moneyOut,
            'missing_cost_qty' => (int) $menus->sum('missing_cost_qty'),
            'menus' => $menus,
            'expense_by_category' => $out->groupBy(fn ($e) => $e->category?->name ?? '-')->map(fn ($rows) => (float) $rows->sum('amount'))->sortDesc(),
        ];
    }

    /**
     * Penjualan, HPP & laba kotor per menu dari pesanan yang dibayar di periode itu. HPP Rp0 dihitung "belum memiliki
     * HPP" (missing_cost_qty) — kolom HPP menu berawal 0, jadi menu yang lupa diisi tidak tampak sebagai laba 100%.
     */
    public function menuBreakdown(Builder $ledger): Collection
    {
        $orderIds = (clone $ledger)->where('entry_type', LedgerEntry::TYPE_PAYMENT)->select('order_id');

        return OrderItem::query()
            ->where('item_type', 'FNB')
            ->whereIn('order_id', $orderIds)
            ->selectRaw('reference_id, MAX(item_name) as name, SUM(quantity) as qty, SUM(subtotal) as sales,
                SUM(quantity * COALESCE(unit_cost, 0)) as hpp, SUM(CASE WHEN unit_cost IS NULL OR unit_cost = 0 THEN quantity ELSE 0 END) as missing_cost_qty')
            ->groupBy('reference_id')
            ->get()
            ->map(fn ($r) => [
                'menu_id' => $r->reference_id,
                'name' => $r->name,
                'qty' => (int) $r->qty,
                'sales' => (float) $r->sales,
                'hpp' => (float) $r->hpp,
                'profit' => (float) $r->sales - (float) $r->hpp,
                'missing_cost_qty' => (int) $r->missing_cost_qty,
            ])
            ->values();
    }

    /** @return array<int, array{label: string, sales: float, gross_profit: float, net_profit: float, money_out: float}> 6 bulan terakhir */
    public function monthlyTrend(int $months = 6): array
    {
        $start = Carbon::now(LedgerReport::TIMEZONE)->startOfMonth()->subMonthsNoOverflow($months - 1);

        return collect(range(0, $months - 1))->map(function (int $i) use ($start) {
            $month = $start->copy()->addMonthsNoOverflow($i);
            $s = $this->summary($month->toDateString(), $month->copy()->endOfMonth()->toDateString());

            return [
                'label' => $month->translatedFormat('M Y'),
                'sales' => $s['sales'],
                'gross_profit' => $s['gross_profit'],
                'net_profit' => $s['net_profit'],
                'money_out' => $s['money_out'],
            ];
        })->all();
    }

    /** Menu dilacak yang stoknya minus, habis, atau di bawah / sama dengan stok minimum. */
    public function stockAlerts(): Collection
    {
        return FnbMenu::query()->where('track_stock', true)
            ->where(fn (Builder $q) => $q->where('stock_qty', '<=', 0)->orWhereColumn('stock_qty', '<=', 'min_stock'))
            ->orderBy('stock_qty')->orderBy('name')
            ->get(['id', 'name', 'stock_qty', 'min_stock', 'track_stock']);
    }

    public function movementsBetween(?string $from, ?string $until): Builder
    {
        $tz = config('app.timezone');

        return FnbStockMovement::query()
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($from, LedgerReport::TIMEZONE)->startOfDay()->setTimezone($tz)))
            ->when($until, fn (Builder $q) => $q->where('created_at', '<', Carbon::parse($until, LedgerReport::TIMEZONE)->addDay()->startOfDay()->setTimezone($tz)));
    }
}
