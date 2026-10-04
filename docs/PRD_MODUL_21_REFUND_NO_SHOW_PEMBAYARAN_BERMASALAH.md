# Product Requirements Document (PRD)
## Modul 21: Kebijakan Refund, Booking Hangus (No-Show) & Pembayaran Bermasalah
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-21-REFUND-NOSHOW-PEMBAYARAN-BERMASALAH` |
| **Versi** | `v0.1.0-DRAFT` |
| **Status** | **Draft, menunggu keputusan PM (4 Okt 2026).** Belum dikerjakan. Angka di dokumen ini (persen potongan, batas jam, biaya) adalah **usulan**, bukan keputusan. Daftar pertanyaan ada di §9. |
| **Sumber Requirement** | PM: "Skema refund dibuat lebih sulit supaya customer berpikir dua kali." Owner: pembatalan dari Kelola Pemesanan cukup jadi **pengajuan** yang disetujui di Antrian Refund; kasus yang paling sering terjadi adalah **saldo customer sudah terpotong tapi pembayaran belum masuk ke sistem** (Midtrans maupun kasir); customer yang tidak datang (no-show) dan memaksa minta ganti jadwal. |
| **Dependensi Teknis** | `PRD_MODUL_17_BUKU_TRANSAKSI_TERPADU.md` (Buku Transaksi & Antrian Refund — satu-satunya tempat uang keluar dicatat), `PRD_MODUL_10_UNIFIED_PAYMENT_GATEWAY.md` (pelunasan & rekonsiliasi Midtrans), `PRD_MODUL_11_WALK_IN_OFFLINE_BOOKING.md` (POS kasir), `PRD_MODUL_16_ACTIVITY_AUDIT_LOG.md` (jejak audit). |
| **Prinsip Utama** | **UANG MASUK HANYA LEWAT POS KASIR / MIDTRANS**, **UANG KELUAR HANYA LEWAT ANTRIAN REFUND**, **REFUND BUKAN HAK OTOMATIS**, **SETIAP PENGECUALIAN BERBAYAR / BERIZIN & TERCATAT**, **UANG YANG BELUM TERKONFIRMASI TIDAK MASUK BUKU**. |

---

## 1. Kondisi Sekarang (per 4 Okt 2026)

| Jalur | Siapa | Yang terjadi sekarang | Masalah |
| :--- | :--- | :--- | :--- |
| Kelola Pemesanan → Batalkan + Refund | super_admin | Booking batal, refund langsung **PROCESSED** & tercatat di Buku Transaksi | Satu orang memutuskan sekaligus mencatat uang keluar; tidak ada persetujuan kedua |
| Customer ajukan refund dari web (minimal H-24) | Customer | Status booking jadi `REFUND_PENDING` | **Tidak membuat catatan refund** → tidak muncul di Antrian Refund; kalau admin lupa, booking menggantung selamanya |
| Refund otomatis (bayar dobel, uang Midtrans masuk setelah booking batal / hangus) | Sistem | Refund **PENDING** masuk Antrian Refund | Jarang terjadi, tapi kasus "uang telat masuk" (§6.1) justru berakhir di sini |
| Customer tidak datang sampai jam main habis | Sistem | Booking lunas otomatis jadi **EXPIRED (hangus)**; tidak bisa di-reschedule / di-refund | Sudah benar |
| Customer telat, **jam main sudah mulai tapi belum habis** | super_admin | Booking masih `PAID` → **masih bisa di-reschedule gratis atau di-refund penuh** | **Celah**: jam main yang sudah berjalan bisa dipindah tanpa biaya |
| Pengecekan hangus otomatis terlambat jalan (scheduler mati) | super_admin | Booking yang jamnya sudah lewat masih `PAID` → bisa di-reschedule / refund | **Celah** yang sama |
| Saldo customer terpotong tapi pembayaran tidak masuk | Kasir / CS | **Tidak ada pencatatan** sama sekali | Tidak ada jejak, kasir bisa menggesek ulang (customer terpotong dua kali) |
| Reschedule oleh customer sendiri | — | **Belum ada** (hanya admin) | — |

---

## 2. Prinsip

1. **Satu pintu uang keluar.** Semua pengembalian uang hanya dicairkan dari **Antrian Refund** oleh pemegang izin `process_refund_queue`, lengkap dengan metode & nomor referensi transfer. Halaman lain hanya boleh **mengajukan**. (Pasangan dari aturan yang sudah berlaku: uang masuk hanya di POS kasir.)
2. **Refund bukan hak otomatis.** Default pembatalan adalah *tidak ada refund uang*; refund hanya untuk kondisi yang memenuhi aturan (§4) atau kesalahan dari pihak venue.
3. **Pengecualian harus berbayar atau berizin, dan selalu tercatat.** Tidak ada "tolong dong" yang bisa dieksekusi staf tanpa jejak di Log Aktivitas.
4. **Uang yang belum terkonfirmasi tidak masuk Buku Transaksi.** Laporan "pembayaran bermasalah" (§6) hanya catatan kasus; buku baru berubah saat uang benar-benar terbukti masuk / keluar.

---

## 3. Skema A — Refund Dua Langkah (Pengajuan → Persetujuan)

```
 Kelola Pemesanan            Antrian Refund                         Buku Transaksi
 "Ajukan Refund"  ───────►  MENUNGGU ──► Proses (metode + no. ref) ──► baris refund (uang keluar)
 Web customer (H-24) ────►     │
 Sistem (bayar dobel) ───►     └──────► Tolak (alasan wajib) ─────► tidak ada perubahan buku
