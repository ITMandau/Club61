{{--
    Invoice salinan admin dari Buku Transaksi (Modul 17 FR-04) untuk transaksi selain struk kasir padel: booking online,
    membership, F&B, refund. Angka diambil dari baris buku (terkunci saat uang masuk), bukan dihitung ulang dari order.
    Data: LedgerTransactionPresenter::invoice().
--}}
@php
    use App\Models\Finance\LedgerEntry;
    $rp = fn ($v) => \App\Filament\Pages\BukuTransaksi::rupiah($v);
    $isRefund = $head->entry_type === LedgerEntry::TYPE_REFUND;
    $line = 'display:flex; justify-content:space-between; gap:0.75rem;';
@endphp
<div id="printable-ledger-invoice"
    style="background:#FFFFFF; border:1px solid #E5E7EB; padding:1.25rem; width:100%; max-width:420px; font-family:monospace; font-size:0.75rem; color:#111827; border-radius:8px;">
    <div style="text-align:center; border-bottom:1px dashed #000; padding-bottom:0.65rem; margin-bottom:0.65rem;">
        <div style="font-weight:900; font-size:1rem; letter-spacing:0.05em;">CLUB 61 PADEL ARENA</div>
        <div style="font-size:0.65rem; color:#4B5563;">{{ \App\Models\Setting\CompanyProfileSetting::receiptAddress() }}</div>
        <div style="font-size:0.7rem; font-weight:900; margin-top:0.35rem;">{{ $isRefund ? 'BUKTI REFUND' : 'INVOICE' }}</div>
    </div>

    <div style="border-bottom:1px dashed #000; padding-bottom:0.5rem; margin-bottom:0.5rem; line-height:1.45;">
        <div>No. Order: <strong>{{ $head->order_number }}</strong></div>
        <div>{{ $isRefund ? 'Diproses' : 'Dibayar' }}: {{ $head->occurred_at?->timezone(\App\Services\Finance\LedgerReport::TIMEZONE)->format('d/m/Y H:i') }} WIB</div>
        <div>Customer: {{ $head->customer_name ?? '-' }}</div>
        <div>Sumber: {{ LedgerEntry::sourceLabel($head->source) }}</div>
        <div>Metode: <strong>{{ $head->payment_method_label ?? $head->payment_method ?? '-' }}</strong></div>
        @if ($head->cashier_name)
            <div>Kasir: {{ $head->cashier_name }}</div>
        @endif
        @if (! empty($proof['RRN QRIS']) || ! empty($proof['Approval Code']) || ! empty($proof['Nomor VA']))
            <div style="font-size:0.65rem; color:#4B5563;">
                {{ collect(['RRN QRIS', 'Approval Code', 'Trace Number', 'Nomor VA'])->filter(fn ($k) => ! empty($proof[$k]))->map(fn ($k) => $k.' '.$proof[$k])->implode(' · ') }}
            </div>
        @endif
        @if ($schedule_before)
            <div style="font-size:0.65rem; color:#4B5563;">Pelunasan selisih reschedule (jadwal sebelumnya: {{ is_array($schedule_before) ? implode(' ', array_filter(array_map(fn ($v) => is_scalar($v) ? $v : null, $schedule_before))) : $schedule_before }})</div>
        @endif
    </div>

    @if ($items->isNotEmpty() && ! $isRefund)
        <div style="border-bottom:1px dashed #000; padding-bottom:0.5rem; margin-bottom:0.5rem;">
            @foreach ($items as $item)
                <div style="{{ $line }}"><span>{{ $item->item_name }} x{{ $item->quantity }}</span><span>{{ $rp($item->subtotal) }}</span></div>
            @endforeach
        </div>
    @endif

    <div style="border-bottom:1px dashed #000; padding-bottom:0.5rem; margin-bottom:0.5rem; line-height:1.5;">
        @foreach ($rows as $r)
            <div style="{{ $line }}"><span>{{ LedgerEntry::categoryLabel($r->category) }}</span><span>{{ $rp($r->gross_amount) }}</span></div>
        @endforeach
        @if ((float) $totals['discount_amount'] != 0)
            <div style="{{ $line }}"><span>Diskon</span><span>-{{ $rp(abs($totals['discount_amount'])) }}</span></div>
        @endif
        <div style="{{ $line }} font-weight:800;"><span>Penjualan bersih</span><span>{{ $rp($totals['net_amount']) }}</span></div>
        @if ((float) $totals['service_amount'] != 0)
            <div style="{{ $line }}"><span>Biaya layanan</span><span>{{ $rp($totals['service_amount']) }}</span></div>
        @endif
        @if ((float) $totals['tax_amount'] != 0)
            <div style="{{ $line }}"><span>Pajak</span><span>{{ $rp($totals['tax_amount']) }}</span></div>
        @endif
        <div style="{{ $line }} font-weight:900; font-size:0.85rem; margin-top:0.2rem;"><span>{{ $isRefund ? 'TOTAL REFUND' : 'TOTAL DIBAYAR' }}</span><span>{{ $rp($totals['total_amount']) }}</span></div>
        @if ((float) $totals['benefit_amount'] > 0)
            <div style="font-size:0.65rem; color:#4B5563;">Ditanggung kuota member / voucher: {{ $rp($totals['benefit_amount']) }}</div>
        @endif
    </div>

    @if ($isRefund && $refund)
        <div style="font-size:0.68rem; color:#4B5563; margin-bottom:0.5rem;">
            Alasan: {{ $refund->reason }}
            @if ($refund->refund_method)<br>Dikembalikan via {{ $refund->refund_method }}{{ $refund->refund_reference ? ' · Ref '.$refund->refund_reference : '' }}@endif
        </div>
    @endif

    <div style="text-align:center; font-size:0.65rem; font-weight:900;">*** SALINAN ADMIN {{ $copy_at }} ***</div>
</div>
