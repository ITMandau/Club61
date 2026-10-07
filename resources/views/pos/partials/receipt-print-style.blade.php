{{-- Cetak struk thermal (ukuran dari App\Support\ReceiptPaper). Pakai: @include('pos.partials.receipt-print-style', ['selectors' => ['#id-struk']])
     Tombol cetak memanggil club61PrintReceipt('#id-struk'); <style> di bawah hanya cadangan untuk Ctrl+P. --}}
<style data-club61-receipt-print>{!! \App\Support\ReceiptPaper::printCss($selectors) !!}</style>
@include('pos.partials.receipt-print-script')
