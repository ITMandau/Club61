<?php

namespace App\Services\Fnb;

use App\Models\Fnb\FnbCashCategory;
use App\Models\Fnb\FnbCashEntry;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Finance\LedgerReport;
use App\Services\Media\SecureImageUploader;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Modul 29 — Pengeluaran F&B (belanja bahan, gas, gaji, dst.). Pemasukan F&B hanya dari penjualan kasir (Buku
 * Transaksi), tidak dicatat manual. Catatan tidak diubah / dihapus — dibatalkan dengan alasan. Foto nota disimpan di
 * disk privat dan hanya bisa diunduh dari halaman Pengeluaran F&B (butuh izin).
 */
class FnbCashbookService
{
    public const ATTACHMENT_DISK = 'local';

    public function record(string $categoryId, string $date, float $amount, ?string $notes, TemporaryUploadedFile|UploadedFile|null $attachment, User $by): FnbCashEntry
    {
        $category = FnbCashCategory::query()->whereKey($categoryId)->where('is_active', true)->first();
        abort_unless($category, 422, 'Kategori tidak ditemukan atau sudah nonaktif.');
        abort_if($category->system_key === FnbCashCategory::RESTOCK_KEY, 422, 'Pembelian Persediaan dicatat otomatis melalui Stok F&B → Penerimaan Barang.');
        abort_if($amount <= 0, 422, 'Nominal harus lebih dari 0.');
        // Tanggal catatan = tanggal WIB (aplikasi berjalan di UTC; pukul 00:00–06:59 WIB tanggal UTC masih kemarin).
        $entryDate = Carbon::parse($date, LedgerReport::TIMEZONE)->startOfDay();
        abort_if($entryDate->isAfter(now(LedgerReport::TIMEZONE)->endOfDay()), 422, 'Tanggal tidak boleh di masa depan.');

        $path = $attachment ? SecureImageUploader::store($attachment, 'fnb/nota', self::ATTACHMENT_DISK) : null;

        try {
            return DB::transaction(function () use ($category, $entryDate, $amount, $notes, $path, $by) {
                $entry = FnbCashEntry::create([
                    'entry_date' => $entryDate->toDateString(),
                    'category_id' => $category->id,
                    'amount' => round($amount, 2),
                    'notes' => trim(mb_substr((string) $notes, 0, 500)) ?: null,
                    'attachment_path' => $path,
                    'created_by' => $by->id,
                ]);

                ActivityLogger::record('FNB', 'fnb_cash.recorded',
                    "Mencatat pengeluaran F&B {$category->name} ".ActivityLogger::rupiah($amount),
                    subject: $entry,
                    meta: ['tanggal' => $entryDate->format('d/m/Y'), 'kategori' => $category->name, 'nominal' => ActivityLogger::rupiah($amount), 'catatan' => $entry->notes],
                    subjectLabel: $category->name.' '.$entryDate->format('d/m/Y'),
                );

                return $entry;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk(self::ATTACHMENT_DISK)->delete($path);
            }

            throw $e;
        }
    }

    public function void(FnbCashEntry $entry, string $reason, User $by): void
    {
        $reason = trim(mb_substr($reason, 0, 255));
        abort_if(mb_strlen($reason) < 5, 422, 'Alasan pembatalan wajib diisi (minimal 5 karakter).');

        DB::transaction(function () use ($entry, $reason, $by) {
            $entry = FnbCashEntry::query()->lockForUpdate()->with('category')->findOrFail($entry->id);
            abort_if($entry->voided_at !== null, 422, 'Catatan ini sudah dibatalkan.');
            abort_if($entry->receipt_id !== null, 422, 'Pembelian Persediaan dibatalkan melalui Stok F&B → Penerimaan Barang → Batalkan Penerimaan, agar stoknya ikut dikembalikan.');

            $entry->update(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $reason]);

            ActivityLogger::record('FNB', 'fnb_cash.voided',
                'Membatalkan catatan Pengeluaran F&B '.($entry->category?->name ?? '-').' '.ActivityLogger::rupiah((float) $entry->amount),
                subject: $entry,
                meta: ['tanggal' => $entry->entry_date?->format('d/m/Y'), 'alasan' => $reason],
                severity: ActivityLogger::WARNING,
                subjectLabel: ($entry->category?->name ?? '-').' '.$entry->entry_date?->format('d/m/Y'),
            );
        });
    }

    /** Tambah / ubah kategori. Kategori sistem (Pembelian Persediaan) hanya boleh diganti namanya. */
    public function saveCategory(?FnbCashCategory $category, string $name, bool $isStockPurchase): FnbCashCategory
    {
        $name = trim(mb_substr($name, 0, 60));
        abort_if($name === '', 422, 'Nama kategori wajib diisi.');
        abort_if(FnbCashCategory::where('name', $name)->when($category, fn ($q) => $q->whereKeyNot($category->id))->exists(), 422, 'Nama kategori sudah dipakai.');

        if ($category?->isSystem()) {
            $category->update(['name' => $name]);

            return $category;
        }

        $data = ['name' => $name, 'is_stock_purchase' => $isStockPurchase];
        if ($category) {
            // Laporan membaca jenis kategori saat ini — mengubahnya akan menggeser Laba periode yang sudah dilaporkan.
            abort_if($category->is_stock_purchase !== $isStockPurchase && $category->entries()->exists(), 422,
                'Jenis kategori tidak dapat diubah karena sudah memiliki catatan. Buat kategori baru lalu nonaktifkan kategori ini.');
            $category->update($data);

            return $category;
        }

        return FnbCashCategory::create($data + ['is_active' => true, 'sort_order' => (int) FnbCashCategory::max('sort_order') + 1]);
    }

    public function toggleCategory(FnbCashCategory $category): void
    {
        abort_if($category->isSystem(), 422, 'Kategori Pembelian Persediaan digunakan otomatis oleh Penerimaan Barang dan tidak dapat dinonaktifkan.');
        $category->update(['is_active' => ! $category->is_active]);
    }
}
