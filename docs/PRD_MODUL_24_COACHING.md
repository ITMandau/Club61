# Product Requirements Document (PRD)
## Modul 24: Coaching — Booking Sesi Coach, Setoran Lapangan & Payout Coach
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-24-COACHING` |
| **Versi** | `v0.1.0-DRAFT` |
| **Status** | **Draft — menunggu jawaban PM** (6 Okt 2026). Bagian bertanda **[ASUMSI]** dipakai sementara dan berubah kalau jawaban PM berbeda (§10). |
| **Sumber Requirement** | Daftar harga coaching dari PM (Okt 2026): setoran lapangan Rp100.000/jam (06:00–17:00) dan Rp150.000/jam (17:00–00:00); harga ke customer maksimal Rp400.000/sesi; 1 coach untuk maksimal 2 member tanpa biaya tambahan; profit academy dipakai untuk pengembangan coach; logo Club 61 di jersey tanding coach. |
| **Dependensi Teknis** | `PRD_MODUL_04` (booking lapangan padel), `PRD_MODUL_17` (Buku Transaksi), `PRD_MODUL_21_REFUND_NO_SHOW_PEMBAYARAN_BERMASALAH.md` (refund, no-show, reschedule), `PRD_MODUL_09_DYNAMIC_RBAC_FILAMENT_SHIELD.md` (role & izin), `PeakHourService` (jam reguler/peak). |
| **Prinsip Utama** | **UANG MASUK SATU PINTU**: pembayaran coaching hanya lewat Midtrans (online) atau kasir POS (walk-in), sama seperti booking lapangan. Bagian coach dicatat sebagai utang Club dan dibayar lewat payout. |

---

## 1. Ringkasan

Club 61 bekerja sama dengan coach / academy partner yang mengajar padel memakai lapangan Club 61. Coach **bukan karyawan**. Club mendapat **setoran lapangan** per jam, coach mendapat sisa harga sesi.

Modul ini menyediakan:
1. data coach partner (profil, harga sesi, jadwal tersedia, akun login);
2. booking sesi coaching oleh customer (app) dan kasir (POS Walk-In) — lapangan + jadwal coach dikunci sekaligus;
3. pencatatan uang: setoran lapangan = pendapatan Club, bagian coach = utang ke coach;
4. rekap & payout coach per periode;
5. portal coach: jadwal, check-in murid, pendapatan.

Di luar sistem (kesepakatan bisnis, **tidak dibuat fiturnya**): penggunaan profit academy untuk pengembangan coach, dan logo Club 61 di jersey tanding.

---

## 2. Aturan Harga

| Jam mulai sesi | Customer bayar | Setoran ke Club | Bagian coach |
| :--- | ---: | ---: | ---: |
| 06:00–16:59 | maks Rp400.000 | Rp100.000 | Rp300.000 |
| 17:00–23:59 | maks Rp400.000 | Rp150.000 | Rp250.000 |

- **[ASUMSI]** 1 sesi = 1 jam. Booking 2 jam = 2 sesi (harga & setoran dikali 2). Sesi tidak bisa dipecah.
- **[ASUMSI]** Rp400.000 sudah **termasuk** lapangan. Customer tidak membayar sewa lapangan terpisah.
- Coach boleh memasang harga **lebih rendah** dari Rp400.000 (diatur admin per coach), tidak boleh lebih tinggi. Batas maks disimpan sebagai pengaturan, bukan angka mati di kode.
- Tarif setoran disimpan sebagai pengaturan (2 rentang jam di atas), bisa diubah admin. **[ASUMSI]** Rentang ini berlaku semua hari, tidak ikut aturan weekend = peak di `PeakHourService` (lihat pertanyaan §10 no. 7).
- 1 sesi = 1 coach, maksimal **2 murid** tanpa biaya tambahan. Murid ke-3 tidak bisa ditambahkan.
- Biaya layanan & pajak mengikuti pengaturan biaya yang sudah ada (seperti booking lapangan). **[ASUMSI]** Potongan biaya Midtrans ditanggung Club, tidak memotong bagian coach (§10 no. 5).

---

## 3. Data Coach

Kolom `padel_bookings.coach_id` saat ini mengarah ke `staff_profiles` (karyawan) dan `coach_fee` belum pernah dipakai. Karena coach adalah partner luar, dibuat data tersendiri:

**Tabel `coaches`**
| Kolom | Keterangan |
| :--- | :--- |
| `id` (ULID) | |
| `user_id` | Akun login coach (role baru `coach`), nullable sampai akun dibuat |
| `academy_name` | Nama academy, nullable untuk coach independen |
| `name`, `phone`, `photo_path`, `bio`, `level` | Profil yang tampil ke customer |
| `session_price` | Harga per sesi ke customer (≤ batas maks) |
| `is_active` | Coach nonaktif tidak bisa dibooking |
| `payout_bank_name`, `payout_account_number`, `payout_account_name` | Rekening payout |

**Tabel `coach_availabilities`** — jadwal mingguan coach (hari, jam mulai, jam selesai) + pengecualian tanggal (cuti/libur).

Migrasi: `padel_bookings.coach_id` dipindah foreign key-nya ke `coaches`. Kolom `coach_fee` diisi **bagian coach** per booking (snapshot saat bayar). Tambah kolom `coaching_court_fee` (setoran Club, snapshot) dan `coaching_students` (1–2, JSON nama murid).

---

## 4. Alur Booking

### 4.1 Customer (web / app)
1. Menu **Coaching** → daftar coach aktif (foto, academy, harga sesi, level).
2. Pilih coach → tanggal → slot jam yang kosong **untuk coach DAN minimal satu lapangan** → jumlah murid (1–2, isi nama murid ke-2).
3. Lapangan dipilih otomatis (lapangan kosong pertama). **[ASUMSI]** Customer tidak memilih lapangan sendiri.
4. Checkout & bayar lewat Midtrans, alur hold sama dengan booking lapangan (slot ditahan selama batas waktu pembayaran).
5. Setelah lunas: tiket QR terbit seperti booking lapangan, lapangan & jadwal coach terkunci.

### 4.2 Kasir (POS Walk-In)
- Tab/mode **Coaching** di POS Walk-In: pilih coach → slot → murid → bayar dengan metode POS yang sudah ada (tunai, EDC, QRIS). Uang hanya diterima di POS, sesuai aturan pembayaran Club 61.

### 4.3 Bentrok
- Jadwal coach dicek dengan index `idx_padel_coach_schedule`. Coach tidak bisa punya dua sesi di jam yang sama.
- Lapangan dicek dengan aturan booking yang sudah ada (termasuk hold & tidak boleh dobel).
- Booking coaching muncul di grid jadwal lapangan dengan label "Coaching – nama coach".

---

## 5. Pencatatan Keuangan

- **Pendapatan Club** di Buku Transaksi = setoran lapangan (+ biaya layanan bila ada). Kategori baru **Coaching**.
- **Bagian coach** dicatat sebagai **utang ke coach** (bukan pendapatan Club) sampai payout dibayar.
- Struk & invoice coaching memakai layout struk yang sama (Modul 18), dengan baris "Sesi coaching – nama coach".
- Analytics: baris "Pelatih & Coaching Session" (sekarang "Menyusul") diisi jumlah sesi, pendapatan setoran, dan total utang coach.

---

## 6. Payout Coach

- Halaman admin **Payout Coach**: per periode (**[ASUMSI]** bulanan), per coach: jumlah sesi selesai, total bagian coach, potongan (kalau ada), total dibayar.
- Hanya sesi yang **sudah dimainkan** (bukan dibatalkan/refund) yang masuk payout.
- Tombol **Tandai Sudah Dibayar** (wajib isi tanggal transfer + bukti), tercatat di Log Aktivitas. Konfirmasi memakai modal konfirmasi Filament.
- Export rekap per coach (PDF/Excel) untuk dikirim ke coach / academy.
- **[ASUMSI]** Payout ke masing-masing coach. Kalau coach tergabung di academy, PM menentukan apakah payout digabung ke academy (§10 no. 4).

---

## 7. Refund, No-Show & Reschedule

Mengikuti `PRD_MODUL_21`:
- Customer batal / refund: setoran Club dan bagian coach ikut batal; tidak masuk payout.
- Customer no-show: **[ASUMSI]** sesi dianggap terjadi — Club tetap dapat setoran, coach tetap dapat bagiannya.
- **Coach berhalangan / batal**: customer dapat reschedule gratis atau refund penuh; sesi tidak masuk payout.
- Reschedule harus mengecek jadwal coach dan lapangan baru sekaligus.

---

## 8. Portal Coach

Role baru `coach` (Filament Shield), hanya melihat datanya sendiri:
- **Jadwal Saya**: daftar sesi (tanggal, jam, lapangan, nama murid).
- **Check-in murid**: scan QR tiket atau tombol hadir → sesi berstatus dimainkan.
- **Pendapatan Saya**: sesi per periode, bagian coach, status payout.
- Coach **tidak** bisa melihat data keuangan Club atau customer lain, dan tidak bisa menerima pembayaran.

---

## 9. Di Luar Lingkup Versi Ini

- Paket coaching (misal 4 sesi / bulan) dan kelas grup > 2 murid.
- Coach menjual langsung ke muridnya lalu membayar sewa lapangan tarif partner di kasir (alur opsional, rawan disalahgunakan untuk main biasa dengan tarif murah) — hanya dibuat kalau PM memintanya, dengan syarat nama murid wajib diisi dan diverifikasi kasir.
- Rating / ulasan coach.

---

## 10. Pertanyaan untuk PM

| # | Pertanyaan | Asumsi sementara di PRD ini |
| :--- | :--- | :--- |
| 1 | Uang dari customer masuk ke siapa: ke Club (Club bayar coach), ke coach langsung (coach setor sewa ke Club), atau keduanya boleh? | Masuk ke Club, coach dibayar lewat payout |
| 2 | Rp400.000 per sesi sudah termasuk lapangan? | Ya, sudah termasuk |
| 3 | 1 sesi = 1 jam? Booking 2 jam dihitung 2 sesi? | Ya |
| 4 | Coach dari satu academy atau beberapa coach independen? Payout ke academy atau ke tiap coach? | Payout ke tiap coach |
| 5 | Payout tiap kapan? Ada potongan (biaya Midtrans, pajak, dll.) dan siapa yang menanggung? | Bulanan, potongan Midtrans ditanggung Club |
| 6 | Member dapat benefit coaching (diskon / kuota sesi di paket membership)? | Belum ada benefit |
| 7 | Rentang setoran 06–17 / 17–00 berlaku juga saat weekend, atau weekend selalu tarif Rp150.000? | Berlaku sama semua hari |
| 8 | Customer no-show: coach tetap dibayar penuh? | Ya, Club & coach tetap dapat bagiannya |
| 9 | Customer boleh memilih lapangan sendiri, atau cukup otomatis? | Otomatis |

---

## 11. Checklist Implementasi (setelah PM menjawab)

- [ ] Tabel `coaches` + `coach_availabilities`, pindah FK `padel_bookings.coach_id`, kolom snapshot setoran & murid.
- [ ] Pengaturan tarif setoran per rentang jam + batas maks harga sesi.
- [ ] Admin: kelola coach (profil, harga, jadwal, rekening, akun login).
- [ ] Booking coaching customer (web/app) + API untuk Flutter.
- [ ] Mode Coaching di POS Walk-In.
- [ ] Buku Transaksi kategori Coaching (setoran = pendapatan, bagian coach = utang) + Analytics.
- [ ] Halaman Payout Coach + export.
- [ ] Role & portal coach (jadwal, check-in, pendapatan).
- [ ] Refund / reschedule / no-show mengikuti Modul 21.
- [ ] Test: bentrok jadwal coach & lapangan, maksimal 2 murid, perhitungan setoran per rentang jam, payout hanya sesi yang dimainkan.
