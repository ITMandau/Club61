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
    public const BASE_FONT_PX = 11;

    /** CSS @media print untuk elemen struk dengan selector tertentu (sisa halaman disembunyikan). */
    public static function printCss(array $selectors): string
    {
        $self = implode(', ', $selectors);
        $withChildren = implode(', ', array_map(fn (string $s) => "{$s}, {$s} *", $selectors));
        $children = implode(', ', array_map(fn (string $s) => "{$s} *", $selectors));
        $media = implode(', ', array_map(fn (string $s) => "{$s} img, {$s} svg, {$s} canvas", $selectors));
        $paper = self::PAPER_MM;
        $print = self::PRINT_MM;
        $font = self::BASE_FONT_PX;

        return <<<CSS
@media print {
    @page { size: {$paper}mm auto; margin: 0; }
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