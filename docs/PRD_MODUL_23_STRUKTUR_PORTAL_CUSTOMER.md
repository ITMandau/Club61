# Product Requirements Document (PRD)
## Modul 23: Struktur Portal Customer — Membership, My Club, Profile & Voucher Saya
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-23-STRUKTUR-PORTAL-CUSTOMER` |
| **Versi** | `v0.1.0-DRAFT` |
| **Status** | **Disetujui arahnya, belum dikerjakan** (5 Okt 2026). |
| **Sumber Requirement** | Pemilik produk (5 Okt 2026): Membership dipisah dari My Club dan jadi tab sendiri; My Club cukup jadi tampilan profil klub (compro); Profile pindah ke dropdown akun dan berisi membership aktif + menu voucher ala Shopee. |
| **Dependensi Teknis** | `PRD_MODUL_05` (membership & paket), `PRD_MODUL_12_SPONSOR_CORPORATE_ACCOUNT.md` (voucher jam corporate), `PRD_MODUL_21_REFUND_NO_SHOW_PEMBAYARAN_BERMASALAH.md` + `PRD_MODUL_22_VOUCHER_DAN_PROMO.md` (voucher saldo & promo), `PRD_MODUL_14_COMPANY_PROFILE_CONTENT.md` (konten klub). |
| **Prinsip Utama** | **SATU HALAMAN, SATU TUJUAN**: My Club = kenal klub, Membership = beli paket, Profile = milik saya. Barang milik customer (membership aktif, voucher) **tidak** dicampur dengan katalog jualan. |

---

## 1. Ringkasan

Halaman **My Club** sekarang campur aduk dalam satu halaman:
- kartu membership aktif;
- voucher jam corporate;
- voucher saldo;
- katalog semua paket membership;
- info klub, fasilitas, dan aturan.

Customer susah menemukan apa yang dicari, dan tampilannya tidak terasa seperti profil klub.

Struktur baru:

| Halaman | Tujuan | Isi |
| :--- | :--- | :--- |
| **My Club** | Kenal klub (company profile) | Hero klub, info utama, fasilitas, aturan klub, keuntungan member, lokasi & kontak, ringkasan membership aktif customer (satu baris), **3 paket paling laku** + tombol ke Membership |
| **Membership** *(tab baru)* | Beli / perpanjang paket | Semua paket aktif, detail benefit, bayar (halaman `/membership` yang sudah ada) |
| **Profile** *(lewat dropdown akun)* | Milik saya | Kartu membership aktif, menu **Voucher Saya**, pengaturan akun, logout |
| **Voucher Saya** *(dari Profile)* | Lihat & pakai voucher | Daftar voucher model Shopee: tab Tersedia / Riwayat |

---

## 2. Navigasi

### 2.1 HP & tablet (navigasi bawah, layar < 768px)

| Sekarang | Baru |
| :--- | :--- |
| Home · Book Court · My Club · Invoice · **Profile** | Home · Book Court · My Club · Invoice · **Membership** |

- Tab **Membership** membuka `/membership`, ikon kartu member. Tab aktif saat berada di `/membership`.
- Tab **My Club** hanya aktif di `/my-club`. Sekarang tab ini ikut menyala di `/membership`, dan itu harus dihapus.
- **Profile** pindah ke **avatar di header atas**. Tap avatar → dropdown (§2.3).

### 2.2 Desktop (navbar atas, ≥ 768px)

- Menu: Home · Book Court · My Club · **Membership** · Invoice (+ Sponsor Team untuk PIC corporate, seperti sekarang).
- Blok nama + status member + tombol logout terpisah di kanan diganti **satu tombol akun** (avatar + nama + status member) yang membuka dropdown (§2.3).

### 2.3 Dropdown akun (HP & desktop sama)

| Item | Tujuan |
| :--- | :--- |
| Kepala dropdown | Avatar, nama, email/telepon, status member (mis. "Gold · s/d 12 Jan 2027" atau "Belum member") |
| **Profile** | `/profile` |
| **Voucher Saya** + angka voucher tersedia | `/profile/vouchers` |
| **Logout** | Form logout yang sudah ada, warna merah, paling bawah |

- Di HP tampil sebagai panel di bawah header, lebar penuh. Di desktop tampil sebagai dropdown kecil di kanan.
- Tutup saat klik di luar, tekan Esc, atau pindah halaman.
- Angka di Voucher Saya = voucher saldo + voucher jam corporate yang masih bisa dipakai. Disembunyikan kalau 0.

---

## 3. Halaman

### 3.1 My Club (`/my-club`) — company profile

**Tetap ada:**
- hero klub;
- info utama (3 kartu);
- 6 kartu fasilitas;
- aturan klub;
- keuntungan member;
- lokasi & kontak (alamat, jam buka, WhatsApp, peta bila ada).

Konten idealnya dari Konten Website (Modul 14). Untuk tahap ini boleh tetap statis.

**Ditambah:**
1. **Baris membership aktif** (hanya kalau customer punya membership aktif): satu kartu tipis, contoh "Kamu member **Gold** · aktif s/d 12 Jan 2027 · Lihat di Profile →". Kartu member lengkap (QR, kuota) tetap di Profile, tidak di sini.
2. **Bagian "Membership"**:
   - 3 paket paling laku (§4.1);
   - tiap kartu berisi nama paket, harga + masa aktif, 2–3 benefit utama, dan tombol "Pilih" yang membuka `/membership` dengan paket itu langsung terpilih (`/membership?plan={kode}`);
   - paket peringkat 1 diberi badge **"Most popular"**;
   - di bawahnya tombol **"Lihat semua paket"** → `/membership`;
   - kalau paket aktif kurang dari 3, tampilkan yang ada. Kalau tidak ada paket aktif, bagian ini disembunyikan.

**Dihapus dari My Club:**
- kartu membership aktif lengkap (pindah ke Profile);
- voucher jam corporate dan voucher saldo (pindah ke Voucher Saya);
- katalog semua paket (sudah ada di halaman Membership).

### 3.2 Membership (`/membership`) — beli paket

Halaman yang sudah ada tetap dipakai: pilih paket, lihat benefit, pilih metode bayar, bayar via Midtrans.

Penyesuaian:
- Mendukung `?plan={kode}` untuk membuka paket tertentu (dari kartu di My Club).
- Tombol / redirect "Kembali ke My Club" diganti sesuai konteks: setelah bayar sukses → **Profile** (kartu member baru terlihat di sana); tombol kembali → halaman sebelumnya / Home.
- Kalau customer sudah punya membership aktif, tampilkan baris info di atas: "Kamu member Gold s/d 12 Jan 2027 — paket baru berlaku setelah / menggantikan …". Aturan perpanjangan mengikuti logika yang sudah berjalan di server, bukan aturan baru.
- Tampilan dirapikan dengan gaya yang sama seperti Book Court / Checkout. Wajib dicek di HP 360–390px, tablet, desktop 1280 & 1920px.

### 3.3 Profile (`/profile`) — milik saya

Urutan isi:
1. **Kepala profil**: avatar, nama, email, telepon, status member.
2. **Kartu membership aktif**, dipindah dari My Club: nama paket, kode member, masa aktif, QR kartu member digital, sisa kuota per fasilitas (jam / kunjungan / diskon %). Kalau belum member: kartu ajakan "Jadi member" → `/membership`.
3. **Menu**:
   - **Voucher Saya** (jumlah tersedia) → `/profile/vouchers`;
   - **Riwayat transaksi** → `/invoice`;
   - **Tim Corporate** → `/corporate`, hanya untuk PIC sponsor.
4. **Pengaturan akun**: ubah nama / email / telepon, ganti password, hapus akun. Pakai form Breeze yang sudah ada (`profile.update`, password, `profile.destroy`), tampilannya dirapikan, logikanya tidak diubah.
5. **Logout**.

### 3.4 Voucher Saya (`/profile/vouchers`) — gaya Shopee

**Tab:**

| Tab | Isi |
| :--- | :--- |
| **Tersedia** | Voucher yang masih bisa dipakai, diurutkan dari yang paling cepat habis masa berlakunya |
| **Riwayat** | Voucher yang sudah habis terpakai, kedaluwarsa, atau dinonaktifkan admin, dengan label alasannya |

**Bentuk kartu (kupon):**
- **Potongan kiri** berwarna:
  - hijau tua = voucher saldo;
  - emas = voucher jam corporate;
  - (nanti) merah = promo marketing.
  Isinya nilai besar ("Rp150rb" / "2 JAM") dan jenisnya ("SALDO" / "CORPORATE").
- Garis putus-putus berlubang di antara potongan dan isi, seperti tiket.
- **Isi kanan:**
  - judul (mis. "Voucher saldo refund" / nama perusahaan);
  - kode voucher (bisa disalin, tap = salin + toast "Kode disalin");
  - syarat singkat (mis. "Min. transaksi —", "Khusus sewa lapangan");
  - masa berlaku. Label merah **"Berakhir 3 hari lagi"** kalau ≤ 7 hari.
- **Tombol "Pakai"** → `/booking`. Voucher saldo sudah otomatis muncul di checkout (sudah berjalan), voucher jam corporate juga otomatis lewat toggle benefit di checkout (sudah berjalan).
- **Kosong:** ilustrasi + "Belum ada voucher" + tombol Book a Court.

**Sumber data:**

| Jenis | Sumber | Catatan |
| :--- | :--- | :--- |
| Voucher saldo | `vouchers` jenis `CREDIT` milik customer (`VoucherService::walletFor` untuk Tersedia) | **Perlu data baru untuk Riwayat**: saat ini hanya ada daftar voucher aktif. Tambah endpoint daftar semua voucher saldo milik customer + status (tersedia / habis / kedaluwarsa / dinonaktifkan). |
| Voucher jam corporate | `SponsorOrganizationMember` → `vouchers` (Modul 12) | Popup klaim di Home tetap berjalan. Link "View all my vouchers" pindah ke `/profile/vouchers`. |
| Promo marketing | Belum ada | Menyusul setelah keputusan PRD 22 §4 (skema simpan voucher ke akun). Tab & kartu disiapkan supaya tinggal ditambah. |

**Aturan voucher tidak berubah di modul ini.** Termasuk keputusan voucher saldo sekali pakai (PRD 22 §3), yang tetap mengikuti PRD 22.

---

## 4. Logika

### 4.1 "3 paket paling laku"
- **Jumlah** = banyaknya pembelian membership yang **sudah lunas** per paket (status membership `ACTIVE` atau `EXPIRED`, atau order membership `PAID`). Pembelian yang dibatalkan / belum dibayar tidak dihitung.
- Hanya paket yang **aktif** (bisa dibeli) yang tampil.
- **Seri / belum ada penjualan** (kemungkinan besar saat launch 10 Okt 2026): urut berdasarkan harga termurah. Tabel `membership_plans` belum punya kolom urutan tampil. Kalau admin ingin mengatur urutan sendiri, tambah kolom `sort_order` (opsional, di luar cakupan wajib).
- Dihitung di server, boleh di-cache 1 jam supaya halaman tidak menghitung ulang setiap dibuka.

### 4.2 Status member di dropdown & My Club
Pakai query membership aktif yang sudah dipakai navbar & Home (membership `ACTIVE` dan `end_date` kosong / belum lewat). Cukup satu helper supaya tidak ditulis ulang di tiga tempat.

---

## 5. Link Lama yang Harus Dipindah

| Lokasi | Sekarang | Baru |
| :--- | :--- | :--- |
| Home — tile "My Vouchers" (member corporate) | `/my-club#corporate-vouchers` | `/profile/vouchers` |
| Home — popup klaim voucher corporate, "View all my vouchers" | `/my-club#corporate-vouchers` | `/profile/vouchers` |
| Home — kartu "Upgrade to Diamond Club" | Notice WhatsApp | `/membership` (sekalian membereskan catatan DUMMY di Modul 20) |
| Home — tile "My Club" | `/my-club` | Tetap |
| Navbar — badge status member | `/my-club` atau `/membership` | Masuk ke dropdown akun (§2.3) |
| Bottom nav — highlight My Club di `/membership` | Menyala | Tidak menyala, tab Membership yang menyala |
| Halaman Membership — "Kembali ke My Club" & redirect setelah bayar | `/my-club` | Lihat §3.2 |
| Email voucher saldo terbit (`CreditVoucherIssued`) | Cek tautan | Arahkan ke `/profile/vouchers` bila ada tautan |

