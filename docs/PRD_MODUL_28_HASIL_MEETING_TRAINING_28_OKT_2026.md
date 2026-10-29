# Product Requirements Document (PRD)
## Modul 28 — Hasil Meeting Training Club 61 (28 Okt 2026)
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-28-MEETING-TRAINING-28-OKT` |
| **Versi** | `v0.2.0` |
| **Status** | **DRAFT.** Batch 1 & 2 dibangun (belum di-commit); batch lain menunggu keputusan di bagian *Pertanyaan Terbuka*. |
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
| 6 | Aturan akses paket (hari + jam / di luar peak) + paket "Khusus Front Desk / Admin" | Hanya batas jam; tidak dicek untuk karyawan corporate; semua paket aktif bisa dibeli online | **Batch 2** (dibangun 29 Okt 2026) |
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

Keputusan & yang dibangun (29 Okt 2026):
- Aturan akses diatur di **benefit Padel paket membership** lewat satu pilihan **Waktu Berlaku**:
  - *Kapan saja* — mis. Community Partnership (all hours);
  - *Hanya di luar jam peak* — mis. Daytime + Weekend, Protelindo, BCA;
  - *Hanya di jam peak* — mis. Weekday Prime Time;
  - *Atur manual* — centang hari + jam akses mulai–selesai (format 24 jam), mis. paket pagi 06:00–12:00.
  Jam peak & tanggal merah (tanggal merah = aturan hari Minggu) dibaca dari **Master Data → Jam Peak**, sumber yang sama dengan harga lapangan. Satu pengecek: `PadelAccessRule`.
- Form menampilkan ringkasan **Paket Dapat Digunakan** per hari (sesuai Master Data saat itu & jam buka lapangan). Aturan yang tidak menyisakan satu jam pun **tidak bisa disimpan**.
- Proposal harga klien (29 Okt 2026): peak hanya **Senin–Jumat 17:00–00:00**; weekend Rp200rb = reguler. Master Data di tiap server harus diisi sesuai ini (diatur owner aplikasi). Belum dijawab: tanggal merah reguler atau peak; paket Daytime berakhir 17:00 atau 18:00 (18:00 belum bisa — jam berbeda per hari belum didukung).
- Dicek di semua jalur pemakaian jam paket: checkout online, kasir Walk-In (layar & bayar), **voucher jam karyawan corporate** (memakai aturan paket perusahaannya — sebelumnya tidak dicek sama sekali), reschedule, dan Jadwal Tetap.
- Di luar aturan → jam paket tidak dipakai, harga normal + alasan di layar (kode alasan tetap `OUTSIDE_TIME_WINDOW` supaya layar & aplikasi lama tetap mengenalinya). Kartu yang dipilih eksplisit → ditolak 422 dengan alasan. Booking yang sudah terjadi tidak disentuh.
- **Salinan aturan di kartu (opsi a):** kartu menyimpan aturan paket saat dibeli. Edit paket tidak mengubah kartu yang sudah terjual; pembeli baru langsung dapat aturan terbaru. Tombol **Terapkan Aturan** di daftar Paket Membership menyamakan kartu aktif / menunggu bayar dengan aturan terbaru — izin `apply_membership_plan_rules` (pintu belakang, bawaan hanya super admin), konfirmasi jumlah kartu, tercatat di Log Aktivitas. Kuota, diskon & masa aktif tidak disentuh.
- **Saluran Penjualan** (`sales_channel`): *Online (Web & Aplikasi) dan Front Desk* (bawaan, perilaku lama) atau *Khusus Front Desk / Admin* — tidak tampil di halaman Membership, aplikasi, halaman depan & paket gym; checkout online ditolak di server. POS Jual Membership & pemberian paket corporate oleh admin tetap bisa.
- Waktu Berlaku hanya untuk Padel; gym & wellness tetap memakai jam akses biasa (menyusul).
- Menu Jadwal Akses Sponsor **dibiarkan apa adanya** (Q4 ditunda).
- Migrasi `2026_10_29_100001` hanya menambah kolom dengan nilai bawaan yang tidak mengubah perilaku.

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
| v0.2.0 | 29 Okt 2026 | Batch 2 dibangun: aturan akses paket (hari / jam / di luar peak) termasuk karyawan corporate, tombol Terapkan Aturan (super admin), paket "Khusus Front Desk / Admin". Menu Jadwal Akses Sponsor dibiarkan. |
