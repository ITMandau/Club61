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
    public const BASE_FONT_PX = 13;

    /** Font gambar struk. Android tidak punya Segoe UI → jatuh ke Roboto yang mirip. */
    public const FONT_STACK = "'Segoe UI', Roboto, Arial, sans-serif";

    /** Ruang kosong kiri & kanan di dalam area cetak (mm). */
    public const SIDE_PADDING_MM = 1.5;

    /**
     * Struk digambar ke kanvas hitam-putih selebar kepala print, lalu gambar itu yang dicetak: lewat RawBT sebagai ESC/POS
     * (tablet / HP Android) dan lewat Chrome (PC kasir) — hasil keduanya sama dan hitam pekat.
     * Kepala print 58mm = 384 titik pada 203 dpi.
     */
    public const RASTER_DOTS = 384;

    public const RASTER_DPI = 203;

    /** Tinggi kertas minimal (mm) supaya struk sangat pendek tetap terpotong rapi. */
    public const MIN_LENGTH_MM = 40;

    /** Ruang kosong di bawah struk (mm) sebelum dipotong. */
    public const BOTTOM_FEED_MM = 4;

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
        $family = self::FONT_STACK;
        $side = self::SIDE_PADDING_MM;

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