---

## 6. Kriteria Selesai

1. Navigasi bawah HP berisi Home · Book Court · My Club · Invoice · Membership. Profile bisa dibuka dari avatar header di HP dan desktop.
2. Dropdown akun berisi Profile, Voucher Saya (dengan angka), Logout. Bisa ditutup dengan klik di luar / Esc.
3. My Club tidak lagi menampilkan kartu member lengkap, voucher, atau katalog semua paket. Yang tampil: baris status member (bila ada) + tepat ≤ 3 paket paling laku + tombol "Lihat semua paket".
4. Kartu "Pilih" di My Club membuka `/membership` dengan paket tersebut terpilih.
5. Profile menampilkan kartu member aktif (QR + kuota) atau ajakan jadi member.
6. Voucher Saya menampilkan voucher saldo & voucher jam corporate, tab Tersedia / Riwayat, kode bisa disalin, tombol Pakai ke Book Court.
7. Semua link lama di §5 sudah pindah, tidak ada lagi tautan ke `#corporate-vouchers`.
8. Dicek dengan screenshot di HP 360 & 390px, tablet 820px, desktop 1280 & 1920px. Isi halaman lebar penuh sejajar navbar, tidak ada teks terpotong / turun baris.
9. Test otomatis lulus, termasuk test baru: urutan 3 paket paling laku (dengan & tanpa penjualan), voucher milik customer lain tidak muncul, riwayat voucher saldo, akses `/profile/vouchers` wajib login customer.

---

## 7. Perkiraan & Urutan Kerja (± 2–3 hari)

1. Navigasi: bottom nav, navbar desktop, dropdown akun — ½ hari.
2. My Club versi compro + 3 paket paling laku + baris status member — ½ hari.
3. Profile hub + kartu member dipindah + form akun dirapikan — ½ hari.
4. Voucher Saya + endpoint riwayat voucher saldo — ½–1 hari.
5. Penyesuaian halaman Membership (`?plan=`, redirect, info member aktif, rapikan tampilan) — ½ hari.
6. Pindah link lama, test, screenshot semua ukuran — ½ hari.

---

## 8. Catatan

- **Interpretasi yang dipakai:** "di My Club juga menampilkan membership yang aktif" dibaca sebagai (a) baris tipis status membership customer + (b) paket-paket yang sedang dijual (3 paling laku). Kartu member lengkap hanya di Profile. Ubah bagian ini kalau maksudnya lain.
- Voucher promo marketing menunggu PRD 22. Halaman Voucher Saya disiapkan supaya tinggal menambah jenis baru.
- Halaman Corporate (`/corporate`) untuk PIC sponsor tidak berubah, hanya ditautkan dari Profile.
