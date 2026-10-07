# Product Requirements Document (PRD)
## Modul 22: Voucher Saldo & Voucher Promo Marketing
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-22-VOUCHER-DAN-PROMO` |
| **Versi** | `v0.1.0-DRAFT` |
| **Status** | **Draft, menunggu arahan PM (5 Okt 2026).** Fondasi teknis sudah ada (§2). Satu keputusan sudah ada tapi **belum diterapkan**: voucher saldo sekali pakai (§3). Skema promo marketing belum diputuskan (§4). |
| **Sumber Requirement** | PM (5 Okt 2026): voucher dari refund yang ditolak **habis sekali pakai** — sisa nilainya hangus, bukan disimpan. Skema promo marketing (kuota, disebar lewat sosmed, cara customer menyimpan voucher) menunggu arahan berikutnya. |
| **Dependensi Teknis** | `PRD_MODUL_21_REFUND_NO_SHOW_PEMBAYARAN_BERMASALAH.md` (asal voucher saldo), `PRD_MODUL_17_BUKU_TRANSAKSI_TERPADU.md` (pencatatan potongan), `PRD_MODUL_10_UNIFIED_PAYMENT_GATEWAY.md` (checkout online), `PRD_MODUL_11_WALK_IN_OFFLINE_BOOKING.md` (POS kasir). |
| **Prinsip Utama** | **SATU TABEL VOUCHER, SATU MESIN HITUNG** untuk online & kasir; **VOUCHER BUKAN UANG MASUK BARU** (tercatat sebagai potongan); **ATURAN DICEK DI SERVER**, bukan di halaman. |

---

## 1. Ringkasan

Ada dua jenis voucher dengan tujuan berbeda:

| | Voucher Saldo | Voucher Promo |
| :--- | :--- | :--- |
| Asal | Otomatis saat refund **ditolak** di Antrian Refund (Modul 21) | Dibuat tim marketing |
| Isi | Nilai rupiah sebesar refund yang ditolak | Potongan persen / rupiah |
| Pemilik | Satu customer (terikat ke akunnya) | Umum, atau per customer (tergantung skema §4) |
| Sifatnya | Uang customer yang disimpan klub | Biaya promosi klub |
| Status | Jalan, aturan pakainya perlu diubah (§3) | Mesin ada, halaman pembuatnya belum (§4) |

---

## 2. Fondasi yang Sudah Ada (per 5 Okt 2026)

| Bagian | Yang sudah jalan |
| :--- | :--- |
| Data | Tabel `vouchers`: kode, jenis (`PERCENT` / `FIXED` / `CREDIT`), nilai, minimal transaksi, maksimal potongan, kuota, masa berlaku, aktif/nonaktif, pemilik (`user_id`), asal refund (`refund_id`), sisa saldo (`balance`). `orders.voucher_code` + `orders.discount_amount`. |
| Mesin hitung | `App\Services\Finance\VoucherService` — dipakai checkout online **dan** POS Walk-In: validasi (aktif, kedaluwarsa, pemilik, kuota, minimal transaksi), besar potongan, pemotongan kuota / saldo saat lunas, pengembalian saldo saat booking batal. |
| Checkout online | Kolom kode voucher dicek ke server (`POST /api/v1/padel/vouchers/check`); voucher saldo milik customer tampil otomatis (`GET /api/v1/padel/vouchers/mine`). (Dulu kolom ini palsu: kode ditulis di JavaScript, potongan selalu Rp40.000.) |
| POS Walk-In | Kolom voucher + daftar voucher saldo customer terpilih. Tagihan yang ditutup penuh voucher lunas tanpa bukti EDC/QRIS. Struk menampilkan potongan. |
| Customer | Halaman My Club menampilkan voucher saldo; tiket booking yang refund-nya ditolak menampilkan kode vouchernya; email pemberitahuan saat voucher terbit. |
| Admin | **Daftar Voucher** (menu Keuangan, superadmin): semua voucher, cari & filter, ringkasan saldo aktif / terbit / terpakai / hangus, riwayat pemakaian, nonaktifkan. |
| Pembukuan | Potongan voucher tercatat di Buku Transaksi sebagai diskon order — uang tidak dihitung dua kali. |
| Belum ada | Halaman **membuat** voucher promo (menu Marketing masih tampilan contoh, angkanya tidak nyata), batas pemakaian per customer, jam/hari berlaku, voucher di F&B & penjualan membership. |

---

## 3. Voucher Saldo — Sekali Pakai (keputusan PM, BELUM diterapkan)

### 3.1 Aturan
- Voucher saldo **hanya bisa dipakai satu kali**.
- Nilai voucher **lebih besar** dari tagihan → tagihan jadi Rp0, **sisa nilainya hangus**.
  Contoh: voucher Rp1.050.000, booking Rp950.000 → bayar Rp0, sisa Rp100.000 hangus.
- Nilai voucher **lebih kecil** dari tagihan → voucher habis, customer membayar sisanya (online via Midtrans / di kasir).
- **Perilaku sekarang berbeda:** sisa saldo tetap tersimpan dan bisa dipakai lagi. Perlu diubah sebelum / sesudah launch sesuai arahan PM.

### 3.2 Perubahan yang dibutuhkan (perkiraan ± setengah hari)
| Bagian | Perubahan |
| :--- | :--- |
| Mesin hitung | Saat order lunas, voucher saldo langsung habis (saldo 0); nilai yang hangus dicatat (kolom baru, mis. `forfeited_amount`). |
| Checkout & kasir | Peringatan sebelum dipakai kalau voucher lebih besar dari tagihan: *"Sisa Rp100.000 akan hangus. Lanjutkan?"* Customer boleh memilih tidak memakai voucher dulu (mis. menambah jam / add-on supaya tidak ada yang hangus). |
| Daftar Voucher | Kolom "hangus karena sisa" + masuk ringkasan "saldo hangus". |
| Pembukuan | Bagian yang hangus **bukan uang masuk baru** (sudah tercatat saat pembayaran awal) — cukup informasi, sama seperti selisih reschedule hangus. |

### 3.3 Pertanyaan untuk PM
| # | Pertanyaan | Usulan default |
| :--- | :--- | :--- |
| 1 | Booking yang dibayar pakai voucher saldo lalu **dibatalkan lagi**: voucher dikembalikan? | Ya, voucher aktif lagi dengan nilai penuh & masa berlaku semula |
| 2 | Biaya layanan & pajak dihitung **sebelum** atau **sesudah** voucher? (Sekarang: sesudah — tagihan yang ditutup penuh voucher tidak kena biaya layanan.) | Sesudah (seperti sekarang) |
| 3 | Masa berlaku voucher saldo? (Sekarang 6 bulan.) | 6 bulan |
| 4 | Bisa dipakai untuk apa? (Sekarang: booking padel, online & kasir.) | Booking padel saja |

---

## 4. Voucher Promo Marketing — Skema (USULAN, menunggu arahan PM)

### 4.1 Cara menyebar voucher — pilih satu atau lebih

| Skema | Cara kerja | Cocok untuk | Customer menyimpan voucher di mana |
| :--- | :--- | :--- | :--- |
| **A. Kode umum** | Satu kode (mis. `CLUB61OPEN`) disebar di Instagram / TikTok / WhatsApp; kuota total (mis. 100 pemakaian pertama) + batas per akun. | Promo launching, kampanye sosmed | Tidak perlu disimpan — diketik saat checkout / disebut di kasir |
| **B. Kode unik** | Sistem membuat banyak kode berbeda sekaligus (mis. 50 kode `EVT-XXXX`), masing-masing sekali pakai; daftar kodenya diunduh (CSV) untuk dibagikan. | Event, influencer, hadiah turnamen, kerja sama brand | Dipegang customer (kertas / pesan); diketik saat checkout |
| **C. Klaim ke akun** | Banner / tombol "Klaim Voucher" di aplikasi atau link dari sosmed; voucher masuk ke **dompet voucher** akun customer sampai kuota klaim habis. | Promo yang ingin mengumpulkan pendaftar akun, retensi | Dompet voucher di My Club, otomatis muncul di checkout (seperti voucher saldo sekarang) |
| **D. Otomatis** | Voucher terbit sendiri karena kejadian tertentu: customer baru, ulang tahun, ajak teman (referral). | Retensi jangka panjang | Dompet voucher |

**Rekomendasi tim dev:** **A** untuk launching (paling cepat, mesin sudah siap — tinggal halaman pembuatnya + batas per akun). **C** menyusul kalau PM ingin promo yang mendorong customer membuat akun. **B** dan **D** setelah ada kebutuhan nyata.

### 4.2 Aturan yang bisa dipasang per voucher

| Aturan | Sudah ada | Perlu dibuat |
| :--- | :--- | :--- |
| Potongan persen / rupiah, maksimal potongan, minimal transaksi | ✓ | |
| Kuota total pemakaian, masa berlaku sampai tanggal | ✓ | |
| Mulai berlaku dari tanggal | | ✓ |
| Batas pemakaian **per customer** (mis. 1x per akun) | | ✓ |
| Berlaku di: online / kasir / keduanya | | ✓ |
| Berlaku untuk: sewa lapangan / add-on / F&B / membership | | ✓ (sekarang: lapangan + add-on padel) |
| Jam & hari tertentu (happy hour, Senin–Jumat 14.00–17.00) | | ✓ |
| Khusus customer baru (belum pernah booking) | | ✓ |
| Boleh digabung dengan diskon member / voucher sponsor | sekarang **boleh** (voucher dipotong setelah benefit member) | keputusan PM |

### 4.3 Halaman admin
- **Marketing** (izin `manage_vouchers`, bisa diberikan ke staf marketing): buat / ubah / nonaktifkan kode promo, lihat jumlah terpakai & total potongan per promo. Menggantikan tampilan contoh yang sekarang.
- **Daftar Voucher** (menu Keuangan, superadmin): pengawasan semua voucher — promo dan voucher saldo customer — dari tabel yang sama. Promo yang dibuat di Marketing otomatis tampil di sini; data tidak dobel.

### 4.4 Pembukuan & laporan
- Potongan promo = diskon order (sudah berjalan). Pendapatan bersih di Buku Transaksi / Analytics sudah setelah potongan.
- Laporan baru (usulan): **total potongan per kode promo** & jumlah order yang memakainya — untuk menilai efektivitas kampanye.
- Pajak & biaya layanan dihitung dari harga setelah potongan promo (praktik umum).

### 4.5 Pertanyaan untuk PM
| # | Pertanyaan | Usulan default |
| :--- | :--- | :--- |
| 5 | Skema sebar mana yang dipakai saat launching (A / B / C / D)? | A (kode umum) |
| 6 | Batas pemakaian per customer? | 1x per akun per kode |
| 7 | Berlaku online saja, kasir saja, atau keduanya? | Keduanya |
| 8 | Berlaku untuk apa saja (lapangan / add-on / F&B / membership)? | Lapangan + add-on padel |
| 9 | Boleh digabung dengan diskon member / voucher sponsor? | Tidak — voucher promo tidak berlaku untuk booking yang sudah memakai benefit member / sponsor |
| 10 | Siapa yang boleh membuat promo? Perlu persetujuan owner sebelum aktif? | Staf marketing membuat, langsung aktif; semua perubahan tercatat di Log Aktivitas |
| 11 | Perlu promo jam / hari tertentu (happy hour) saat launching? | Belum |

---

## 5. Rencana Implementasi (setelah arahan PM)

| Tahap | Isi | Perkiraan |
| :--- | :--- | :--- |
| 1 | Voucher saldo sekali pakai (§3.2) | ½ hari |
| 2 | Halaman Marketing: buat / ubah / nonaktifkan kode promo + batas per akun + mulai berlaku + kanal online/kasir | 1 hari |
| 3 | Dompet voucher & klaim (skema C) | 1 hari |
| 4 | Kode unik massal + unduh CSV (skema B), jam/hari berlaku, khusus customer baru | 1 hari |
| 5 | Voucher di F&B & penjualan membership, laporan efektivitas promo | 1 hari |

## 6. Catatan Teknis untuk Tim Dev
- Semua aturan baru masuk ke `VoucherService::resolve()` — satu tempat untuk online & kasir; halaman hanya menampilkan hasil cek server.
- Pemotongan kuota / saldo tetap sekali, di `PaymentOrchestratorService::markOrderAsPaid` (pembayaran sukses pertama order) lewat `VoucherService::consume()`.
- Batas per customer menghitung order lunas + order belum dibayar milik akun itu dengan kode yang sama (pola "dipesan" yang sama dengan kuota sekarang).
- Skema C butuh tabel klaim (`user_vouchers`: user, voucher, diklaim, dipakai di order) — voucher saldo sekarang bisa dipindah ke pola yang sama.
