# Product Requirements Document (PRD)
## Modul 29 — Mini Inventory & Keuangan F&B
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-29-MINI-INVENTORY-KEUANGAN-FNB` |
| **Versi** | `v0.6.0` |
| **Status** | **DIBANGUN (Tahap 1–3), belum di-commit** — untuk dilihat alurnya oleh owner aplikasi. Detail implementasi di §12; revisi Penerimaan Barang, impor Excel & istilah formal di **§13 (menggantikan istilah "Stok Masuk" / "Belanja Stok" di bagian sebelumnya)**. |
| **Sumber** | Permintaan PM (29 Okt 2026): mini inventory semua menu F&B, restock sebagai pengeluaran, keuntungan penjualan, dan dashboard. Dirinci bersama owner aplikasi di hari yang sama. |
| **Dependensi** | Menu F&B (Modul 15), POS F&B & Shift Kasir (Modul 25), Buku Transaksi (Modul 17), Role & Permission (Modul 9), Log Aktivitas (Modul 16). |

---

## 1. Ringkasan

Club 61 memerlukan pencatatan stok dan keuangan F&B yang **simpel tetapi lengkap**:
- jumlah stok per menu;
- modal (HPP) per menu;
- barang masuk (restock) yang otomatis tercatat sebagai pengeluaran;
- pengeluaran dan pemasukan lain;
- laporan laba serta arus kas F&B.

Tidak ada pengelolaan bahan baku atau resep.

| # | Kebutuhan | Tahap |
| :--- | :--- | :--- |
| 1 | HPP manual per menu + pilihan lacak stok per menu | Tahap 1 |
| 2 | Stok berkurang otomatis saat pesanan F&B lunas (boleh minus) | Tahap 1 |
| 3 | Stok Masuk (restock), Penyesuaian, Stok Opname, Kartu Stok | Tahap 1 |
| 4 | Peringatan stok menipis / minus (admin & kasir F&B) | Tahap 1 |
| 5 | Hak akses per aksi + Log Aktivitas | Tahap 1 |
| 6 | Buku Kas F&B: pengeluaran & pemasukan lain berkategori; restock otomatis masuk | Tahap 2 |
| 7 | Laporan Laba & Arus Kas F&B + dashboard | Tahap 3 |

---

## 2. Keputusan

| Topik | Keputusan |
| :--- | :--- |
| Bahan baku / resep | **Tidak dipakai.** Stok langsung per menu dalam satuan qty (pcs / porsi). Tabel lama `raw_materials` & `recipe_boms` dibiarkan, tidak dipakai. |
| HPP | **Diisi manual per menu**, mis. Nasi Goreng HPP Rp20.000 (perkiraan seluruh bahan per porsi). |
| Lacak stok | **Pilihan per menu.** *Ya* untuk barang yang bisa dihitung (minuman botol, snack, porsi siap jual). *Tidak* untuk menu yang dimasak saat dipesan — stok tidak dihitung, HPP tetap dipakai untuk laba. |
| Stok habis | **Penjualan tidak diblok.** Stok boleh minus dan memunculkan peringatan. Stok Masuk langsung menutup minus (−3 + 24 = 21). |
| Refund / batal | **F&B tidak memiliki refund.** Stok hanya berkurang saat pesanan **lunas**; QRIS yang dibatalkan / kedaluwarsa tidak pernah lunas sehingga tidak memotong stok. Kejadian seperti salah buat / tumpah / dibuang dicatat lewat Penyesuaian. |
| Cakupan pengeluaran | **Hanya F&B** untuk saat ini. |
| Akses | **Permission per aksi**, diatur di Role & Permission; semua aksi tercatat di Log Aktivitas. Penyesuaian wajib menyertakan alasan. |
| HPP varian / tambahan (modifier) | **Ditunda.** Versi pertama hanya HPP menu utama. |

---

## 3. Kondisi sistem saat ini

- Menu, kategori, varian / tambahan, harga jual, stasiun, dan kasir F&B sudah berjalan.
- Penjualan F&B tercatat di Buku Transaksi (kategori `FNB`, sumber `POS_FNB`) dan tampil di Analytics.
- Order F&B hanya dibuat dari **kasir F&B**. API customer hanya membaca daftar menu.
- Pesanan lunas diproses `FnbFulfillmentHandler`, yang hanya berjalan untuk order berstatus `PAID` (membuat slip stasiun).
- Tabel `raw_materials` & `recipe_boms` dan izin `manage_raw_materials` sudah ada sejak awal, tetapi **tidak dipakai** di mana pun.
- Stok menu saat ini hanya berupa tombol **Tersedia** (on/off) di Kelola Menu F&B.
- **Belum ada pencatatan pengeluaran.** Buku Transaksi hanya mencatat uang masuk dan refund, dan sifatnya tidak dapat diubah (immutable). Karena itu pengeluaran F&B dicatat di buku terpisah (Buku Kas F&B), tidak dicampur ke Buku Transaksi.

---

## 4. Aktor & hak akses

| Aktor | Kegiatan |
| :--- | :--- |
| Admin / Manajer F&B | Mengatur HPP & stok menu, Stok Masuk, Penyesuaian, Stok Opname, mencatat pengeluaran / pemasukan. |
| Kasir F&B | Berjualan seperti biasa (stok berkurang otomatis). Dapat diberi izin Stok Masuk bila diperlukan. |
| Owner / PM | Melihat laporan dan dashboard. |

Usulan izin baru (nama final menyesuaikan Club61PermissionMatrix saat implementasi):

| Izin | Keterangan |
| :--- | :--- |
| Lihat Stok F&B | Daftar stok, Kartu Stok, peringatan |
| Input Stok Masuk F&B | Mencatat restock |
| Penyesuaian Stok F&B | Menambah / mengurangi stok dengan alasan |
| Stok Opname F&B | Menyimpan hasil hitung fisik |
| Kelola HPP & Pelacakan Stok Menu | Mengubah HPP, lacak stok, stok minimum |
| Lihat Buku Kas F&B | Melihat pengeluaran & pemasukan |
| Kelola Buku Kas F&B | Mencatat / membatalkan pengeluaran & pemasukan, mengatur kategori |
| Lihat Laporan Keuangan F&B | Laporan Laba, Arus Kas, dashboard F&B |

Izin baru otomatis diberikan ke role preset yang relevan lewat migrasi (pola `grantNewPermissionsToPresetRoles` yang sudah ada), super admin selalu punya semua izin.

---

## 5. Alur bisnis

### 5.0 Persiapan awal (sekali)
1. Admin membuka setiap menu dan mengisi **HPP**, **Lacak Stok (Ya / Tidak)**, dan **Stok Minimum**.
2. Admin mengisi **stok awal** lewat Stok Opname pertama. Stok awal **tidak** dicatat sebagai pengeluaran, karena barangnya sudah ada sebelum sistem dipakai.
3. Admin mengatur **kategori Buku Kas F&B** (Tahap 2).
4. Super admin mengatur hak akses di Role & Permission.

### 5.1 Penjualan (otomatis)
```
Kasir F&B input pesanan → pembayaran LUNAS
   ├─ Stok menu berkurang sesuai qty (hanya menu Lacak Stok = Ya), boleh sampai minus
   ├─ HPP menu saat itu disimpan di item pesanan (snapshot)
   └─ Penjualan masuk Buku Transaksi (sudah berjalan)
