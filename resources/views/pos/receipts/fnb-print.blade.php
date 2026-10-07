{{-- Cetak otomatis F&B begitu lunas: struk customer, lalu slip pesanan dapur & bar — satu kali cetak. Param: $receipt. --}}
@include('pos.receipts.fnb', ['receipt' => $receipt])
@include('pos.receipts.fnb-kitchen', ['receipt' => $receipt])
