<?php

namespace App\Support;

/**
 * Ukuran kertas printer struk thermal kasir — satu sumber untuk semua struk (POS Walk-In, Z-Report, F&B, Jual
 * Membership, salinan admin Buku Transaksi). Sementara ditulis tetap; nanti diganti pengaturan master layout struk.
 */
final class ReceiptPaper
{
    /** Lebar gulungan kertas. */
    public const PAPER_MM = 58;

    /** Area cetak printer 58mm (kepala print 48mm / 384 dot). Isi struk dibatasi ke lebar ini supaya tidak terpotong. */
    public const PRINT_MM = 48;

    /** Ukuran huruf dasar saat dicetak — gaya struk memakai rem, jadi semua ukuran ikut mengecil proporsional. */
    public const BASE_FONT_PX = 14;

    /**
     * Jumlah huruf per baris saat dicetak lewat RawBT (ESC/POS, font A 12 dot × 384 dot kepala print 58mm). Dipakai
     * tablet / HP Android — di sana struk dikirim sebagai perintah printer, bukan dicetak lewat browser.
     */
    public const ESC_POS_COLUMNS = 32;

    /** Tinggi kertas minimal (mm) supaya struk sangat pendek tetap terpotong rapi. */
    public const MIN_LENGTH_MM = 40;

    /** Ruang kosong di bawah struk (mm) sebelum dipotong. */
    public const BOTTOM_FEED_MM = 4;

    /**
     * Gaya struk di dalam jendela cetak khusus (iframe dari club61PrintReceipt). Struk dibungkus #club61-receipt-root,
     * jadi berlaku untuk struk apa pun.
     */
    public static function frameCss(): string
    {
        $print = self::PRINT_MM;
        $font = self::BASE_FONT_PX;

        return <<<CSS
html { font-size: {$font}px !important; }
html, body { margin: 0 !important; padding: 0 !important; background: #FFFFFF !important; }
#club61-receipt-root {
    width: {$print}mm !important; max-width: {$print}mm !important; margin: 0 !important; padding: 1mm 1.5mm 0 !important;
    box-sizing: border-box !important; background: #FFFFFF !important; color: #000000 !important;
    font-family: Arial, Helvetica, sans-serif !important; line-height: 1.45 !important; letter-spacing: 0.02em !important;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
#club61-receipt-root > * {
    width: 100% !important; max-width: 100% !important; min-width: 0 !important; margin: 0 !important; padding: 0 !important;
    border: none !important; border-radius: 0 !important; box-shadow: none !important;
}
/* Printer thermal hanya hitam/putih: teks abu-abu dicetak sebagai titik-titik (belang), jadi semua teks hitam dengan
   garis huruf sedikit ditebalkan. Tidak semua dipaksa bold (huruf jadi rapat / mepet) — judul & total tetap bold dari
   desain struknya. */
#club61-receipt-root * {
    color: #000000 !important; background: transparent !important; box-shadow: none !important;
    font-family: inherit !important; border-color: #000000 !important; line-height: inherit !important;
    -webkit-text-stroke: 0.15px #000000; max-width: 100% !important; overflow-wrap: break-word;
}
/* font-weight 800/900 di Windows memakai Arial Black (gemuk & kotak) — cukup bold biasa. */
#club61-receipt-root [style*="font-weight:900"], #club61-receipt-root [style*="font-weight: 900"],
#club61-receipt-root [style*="font-weight:800"], #club61-receipt-root [style*="font-weight: 800"],
#club61-receipt-root .font-black, #club61-receipt-root .font-extrabold { font-weight: 700 !important; }
/* Kartu struk di layar (bingkai, sudut bulat, padding) tidak ikut dicetak — padding-nya memakan lebar kertas. */
#club61-receipt-root [id^="printable-"], #club61-receipt-root #fnbpos-receipt {
    width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 0 !important;
    border: none !important; border-radius: 0 !important; box-shadow: none !important;
}
/* Baris label-nominal: nominal ("Rp 500.000") tidak pernah dipotong ke baris bawah, label yang mengalah. */
/* Kalau label + nominal tidak muat sebaris, nominal pindah ke baris berikutnya (rata kanan) — label tetap utuh,
   tidak terpotong di tengah kata seperti "TOTAL / BAYAR". */
#club61-receipt-root [style*="space-between"], #club61-receipt-root .justify-between {
    flex-wrap: wrap !important; column-gap: 1.5mm !important; row-gap: 0 !important; gap: 0 1.5mm !important;
}
#club61-receipt-root [style*="space-between"] > :first-child, #club61-receipt-root .justify-between > :first-child { min-width: 0 !important; flex: 1 1 auto !important; }
#club61-receipt-root [style*="space-between"] > :last-child:not(:first-child), #club61-receipt-root .justify-between > :last-child:not(:first-child) {
    white-space: nowrap !important; flex: 0 0 auto !important; margin-left: auto !important; text-align: right !important;
}
#club61-receipt-root img, #club61-receipt-root svg, #club61-receipt-root canvas { max-width: 100% !important; height: auto !important; }
#club61-receipt-root .no-print { display: none !important; }
CSS;
    }

    /**
     * CSS @media print untuk elemen struk dengan selector tertentu — cadangan kalau dicetak lewat Ctrl+P. Tombol cetak
     * memakai club61PrintReceipt() yang mengatur panjang kertas = tinggi struk. Ukuran @page sengaja tidak diisi di sini:
     * dulu "58mm" + "auto" (tidak valid) diabaikan browser sehingga kertas ikut setelan driver, bisa bermeter-meter.
     */
    public static function printCss(array $selectors): string
    {
        $self = implode(', ', $selectors);
        $withChildren = implode(', ', array_map(fn (string $s) => "{$s}, {$s} *", $selectors));
        $children = implode(', ', array_map(fn (string $s) => "{$s} *", $selectors));
        $media = implode(', ', array_map(fn (string $s) => "{$s} img, {$s} svg, {$s} canvas", $selectors));
        $print = self::PRINT_MM;
        $font = self::BASE_FONT_PX;

        return <<<CSS
@media print {
    @page { margin: 0; }
    html { font-size: {$font}px !important; }
    html, body { margin: 0 !important; padding: 0 !important; background: #FFFFFF !important; }
    body * { visibility: hidden; }
    {$withChildren} { visibility: visible; }
    {$self} {
        position: absolute !important; left: 0 !important; top: 0 !important;
        width: {$print}mm !important; max-width: {$print}mm !important; min-width: 0 !important;
        margin: 0 !important; padding: 1mm 0 4mm !important; box-sizing: border-box !important;
        border: none !important; border-radius: 0 !important; box-shadow: none !important;
        background: #FFFFFF !important; color: #000000 !important;
        font-family: 'Courier New', Courier, monospace !important; line-height: 1.3 !important; overflow: visible !important;
    }
    {$children} {
        color: #000000 !important; background: transparent !important; box-shadow: none !important;
        max-width: 100% !important; overflow-wrap: anywhere; word-break: break-word;
    }
    {$media} { max-width: 100% !important; height: auto !important; }
    .no-print { display: none !important; }
}
CSS;
    }
}