```

| Langkah | Siapa (usulan) | Yang terjadi |
| :--- | :--- | :--- |
| 1. Ajukan | Admin / frontdesk dengan izin `request_refund` (baru) dari Kelola Pemesanan; customer dari web | Booking **dibatalkan & slot dilepas** (lihat pertanyaan PM no. 2), refund dibuat berstatus **MENUNGGU** dengan nominal sesuai aturan §4, alasan & kategori wajib |
| 2a. Proses | Owner / Finance dengan izin `process_refund_queue` | Uang dikembalikan di luar sistem (transfer / void EDC / refund Midtrans), lalu diisi metode + nomor referensi → refund **DIPROSES** → tercatat di Buku Transaksi |
| 2b. Tolak | Owner / Finance | Alasan wajib; refund **DITOLAK**; tidak ada uang keluar |

- Pengaju dan penyetuju **boleh / tidak boleh** orang yang sama → pertanyaan PM no. 3.
- Refund di atas nominal tertentu butuh dua persetujuan → pertanyaan PM no. 4.
- Semua langkah tercatat di Log Aktivitas (severity KRITIS untuk proses & tolak).
- Pengajuan H-24 dari customer otomatis masuk antrian (menutup celah §1 baris 2).

---

## 4. Skema B — Aturan Refund untuk Customer (USULAN, angka menunggu PM)

| Waktu pembatalan sebelum jam main | Hasil (usulan) |
| :--- | :--- |
| ≥ 48 jam (H-2) | Refund **dipotong 25%** dari harga lapangan; biaya layanan & biaya gateway tidak dikembalikan |
| 24–48 jam | **Tidak ada refund uang**; hanya boleh **reschedule 1x** (oleh admin, selisih harga mengikuti aturan reschedule yang berlaku) |
| < 24 jam | Tidak ada refund, tidak ada reschedule |
| Tidak datang (no-show) | **Hangus** (lihat §5) |
| Gangguan dari pihak venue (lapangan rusak, listrik padam, venue tutup mendadak) | **Refund penuh** atau reschedule gratis, kategori khusus "Kesalahan Venue" |

Catatan:
- Booking yang dibayar dengan **kuota membership / voucher sponsor**: tidak ada uang yang dikembalikan; hanya jam kuota yang dikembalikan (sudah berjalan sekarang) dan mengikuti batas waktu yang sama.
- Aturan ditampilkan di halaman checkout & invoice, dan customer wajib mencentang "Saya setuju dengan kebijakan pembatalan" sebelum bayar (usulan).
- Pengembalian bisa berupa uang **atau** saldo/kredit booking — **saldo/kredit belum ada di sistem** (fitur baru, lihat pertanyaan PM no. 7).

---

## 5. Skema C — Booking Hangus (No-Show) & Customer yang Memaksa Ganti Jadwal

### 5.1 Aturan dasar (usulan)
- Customer tidak check-in sampai jam main **habis** → **hangus**, tanpa refund & tanpa reschedule (sudah berjalan).
- Customer datang **terlambat** saat jam main sedang berjalan → boleh main **sisa waktunya saja**, tanpa perpanjangan.
- **Begitu jam main dimulai, reschedule & refund biasa dikunci** (menutup celah §1 baris 5–6), terlepas dari apakah pengecekan hangus otomatis sudah jalan.
- Batas akhir reschedule biasa: **X jam sebelum jam main** (usulan: 2 jam) → pertanyaan PM no. 9.

### 5.2 Pengecualian "Reschedule Darurat" — dua opsi untuk PM

| | Opsi 1: Tegas | Opsi 2: Reschedule Darurat Berbayar |
| :--- | :--- | :--- |
| Aturan | Hangus = final, tidak ada pengecualian | Booking hangus boleh dipindah **satu kali** dengan syarat ketat |
| Siapa | — | Hanya pemegang izin baru `reschedule_expired_booking` (owner / manager), **bukan kasir** |
| Batas waktu | — | Maksimal **24 jam** setelah jam main berakhir |
| Biaya | — | **Wajib bayar** (usulan: 50% harga lapangan), ditagihkan di **POS kasir** seperti tagihan lain; jadwal baru aktif setelah lunas |
| Jadwal baru | — | Maksimal **7 hari** ke depan, tetap tunduk aturan slot biasa |
| Dikecualikan | — | Booking kuota membership / voucher sponsor (mencegah celah main gratis) |
| Jejak | — | Alasan wajib, Log Aktivitas KRITIS, muncul di Buku Transaksi sebagai pembayaran biaya reschedule |
| Risiko | Customer kecewa / komplain | Staf "kasihan" lalu menyalahgunakan; dikendalikan lewat izin, biaya wajib & log |

**Rekomendasi tim dev:** Opsi 1 untuk launching (paling aman dari customer yang memaksa), Opsi 2 bisa ditambahkan setelah ada data seberapa sering kasus ini terjadi.

---

## 6. Skema D — Pembayaran Bermasalah (Saldo Terpotong, Uang Belum Masuk)

### 6.1 Kasus yang ditangani

| Kasus | Yang biasanya terjadi di bank / Midtrans | Perilaku sistem sekarang |
| :--- | :--- | :--- |
| **Online (Midtrans)**: saldo terpotong, status masih *pending* | Midtrans akhirnya *settlement* (lunas) atau gagal; kalau gagal, bank me-reverse saldo customer | Sistem menunggu notifikasi + rekonsiliasi tiap 5 menit. Booking ditahan sampai batas bayar + 15 menit. Kalau uang masuk **setelah** booking hangus → otomatis jadi **refund MENUNGGU** di Antrian Refund (booking tidak aktif lagi) |
| **Kasir EDC / QRIS**: saldo terpotong tapi mesin / aplikasi menyatakan gagal | Bank biasanya me-reverse otomatis (1–14 hari kerja), kadang uang justru masuk ke rekening venue saat settlement | **Tidak tercatat**; kasir berisiko menggesek ulang → customer terpotong dua kali |
| **Transfer manual** yang belum terlihat di mutasi | Masuk saat mutasi diperbarui / salah rekening | Tidak tercatat |

### 6.2 Alur yang diusulkan

```
 Kasir / CS: "Laporkan Pembayaran Bermasalah"
        │  (nominal, metode, bank/penyedia, waktu, RRN/approval/VA, foto bukti, order/booking terkait)
        ▼
   DILAPORKAN ──► DICEK oleh Finance (mutasi bank, settlement EDC, dashboard Midtrans)
                     │
                     ├─► UANG MASUK, transaksi belum lunas   → dilunasi di POS kasir dengan bukti yang sama (tanpa gesek ulang)
                     ├─► UANG MASUK, transaksi sudah lunas lewat cara lain → otomatis jadi refund MENUNGGU (Antrian Refund)
                     ├─► UANG TIDAK MASUK / sudah di-reverse bank → DITUTUP, customer diarahkan ke bank-nya
                     └─► BELUM JELAS lewat batas waktu (usulan 3 hari kerja) → dieskalasi ke owner
