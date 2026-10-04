# Product Requirements Document (PRD)
## Modul 17: Buku Transaksi Terpadu & Laporan Keuangan per Sumber — Panel Admin
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-17-BUKU-TRANSAKSI-TERPADU` |
| **Versi** | `v1.0.0-DRAFT` |
| **Status** | **Fase 1 (fondasi data) & Fase 2 (Buku Transaksi + Antrian Refund) selesai 4 Okt 2026; Fase 3 (dashboard) belum.** Keputusan di §10 memakai usulan default sampai PM menjawab. |
| **Sumber Requirement** | Pemilik produk: "Laporan transaksi yang dipecah per POS — walk-in, booking online, add-ons, F&B, dan lainnya — yang bisa dicek detailnya. Seperti buku transaksi terpadu + bisa lihat invoice dari admin, lengkap dengan metode pembayarannya. Angka yang diambil adalah harga tanpa pajak." |
| **Dependensi Teknis** | `PRD_MODUL_10_UNIFIED_PAYMENT_GATEWAY.md` (`PaymentOrchestratorService::markOrderAsPaid` sebagai satu-satunya pintu pelunasan), `PRD_MODUL_16_ACTIVITY_AUDIT_LOG.md` (jejak audit & export), `PRD_MODUL_09_DYNAMIC_RBAC_FILAMENT_SHIELD.md` (izin baru). Tidak ada package baru yang wajib. |
| **Target Pengguna** | **Owner / Super Admin & Finance** (default). Kasir **tidak** melihat modul ini — kasir sudah punya Riwayat Transaksi di POS masing-masing. |
| **Prinsip Utama** | **PENDAPATAN = PENJUALAN BERSIH SEBELUM PAJAK**, **PAJAK BUKAN PENDAPATAN**, **DICATAT SEKALI SAAT UANG MASUK & TIDAK PERNAH DIUBAH**, **SETIAP BARIS BISA DITELUSURI SAMPAI STRUK & BUKTI BAYARNYA**, **JUMLAH BUKU = JUMLAH UANG YANG BENAR-BENAR MASUK**. |

---

## 1. Latar Belakang & Masalah

Uang Club 61 masuk dari banyak pintu, tapi tidak ada satu tempat untuk melihat semuanya:

| Sumber uang | Sudah tercatat di | Kekurangan untuk laporan |
| :--- | :--- | :--- |
| POS Walk-In Padel | `orders` (`WALK_IN`) + `payments` | Sewa alat (raket/bola/handuk) tercatat sebagai `item_type = PADEL`, **tidak bisa dipisah dari sewa lapangan** |
| Booking online Padel | `orders` (`ONLINE_BOOKING`) + `payments` (Midtrans) | Sama: add-on tercampur dengan lapangan |
| Pelunasan selisih reschedule | `payments` dengan `payload_log.type = RESCHEDULE_PRICE_DELTA` | Tidak punya `order_items`; rinciannya hanya di `payload_log` (`court_delta`, `tax_delta`, `admin_fee_delta`) |
| POS Membership & membership online | `orders` (`MEMBERSHIP`) + `payments` | — |
| POS F&B | `orders` (`DINE_IN`) + `payments` | **Tidak dihitung sama sekali** di halaman Analytics & Keuangan |
| Wellness, Gym, Merchandise, Salon | Baru ada model, belum ada POS / order | Belum menghasilkan transaksi |
| Refund | `refunds` | Refund PENDING (kelebihan bayar, uang telat masuk) **tidak punya layar untuk diproses** |

Masalah utama yang membuat laporan tidak bisa langsung diambil dari tabel `orders`:

1. **Pajak, diskon, dan biaya layanan hanya tercatat per order**, bukan per item. Order berisi lapangan + raket + diskon voucher tidak bisa langsung dipecah "berapa pendapatan lapangan, berapa pendapatan raket".
2. **Total order berubah setelah transaksi** (reschedule menaikkan `grand_total`, pembatalan menurunkannya). Kalau laporan dihitung ulang dari order, angka hari lalu bisa berubah diam-diam.
3. **Halaman Analytics & Keuangan sekarang** hanya menghitung padel & membership, okupansi memakai rumus tetap, dan tidak ada rentang tanggal / export.

### Yang TIDAK Sedang Dibangun (v1)

- **Bukan software akuntansi** (jurnal debit-kredit, neraca, laba-rugi, aset). Buku ini adalah sumber data yang nanti bisa diekspor ke akuntansi.
- **Bukan pengganti rekap shift kasir.** Tutup shift tetap memakai `PosCashierShift::settlementBreakdown()`; Buku Transaksi menampilkan lintas shift & lintas POS.
- **Belum menghitung potongan MDR Midtrans** (QRIS ±0,7%, VA ±Rp4.000) — fase berikutnya (§10 pertanyaan 3).
- **Tidak membangun POS Wellness/Gym/Merch/Salon.** Modul itu nanti cukup menulis ke buku yang sama.

---

## 2. Definisi Angka (Wajib Seragam di Seluruh Laporan)

Contoh satu transaksi walk-in:

| Komponen | Nominal | Arti | Masuk pendapatan? |
| :--- | ---: | :--- | :--- |
| Sewa lapangan | 300.000 | Harga item | |
| Sewa raket (add-on) | 50.000 | Harga item | |
| **Penjualan kotor** | **350.000** | Jumlah harga item sebelum potongan | |
| Diskon (voucher order) | −35.000 | Potongan yang mengurangi tagihan | |
| **Penjualan bersih** | **315.000** | **Angka pendapatan utama** | **Ya** |
| Biaya layanan 3% | 9.450 | Biaya admin / service charge | Ya, ditampilkan **terpisah** |
| Pajak PB1 10% | 31.500 | Titipan pajak daerah, disetor ke Pemda | **Tidak** — dilaporkan sebagai pajak terkumpul |
| **Total dibayar customer** | **355.950** | Uang yang benar-benar diterima | Dipakai untuk mencocokkan kasir & mutasi bank |

Istilah lain:

- **Benefit (non-tunai)** — nilai yang ditanggung kuota/diskon member atau voucher sponsor (`member_discount_court`, `sponsor_discount_court`). Sudah dipotong dari harga sebelum masuk order, jadi **bukan bagian penjualan**. Ditampilkan sebagai informasi ("nilai yang dipakai dari kuota/voucher") supaya owner tahu besarnya subsidi.
- **Refund** — uang yang dikembalikan. Mengurangi buku **pada tanggal refund diproses**, bukan tanggal transaksi aslinya.
- **Hangus (forfeit)** — selisih reschedule ke jadwal lebih murah yang tidak dikembalikan (`reschedule_forfeited_amount`). Uangnya **sudah tercatat** saat pembayaran awal, jadi **tidak menambah buku lagi**; ditampilkan sebagai informasi.
- **Kelebihan bayar** — uang masuk saat order sudah lunas (bayar dobel). Tetap dicatat sebagai uang masuk, dan otomatis berpasangan dengan refund PENDING.

### 2.1 Aturan Pengakuan

- **Tanggal transaksi = tanggal & jam uang diterima** (pembayaran berubah jadi `SUCCESS`), zona waktu **Asia/Jakarta**. Booking untuk bulan depan yang dibayar hari ini masuk laporan hari ini. (Usulan default, lihat §10 pertanyaan 1.)
- **Yang dihitung:** hanya pembayaran `SUCCESS` dan refund yang sudah `PROCESSED`.
- **Yang tidak dihitung:** order belum dibayar, keranjang ditinggal, booking expired tanpa pembayaran, tagihan selisih yang belum lunas, transaksi `MOCK` / simulasi.

---

## 3. Solusi Arsitektur

### 3.1 Tabel Buku Besar `ledger_entries` — Ditulis Sekali, Tidak Pernah Diubah

Setiap kali uang masuk atau keluar, sistem menulis **satu atau beberapa baris buku** yang sudah dipecah per kategori pendapatan. Laporan cukup menjumlahkan baris; tidak menghitung ulang dari `orders`.

Kenapa tabel sendiri (bukan query langsung ke `orders` / `payments`):

| Query langsung ke order | Tabel buku |
| :--- | :--- |
| Angka berubah kalau order diubah setelah lunas (reschedule, batal) | Angka terkunci saat uang masuk |
| Pajak & diskon harus dibagi ulang setiap kali laporan dibuka | Dibagi sekali, tersimpan per baris |
| Selisih reschedule perlu dibaca dari JSON `payload_log` | Sudah jadi baris biasa |
| Lambat di data besar (join banyak tabel) | Satu tabel, indeks sederhana |

### 3.2 Dua Pintu Tulis Saja

Mengikuti pola Modul 10 & 16 (satu pintu, satu sumber kebenaran):

1. **Uang masuk** → `PaymentOrchestratorService::markOrderAsPaid()` memanggil `LedgerWriter::recordPayment($order, $payment)`, termasuk jalur kelebihan bayar dan uang masuk untuk tagihan yang sudah ditutup.
2. **Uang keluar** → saat refund berstatus `PROCESSED`, `LedgerWriter::recordRefund($refund)`.

Penulisan berada **di transaksi database yang sama** dengan pelunasan/refund. Kalau pelunasan gagal & di-rollback, baris buku ikut hilang; tidak ada buku tanpa uang atau uang tanpa buku.

### 3.3 Pembagian per Kategori

**Kategori pendapatan (`category`):**

| Kode | Label | Sumber data |
| :--- | :--- | :--- |
| `SEWA_LAPANGAN` | Sewa Lapangan Padel | Item lapangan; bagian lapangan dari selisih reschedule (`court_delta`) |
| `ADDON_PADEL` | Add-on Padel (raket, bola, handuk, pelatih) | Item sewa alat — **perlu perbaikan data, §4.2** |
| `MEMBERSHIP` | Membership | Item `MEMBERSHIP` |
| `FNB` | F&B | Item `FNB` (termasuk modifier) |
| `WELLNESS` / `GYM` / `MERCH` / `SALON` | (nanti) | Saat modulnya punya POS |

**Sumber transaksi (`source`):**

| Kode | Label |
| :--- | :--- |
| `POS_WALKIN_PADEL` | POS Walk-In Padel |
| `ONLINE_PADEL` | Booking Online Padel |
| `RESCHEDULE_DELTA_POS` / `RESCHEDULE_DELTA_ONLINE` | Pelunasan selisih reschedule (di kasir / via Midtrans) |
| `POS_MEMBERSHIP` / `ONLINE_MEMBERSHIP` | Membership di kasir / online |
| `POS_FNB` | POS F&B |

**Jenis baris (`entry_type`):** `PAYMENT`, `OVERPAYMENT` (uang masuk saat sudah lunas / tagihan sudah ditutup), `REFUND` (nilai negatif).

### 3.4 Aturan Pembagian Pajak, Diskon & Biaya Layanan

Untuk satu pembayaran:

1. Hitung **penjualan kotor per kategori** dari `order_items.subtotal` (+ modifier F&B).
2. **Diskon order** (`orders.discount_amount`) dibagi proporsional terhadap penjualan kotor tiap kategori.
3. **Pajak** dan **biaya layanan** dibagi proporsional terhadap penjualan bersih tiap kategori.
4. Kalau order dibayar beberapa kali (mis. checkout awal + pelunasan selisih), **setiap pembayaran dibagi berdasarkan isi yang dibayarnya sendiri**:
   - Pembayaran awal → komposisi order saat itu.
   - Pelunasan selisih reschedule → langsung dari `payload_log` (`court_delta` → `SEWA_LAPANGAN`, `tax_delta`, `admin_fee_delta`).
5. **Pembulatan:** semua nominal rupiah bulat. Sisa pembulatan diberikan ke baris dengan penjualan bersih terbesar.
6. **Invarian wajib:** `SUM(total_amount)` semua baris satu pembayaran **= `payments.amount` persis**. Kalau tidak sama, penulisan dibatalkan dan error dilaporkan (bukan diam-diam dicatat salah).

Refund dibagi dengan proporsi yang sama dengan baris pembayaran yang direfund, sehingga refund raket mengurangi kategori add-on, bukan lapangan.

---

## 4. Skema Database

### 4.1 Tabel Baru `ledger_entries`

| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | ULID | |
| `occurred_at` | datetime, index | Saat uang masuk / refund diproses (waktu Jakarta) |
| `entry_type` | string(20) | `PAYMENT` / `OVERPAYMENT` / `REFUND` |
| `source` | string(40), index | §3.3 |
| `category` | string(30), index | §3.3 |
| `order_id` | ULID, index | |
| `payment_id` | ULID nullable, index | |
| `refund_id` | ULID nullable | |
| `order_number` | string(35) | Snapshot — tetap terbaca walau order dihapus |
| `customer_id` / `customer_name` | ULID nullable / string | Snapshot |
| `cashier_id` / `cashier_name` | ULID nullable / string | Snapshot; kosong untuk pembayaran online |
| `pos_shift_id` | ULID nullable, index | |
| `payment_gateway` / `payment_method` / `payment_method_label` | string | Contoh: `CASHIER_POS` / `EDC_MANDIRI` / "EDC Mandiri Debit •••1234" |
| `payment_reference` | string nullable | RRN / approval code / order_id Midtrans |
| `gross_amount` | decimal(14,2) | Penjualan kotor |
| `discount_amount` | decimal(14,2) | |
| `net_amount` | decimal(14,2) | **Penjualan bersih (pendapatan)** |
| `service_amount` | decimal(14,2) | Biaya layanan |
| `tax_amount` | decimal(14,2) | Pajak terkumpul |
| `total_amount` | decimal(14,2) | Uang diterima (negatif untuk refund) |
| `benefit_amount` | decimal(14,2) | Nilai kuota/voucher yang dipakai (informasi, non-tunai) |
| `meta` | json nullable | Kode booking, nama item, jadwal, dll. |
| `created_at` | timestamp | Tanpa `updated_at` — baris tidak pernah diubah |

Indeks gabungan: `(occurred_at, source)`, `(occurred_at, category)`, `(occurred_at, payment_method)`.

Model `LedgerEntry` dibuat **immutable** (melempar exception saat `updating` / `deleting`), sama seperti `ActivityLog`.

### 4.2 Perbaikan Data yang Sudah Ada

1. **Pisahkan add-on dari lapangan.** Item sewa alat saat checkout online & walk-in (`ManagesCheckoutAndPayments`, sekitar baris 213–232 dan 956–975) ditandai `item_type = 'EQUIPMENT'`. Data lama dibetulkan lewat migration: item `PADEL` yang `reference_id`-nya ada di `court_equipments` diubah jadi `EQUIPMENT`. `PadelFulfillmentHandler` harus tetap berjalan (aktivasi booking membaca `order.padelBookings`, bukan `item_type`). Wajib diuji ulang.
2. **Kolom `payments.paid_at`** (datetime, nullable, index) — diisi saat status jadi `SUCCESS`. Riwayat POS sekarang memakai `updated_at` sebagai pendekatan; `paid_at` menggantikannya. Data lama: diisi dari `updated_at`.
3. **Snapshot komposisi order** di `payments.payload_log.ledger_basis` saat pembayaran awal, supaya backfill & audit tahu komposisi yang dibayar walau order berubah setelahnya.

---

## 5. Kebutuhan Fungsional

### FR-01: Pencatatan Otomatis ke Buku

- Setiap pembayaran `SUCCESS` (semua jalur: POS walk-in, POS membership, POS F&B, webhook Midtrans, rekonsiliasi Midtrans, pelunasan kasir) menghasilkan baris buku per kategori.
- Setiap refund `PROCESSED` menghasilkan baris negatif.
- **Idempoten:** pembayaran yang sama tidak boleh tercatat dua kali (unique key `payment_id + category + entry_type`).
- Transaksi bernilai 0 yang ditutup penuh oleh kuota/voucher tetap dicatat (net 0, `benefit_amount` terisi) supaya pemakaian benefit terlihat.

### FR-02: Halaman "Buku Transaksi" (Filament Page)

**Kartu ringkasan** untuk rentang tanggal terpilih:

- Penjualan bersih
- Biaya layanan
- Pajak terkumpul
- Refund
- **Total uang masuk (bersih setelah refund)**
- Informasi: nilai benefit kuota/voucher, nilai hangus, refund menunggu diproses

**Tabel transaksi**, satu baris per pembayaran (baris kategori digabung per pembayaran):

| Kolom | Contoh |
| :--- | :--- |
| Tanggal & jam bayar | 01 Okt 2026 18:42 |
| No. order | ORD-PAD-8K2L... |
| Sumber | POS Walk-In Padel |
| Kategori | Sewa Lapangan + Add-on |
| Customer | Budi |
| Kasir / shift | Rina · SFT-PADEL-0012 |
| Metode bayar | QRIS BCA · RRN 1234... |
| Penjualan bersih | 315.000 |
| Pajak | 31.500 |
| Biaya layanan | 9.450 |
| Total dibayar | 355.950 |
| Status | Lunas / Direfund sebagian / Direfund / Kelebihan bayar |

**Filter:** rentang tanggal (preset: hari ini, kemarin, 7 hari, bulan ini, bulan lalu), sumber, kategori, metode bayar, kasir, shift, status, pencarian (no. order / kode booking / nama / HP).

### FR-03: Rincian per Kategori, Sumber & Metode

Tiga tabel ringkas di atas atau samping tabel utama:

- **Per kategori:** Sewa lapangan / Add-on / F&B / Membership — penjualan bersih, pajak, jumlah transaksi.
- **Per sumber/POS:** POS Walk-In / Online / Pelunasan selisih / Membership / F&B.
- **Per metode bayar:** QRIS (per penyedia), EDC (per bank & debit/kredit), VA (per bank), Transfer — untuk mencocokkan mutasi bank & settlement EDC.

### FR-04: Detail Transaksi & Lihat Invoice

Klik baris → panel samping (slide-over) berisi:

- Rincian item & pembagian per kategori (kotor, diskon, bersih, pajak, biaya layanan).
- Semua pembayaran & refund order tersebut berurutan.
- Bukti bayar: RRN / approval code / trace / nomor VA / order_id Midtrans.
- Booking terkait (lapangan, jadwal, status, riwayat reschedule & hangus).
- Tautan ke Log Aktivitas untuk order tersebut.
- **Tombol "Lihat Invoice / Cetak Struk"**, memakai tampilan yang sudah ada:

| Sumber | Tampilan yang dipakai |
| :--- | :--- |
| POS Walk-In Padel & pelunasan selisih | `filament.partials.walkin-receipt` (`BookOfflineCourt::buildWalkInReceipt`) |
| POS Membership | Struk membership (`JualMembership::buildMembershipReceipt`) |
| POS F&B | Struk F&B terminal kasir |
| Booking online padel | Invoice customer (`customer/invoice-partials`) dalam mode admin read-only |
| Membership online | Invoice membership customer, mode admin read-only |

Struk yang dibuka dari sini ditandai **"SALINAN ADMIN"** (bukan "CETAK ULANG" kasir) dan aksinya tercatat di Log Aktivitas.

### FR-05: Export

- Export **Excel (XLSX) & CSV** sesuai filter aktif. Dua lembar: *Transaksi* (per pembayaran) dan *Rincian Kategori* (per baris buku).
- Di-stream lewat route biasa (bukan aksi Livewire) dengan batas baris, sama seperti export Log Aktivitas.
- Perlindungan CSV injection & pencatatan export di Log Aktivitas.

### FR-06: Antrian Refund (Prasyarat)

> **Lanjutan (4 Okt 2026):** kebijakan refund dua langkah (Kelola Pemesanan hanya mengajukan, uang keluar hanya dari Antrian Refund), aturan refund customer, booking hangus, dan pencatatan pembayaran bermasalah dibahas di [`PRD_MODUL_21_REFUND_NO_SHOW_PEMBAYARAN_BERMASALAH.md`](PRD_MODUL_21_REFUND_NO_SHOW_PEMBAYARAN_BERMASALAH.md) — menunggu keputusan PM.

Refund `PENDING` (kelebihan bayar, uang telat masuk untuk tagihan yang sudah ditutup) saat ini **tidak bisa diproses dari mana pun**. Dibutuhkan layar kecil:

- Daftar refund PENDING beserta alasan & order.
- Aksi **Proses** (isi metode pengembalian + nomor referensi transfer / void EDC) → `PROCESSED`, menulis baris buku negatif.
- Aksi **Tolak** dengan alasan wajib → `REJECTED`.
- Izin terpisah, dan semua aksi masuk Log Aktivitas dengan severity KRITIS.

### FR-07: Otorisasi

| Izin | Fungsi | Default |
| :--- | :--- | :--- |
| `View:BukuTransaksi` | Membuka halaman | super_admin |
| `export_ledger` | Export Excel/CSV | super_admin |
| `view_ledger_invoice` | Membuka invoice/struk dari Buku Transaksi | super_admin |
| `process_refund_queue` | Memproses / menolak refund PENDING | super_admin |

Semua izin masuk `Club61PermissionMatrix` & daftar backdoor (tidak otomatis ke role admin). Pengecekan wajib di server, bukan hanya menyembunyikan tombol.

### FR-08: Backfill & Pemeriksaan Konsistensi

- Command `ledger:backfill {--from=} {--dry-run}` mengisi buku dari pembayaran & refund lama, memakai aturan §3.4. Aman dijalankan ulang (idempoten).
- Command terjadwal harian `ledger:verify`: membandingkan jumlah `payments.amount` SUCCESS dengan jumlah `ledger_entries.total_amount` per hari. Selisih → Log Aktivitas KRITIS.

### FR-09: Halaman Analytics & Keuangan Membaca dari Buku

Kartu pendapatan di Analytics memakai `ledger_entries`, sehingga F&B ikut terhitung dan angkanya sama dengan Buku Transaksi.

---

## 6. Kebutuhan Non-Fungsional

| Aspek | Kebutuhan |
| :--- | :--- |
| Akurasi | Invarian §3.4 butir 6 wajib. Total Buku Transaksi per hari = total pembayaran SUCCESS − refund PROCESSED per hari. |
| Kekekalan | Baris buku tidak bisa diubah/dihapus dari aplikasi. Koreksi = baris baru (refund / penyesuaian), bukan edit. |
| Performa | Halaman terbuka < 2 detik untuk rentang 1 bulan dengan ±50.000 baris; ringkasan memakai agregasi SQL, bukan perulangan PHP. |
| Zona waktu | Semua filter & tampilan Asia/Jakarta, konsisten dengan `config('app.timezone')`. |
| Keamanan data | Export hanya berisi nama & nomor order; nomor HP dan email customer tidak ikut kecuali diminta (lihat §10 pertanyaan 5). |
| Ketahanan | Kalau penulisan buku gagal, pelunasan ikut gagal (satu transaksi). Lebih baik transaksi ditolak daripada uang masuk tanpa tercatat. |

---

## 7. Rencana Implementasi Bertahap

| Fase | Isi | Perkiraan |
| :--- | :--- | :--- |
| **1 — Fondasi data** | Pisahkan item add-on (`EQUIPMENT`) + migrasi data lama; kolom `payments.paid_at`; tabel & model `ledger_entries`; `LedgerWriter` dipasang di `markOrderAsPaid` & refund; backfill + verify; test invarian | 2–3 hari |
| **2 — Buku Transaksi** | Halaman tabel + kartu ringkasan + filter + detail slide-over + lihat invoice/struk + export; Antrian Refund | 3–4 hari |
| **3 — Dashboard** | Rincian per kategori/sumber/metode, grafik tren harian, Analytics membaca dari buku | 2 hari |
| **4 — Lanjutan** | Potongan MDR Midtrans (dari settlement report), rekonsiliasi mutasi bank, ekspor format akuntansi | Menyusul |

---

## 8. Rencana Pengujian

Minimal:

1. Walk-in lapangan + raket + diskon voucher → 2 baris buku (`SEWA_LAPANGAN`, `ADDON_PADEL`), pajak & diskon terbagi proporsional, total = `payments.amount`.
2. Booking online lunas via webhook & via rekonsiliasi → tercatat sekali saja (idempoten).
3. Pelunasan selisih reschedule (kasir & Midtrans) → baris `SEWA_LAPANGAN` sesuai `court_delta`, pajak `tax_delta`, sumber `RESCHEDULE_DELTA_*`.
4. Reschedule ke jadwal lebih murah (hangus) → tidak menambah baris buku; nilai hangus tampil sebagai informasi.
5. Order 2 booking, salah satu direfund → refund negatif hanya di bagian booking itu.
6. Kelebihan bayar → baris `OVERPAYMENT` + refund PENDING; setelah diproses → baris `REFUND`; total bersih kembali benar.
7. Booking 100% kuota member → baris net 0 dengan `benefit_amount` terisi.
8. Membership di kasir & online, F&B dine-in → kategori & sumber benar.
9. Order diubah setelah lunas (reschedule naik) → baris buku pembayaran awal **tidak berubah**.
10. Pembulatan: kombinasi nominal ganjil tetap menghasilkan total = `payments.amount` persis.
11. Filter tanggal 17:00–24:00 WIB masuk ke hari yang benar.
12. Otorisasi: tanpa izin → 403 untuk halaman, export, invoice, dan proses refund.
13. Backfill dua kali → tidak ada baris ganda; `ledger:verify` mendeteksi selisih buatan.
14. Item add-on lama terkonversi ke `EQUIPMENT` dan aktivasi booking (`PadelFulfillmentHandler`) tetap berjalan.

---

## 9. Ruang Lingkup & Batasan (Out of Scope v1)

- Potongan MDR / biaya gateway, settlement bank, dan rekonsiliasi mutasi rekening.
- Jurnal akuntansi double-entry, laba-rugi, neraca, HPP F&B.
- Laporan pajak resmi (format e-Filing). Buku menyediakan angka pajak terkumpul per periode sebagai bahan.
- POS Wellness, Gym, Merchandise, Salon (cukup siapkan kategorinya).
- Multi-cabang (Modul 13) — kolom `branch_id` ditambahkan saat modul itu jalan.

---

## 10. Pertanyaan Terbuka (Butuh Keputusan PM)

| # | Pertanyaan | Usulan default |
| :--- | :--- | :--- |
| 1 | Laporan dihitung berdasarkan **tanggal bayar** atau **tanggal main**? | **Tanggal bayar** — cocok dengan kasir, shift, dan mutasi bank. Tanggal main tersedia sebagai filter tambahan di detail booking. |
| 2 | Biaya layanan masuk pendapatan? | **Ya, tapi ditampilkan sebagai kolom terpisah** dari penjualan bersih. |
| 3 | Potongan MDR Midtrans dihitung sekarang? | **Fase 4.** Butuh data settlement dari Midtrans. |
| 4 | Siapa yang boleh membuka Buku Transaksi selain owner? | Hanya super_admin; role Finance bisa dibuat lewat Roles & Hak Akses. |
| 5 | Nomor HP / email customer ikut di export? | **Tidak** (data pribadi). Cukup nama & nomor order. |
| 6 | Bola (consumable) dihitung "add-on" atau kategori "penjualan barang" sendiri? | Ikut **Add-on Padel** dulu; bisa dipisah saat Merchandise jalan. |
| 7 | Refund PENDING diproses siapa & butuh persetujuan dua orang? | Satu orang dengan izin `process_refund_queue`; dua tingkat persetujuan menyusul kalau diminta. |
| 8 | Benefit kuota/voucher sponsor perlu ditagihkan ke perusahaan sponsor (laporan per sponsor)? | Di luar v1; kolom `benefit_amount` + meta sponsor sudah cukup untuk laporan itu nanti. |

---

## 11. Dampak ke Modul Lain

- **Modul 10 (Payment):** `markOrderAsPaid` & pembuatan refund menulis ke buku; test pembayaran yang ada harus tetap lolos.
- **Modul 11 & 15 (POS Walk-In, F&B):** item add-on berganti `item_type`; struk & riwayat POS tidak berubah.
- **Modul 16 (Log Aktivitas):** event baru `ledger.exported`, `ledger.invoice_viewed`, `refund.processed`, `refund.rejected`, `ledger.verify_mismatch`.
- **Modul 18 (Pengaturan Invoice):** tampilan "Lihat Invoice" memakai header/alamat dari pengaturan Modul 18 setelah modul itu jadi.
- **Analytics & Keuangan:** sumber angka pindah ke `ledger_entries` (Fase 3).
