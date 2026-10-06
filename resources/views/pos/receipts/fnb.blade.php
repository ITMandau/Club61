{{--
    Struk F&B — SATU sumber untuk modal struk, tombol Cetak Struk, dan cetak otomatis Bayar Otomatis
    (App\Livewire\Concerns\AutoPrintsReceipts). Param: $receipt (FnbCashierTerminal::receiptDataFor()).
--}}
@php
    $meta = $receipt['payment_meta'] ?? [];
    $qrisProviderLabels = [
        'BCA_QRIS' => 'QRIS BCA Frontdesk', 'MANDIRI_QRIS' => 'QRIS Bank Mandiri',
        'GOPAY' => 'GoPay / Midtrans QRIS', 'GOPAY_QRIS' => 'GoPay / Midtrans QRIS',
        'OVO' => 'OVO', 'SHOPEEPAY' => 'ShopeePay', 'DANA' => 'DANA',
        'LIVIN' => 'Livin Mandiri', 'LAINNYA' => 'QRIS Lainnya / Bank Lain', 'MIDTRANS_QRIS' => 'QRIS Otomatis (Kasir)',
    ];
@endphp
<div id="fnbpos-receipt" class="w-full bg-white rounded-2xl border-2 border-[#D4AF37] p-4 font-mono text-xs space-y-2">
    <div class="text-center font-serif font-black text-lg border-b border-dashed border-gray-400 pb-1 mb-1">CLUB 61 F&amp;B</div>
    <div class="text-center pb-2.5 mb-1.5 border-b border-dashed border-gray-400">
        <div class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">Nomor Antrian</div>
        <div class="text-4xl font-black text-[#8C6418] leading-tight">{{ $receipt['queue_number'] ? str_pad((string) $receipt['queue_number'], 3, '0', STR_PAD_LEFT) : '—' }}</div>
    </div>
    <div class="flex justify-between"><span class="text-gray-500">No. Order</span><span class="font-bold">{{ $receipt['order_number'] }}</span></div>
    <div class="flex justify-between"><span class="text-gray-500">Waktu</span><span class="font-bold">{{ $receipt['created_at'] ?? now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }}</span></div>
    <div class="flex justify-between"><span class="text-gray-500">Tipe Pesanan</span><span class="font-bold">{{ $receipt['order_type'] === 'DINE_IN' ? 'Dine-In' : 'Bawa Pulang' }}</span></div>
    <div class="flex justify-between"><span class="text-gray-500">No. Meja</span><span class="font-bold">{{ $receipt['table_number'] ?: '—' }}</span></div>
    <div class="flex justify-between"><span class="text-gray-500">Nama Pelanggan</span><span class="font-bold">{{ $receipt['customer_name'] ?? '' ?: '—' }}</span></div>
    <div class="flex justify-between"><span class="text-gray-500">Kasir</span><span class="font-bold">{{ $receipt['cashier_name'] }}</span></div>
    <div class="border-t border-dashed border-gray-400 my-2.5"></div>
    @foreach($receipt['items'] as $item)
        <div class="flex justify-between"><span>{{ $item['name'] }} x{{ $item['quantity'] }}</span><span>Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</span></div>
    @endforeach
    <div class="border-t border-dashed border-gray-400 my-2.5"></div>
    <div class="flex justify-between"><span>Subtotal</span><span>Rp {{ number_format($receipt['subtotal'], 0, ',', '.') }}</span></div>
    @if($receipt['tax_amount'] > 0)
        <div class="flex justify-between"><span>{{ $receipt['tax_name'] }}</span><span>Rp {{ number_format($receipt['tax_amount'], 0, ',', '.') }}</span></div>
    @endif
    <div class="flex justify-between font-bold text-base border-t border-gray-400 pt-2.5 mt-1"><span>TOTAL</span><span>Rp {{ number_format($receipt['grand_total'], 0, ',', '.') }}</span></div>

    <div class="border-t border-dashed border-gray-400 my-2.5"></div>
    <div class="text-center font-bold text-[#8C6418]">{{ match($receipt['payment_status'] ?? 'PAID') { 'PAID' => 'LUNAS', 'CANCELLED' => 'DIBATALKAN', 'REFUNDED' => 'DIREFUND', default => 'BELUM DIBAYAR' } }} &bull; {{ $receipt['payment_method_label'] ?? $receipt['payment_method'] }}</div>

    @if(!empty($meta['qris_provider']) || !empty($meta['qris_rrn']))
        <div class="text-xs text-[#4B5563] space-y-1 pt-1.5">
            <div class="flex justify-between"><span>Provider QRIS</span><span class="font-bold">{{ $qrisProviderLabels[$meta['qris_provider']] ?? $meta['qris_provider'] }}</span></div>
            @if(! empty($meta['qris_rrn']))
                <div class="flex justify-between"><span>RRN</span><span class="font-bold">{{ $meta['qris_rrn'] }}</span></div>
            @endif
            @if(!empty($meta['qris_sender_name']))
                <div class="flex justify-between"><span>Pengirim</span><span class="font-bold">{{ $meta['qris_sender_name'] }}</span></div>
            @endif
        </div>
    @elseif(!empty($meta['terminal']) || !empty($meta['card_last_4']))
        <div class="text-xs text-[#4B5563] space-y-1 pt-1.5">
            <div class="flex justify-between"><span>Terminal</span><span class="font-bold">{{ $meta['terminal'] === 'EDC_BCA' ? 'Mesin EDC BCA' : ($meta['terminal'] === 'EDC_MANDIRI' ? 'Mesin EDC Mandiri' : $meta['terminal']) }}</span></div>
            <div class="flex justify-between"><span>Kartu</span><span class="font-bold">{{ $meta['card_issuer'] ?? '' }} {{ $meta['card_network'] ?? '' }} &bull;&bull;&bull;&bull;{{ $meta['card_last_4'] }}</span></div>
            <div class="flex justify-between"><span>Approval Code</span><span class="font-bold">{{ $meta['approval_code'] }}</span></div>
            <div class="flex justify-between"><span>Trace Number</span><span class="font-bold">{{ $meta['trace_number'] }}</span></div>
        </div>
    @endif
</div>
