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

    /**
     * Gaya struk POS umum (ESB, Moka): teks 32 kolom font A bawaan printer — Android lewat RawBT mengirim teks ESC/POS,
     * PC mencetak teks monospace dengan tata letak yang sama.
     */
    public const COLUMNS = 32;

    /** Kolom cetak dari PC — lebih sedikit dari font printer supaya huruf lebih besar & tidak terkesan pelit. */
    public const PC_COLUMNS = 28;

    /** Lebar teks di cetak dari PC (mm), di tengah kertas 58mm — selebar kepala print. */
    public const TEXT_MM = 48;

    /** Font cetak dari PC: monospace tipis tapi tajam (angka & huruf mirip mudah dibedakan), dimuat dari Google Fonts. */
    public const FONT_STACK = "'JetBrains Mono', Consolas, monospace";

    public const FONT_URL = 'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=block';

    /** Baris judul & TOTAL di PC dipanjangkan ke atas segini (dobel tinggi di printer). Teks biasa tidak dipanjangkan. */
    public const BIG_STRETCH_Y = 1.5;

    /** Tinggi satu baris cetak dari PC (mm). */
    public const LINE_MM = 4.8;

    /** Baris kosong di bawah struk sebelum disobek / dipotong. */
    public const FEED_LINES = 4;

    /** Tinggi kertas minimal (mm) supaya struk sangat pendek tetap terpotong rapi. */
    public const MIN_LENGTH_MM = 40;

    /** Ukuran huruf cadangan Ctrl+P (printCss). */
    public const BASE_FONT_PX = 12;

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