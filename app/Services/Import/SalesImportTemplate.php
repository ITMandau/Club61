<?php

namespace App\Services\Import;

use App\Models\Fnb\FnbMenu;
use App\Models\Padel\PadelCourt;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Template Excel import penjualan (soft opening): sheet Petunjuk, Booking Padel, Penjualan F&B, dan Lapangan & Menu
 * (referensi nama & harga lapangan / menu yang dikenali sistem). Sheet data hanya berisi judul kolom + nomor urut —
 * contoh pengisian ada di Petunjuk, supaya contoh tidak ikut ter-import.
 */
class SalesImportTemplate
{
    public const SHEET_REFERENCE = 'Lapangan & Menu';

    /** Baris kosong bernomor urut yang disiapkan di sheet data. */
    private const DATA_ROWS = 300;

    /** Lebar kolom per judul (karakter) — nama menu / lapangan / customer terbaca utuh. */
    private const WIDTHS = [
        'No.' => 6, 'No. Transaksi' => 16, 'Tanggal Bayar*' => 18, 'Nama Customer*' => 24, 'No. HP*' => 16,
        'Email Customer' => 26, 'Lapangan*' => 24, 'Tanggal Main*' => 14, 'Jam Mulai*' => 11, 'Durasi (Jam)*' => 11,
        'Harga Sewa*' => 14, 'Diskon' => 12, 'Pajak' => 12, 'Biaya Layanan' => 13, 'Total Dibayar*' => 15,
        'Email Kasir' => 26, 'Catatan' => 32, 'Tipe Pesanan*' => 14, 'No. Meja' => 10, 'Nama Pelanggan' => 20,
        'Menu*' => 34, 'Qty*' => 7, 'Harga Satuan*' => 14, 'Catatan Item' => 26,
    ];

    /** Kolom berformat teks: Excel tidak mengubah tanggal / jam / No. HP yang diketik jadi angka. */
    private const TEXT_COLUMNS = ['No. Transaksi', 'Tanggal Bayar*', 'No. HP*', 'Tanggal Main*', 'Jam Mulai*', 'No. Meja'];

