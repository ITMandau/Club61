# Product Requirements Document (PRD)
## Modul 28 — Hasil Meeting Training Club 61 (28 Okt 2026)
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-28-MEETING-TRAINING-28-OKT` |
| **Versi** | `v0.1.1` |
| **Status** | **DRAFT.** Batch 1 sedang dikerjakan; batch lain menunggu keputusan di bagian *Pertanyaan Terbuka*. |
| **Sumber** | Catatan meeting "Training Club61" (28 Okt 2026) dan klarifikasi owner aplikasi di hari yang sama. |
| **Dependensi** | POS Walk-In (Modul 11), Membership (Modul 5), Sponsor / Corporate (Modul 12), Voucher (Modul 22), Coaching Opsi B (Modul 24), Refund & Reschedule (Modul 21), Shift Kasir (Modul 25), Jadwal Tetap Mingguan. |

---

## 1. Ringkasan

| # | Kebutuhan | Kondisi sistem saat ini | Status |
| :--- | :--- | :--- | :--- |
| 1 | Mesin EDC fisik hanya BCA | Kasir masih bisa memilih EDC Mandiri / Lainnya | **Batch 1** |
| 2 | Reschedule maksimal 3x per booking | Jumlah reschedule dicatat, tidak dibatasi | **Batch 1** |
| 3 | Nama "Sauna" diganti "Wellness" | Teks "Sauna" masih tampil di portal customer & admin | **Batch 1** |
| 4 | Hapus menu Revoke di Team sponsor | Tombol Revoke ada di portal corporate | **Batch 1** |
| 5 | ~~Pindah prime → regular: selisih jadi voucher~~ | Selisih HANGUS (kebijakan PM 30 Sep 2026) | **Batal** (28 Okt 2026) — aturan hangus tetap (§3.5) |
| 6 | Aturan akses paket (hari + jam / di luar peak) | Hanya batas jam; tidak dicek untuk karyawan corporate | Batch 2 |
| 7 | Tampilan jadwal mingguan & bulanan | Monitoring & POS hanya harian | Batch 3 |
| 8 | Coaching sistem kontrak (tagihan per periode) | Coaching dibayar per booking | Batch 4 (PRD mini) |
| 9 | Indosat: 100 jam off-peak + 100 jam anytime | Satu paket = satu saldo jam padel | Batch 5 |
| 10 | Tombol "Batalkan & Ajukan" | **Sudah ada** di Kelola Pemesanan → modal Ajukan Pembatalan & Refund | Selesai |
| 11 | Venue untuk company profile | **Sudah ada** — fasilitas compro diatur di Kelola Konten Website | Selesai |
| 12 | Kelas kids (harga per class) | — | **Ditunda** (belum jelas cakupannya) |
| 13 | Harga sewa 120 / 80 / 170 | — | **Ditunda** (angka belum pasti) |

---

## 2. Batch 1 — Perubahan kecil

### 2.1 EDC hanya BCA
- Layar bayar kasir (POS Walk-In, POS F&B, Jual Membership, pelunasan tagihan / selisih reschedule) hanya menawarkan **Mesin EDC BCA**.
- Dicek di **server**: pembayaran baru dengan terminal EDC Mandiri / Lainnya ditolak (422), termasuk request hasil rekayasa.
- Koreksi metode bayar (Detail Shift Kasir) hanya bisa ke metode yang masih ada.
- Data lama tetap utuh: label "Mesin EDC Mandiri / Lainnya" tetap dikenali di struk, rekap shift, tutup shift, laporan, dan Buku Transaksi.
- Bank penerbit kartu (BCA / Mandiri / BNI / dst.) **tidak** dibatasi — kartu bank apa pun tetap bisa digesek di mesin BCA.

### 2.2 Reschedule maksimal 3x
- Satu booking paling banyak dipindah **3 kali** (angka tetap, bukan pengaturan admin).
- Berlaku untuk semua jalur pindah jadwal: reschedule di Kelola Pemesanan dan pemindahan booking Jadwal Tetap Mingguan saat jadwalnya diubah (memakai penghitung yang sama).
- Reschedule ke-4 ditolak di server dengan pesan yang jelas. Di Kelola Pemesanan tombol reschedule nonaktif dan menampilkan jumlah yang sudah terpakai.
- Batas waktu reschedule global (tombol Batas Reschedule) tetap berlaku, terpisah dari batas jumlah ini.

### 2.3 Sauna → Wellness
- Semua **teks tampilan** "Sauna" di portal customer (termasuk My Club), halaman admin, dan teks default diganti "Wellness".
- Kode internal tidak diubah (kode fasilitas `SAUNA`, nama izin, nama tabel) supaya data dan laporan lama tetap terbaca.
- Nama fasilitas yang sudah tersimpan di database (Master Fasilitas, konten company profile) adalah data — diganti admin lewat menu masing-masing di tiap server.

### 2.4 Hapus Revoke di Team sponsor
- Tombol **Revoke** di portal corporate (tab Team, tampilan desktop & HP) dihapus, dan endpoint API revoke dihapus (tidak bisa dipanggil langsung).
- Alasan: revoke membuat sisa jam karyawan tidak bisa dipakai dan tidak kembali ke perusahaan (checkout mewajibkan status anggota ACTIVE). Jam tidak boleh hangus.
- Anggota yang terlanjur berstatus REVOKED tetap bisa **diaktifkan kembali** (tombol Activate tetap ada) supaya jamnya bisa dipakai lagi.
- Salah input nama dari CSV: pakai **Hapus voucher** yang sudah ada (hanya jika jamnya belum terpakai; jam kembali ke kuota perusahaan).
- Skema "ganti orang" (memindahkan jatah ke karyawan lain) **belum diputuskan** — lihat Pertanyaan Terbuka.

---

## 3. Batch 2–5 — Rancangan

### 3.1 Aturan akses paket membership (Batch 2)
Kebutuhan: Protelindo dan BCA masing-masing 100 jam, hanya boleh dipakai di jam tertentu (tidak di peak hour); paket membership "weekday jam sekian" harus benar-benar dicek.

Temuan:
- Paket membership hanya punya **batas jam** (`time_window_start/end`); **batas hari belum ada** (tulisan "weekday" hanya di deskripsi paket).
- Batas jam paket dicek untuk membership pribadi dan Jadwal Tetap, **tidak** dicek saat karyawan corporate booking memakai jam dari paket perusahaan.
- Menu Jadwal Akses Sponsor masih berlabel "sedang dalam proses pembuatan" dan tidak dicek saat booking.

Rancangan:
- Aturan akses diatur di **paket membership**: pilih **hari** (Senin–Minggu), **jam** mulai–selesai, atau centang **"Hanya di luar peak hour"** (mengikuti jam peak & tanggal merah di Master Data).
- Berlaku untuk semua pemakai paket: member pribadi, karyawan corporate, Jadwal Tetap, Walk-In, dan reschedule.
- Booking di luar aturan → harga normal + alert ke staf (sama dengan aturan benefit gugur yang sudah ada).
- Nasib menu Jadwal Akses Sponsor: lihat Pertanyaan Terbuka.

### 3.2 Tampilan mingguan & bulanan (Batch 3)
Kebutuhan: di Monitoring dan POS Walk-In, selain harian, bisa melihat siapa saja yang booking di jam berapa dalam 1 minggu dan 1 bulan.

Rancangan (usulan UI):
- Tab baru **Rekap Jadwal** di Monitoring Lapangan, bisa dibuka juga dari POS Walk-In.
- **Mode Minggu:** 7 kolom (Senin–Minggu); tiap kolom daftar booking urut jam — jam, lapangan, nama, warna per jenis (member / corporate / coaching / walk-in / online).
- **Mode Bulan:** kalender sebulan; tiap tanggal menampilkan jumlah booking dan jam terpadat; klik tanggal → daftar lengkap hari itu.
- Filter jenis booking, filter lapangan, cari nama. Hanya baca (tidak mengubah booking).

### 3.3 Coaching sistem kontrak (Batch 4)
Kebutuhan: saat ini coaching dibayar per transaksi. Academy (mis. SR) mau sistem kontrak: dalam periode tertentu dihitung berapa sesi / coach yang melatih di venue, lalu ditagih sekaligus. Periode bisa **harian, mingguan, atau 2 mingguan**. Club 61 mengirim invoice ke academy.

Rancangan awal:
- Tiap academy punya **cara bayar**: *Per booking* (seperti sekarang) atau *Kontrak* dengan periode tagihan (harian / mingguan / 2 mingguan).
- Booking academy kontrak langsung terkunci **tanpa bayar di muka** (status "masuk tagihan").
- Halaman **Tagihan Coaching**: per academy per periode — daftar sesi, lapangan, jam, coach, tarif, total. Admin menerbitkan invoice periode; pelunasan tetap di **kasir POS** (aturan: uang hanya diterima di kasir).
- Tarif tetap dari tarif setoran coaching yang sudah ada (angka 120 / 80 / 170 tinggal diisi di sana setelah pasti).
- Perlu PRD mini sebelum dibangun (lihat Pertanyaan Terbuka).

### 3.4 Indosat: dua kantong jam (Batch 5)
Kebutuhan: Indosat punya 100 jam **off-peak** dan 100 jam **anytime**.
- Customer (IM3) memakai kantong off-peak, hanya di luar peak hour.
- Karyawan Kamis 19:00–22:00 (peak, Jadwal Tetap) memotong kantong anytime.

Temuan: satu paket membership hanya bisa punya **satu saldo jam padel** (dikunci di database), jadi dua kantong di satu organisasi belum bisa.

Rancangan:
- Satu organisasi boleh punya beberapa kantong jam padel, masing-masing dengan aturan akses (Batch 2). Sistem memilih kantong otomatis dari jam main: jam peak → anytime; di luar peak → off-peak dulu.
- Sementara (tanpa ubah kode): dua organisasi sponsor terpisah, mis. "Indosat – Karyawan" (anytime) dan "Indosat – Customer" (off-peak), masing-masing paket sendiri.

### 3.5 Selisih prime → regular jadi voucher — BATAL
**Dibatalkan owner aplikasi (28 Okt 2026), tidak dibangun.** Aturan lama tetap berlaku: pindah ke jadwal lebih murah →
selisih **HANGUS** (kebijakan PM 30 Sep 2026). Harga lapangan booking tetap dicatat sebesar yang sudah dibayar, selisihnya
dicatat terpisah (`reschedule_forfeited_amount`) dan tampil di laporan sebagai "selisih hangus".

Catatan kalau suatu saat diangkat lagi: voucher selisih wajib diiringi penurunan harga lapangan booking ke harga jadwal
baru. Tanpa itu, booking yang kemudian di-refund mengembalikan uang penuh plus voucher, dan booking yang dipindah lagi ke
jam prime tidak ditagih padahal selisihnya sudah keluar sebagai voucher.

---

## 4. Pertanyaan Terbuka (dicatat, belum diputuskan)

| # | Topik | Pertanyaan |
| :--- | :--- | :--- |
| Q3 | Revoke | Skema "ganti orang": apakah jatah jam karyawan yang keluar boleh dipindah ke karyawan lain / dikembalikan ke kuota perusahaan meski sebagian sudah terpakai? |
| Q4 | Aturan akses (§3.1) | Menu Jadwal Akses Sponsor: dihapus (semua aturan di paket) atau dipertahankan khusus untuk batas maksimal lapangan bersamaan per perusahaan? |
| Q5 | Protelindo / BCA | Jam akses persisnya (hari & jam) untuk masing-masing paket 100 jam. |
| Q6 | Coaching kontrak (§3.3) | Booking kontrak yang dibatalkan / tidak datang: tetap ditagih atau tidak? Batas waktu batal? |
| Q7 | Coaching kontrak (§3.3) | Ada batas tagihan menggantung (plafon) per academy? Apa yang terjadi jika invoice periode lalu belum lunas — booking baru diblok? |
| Q8 | Coaching kontrak (§3.3) | Invoice ke academy dikirim lewat email (template invoice yang ada) atau cukup dicetak / PDF? |
| Q9 | Kelas kids | Cakupan "per class" dan harga kelas kids — ditunda sampai jelas. |
| Q10 | Harga 120 / 80 / 170 | Angka sewa belum pasti — diisi di tarif setelah final. |
| Q11 | Indosat (§3.4) | Kantong off-peak IM3: 100 jam dibagi ke berapa customer, dan siapa yang membagikan (PIC Indosat lewat portal)? |
| Q12 | Tampilan mingguan / bulanan (§3.2) | Perlu bisa dicetak / diunduh, atau cukup dilihat di layar? |

---

## 5. Riwayat Dokumen

| Versi | Tanggal | Perubahan |
| :--- | :--- | :--- |
| v0.1.0 | 28 Okt 2026 | Draft awal dari catatan meeting + klarifikasi owner aplikasi. |
| v0.1.1 | 28 Okt 2026 | Batch 1 dibangun; poin 5 (selisih jadi voucher) dibatalkan — Q1 & Q2 dihapus. |