```

- Menu baru **"Pembayaran Bermasalah"** di grup Keuangan + tombol lapor di POS Walk-In & POS F&B. Badge jumlah kasus terbuka.
- Satu bukti (RRN / approval code) tidak bisa dipakai untuk dua pelunasan (aturan ini sudah berlaku di POS).
- Laporan **tidak menulis Buku Transaksi**. Buku hanya berubah saat kasus berakhir jadi pelunasan (uang masuk) atau refund diproses (uang keluar).
- Semua perubahan status tercatat di Log Aktivitas.

### 6.3 Perilaku di loket saat kejadian (usulan, perlu keputusan PM)
- Kasir **dilarang menggesek ulang kartu yang sama** sebelum kasus dicek; customer boleh membayar ulang dengan **metode lain** (misal QRIS) setelah menandatangani / menyetujui bahwa kalau uang pertama ternyata masuk, akan dikembalikan lewat Antrian Refund.
- Atau slot ditahan (maksimal N jam) sampai Finance mengonfirmasi → pertanyaan PM no. 15.

---

## 7. Hak Akses (Usulan)

| Izin | Fungsi | Default |
| :--- | :--- | :--- |
| `request_refund` (baru) | Mengajukan pembatalan + refund dari Kelola Pemesanan | super_admin, admin |
| `process_refund_queue` (sudah ada) | Memproses / menolak refund | super_admin |
| `reschedule_expired_booking` (baru, hanya jika Opsi 2) | Reschedule darurat booking hangus | super_admin |
| `report_payment_issue` (baru) | Melaporkan pembayaran bermasalah | super_admin, admin, cashier |
| `resolve_payment_issue` (baru) | Mengecek & menutup kasus pembayaran bermasalah | super_admin |

---

## 8. Rekomendasi Tim Dev (Bisa Dikerjakan Begitu Disetujui, Tidak Bergantung Angka)

1. Kunci reschedule & refund biasa begitu jam main dimulai (menutup celah §1).
2. Pengajuan refund H-24 dari customer otomatis masuk Antrian Refund.
3. Booking `REFUND_PENDING` yang tidak diproses tidak boleh menggantung tanpa batas (tampil di antrian dengan umur pengajuan).
4. Teks kebijakan pembatalan tampil di checkout & invoice customer.

---

## 9. Daftar Pertanyaan untuk PM

**Refund**

| # | Pertanyaan | Opsi / usulan default |
| :--- | :--- | :--- |
| 1 | Setuju refund dua langkah (Kelola Pemesanan hanya mengajukan, uang keluar hanya dari Antrian Refund)? | **Ya** |
| 2 | Kalau pengajuan refund **ditolak**, booking-nya bagaimana? | **(a) Tetap batal** — slot sudah dilepas sejak pengajuan (disarankan, menghindari bentrok); (b) slot baru dilepas setelah disetujui, kalau ditolak booking aktif lagi |
| 3 | Boleh pengaju dan penyetuju orang yang sama (misal owner mengajukan lalu menyetujui sendiri)? | Boleh untuk owner; admin tidak bisa menyetujui pengajuannya sendiri |
| 4 | Perlu persetujuan dua orang untuk refund besar? Batasnya berapa? | Belum perlu di v1 |
| 5 | Batas waktu & potongan refund (§4): H-berapa, potongan berapa persen? | ≥H-2: potong 25%; H-1–H-2: reschedule saja; <H-1: hangus |
| 6 | Biaya layanan & biaya gateway Midtrans (MDR) ikut dikembalikan? | **Tidak** |
| 7 | Refund berupa uang saja, atau juga saldo/kredit booking (fitur baru)? | Uang saja di v1 |
| 8 | Target waktu pencairan refund ke customer (SLA)? | Maks. 3 hari kerja setelah disetujui |

**Booking hangus & reschedule**

| # | Pertanyaan | Opsi / usulan default |
| :--- | :--- | :--- |
| 9 | Batas akhir reschedule biasa sebelum jam main? | 2 jam sebelum main |
| 10 | Booking hangus: Opsi 1 (tegas) atau Opsi 2 (reschedule darurat berbayar)? | **Opsi 1** untuk launching |
| 11 | Kalau Opsi 2: biaya berapa, batas berapa jam setelah hangus, siapa yang boleh? | 50% harga lapangan, maks. 24 jam, owner/manager saja |
| 12 | Customer telat: boleh main sisa waktu saja (tanpa perpanjangan)? | Ya |
| 13 | Customer boleh reschedule sendiri dari web (sekarang hanya lewat admin)? | Belum di v1 |
| 14 | Gangguan dari venue: refund penuh atau reschedule gratis? Siapa yang menetapkan kejadian "kesalahan venue"? | Customer memilih; ditetapkan owner |

**Pembayaran bermasalah**

| # | Pertanyaan | Opsi / usulan default |
| :--- | :--- | :--- |
| 15 | Saat EDC/QRIS gagal tapi saldo terpotong di loket: customer boleh bayar ulang pakai metode lain, atau slot ditahan sampai dicek? | Boleh bayar ulang dengan metode **lain** (bukan kartu yang sama), dengan persetujuan customer bahwa kelebihan dikembalikan lewat refund |
| 16 | Bukti wajib apa? (foto struk EDC / screenshot m-banking / RRN) | Minimal salah satu: RRN/approval code **atau** foto bukti |
| 17 | Siapa yang mengecek mutasi bank & settlement (Finance / owner)? Batas waktu pengecekan? | Owner / Finance, maks. 3 hari kerja |
| 18 | Pembayaran Midtrans yang baru masuk **setelah** booking hangus: booking diaktifkan lagi kalau slot masih kosong, atau tetap dibuatkan refund (perilaku sekarang)? | **Tetap refund** (lebih aman, tidak ada bentrok jadwal) |

---

## 10. Rencana Implementasi (setelah keputusan PM)

| Tahap | Isi | Perkiraan |
| :--- | :--- | :--- |
| 1 | Refund dua langkah + pengajuan customer masuk antrian + kunci reschedule/refund setelah jam main mulai | 1 hari |
| 2 | Aturan refund §4 (hitung nominal otomatis sesuai jarak waktu) + teks kebijakan di checkout/invoice | 1 hari |
| 3 | Pembayaran Bermasalah (tabel, menu, tombol lapor di POS, alur verifikasi) | 1,5–2 hari |
| 4 | (Jika Opsi 2) Reschedule darurat berbayar | 1 hari |

## 11. Dampak ke Modul Lain

- **Modul 02 / 11 (Booking & POS Walk-In):** tombol "Batalkan + Refund" berubah jadi "Ajukan Refund"; reschedule dikunci setelah jam main mulai.
- **Modul 17 (Buku Transaksi):** Antrian Refund menjadi satu-satunya pintu uang keluar; refund tetap tercatat saat **DIPROSES**.
- **Modul 10 (Payment):** pelunasan dari kasus pembayaran bermasalah tetap lewat `PaymentOrchestratorService::markOrderAsPaid`.
- **Modul 16 (Log Aktivitas):** event baru `refund.requested`, `payment_issue.reported`, `payment_issue.resolved`, `booking.emergency_rescheduled`.