    public function build(string $path): string
    {
        $writer = new Writer();
        $writer->openToFile($path);

        $header = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('662721')->setShouldWrapText();
        $title = (new Style())->setFontBold()->setFontSize(14);
        $bold = (new Style())->setFontBold();
        $wrap = (new Style())->setShouldWrapText();

        // ---- Petunjuk ----
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Petunjuk');
        $sheet->setColumnWidth(30, 1);
        $sheet->setColumnWidth(100, 2);
        $writer->addRow(Row::fromValues(['Import Penjualan Club 61 — Booking Padel Walk-In & F&B'], $title));
        $writer->addRow(Row::fromValues([]));
        foreach ([
            ['Cara isi', 'Isi sheet "Booking Padel" dan/atau "Penjualan F&B" mulai baris No. 1. Satu baris = satu booking (berapa pun jamnya) / satu menu.'],
            ['No. (urut)', 'Nomor urut baris, sudah terisi. Dipakai untuk menunjuk baris yang error di halaman import.'],
            ['No. Transaksi', 'BOLEH KOSONG — nomor transaksi dibuat otomatis oleh sistem. Isi hanya kalau ingin memakai nomor struk asli (huruf, angka, tanda -).'],
            ['Satu transaksi, beberapa baris', 'Kalau satu pembayaran berisi beberapa lapangan / menu: isi Tanggal Bayar & kolom transaksi di baris pertama, lalu KOSONGKAN Tanggal Bayar di baris berikutnya — baris itu otomatis ikut transaksi di atasnya.'],
            ['Kolom transaksi', 'Tanggal Bayar, data customer, Diskon, Pajak, Biaya Layanan, Total Dibayar, Email Kasir cukup diisi di baris pertama transaksi.'],
            ['Kolom wajib', 'Kolom bertanda * wajib diisi.'],
            ['Tanggal Bayar', 'Format 2026-10-05 14:30 (tahun-bulan-tanggal jam:menit, WIB). Kalau hanya tanggal, dicatat pukul 12:00 WIB. Tidak boleh tanggal yang akan datang.'],
            ['Tanggal Main & Jam', 'Tanggal Main format 2026-10-05. Jam Mulai jam penuh (10:00, 15:00, ...). Durasi (Jam) = total jam main, contoh main 15:00–18:00 tulis Jam Mulai 15:00 dan Durasi 3 (cukup 1 baris).'],
            ['Harga Sewa', 'Total harga sewa booking itu untuk seluruh durasi (bukan per jam). Contoh 3 jam x Rp 350.000 → 1050000. Tarif per jam tiap lapangan ada di sheet "Lapangan & Menu".'],
            ['Lapangan / Menu', 'Tulis persis seperti di sheet "Lapangan & Menu" (huruf besar-kecil bebas). Sheet itu hanya daftar referensi dari sistem — tidak perlu diisi / diubah.'],
            ['Nominal', 'Angka rupiah tanpa titik juga boleh: 350000 atau 350.000 atau Rp 350.000.'],
            ['Pajak & lainnya', 'Diskon, Pajak, Biaya Layanan diisi sesuai struk asli; kosong = 0. Sistem mengecek: total harga - Diskon + Pajak + Biaya Layanan = Total Dibayar.'],
            ['Pembayaran', 'Semua transaksi dicatat sebagai pembayaran QRIS — tidak perlu kolom metode bayar / nomor referensi.'],
            ['Customer padel', 'No. HP dipakai untuk mencari akun customer — pakai No. HP asli customer, JANGAN salin nomor contoh. Kalau nomornya sudah dipakai akun lain dengan nama berbeda, halaman import memberi peringatan. Kalau belum ada, akun walk-in dibuat otomatis (password = 6 digit terakhir No. HP).'],
            ['Jadwal padel', 'Booking yang jamnya sudah lewat dicatat "Selesai". Booking yang jamnya belum lewat jadi booking aktif: slot terkunci di jadwal dan customer mendapat QR tiket check-in.'],
            ['Email Kasir', 'Email akun staf kasir. Kosong = tercatat atas nama admin yang meng-import.'],
            ['Upload ulang', 'Aman: transaksi yang sama persis (atau No. Transaksi yang sudah pernah di-import) otomatis dilewati.'],
            ['Sebelum import', 'Halaman import menampilkan pratinjau & semua error per baris dulu. Data baru masuk setelah tombol Import ditekan, dan hanya kalau tidak ada error sama sekali.'],
        ] as [$label, $desc]) {
            $writer->addRow(Row::fromValues([$label, $desc], $wrap));
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Contoh Booking Padel'], $bold));
        $writer->addRow(Row::fromValues(SalesImportService::PADEL_HEADERS, $header));
        $writer->addRow(Row::fromValues($this->padelRow(['No.' => 1, 'Tanggal Bayar*' => '2026-10-05 14:30', 'Nama Customer*' => 'Budi Santoso', 'No. HP*' => '081100000001', 'Lapangan*' => 'Court 1', 'Tanggal Main*' => '2026-10-05', 'Jam Mulai*' => '15:00', 'Durasi (Jam)*' => 3, 'Harga Sewa*' => 1050000, 'Total Dibayar*' => 1400000, 'Catatan' => 'Main 3 jam (15:00-18:00)'])));
        $writer->addRow(Row::fromValues($this->padelRow(['No.' => 2, 'Lapangan*' => 'Court 2', 'Tanggal Main*' => '2026-10-05', 'Jam Mulai*' => '15:00', 'Durasi (Jam)*' => 1, 'Harga Sewa*' => 350000, 'Catatan' => 'Tanggal Bayar kosong = ikut transaksi No. 1'])));
        $writer->addRow(Row::fromValues($this->padelRow(['No.' => 3, 'Tanggal Bayar*' => '2026-10-06 09:00', 'Nama Customer*' => 'Sinta', 'No. HP*' => '081100000002', 'Lapangan*' => 'Court 1', 'Tanggal Main*' => '2026-10-20', 'Jam Mulai*' => '19:00', 'Durasi (Jam)*' => 2, 'Harga Sewa*' => 900000, 'Diskon' => 100000, 'Total Dibayar*' => 800000, 'Catatan' => 'Transaksi baru, main nanti (jadi booking aktif)'])));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Contoh Penjualan F&B'], $bold));
        $writer->addRow(Row::fromValues(SalesImportService::FNB_HEADERS, $header));
        $writer->addRow(Row::fromValues($this->fnbRow(['No.' => 1, 'Tanggal Bayar*' => '2026-10-05 15:10', 'Tipe Pesanan*' => 'Dine-In', 'No. Meja' => '7', 'Nama Pelanggan' => 'Budi', 'Menu*' => 'Iced Spanish Latte', 'Qty*' => 2, 'Harga Satuan*' => 38000, 'Catatan Item' => 'Less sugar', 'Total Dibayar*' => 131000])));
        $writer->addRow(Row::fromValues($this->fnbRow(['No.' => 2, 'Menu*' => 'Smashed Avocado Sourdough', 'Qty*' => 1, 'Harga Satuan*' => 55000])));

        // ---- Sheet data ----
        foreach ([SalesImportService::SHEET_PADEL => SalesImportService::PADEL_HEADERS, SalesImportService::SHEET_FNB => SalesImportService::FNB_HEADERS] as $name => $headers) {
            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($name);
            $sheet->setSheetView((new SheetView())->setFreezeRow(2));
            foreach ($headers as $i => $title) {
                $sheet->setColumnWidth((float) (self::WIDTHS[$title] ?? 14), $i + 1);
            }
            $writer->addRow(Row::fromValues($headers, $header));

            $text = (new Style())->setFormat('@');
            $muted = (new Style())->setFontColor('9A8A84');
            for ($no = 1; $no <= self::DATA_ROWS; $no++) {
                $writer->addRow(new Row(array_map(fn (string $title) => match (true) {
                    $title === 'No.' => Cell::fromValue($no, $muted),
                    in_array($title, self::TEXT_COLUMNS, true) => Cell::fromValue('', $text),
                    default => Cell::fromValue(''),
                }, $headers)));
            }
        }

        // ---- Lapangan & Menu (referensi) ----
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName(self::SHEET_REFERENCE);
        foreach ([24, 11, 18, 22, 3, 34, 16] as $i => $width) {
            $sheet->setColumnWidth($width, $i + 1);
        }
        $writer->addRow(Row::fromValues(['Daftar dari sistem (referensi, tidak perlu diisi). Tulis nama lapangan / menu persis seperti di bawah. Harga = tarif saat ini, harga di Excel tetap mengikuti struk asli.'], $bold));
        $writer->addRow(Row::fromValues(['Nama Lapangan', 'Status', 'Tarif Reguler / Jam', 'Tarif Prime Time / Jam', '', 'Nama Menu F&B', 'Harga Menu'], $header));
        $courts = PadelCourt::orderBy('name')->get(['name', 'is_active', 'hourly_rate_regular', 'hourly_rate_prime'])->values();
        $menus = FnbMenu::orderBy('name')->get(['name', 'base_price'])->values();
        for ($i = 0; $i < max($courts->count(), $menus->count()); $i++) {
            $court = $courts[$i] ?? null;
            $menu = $menus[$i] ?? null;
            $writer->addRow(Row::fromValues([
                $court?->name ?? '',
                $court ? ($court->is_active ? 'Aktif' : 'Nonaktif') : '',
                $court ? (int) $court->hourly_rate_regular : '',
                $court ? (int) $court->hourly_rate_prime : '',
                '',
                $menu?->name ?? '',
                $menu ? (int) $menu->base_price : '',
            ]));
        }

        $writer->close();

        return $path;
    }

    private function padelRow(array $values): array
    {
        return array_map(fn (string $title) => $values[$title] ?? '', SalesImportService::PADEL_HEADERS);
    }

    private function fnbRow(array $values): array
    {
        return array_map(fn (string $title) => $values[$title] ?? '', SalesImportService::FNB_HEADERS);
    }
}
