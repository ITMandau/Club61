# Product Requirements Document (PRD)
## Modul 22: Voucher Saldo & Voucher Promo Marketing
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-22-VOUCHER-DAN-PROMO` |
| **Versi** | `v0.2.0-DRAFT` |
| **Status** | **Rancangan final tim (18 Okt 2026), menunggu konfirmasi PM.** Semua jawaban yang belum diputuskan PM dibuat sebagai **pengaturan di admin** dengan nilai awal sesuai usulan — kalau PM memutuskan lain, cukup diubah dari admin, kode tidak perlu diubah. |
| **Riwayat** | v0.1.0 (5 Okt 2026): fondasi + 11 pertanyaan PM. v0.2.0 (18 Okt 2026): skema dinamis, aturan tetap, cara sebar A–D, kategori layanan, membership dipisah. |
| **Sumber Requirement** | PM (5 Okt 2026): voucher dari refund yang ditolak habis sekali pakai, sisa hangus. Tim (18 Okt 2026): voucher kembali utuh saat checkout kedaluwarsa / booking batal; 1x per akun per promo supaya pembagian merata; cara sebar dipilih per promo; promo untuk sewa lapangan (tanpa add-on), F&B disiapkan; happy hour nanti. |
| **Dependensi Teknis** | `PRD_MODUL_21_REFUND_NO_SHOW_PEMBAYARAN_BERMASALAH.md` (asal voucher saldo), `PRD_MODUL_17_BUKU_TRANSAKSI_TERPADU.md` (pencatatan potongan), `PRD_MODUL_10_UNIFIED_PAYMENT_GATEWAY.md` (checkout online), `PRD_MODUL_11_WALK_IN_OFFLINE_BOOKING.md` (POS kasir), `PRD_MODUL_05_MEMBERSHIP_SYSTEM.md` (benefit member), `PRD_MODUL_23_STRUKTUR_PORTAL_CUSTOMER.md` (Voucher Saya). |
| **Prinsip Utama** | **SATU TABEL VOUCHER, SATU MESIN HITUNG** untuk online & kasir; **VOUCHER BUKAN UANG MASUK BARU** (tercatat sebagai potongan); **ATURAN DICEK DI SERVER**; **KEPUTUSAN BISNIS = PENGATURAN**, bukan kode. |

---

## 1. Ringkasan

Tiga jenis potongan, **dikelola terpisah**:

| | Voucher Saldo | Voucher Promo | Benefit Membership |
| :--- | :--- | :--- | :--- |
| Asal | Otomatis saat refund **ditolak** di Antrian Refund (Modul 21) | Dibuat tim marketing | Customer **membeli** kartu member |
| Isi | Nilai rupiah sebesar refund yang ditolak | Potongan persen / rupiah | Kuota jam / kunjungan atau diskon % per fasilitas |
| Pemilik | Satu customer | Siapa pun yang memenuhi aturan promo | Pemegang kartu |
| Cara pakai | Muncul otomatis di checkout / kasir | Kode, kode unik, atau klaim ke akun (§4) | Otomatis menempel ke kartu, tanpa kode |
| Sifat | Uang customer yang disimpan klub | Biaya promosi klub | Hak yang sudah dibayar |
| Diatur di | Daftar Voucher + Pengaturan Voucher Saldo | Halaman **Marketing** | Paket Membership → Benefit (sudah ada, tidak diubah) |

**Kenapa membership dipisah:** kuota yang dibayar customer tidak boleh tercampur dengan potongan gratis dari promo —
laporan biaya promosi, aturan batal / refund, dan kuota jam jadi kacau. Yang dihubungkan hanya **kode kategori yang
sama** (§5) dan **aturan boleh digabung** (§4.3).

---

## 2. Fondasi yang Sudah Ada

| Bagian | Yang sudah jalan |
| :--- | :--- |
| Data | Tabel `vouchers`: kode, jenis (`PERCENT` / `FIXED` / `CREDIT`), nilai, minimal transaksi, maksimal potongan, kuota, masa berlaku, aktif/nonaktif, pemilik (`user_id`), asal refund (`refund_id`), sisa saldo (`balance`). `orders.voucher_code` + `orders.discount_amount`. |
| Mesin hitung | `App\Services\Finance\VoucherService` — dipakai checkout online **dan** POS Walk-In. Sejak audit 16 Okt 2026 hitungan saldo / kuota yang sedang dipesan memakai locking read (checkout paralel tidak bisa memakai saldo yang sama dua kali). |
| Checkout online | Kode voucher dicek ke server (`POST /api/v1/padel/vouchers/check`); voucher saldo milik customer tampil otomatis (`GET /api/v1/padel/vouchers/mine`). |
| POS Walk-In | Kolom voucher + daftar voucher saldo customer terpilih; tagihan yang ditutup penuh voucher lunas tanpa bukti EDC/QRIS; struk menampilkan potongan. |
| Customer | My Club menampilkan voucher saldo; tiket booking yang refund-nya ditolak menampilkan kode voucher; email saat voucher terbit. |
| Admin | **Daftar Voucher** (Keuangan, superadmin): semua voucher, cari & filter, ringkasan saldo, riwayat pemakaian, nonaktifkan. |
| Pembukuan | Potongan tercatat di Buku Transaksi sebagai diskon order — uang tidak dihitung dua kali. |
| **Belum ada** | Voucher saldo sekali pakai (sisa masih disimpan), halaman **membuat** promo (menu Marketing masih tampilan contoh dengan kode `CLUB61` / `HAPPYHOUR` tertulis di halaman), batas per akun, cara sebar B–D, kategori. |

---

## 3. Aturan Tetap (berlaku untuk semua voucher, tidak diatur per voucher)

| # | Aturan | Isi |
| :--- | :--- | :--- |
| T1 | **Sekali pakai, sisa hangus** | Voucher dipakai satu kali. Nilai voucher lebih besar dari tagihan → tagihan Rp0, **sisanya hangus** dan dicatat sebagai nilai hangus. Nilai lebih kecil → voucher habis, customer membayar sisanya. Sebelum dipakai muncul peringatan: *"Sisa Rp100.000 akan hangus. Lanjutkan?"* — customer boleh batal memakai voucher. |
| T2 | **Kembali seperti semula** | Checkout yang **kedaluwarsa** (tidak dibayar sampai batas bayar) atau booking yang **dibatalkan** → voucher aktif lagi dengan **nilai penuh & masa berlaku semula**. Berlaku untuk voucher saldo **dan** promo: kuota promo dan jatah per akun ikut kembali. |
| T3 | **1x per akun per promo** | Akun yang sudah memakai / mengklaim sebuah promo **tidak bisa mendapatkannya lagi**, supaya pembagian merata. Kode unik massal (§4 B): satu akun hanya bisa memakai satu kode dari kampanye yang sama. Yang dihitung: order lunas + order yang masih menunggu bayar (dilepas lagi kalau T2 berlaku). |
| T4 | **Satu voucher per transaksi** | Voucher saldo **atau** promo — pilih satu (aturan bisnis yang sudah berjalan). |
| T5 | **Dicek di server** | Semua aturan di `VoucherService::resolve()` — sama untuk online & kasir; halaman hanya menampilkan hasil cek. |
| T6 | **Kasir walk-in ikut aturan akun** | Customer walk-in selalu punya akun (dibuat otomatis dari nomor HP), jadi T3 berlaku juga di kasir. |

---

## 4. Voucher Promo — Cara Sebar & Pengaturan

### 4.1 Cara sebar (dipilih per promo saat dibuat)

| | A. Kode Umum | B. Kode Unik Massal | C. Klaim ke Akun | D. Otomatis |
| :--- | :--- | :--- | :--- | :--- |
| Isi | **Satu** kode untuk semua orang | **Banyak** kode berbeda, masing-masing sekali pakai | Voucher masuk ke **Voucher Saya** akun customer | Voucher terbit sendiri karena kejadian |
| Contoh | `CLUB61OPEN` disebar di Instagram | 50 kode `TRN-7K2M9Q`, `TRN-P3X8WA`, … untuk pemenang turnamen | Banner "Klaim Diskon 20%" di aplikasi / link di bio Instagram | Akun baru dapat voucher selamat datang |
| Sampai ke customer lewat | Postingan sosmed, WhatsApp, spanduk | Admin mengunduh CSV, lalu dibagikan satu per satu (kertas, DM, email) | Customer menekan **Klaim** (wajib login) | Sistem langsung mengisi Voucher Saya |
| Cara pakai | Ketik kode di checkout / sebut ke kasir | Ketik kodenya sendiri | Pilih di checkout (muncul otomatis) | Pilih di checkout |
| Kuota dihitung saat | Dipakai | Kode dipakai (jumlah kode = kuota) | **Diklaim** ("100 klaim pertama") | Terbit |
| Kontrol | Rendah — kode bisa menyebar ke mana saja (dibatasi kuota + T3) | Tinggi — tiap kode untuk satu orang | Sedang — wajib akun, 1 klaim per akun | Tinggi — sesuai kejadian |
| Cocok untuk | Launching, kampanye sosmed | Event, influencer, hadiah, kerja sama brand | Menambah pendaftar akun, retensi | Customer baru, ulang tahun, referral |
| Tahap | 2 | 3 | 3 | 4 |

**Alur A — Kode Umum**
1. Marketing membuat promo: kode `CLUB61OPEN`, diskon 20% maks. Rp50.000, kuota 100, periode 1–31 Nov.
2. Kode diposting di Instagram.
3. Customer booking, mengetik `CLUB61OPEN` di checkout → server mengecek: promo aktif & dalam periode, sisa kuota,
   akun belum pernah memakai (T3), kategori & kanal cocok → potongan tampil.
4. Bayar → kuota terpakai 1. Tidak dibayar sampai batas bayar → kuota & jatah akun kembali (T2).
5. Pemakaian ke-101 / akun yang sudah pernah memakai → *"Kuota habis"* / *"Promo ini sudah pernah kamu pakai"*.

**Alur B — Kode Unik Massal**
1. Marketing membuat kampanye "Hadiah Turnamen Okt": diskon Rp100.000, **jumlah kode 50**, awalan `TRN`.
2. Sistem membuat 50 kode acak → tombol **Unduh CSV**.
3. Tiap kode dibagikan ke satu orang. Kode yang sudah dipakai mati; kode yang bocor hanya bisa dipakai sekali.
4. Satu akun hanya bisa memakai satu kode dari kampanye ini (T3) — customer yang dapat dua kode tidak bisa memakai keduanya.
5. Laporan: kode mana yang sudah / belum dipakai.

**Alur C — Klaim ke Akun**
1. Marketing membuat promo klaim: diskon 15%, **kuota klaim 200**, periode, link klaim.
2. Customer membuka link / banner → login → **Klaim** → voucher masuk Voucher Saya; kuota klaim berkurang 1.
3. Satu akun satu klaim (T3). Kuota klaim habis → tombol *"Habis diklaim"*.
4. Di checkout voucher hasil klaim muncul otomatis (seperti voucher saldo sekarang) — tidak perlu mengetik kode.
5. Klaim yang tidak dipakai hangus saat periode promo berakhir. Klaim yang dipakai lalu bookingnya batal / kedaluwarsa
   → voucher kembali ke Voucher Saya (T2).

**Alur D — Otomatis** (tahap 4, pemicu dipilih per promo)
- **Akun baru** — voucher selamat datang terbit saat daftar.
- **Ulang tahun** — butuh tanggal lahir di profil (belum ada).
- **Ajak teman (referral)** — butuh kode referral per akun (belum ada).
Voucher masuk Voucher Saya; masa berlaku dihitung sejak terbit (mis. 30 hari).

### 4.2 Isian per promo (halaman Marketing)

| Isian | Pilihan | Nilai awal |
| :--- | :--- | :--- |
| Nama promo | Bebas | — |
| Cara sebar | A / B / C / D (§4.1) | A |
| Kode | A: diketik / dibuat otomatis. B: awalan + jumlah kode. C: kode klaim / link. D: tanpa kode | — |
| Potongan | Persen (+ maksimal potongan) / rupiah | — |
| Minimal transaksi | Rupiah (boleh kosong) — dihitung dari nilai kategori yang dicakup | Kosong |
| Kuota | A: jumlah pemakaian. B: jumlah kode. C: jumlah klaim. Kosong = tanpa batas (A, C) | — |
| Periode | Mulai tanggal → berakhir tanggal | — |
| Kategori | Centang (§5) | Sewa lapangan padel |
| Kanal | Online / Kasir / keduanya | Keduanya |
| Boleh digabung benefit member | Ya / Tidak | Tidak |
| Boleh digabung voucher sponsor | Ya / Tidak | Tidak |
| Status | Aktif / Nonaktif | Aktif |
| ~~Hari & jam berlaku (happy hour)~~ | Ditunda (keputusan tim 18 Okt 2026) | — |

### 4.3 Aturan gabung dengan membership & sponsor
- Diatur **di promo** (dua saklar di §4.2), bukan di membership.
- Saklar "Tidak" → promo ditolak untuk booking yang memakai benefit member / voucher sponsor, dengan pesan jelas.
- Saklar "Ya" → promo dipotong **setelah** benefit member (urutan yang sudah berjalan).

### 4.4 Hak akses
- `manage_vouchers` (bisa diberikan ke staf marketing lewat Roles & Hak Akses): buat / ubah / nonaktifkan promo, unduh
  CSV kode unik. Promo langsung aktif; semua perubahan tercatat di Log Aktivitas.
- Kalau PM ingin persetujuan owner: izin terpisah "Aktifkan promo" — promo buatan staf berstatus *menunggu persetujuan*.

---

## 5. Kategori Layanan (dipakai voucher & membership)

| Kode | Kategori | Promo | Voucher saldo | Status |
| :--- | :--- | :--- | :--- | :--- |
| `PADEL` | Sewa lapangan padel (biaya sewa lapangan saja) | ✓ | ✓ | **Aktif** |
| `PADEL_ADDON` | Add-on padel (raket, bola) | ✗ (keputusan tim) | ✓ (uang customer sendiri) | Aktif |
| `FNB` | F&B | ✓ (bisa dicentang & disimpan) | — | **Disiapkan** — berlaku otomatis begitu kasir F&B tersambung ke `VoucherService` |
| `SAUNA` / `GYM` / `SALON` / `MEMBERSHIP` | — | — | — | Ditambahkan saat modulnya berjualan |

- Daftar kategori **ditetapkan di kode** (setiap kategori butuh sambungan ke checkout-nya), bukan dibuat bebas dari admin.
- Kode yang sama dengan master fasilitas membership (`PADEL`, `GYM`, `SAUNA`) — kalau kelak membership memberi diskon
  F&B / salon, kodenya sudah sama.
- Promo kategori `PADEL` hanya memotong **biaya sewa lapangan**. *Perubahan dari perilaku sekarang:* potongan saat ini
  dihitung dari lapangan + add-on.

---

## 6. Voucher Saldo

| Pengaturan | Nilai awal | Diubah di |
| :--- | :--- | :--- |
| Sisa nilai | **Hangus** (T1) — tetap, bukan pilihan | — |
| Kembali saat kedaluwarsa / batal | Ya, nilai penuh & masa berlaku semula (T2) | — |
| Masa berlaku | 6 bulan (sekarang tertulis di kode `VoucherService::CREDIT_VALID_MONTHS`) | Pengaturan Voucher Saldo |
| Berlaku untuk | Booking padel: lapangan + add-on | — |
| Pajak & biaya layanan | Dihitung **sesudah** potongan | Menu Biaya & Pajak (sudah ada) |

---

## 7. Sisi Customer
- **Checkout online & kasir:** kolom kode (A, B) + daftar voucher milik akun (saldo, hasil klaim C, otomatis D). Peringatan sisa hangus (T1).
- **Voucher Saya** (Modul 23): tab Tersedia / Riwayat — voucher saldo, hasil klaim, voucher otomatis, beserta masa berlaku.
- **Halaman klaim** (C): link per promo → login → Klaim.

## 8. Laporan
- Halaman Marketing, per promo: dipakai / sisa kuota, total potongan, jumlah order; B: daftar kode terpakai / belum; C: jumlah klaim vs dipakai.
- Daftar Voucher: kolom & ringkasan **hangus**.
- Buku Transaksi: potongan tetap tercatat sebagai diskon order; nilai hangus hanya informasi (bukan uang masuk baru).

---

## 9. Keputusan & Asumsi

| # | Pertanyaan | Keputusan / nilai awal | Sumber | Bisa diubah di |
| :--- | :--- | :--- | :--- | :--- |
| 1 | Voucher saldo dibatalkan / checkout kedaluwarsa: voucher kembali? | Ya, nilai penuh & masa berlaku semula — juga untuk promo | Tim 18 Okt | Aturan tetap T2 |
| 2 | Pajak & biaya layanan sebelum / sesudah potongan? | Sesudah (seperti sekarang) | Asumsi | Menu Biaya & Pajak |
| 3 | Masa berlaku voucher saldo? | 6 bulan | Asumsi | Pengaturan Voucher Saldo |
| 4 | Voucher saldo untuk apa? | Booking padel (lapangan + add-on) | Asumsi | — |
| — | Sisa voucher saldo? | Hangus | PM 5 Okt + tim 18 Okt | Aturan tetap T1 |
| 5 | Cara sebar promo? | Dipilih per promo (A / B / C / D); awal A | Tim 18 Okt | Per promo |
| 6 | Batas per customer? | 1x per akun per promo, tetap | Tim 18 Okt | Aturan tetap T3 |
| 7 | Online / kasir? | Dipilih per promo; awal keduanya | Asumsi | Per promo |
| 8 | Berlaku untuk apa? | Sewa lapangan padel (tanpa add-on); F&B disiapkan | Tim 18 Okt | Per promo |
| 9 | Digabung benefit member / voucher sponsor? | Tidak (saklar per promo) | Asumsi | Per promo |
| 10 | Siapa membuat promo, perlu persetujuan? | Staf marketing (`manage_vouchers`), langsung aktif, tercatat di log | Asumsi | Roles & Hak Akses |
| 11 | Happy hour? | Ditunda | Tim 18 Okt | — |

**Yang perlu dikonfirmasi PM:** nomor 2, 3, 4, 7, 9, 10 (sudah ada nilai awal, tidak menghambat pengerjaan).

---

## 10. Rencana Implementasi

| Tahap | Isi | Perkiraan |
| :--- | :--- | :--- |
| 1 | Voucher saldo: sekali pakai + hangus (kolom nilai hangus) + peringatan sisa hangus, kembali utuh saat kedaluwarsa / batal, masa berlaku di pengaturan, kolom hangus di Daftar Voucher | ½–1 hari |
| 2 | Halaman Marketing (ganti tampilan contoh): buat / ubah / nonaktifkan promo **cara A**, semua isian §4.2, kategori `PADEL` (sewa lapangan saja), T3 1x per akun, laporan per promo | 1–1½ hari |
| 3 | Cara **B** (kode unik massal + CSV) dan **C** (klaim → Voucher Saya) | 1–1½ hari |
| 4 | Cara **D** (akun baru dulu; ulang tahun & referral butuh data baru), F&B setelah kasir F&B tersambung, happy hour | Nanti |

## 11. Catatan Teknis untuk Tim Dev
- Semua aturan di `VoucherService::resolve()` — satu tempat untuk online & kasir.
- Pemotongan kuota / saldo tetap sekali, di `PaymentOrchestratorService::markOrderAsPaid` (pembayaran sukses pertama) lewat `VoucherService::consume()`; voucher saldo langsung habis + nilai hangus dicatat.
- T2 (kembali utuh): pemesanan voucher dihitung dari order yang belum dibayar (pola yang sudah ada) — order kedaluwarsa / batal otomatis melepasnya; voucher yang sudah *consume* lalu bookingnya dibatalkan dikembalikan di jalur pembatalan / refund.
- T3 menghitung order lunas + order menunggu bayar milik akun dengan promo yang sama (locking read, sama dengan kuota).
- Cara B: tabel kode turunan per kampanye (atau baris `vouchers` per kode dengan `campaign_id`). Cara C / D: tabel `user_vouchers` (user, voucher, diklaim, dipakai di order) — Voucher Saya membaca tabel ini.
- Kategori: potongan dihitung dari nilai item yang kategorinya dicentang (order item `PADEL` court fee, `EQUIPMENT` untuk add-on, `FNB`).