QRIS dibatalkan / kedaluwarsa → pesanan tidak lunas → stok tidak berubah
```

### 5.2 Stok Masuk (restock)
```
Input: menu, jumlah, total harga beli, supplier (opsional), catatan (opsional)
   ├─ Stok bertambah (termasuk menutup stok minus)
   ├─ Ditampilkan harga beli per pcs sebagai patokan untuk memperbarui HPP (HPP tidak berubah otomatis)
   └─ Tahap 2: otomatis tercatat di Buku Kas F&B sebagai pengeluaran kategori "Belanja Stok"
```

### 5.3 Penyesuaian
Untuk kejadian di luar penjualan:
- **Alasan:** Rusak / Terbuang, Kedaluwarsa, Hilang, Dipakai Internal, Salah Input, Lainnya.
- Catatan wajib diisi.
- Jumlah bisa ditambah atau dikurangi.
- Pengurangan dinilai `qty × HPP` sebagai **kerugian stok** di laporan laba.

### 5.4 Stok Opname
```
Admin mengisi hasil hitung fisik per menu (boleh sebagian menu)
   → sistem menampilkan stok sistem, hitung fisik, dan selisih sebelum disimpan
   → saat disimpan, selisih dicatat otomatis sebagai Penyesuaian "Selisih Opname"
