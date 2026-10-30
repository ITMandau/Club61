{{-- Satu baris Kartu Stok F&B (Modul 29) — dipakai di tab Riwayat Stok & modal Kartu Stok. --}}
@php
    $voided = $mv->voided_at !== null;
    $info = array_filter([
        $mv->receipt ? 'No. '.$mv->receipt->receipt_number : null,
        $mv->purchase_total !== null ? 'Harga pembelian '.\App\Filament\Pages\BukuTransaksi::rupiah($mv->purchase_total) : null,
        $mv->receipt?->supplier && $mv->type === \App\Models\Fnb\FnbStockMovement::RESTOCK ? 'Supplier: '.$mv->receipt->supplier : null,
        $mv->receipt?->invoice_number && $mv->type === \App\Models\Fnb\FnbStockMovement::RESTOCK ? 'Nota: '.$mv->receipt->invoice_number : null,
        $mv->order_id ? 'Pesanan kasir F&B' : null,
        $mv->notes,
        $voided ? 'Dibatalkan '.$mv->voided_at->timezone('Asia/Jakarta')->format('d/m H:i').' oleh '.($mv->voider?->name ?? '-').': '.$mv->void_reason : null,
    ]);
@endphp
<tr wire:key="mv-{{ $mv->id }}">
    <td style="white-space: nowrap;">{{ $mv->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td>
    @if ($showMenu)
        <td class="strong">{{ $mv->menu_name }}</td>
    @endif
    <td><span class="{{ $voided ? 'sf-voided' : '' }}">{{ $mv->typeLabel() }}</span></td>
    <td class="num"><span class="sf-change {{ $mv->qty_change > 0 ? 'is-plus' : 'is-minus' }} {{ $voided ? 'sf-voided' : '' }}">{{ sprintf('%+d', $mv->qty_change) }}</span></td>
    <td class="num">{{ $mv->stock_after }}</td>
    <td style="max-width: 340px;"><div class="sub" style="white-space: normal;">{{ implode(' · ', $info) ?: '-' }}</div></td>
    <td>{{ $mv->creator?->name ?? ($mv->type === \App\Models\Fnb\FnbStockMovement::SALE ? 'Sistem' : '-') }}</td>
</tr>
