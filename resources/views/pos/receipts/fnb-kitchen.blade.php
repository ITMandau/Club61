{{--
    Slip pesanan (KOT) untuk bar & dapur — satu slip per stasiun yang punya pesanan (stasiun dari menu: BAR / KITCHEN).
    Tanpa harga. Dicetak bersama struk customer begitu lunas (pos.receipts.fnb-print) dan bisa dicetak ulang dari modal
    struk. Param: $receipt (FnbCashierTerminal::receiptDataFor()). Baris besar (text-sm ke atas) = dobel tinggi di printer.
--}}
@php
    $stations = ['KITCHEN' => 'PESANAN DAPUR', 'BAR' => 'PESANAN BAR'];
    $byStation = collect($receipt['items'] ?? [])->groupBy(fn ($item) => ($item['station'] ?? 'BAR') === 'KITCHEN' ? 'KITCHEN' : 'BAR');
@endphp
@foreach($stations as $station => $title)
    @if($byStation->has($station))
        <div data-print-slip id="fnbpos-kot-{{ strtolower($station) }}" class="w-full bg-white p-4 font-mono text-xs space-y-1.5">
            <div class="text-center font-black text-lg border-b border-dashed border-gray-400 pb-1">{{ $title }}</div>
            <div class="text-center pb-2 border-b border-dashed border-gray-400">
                <div class="text-[10px] uppercase tracking-widest font-bold">Nomor Antrian</div>
                <div class="text-4xl font-black leading-tight">{{ $receipt['queue_number'] ? str_pad((string) $receipt['queue_number'], 3, '0', STR_PAD_LEFT) : '—' }}</div>
            </div>
            @if(($receipt['order_type'] ?? null) === 'DINE_IN')
                <div class="flex justify-between font-black text-sm"><span>MEJA</span><span>{{ $receipt['table_number'] ?: '—' }}</span></div>
            @else
                <div class="text-center font-black text-sm">BAWA PULANG</div>
            @endif
            @if(! empty($receipt['customer_name']))
                <div class="flex justify-between"><span>Nama</span><span>{{ $receipt['customer_name'] }}</span></div>
            @endif
            <div class="flex justify-between"><span>No. Order</span><span>{{ $receipt['order_number'] }}</span></div>
            <div class="flex justify-between"><span>Waktu</span><span>{{ $receipt['created_at'] ?? now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }}</span></div>
            <div class="border-t border-dashed border-gray-400 my-2"></div>
            @foreach($byStation[$station] as $item)
                <div class="font-black text-sm">{{ $item['quantity'] }}x {{ $item['name'] }}</div>
                @if(trim((string) ($item['notes'] ?? '')) !== '')
                    <div class="pl-3">- {{ $item['notes'] }}</div>
                @endif
            @endforeach
            <div class="border-t border-dashed border-gray-400 my-2"></div>
            <div class="text-center">Kasir {{ $receipt['cashier_name'] ?? '-' }}</div>
        </div>
    @endif
@endforeach