```
Opname dapat dilakukan kapan saja; dianjurkan saat kasir sepi atau setelah tutup supaya tidak ada penjualan selama menghitung.

### 5.5 Pengeluaran & pemasukan lain (Tahap 2)
- **Input:** tanggal, kategori, nominal, catatan, dan foto nota (opsional).
- **Contoh:** belanja bahan dapur, gas, listrik / air, perlengkapan, gaji, perawatan, dan pemasukan di luar penjualan.

### 5.6 Laporan (Tahap 3)
- **Kartu Stok per menu** (Tahap 1): seluruh gerakan stok — waktu, jenis, jumlah, stok setelahnya, oleh siapa, alasan.
- **Laporan Laba:**
  ```
  Penjualan − HPP terjual = Laba Kotor
  Laba Kotor + Pemasukan Lain − Kerugian Stok − Biaya Operasional = Laba Bersih
  ```
- **Laporan Arus Kas:** `Uang Masuk − semua Uang Keluar (termasuk Belanja Stok & Bahan)`.
- **Dashboard F&B:**
  - ringkasan angka di atas per periode;
  - grafik per bulan;
  - menu terlaris dan paling menguntungkan;
  - daftar stok menipis / minus.

---

## 6. Aturan penting

1. **Stok hanya berkurang saat lunas.**
   - Pemotongan dijalankan di titik yang sama dengan pembuatan slip stasiun (order `PAID`).
   - Pemotongan bersifat **idempoten**: satu pesanan tidak bisa memotong stok dua kali, termasuk saat notifikasi pembayaran terkirim berulang.
2. **Penjualan tidak pernah diblok karena stok.**
   - Stok boleh minus.
   - Tombol **Tersedia** di menu tetap diatur manual dan tidak berubah otomatis.
3. **HPP disimpan per transaksi (snapshot).** Perubahan HPP hanya berlaku untuk penjualan berikutnya; laba periode lalu tidak berubah.
4. **Belanja stok tidak dihitung dua kali.**
   - Kategori Buku Kas yang ditandai **"Pembelian Stok / Bahan"** (Belanja Stok, Belanja Bahan Dapur) masuk Arus Kas.
   - Kategori tersebut **tidak** dipotong lagi di Laba, karena modalnya sudah diwakili HPP.
5. **Catatan stok tidak dapat diedit atau dihapus.**
   - Kesalahan dikoreksi lewat Penyesuaian "Salah Input".
   - Stok Masuk yang salah dapat **dibatalkan** dengan alasan wajib. Stoknya dikembalikan dan pengeluaran pasangannya ikut dibatalkan; semuanya tetap terlihat di riwayat.
6. **Pengeluaran / pemasukan tidak dihapus**, hanya dapat dibatalkan dengan alasan; tercatat di Log Aktivitas.
7. **Import Penjualan (soft opening) tidak memotong stok**, karena terjadi sebelum stok mulai dicatat.
8. **Menu Lacak Stok = Tidak** tidak punya angka stok, tidak muncul di opname dan peringatan, tetapi HPP-nya tetap dihitung di laba.
9. **Mengubah Lacak Stok:**
   - *Tidak → Ya*: stok mulai dari 0 (isi lewat Stok Masuk / Opname).
   - *Ya → Tidak*: stok terakhir dibiarkan di riwayat.
10. **Menu yang dihapus:** riwayat stok dan laporan lama tetap terbaca.

---

## 7. Spesifikasi per tahap

### 7.1 Tahap 1 — Stok per menu

**Data (usulan):**
- `fnb_menus` + `hpp` (decimal), `track_stock` (boolean, default false), `stock_qty` (integer, default 0), `min_stock` (integer, nullable).
- Tabel baru `fnb_stock_movements` (immutable):
  - kolom: menu, jenis (`SALE`, `RESTOCK`, `RESTOCK_VOID`, `ADJUSTMENT`, `OPNAME`), perubahan qty (bertanda), stok setelah, harga beli total & per pcs (restock), supplier, alasan, catatan, order (untuk penjualan), dibuat oleh, waktu;
  - unik per (order, menu) untuk jenis `SALE`, sebagai pengaman idempoten.
- `order_items` + `unit_cost` (HPP snapshot per item).

**Layar admin:**
- **Kelola Menu F&B:** field HPP, Lacak Stok, Stok Minimum di form menu; kolom HPP & margin di daftar menu.
- **Stok F&B (menu baru):**
  - daftar stok per menu, dengan filter kategori / menipis / minus;
  - tombol **Stok Masuk**, **Penyesuaian**, **Stok Opname**;
  - **Kartu Stok** per menu.
- Semua teks admin dalam Bahasa Indonesia formal.

**Kasir F&B:**
- Kartu menu menampilkan penanda kecil **"Sisa N"** saat stok ≤ minimum dan **"Stok minus"** saat < 0.
- Penanda ini hanya informasi; tidak menghalangi penjualan.

### 7.2 Tahap 2 — Buku Kas F&B
- Tabel kategori: nama, arah (Keluar / Masuk), tanda **Pembelian Stok / Bahan**, aktif, urutan.
- Kategori awal:
  - **Belanja Stok** (otomatis dari Stok Masuk, bertanda pembelian);
  - **Belanja Bahan Dapur** (bertanda pembelian);
  - Gas, Listrik / Air, Perlengkapan, Gaji, Perawatan, Lain-lain;
  - **Pemasukan Lain**.
- Tabel entri: tanggal, kategori, arah, nominal, catatan, lampiran nota, tautan ke Stok Masuk (bila otomatis), status batal + alasan, dibuat oleh.
- Layar: daftar entri per periode, filter kategori, total keluar / masuk, form input, batalkan dengan alasan, kelola kategori.

### 7.3 Tahap 3 — Laporan & dashboard
- Laporan Laba dan Arus Kas F&B per periode (harian / mingguan / bulanan / rentang tanggal).
- Dashboard F&B:
  - kartu ringkasan, grafik bulanan;
  - menu terlaris dan menu paling menguntungkan;
  - stok menipis / minus.
- Sumber angka penjualan: Buku Transaksi kategori `FNB` (sudah ada). Sumber HPP: snapshot `order_items.unit_cost`.

---

## 8. Contoh angka (1 bulan)

| Kejadian | Angka |
| :--- | :--- |
| Aqua: stok awal 20 (opname), restock 48 × Rp3.000 | Pengeluaran Belanja Stok **Rp144.000** |
| Aqua terjual 60 × Rp8.000 (HPP Rp3.000) | Penjualan Rp480.000; HPP Rp180.000 |
| Aqua 2 pecah (penyesuaian) + opname: fisik 5, sistem 6 | Kerugian stok 3 × Rp3.000 = **Rp9.000** |
| Nasi Goreng terjual 100 × Rp45.000 (HPP Rp20.000, tidak lacak stok) | Penjualan Rp4.500.000; HPP Rp2.000.000 |
| Belanja bahan dapur | Pengeluaran **Rp1.800.000** (bertanda pembelian) |
| Gas Rp300.000 + Gaji Rp2.000.000 | Biaya operasional **Rp2.300.000** |

**Laporan Laba**

| Baris | Nilai |
| :--- | ---: |
| Penjualan | Rp4.980.000 |
| HPP | − Rp2.180.000 |
| **Laba Kotor** | **Rp2.800.000** |
| Kerugian Stok | − Rp9.000 |
| Biaya Operasional | − Rp2.300.000 |
| **Laba Bersih** | **Rp491.000** |

**Laporan Arus Kas**

| Baris | Nilai |
| :--- | ---: |
| Uang masuk | Rp4.980.000 |
| Uang keluar (Rp144.000 + Rp1.800.000 + Rp300.000 + Rp2.000.000) | − Rp4.244.000 |
| **Selisih** | **Rp736.000** |

Belanja stok & bahan dapur mengurangi Arus Kas, tetapi tidak dipotong lagi di Laba karena sudah diwakili HPP.

---

## 9. Di luar cakupan

- Bahan baku, resep, dan konversi satuan.
- HPP varian / tambahan (modifier) — ditunda.
- Pengeluaran di luar F&B (padel, wellness, dll.).
- Purchase order, hutang supplier, multi gudang, transfer stok antar lokasi.
- Pemblokiran penjualan saat stok habis dan pematian menu otomatis.
- Pemotongan stok dari Import Penjualan.

---

## 10. Pertanyaan terbuka

| # | Pertanyaan | Status |
| :--- | :--- | :--- |
| Q1 | Arti "stock bulatan" dari catatan PM | Ditunda — owner lupa maksudnya, dikonfirmasi ulang ke PM |
| Q2 | HPP varian / tambahan (mis. extra shot) | Ditunda — versi pertama HPP Rp0 untuk tambahan |
| Q3 | Daftar kategori Buku Kas final | Usulan di §7.2, bisa diubah admin kapan saja |
| Q4 | Dashboard F&B digabung ke Dashboard utama atau halaman tersendiri | Halaman tersendiri **Laporan F&B** (v0.2.0) — bisa digabung nanti bila diminta |

---

## 11. Riwayat versi

| Versi | Tanggal | Perubahan |
| :--- | :--- | :--- |
| v0.1.0 | 29 Okt 2026 | Draft awal: stok per menu tanpa bahan baku, HPP manual, stok boleh minus, tanpa refund F&B, Buku Kas F&B, laporan Laba & Arus Kas. |
| v0.2.0 | 29 Okt 2026 | Tahap 1–3 dibangun (belum di-commit). Keputusan detail implementasi dicatat di §12. |
| v0.3.0 | 30 Okt 2026 | HPP hanya di data menu; Penerimaan Barang banyak menu per nota; impor Excel; istilah formal (§13). |
| v0.4.0 | 30 Okt 2026 | Templat impor semua menu + stok fisik + harga per satuan + supplier/nota per baris; tombol ringkas; Menu F&B pindah ke grup F&B (§14). |
| v0.5.0 | 30 Okt 2026 | Evaluasi alur: aksi stok satu pintu (tanpa tombol per baris), Penyesuaian banyak menu, alasan Dikembalikan ke Penitip, pemasukan hanya dari penjualan kasir, Buku Kas → Pengeluaran F&B (§15). |
| v0.6.0 | 30 Okt 2026 | Catatan per menu di Stok Opname; Unduh Laporan Excel (5 sheet) & PDF; Cetak Lembar Opname (§16). |

---

## 12. Catatan implementasi (v0.2.0)

### Menu & halaman
- Grup sidebar baru **F&B** berisi tiga halaman:
  - **Stok F&B**: Daftar Stok, Riwayat Stok, Stok Masuk, Penyesuaian, Stok Opname, Kartu Stok, Atur HPP & Stok.
  - **Buku Kas F&B**.
  - **Laporan F&B**: laba, arus kas, tren 6 bulan, menu terlaris / paling untung, stok menipis. Laporan ini sekaligus menjadi dashboard F&B (Q4).
- HPP, Lacak Stok, dan Stok Minimum juga bisa diisi di form **Kelola Menu F&B**, hanya untuk staf yang punya izin `manage_fnb_hpp`. Daftar menu menampilkan kolom HPP.

### Hak akses
Izin baru ada di grup "3. F&B Cafe & Kitchen":
- `manage_fnb_hpp`
- `View:StokFnb`, `restock_fnb_stock`, `adjust_fnb_stock` (juga untuk membatalkan Stok Masuk), `opname_fnb_stock`
- `View:BukuKasFnb`, `manage_fnb_cashbook`
- `View:LaporanFnb`

Role admin otomatis mendapat semuanya. Kasir tidak mendapat izin baru dan diberikan manual lewat Roles & Hak Akses bila perlu.

### Aturan stok & laba
- Stok Masuk, Penyesuaian, dan Opname **hanya untuk menu yang Lacak Stok = Ya**. Belanja bahan untuk menu yang tidak dilacak dicatat di Buku Kas ("Belanja Bahan Dapur").
- **Kerugian stok** = pengurangan Penyesuaian (kecuali alasan *Salah Input*) + selisih minus Opname, × HPP saat itu.
  - Selisih plus tidak dihitung sebagai untung, supaya stok awal lewat opname pertama tidak terbaca sebagai laba.
- **Laba Bersih** = Laba Kotor **+ Pemasukan Lain** − Kerugian Stok − Biaya Operasional.
- **Penjualan** = Buku Transaksi kategori FNB (`net_amount`, angka sama dengan Analytics).
- **Uang Masuk** di Arus Kas = `total_amount` (termasuk pajak & biaya layanan yang diterima).

### Buku Kas
- Kategori **Belanja Stok** adalah kategori sistem. Kategori ini tidak dapat dipilih manual atau dinonaktifkan, karena terisi otomatis dari Stok Masuk.
- Entri otomatis Stok Masuk dibatalkan dari Riwayat Stok, supaya stoknya ikut kembali.
- **Foto nota** disimpan di disk privat (`local`) dan hanya bisa diunduh dari halaman Buku Kas F&B.
- Catatan dengan tanggal di masa depan ditolak.

### Data & kode
- Satu migrasi: `2026_10_29_100003_create_fnb_stock_and_cashbook_tables`.
  - Membuat kolom menu, `order_items.unit_cost`, tiga tabel baru, dan kategori awal.
  - Memberi izin baru ke role preset.
- Stok diubah lewat query builder, sehingga log otomatis "Mengubah Menu F&B" tidak banjir tiap penjualan. Jejak per aksi ada di Kartu Stok + Log Aktivitas (`fnb_stock.*`, `fnb_cash.*`). Perubahan HPP tercatat otomatis sebagai WARNING.
- Pesanan yang dibuat sebelum fitur ini, tetapi baru lunas setelahnya (QR menunggu bayar), mendapat HPP saat lunas.

### Catatan untuk owner
- **Pemasukan Lain** di Buku Kas adalah pencatatan manual di luar kasir, sehingga tidak masuk rekap shift kasir. Untuk penjualan barang tetap gunakan kasir F&B.

---

## 13. Revisi v0.3.0 (30 Okt 2026)

### 13.1 HPP hanya di satu tempat
- HPP, Lacak Persediaan, dan Stok Minimum **hanya** diatur di **F&B → Menu F&B** (menu dipindah dari grup Master Data ke grup F&B, 30 Okt 2026), di form menu (izin `manage_fnb_hpp`).
- Tombol pengaturan di Stok F&B dihapus. Tabel Stok F&B tetap menampilkan HPP dan margin untuk dibaca.
- Pilihan di form menu: **Ya, lacak persediaan** / **Tidak, dibuat sesuai pesanan**.

### 13.2 Penerimaan Barang (menggantikan "Stok Masuk")
- **Satu nota supplier = satu Penerimaan Barang** berisi banyak menu.
  - Header: Supplier, No. Nota / Faktur, Catatan.
  - Baris: Menu, Jumlah Diterima, Total Harga Pembelian. Harga / Satuan dihitung otomatis.
  - Tersedia tombol + Tambah Baris dan total pembelian.
- Nomor otomatis `PB-yymmdd-XXXXX`.
- Semua baris tersimpan sekaligus, atau tidak sama sekali.
  - Menu yang sama tidak boleh tercantum di dua baris.
  - Menu yang persediaannya tidak dilacak ditolak.
- Buku Kas mencatat **satu** pengeluaran **Pembelian Persediaan** (nama baru kategori "Belanja Stok") sebesar total nota.
  - Bila total Rp0, misalnya barang titipan yang belum dibayar, tidak ada pengeluaran.
- **Pembatalan dilakukan per penerimaan**, dengan alasan wajib.
  - Stok semua barangnya dikurangi kembali dan pengeluarannya ikut batal.
  - Baris lama ditandai batal dan muncul "Pembatalan Penerimaan" di Kartu Stok.
- Stok F&B kini punya tiga tab: **Daftar Persediaan**, **Penerimaan Barang** (daftar, rincian, batalkan), dan **Riwayat Stok**.

### 13.3 Impor Excel Penerimaan Barang
1. **Unduh Templat.** Sheet "Penerimaan Barang" berisi semua menu yang persediaannya dilacak, dengan kolom:
   - *ID Sistem (jangan diubah)*, *Nama Menu*, *Kategori*, *Stok Saat Ini* — informasi;
   - *Jumlah Diterima\**, *Total Harga Pembelian (Rp)* — diisi staf.

   Ada juga sheet "Petunjuk".
2. **Unggah .xlsx (maks. 5 MB).** Sistem menampilkan **pratinjau per baris**: baris yang siap disimpan, dan baris bermasalah beserta alasannya:
   - menu tidak ditemukan;
   - menu tidak dilacak;
   - menu dobel;
   - jumlah bukan bilangan bulat ≥ 1;
   - total negatif.

   Baris dengan Jumlah Diterima kosong dilewati. Pencocokan memakai ID Sistem, atau nama menu bila ID kosong.
3. **Simpan** hanya bila semua baris valid. File dibaca ulang saat disimpan, karena pratinjau di layar tidak dipercaya. Hasilnya menjadi satu Penerimaan Barang (sumber "Impor Excel"), maksimal 500 baris.

### 13.4 Istilah formal

| Lama | Baru |
| :--- | :--- |
| Stok Masuk (Restock) | Penerimaan Barang |
| Jumlah Masuk (pcs / porsi) | Jumlah Diterima |
| Total Harga Beli | Total Harga Pembelian |
| Harga beli per pcs | Harga / Satuan |
| HPP per porsi / pcs | HPP (Harga Pokok Penjualan) per Satuan |
| Lacak Stok: Ya, hitung stoknya / Tidak (dimasak saat dipesan) | Lacak Persediaan: Ya, lacak persediaan / Tidak, dibuat sesuai pesanan |
| Penyesuaian (Kurangi / Tambah) | Penyesuaian Stok (Pengurangan / Penambahan), "Catatan" → "Keterangan" |
| Stok Opname (Hitung Fisik) | Stok Opname (Perhitungan Fisik) |
| Belanja Stok | Pembelian Persediaan |
| Daftar Stok / Menu Dilacak | Daftar Persediaan / Persediaan Dilacak |

Istilah **Supplier** dan **Nilai Stok (HPP)** tetap dipakai, sesuai keputusan owner.

### 13.5 Data
Migrasi `2026_10_29_100003` diubah (masih satu file):
- tabel baru `fnb_stock_receipts`;
- `fnb_stock_movements.receipt_id` menggantikan `supplier` dan `related_movement_id`;
- `fnb_cash_entries.receipt_id` menggantikan `stock_movement_id`;
- kategori awal bernama "Pembelian Persediaan".

Lokal yang sudah menjalankan versi sebelumnya: `php artisan migrate:rollback --step=1` lalu `php artisan migrate`.

---

## 14. Revisi v0.4.0 (30 Okt 2026) — impor menyeluruh & tombol ringkas

### 14.1 Templat impor berisi semua menu
Templat memuat **seluruh menu terdaftar**, termasuk yang belum dilacak, dengan kolom:
- **Informasi:** ID Sistem (jangan diubah), Nama Menu, Kategori, Lacak Persediaan, Stok di Sistem;
- **Isian staf:** Stok Fisik Saat Ini (opsional), Jumlah Diterima, Harga Beli per Satuan;
- **Otomatis:** Total = jumlah × harga (rumus Excel; sistem tetap menghitung ulang);
- **Pengelompokan:** Supplier dan No. Nota.

Kolom isian diberi warna, judul dikunci, dan lebar kolom diatur. Ada juga sheet "Petunjuk".

### 14.2 Aturan proses impor
- Baris yang Stok Fisik, Jumlah, dan Harga-nya kosong dilewati.
- Validasi per baris:

  | Kondisi | Hasil |
  | :--- | :--- |
  | Menu tidak ditemukan | Ditolak |
  | Menu tercantum dua kali | Ditolak |
  | Stok fisik bukan bilangan bulat ≥ 0 | Ditolak |
  | Jumlah diterima bukan bilangan bulat ≥ 1 | Ditolak |
  | Jumlah diisi tanpa harga | Ditolak. Harga 0 diperbolehkan untuk barang titipan. |
  | Harga diisi tanpa jumlah | Ditolak |

- Urutan saat disimpan (satu transaksi; semua masuk atau tidak sama sekali):
  1. Menu yang belum dilacak otomatis **diaktifkan Lacak Persediaan**-nya.
  2. **Stok Fisik** dicatat sebagai Stok Opname. Selisih dihitung terhadap stok sistem, sebelum barang baru ditambahkan.
  3. Baris dikelompokkan per **Supplier + No. Nota** (tidak membedakan huruf besar/kecil). Satu kelompok menjadi **satu Penerimaan Barang**, dengan satu pengeluaran Pembelian Persediaan.
  4. Opsional **Perbarui HPP**: HPP menu diganti Harga Beli per Satuan (hanya harga > 0). Hanya tampil untuk staf dengan izin `manage_fnb_hpp`, dan default mati.
- **Izin:**
  - Impor membutuhkan `restock_fnb_stock`.
  - Bila ada Stok Fisik, juga membutuhkan `opname_fnb_stock`.
  - Bila memilih Perbarui HPP, juga membutuhkan `manage_fnb_hpp`.

  Bila izin kurang, sistem menampilkan notifikasi dan tidak ada data yang disimpan.
- **Pratinjau** menampilkan per baris:
  - stok sistem, stok fisik, jumlah diterima;
  - harga per satuan, total;
  - **stok akhir**;
  - keterangan: siap, Lacak Persediaan diaktifkan, atau alasan error.

  Ringkasannya berisi jumlah Penerimaan Barang, total pembelian, jumlah menu yang disesuaikan, dan jumlah menu yang diaktifkan.

### 14.3 Input manual
Baris diisi **Harga Beli per Satuan** (wajib, boleh 0). Total per baris dan total pembelian dihitung sistem.

### 14.4 Tombol
- **Stok F&B:** satu tombol utama **+ Penerimaan Barang**, dengan tab *Input Manual | Impor Excel* di dalam modal. Penyesuaian Stok dan Stok Opname ada di dropdown **Lainnya** (komponen dropdown Filament).
- **Buku Kas F&B:** tombol utama **+ Catat Pengeluaran**. Catat Pemasukan dan Kelola Kategori ada di **Lainnya**.

### 14.5 Menu
**Menu F&B** dipindah dari grup Master Data ke grup **F&B**, di urutan teratas.

---

## 15. Revisi v0.5.0 (30 Okt 2026) — evaluasi alur

Hasil evaluasi bersama owner aplikasi. Bagian ini **menggantikan** aturan pemasukan dan Buku Kas di bagian sebelumnya.

### 15.1 Satu pintu aksi stok
- Tombol aksi per baris di Daftar Persediaan **dihapus**, karena dobel dengan tombol di atas.
- Semua aksi dimulai dari atas:
  - **+ Penerimaan Barang** (tab Input Manual / Impor Excel);
  - **Lainnya** (Penyesuaian Stok, Stok Opname).
- **Klik nama menu** yang dilacak membuka **Kartu Stok**. Kartu Stok hanya untuk dilihat.

### 15.2 Pola form seragam (banyak menu sekaligus)
- **Penerimaan Barang (manual):**
  - Semua menu bisa dipilih.
  - Menu yang belum dilacak **otomatis diaktifkan** Lacak Persediaan-nya, sama dengan impor Excel. Aturan ini ada di `FnbStockService::receive()`.
- **Penyesuaian Stok:**
  - Berisi banyak baris (menu, Pengurangan/Penambahan, jumlah, alasan) dengan satu keterangan umum.
  - Semua baris tersimpan atau tidak sama sekali. Menu yang sama tidak boleh dua kali.
- **Alasan baru "Dikembalikan ke Penitip":** untuk barang titipan yang dikembalikan, **tidak** dihitung sebagai kerugian stok (sama seperti Salah Input).

### 15.3 Pemasukan hanya dari penjualan kasir
- **Pemasukan F&B = penjualan kasir F&B** (Buku Transaksi kategori FNB). Tidak ada pencatatan pemasukan manual.
  - Sejalan dengan aturan klub: uang hanya masuk lewat kasir POS.
- Halaman **Buku Kas F&B** diganti nama menjadi **Pengeluaran F&B**. Izin & class tetap (`View:BukuKasFnb`, `manage_fnb_cashbook`), sehingga hak akses role tidak berubah.
- Yang dihapus:
  - tombol Catat Pemasukan;
  - kategori "Pemasukan Lain";
  - filter Pengeluaran/Pemasukan;
  - kolom `direction` di tabel kategori dan catatan.
- **Laporan F&B:**
  - **Laba Bersih** = Laba Kotor − Kerugian Stok − Biaya Operasional.
  - **Arus Kas** = uang masuk penjualan − semua pengeluaran.

### 15.4 Data
Migrasi `2026_10_29_100003` diubah lagi (tetap satu file): tanpa kolom `direction` dan tanpa kategori "Pemasukan Lain".

Lokal: `php artisan migrate:rollback --step=1` lalu `php artisan migrate`. Data percobaan Modul 29 ikut terhapus.

### 15.5 Alur harian
```
Barang datang      → + Penerimaan Barang (manual / impor Excel) → stok naik + Pengeluaran F&B tercatat
Kasir jualan       → stok turun otomatis + penjualan & HPP tercatat
Rusak / titipan    → Lainnya → Penyesuaian Stok (banyak menu)
Akhir bulan        → Lainnya → Stok Opname
Bayar gas / gaji   → Pengeluaran F&B
Lihat untung       → Laporan F&B
```

---

## 16. Revisi v0.6.0 (30 Okt 2026) — catatan opname per menu & export laporan

### 16.1 Stok Opname: catatan per menu
- Setiap baris opname punya kolom **Catatan** (maks. 200 karakter). Catatan di bawah menjadi **Catatan Umum**.
- Di Kartu Stok, isi catatan disusun: catatan menu · catatan umum.
- Catatan per menu juga masuk Log Aktivitas, di samping angka selisihnya.
- Catatan hanya tersimpan untuk menu yang memiliki selisih. Menu tanpa selisih tidak menghasilkan baris Kartu Stok.

### 16.2 Unduh Laporan (halaman Laporan F&B)
Tombol **Unduh Laporan ▾** mengikuti periode yang dipilih, dengan dua pilihan:
- **Excel**, 5 sheet:

  | Sheet | Isi |
  | :--- | :--- |
  | Ringkasan | Laba, Arus Kas, pengeluaran per kategori |
  | Per Menu | Terjual, penjualan, HPP, laba kotor, margin |
  | Pengeluaran | Semua catatan, termasuk yang dibatalkan beserta alasannya |
  | Riwayat Stok | Semua pergerakan stok |
  | Stok Saat Ini | Stok, minimum, HPP, nilai stok, status |

  Angka di file sama dengan angka di layar. Teks dinetralkan dari formula injection.
- **PDF** (A4, siap cetak untuk owner / PM): kartu ringkasan, Laporan Laba, Arus Kas dengan rincian pengeluaran per kategori, rekap per menu, dan stok menipis.

Izin memakai `export_reports` yang sudah ada ("Export Laporan Keuangan (Excel / PDF)"), sehingga tidak ada izin baru. Setiap unduhan tercatat di Log Aktivitas (`fnb_report.exported`).

### 16.3 Cetak Lembar Opname
- Tombol **Cetak Lembar Opname (PDF)** ada di dalam modal Stok Opname. Izinnya `opname_fnb_stock`.
- Isinya:
  - daftar menu yang persediaannya dilacak;
  - kolom Jumlah Fisik & Catatan yang kosong;
  - tanda tangan Dihitung, Diperiksa, dan Disetujui.
- Stok sistem **tidak dicantumkan** (hitung buta) supaya hasil hitung tidak terpengaruh angka sistem.

### 16.4 Unduh Laporan Pengeluaran F&B
- Tombol **Unduh Laporan ▾** ada di baris filter halaman Pengeluaran F&B. File mengikuti filter yang sedang aktif: periode, kategori, dan status.
- **Excel:**
  - **Rincian:** tanggal, kategori, jenis (pembelian persediaan / biaya operasional), keterangan, nominal, status, no. penerimaan, pencatat, alasan batal.
  - **Per Kategori:** jumlah catatan dan total.
- **PDF:** kartu Total / Pembelian Persediaan / Biaya Operasional, rekap per kategori, dan rincian. Catatan yang dibatalkan ditampilkan abu-abu dengan nominal dalam kurung.
- Total dan rekap **hanya menghitung catatan aktif**.
- Izin `export_reports` (sama dengan Laporan F&B). Setiap unduhan tercatat di Log Aktivitas (`fnb_expense.exported`).
- Query filter di halaman dipisah menjadi satu method (`filteredEntries()`), sehingga isi file = isi tabel di layar.
