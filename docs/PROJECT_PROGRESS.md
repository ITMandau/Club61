# Roadmap & Progress Checklist: Club 61 Central Backend System

Dokumen pelacak progres (Single Source of Truth). Status di bawah ini hasil audit langsung ke kode (bukan ke PRD) per **30 September 2026**. Seluruh teks disajikan bersih tanpa ikon atau emoji.

### Legenda

| Tanda | Arti |
| :--- | :--- |
| `[x]` | Selesai, sudah pakai data asli (database / service / payment gateway) |
| `[~]` | Sebagian jalan, masih ada bagian yang kurang |
| `[ ]` | Belum dikerjakan |
| **DUMMY** | Halaman / fitur sudah tampil, tapi isinya data palsu (hardcoded) atau tombolnya tidak melakukan apa-apa |
| **SKEMA SAJA** | Tabel database & model sudah ada, tapi belum dipakai kode mana pun |
| **KRITIS** | Bug yang bisa bikin rugi uang / celah keamanan, wajib dibereskan sebelum live |

---

## Ringkasan Status Global

| Modul | Deskripsi | Status | Progress |
| :--- | :--- | :--- | :---: |
| 01 | Core Auth & Multi-Door Login | Jalan, reset sandi via email belum | 85% |
| 02 | Padel Court Booking Engine | Selesai | 97% |
| 03 | Wellness & Sauna | API booking jalan, pembayaran & halaman belum | 35% |
| 04 | Salon & Beauty | Katalog saja | 15% |
| 05 | Membership (Padel/Gym/Wellness) & Gym Pass | Jalan, pembayaran online perlu dirapikan | 75% |
| 06 | F&B Cafe, Table QR & Kitchen KDS | POS Kasir F&B jalan, KDS DUMMY, self-order belum | 45% |
| 07 | Merchandise Retail | Katalog saja | 10% |
| 08 | POS Frontdesk & Split Bill | POS Padel & F&B jalan, split bill belum | 55% |
| 09 | Role & Permission Matrix | Selesai | 100% |
| 10 | Unified Payment Gateway (Midtrans) | Jalan + rekonsiliasi, handler baru Padel & Membership | 80% |
| 11 | Walk-In Offline Booking (POS Padel) | Selesai | 100% |
| 12 | Sponsor / Corporate Account | Jalan | 90% |
| 13 | Multi-Branch Tenancy | PRD saja | 0% |
| 14 | Company Profile / Konten Website | Selesai | 100% |
| 15 | Manajemen Menu F&B | Selesai | 100% |
| 16 | Activity / Audit Log | Jalan (panel superadmin, transaksi, perubahan data, login) | 85% |
| 17 | Laporan Keuangan & Riwayat Transaksi Terpadu | Buku Transaksi + Antrian Refund + dashboard Analytics dari buku jalan; MDR & rekonsiliasi bank (Fase 4) menyusul | 90% |
| 18 | Pengaturan Invoice / Struk Terpusat | Belum ada | 0% |
| 19 | Realtime (Laravel Reverb) | Belum terpasang, masih polling | 0% |
| 20 | Halaman Admin Pendukung (Dashboard, Club, Karyawan, Turnamen, Marketing) | DUMMY semua | 0% |
| 21 | Kebijakan Refund, No-Show & Pembayaran Bermasalah | PRD draft, menunggu keputusan PM | 0% |

**Automated test suite:** 634 passed (2748 assertions) — termasuk regresi audit "bom waktu" 1 Okt 2026 (`tests/Feature/Padel/PaymentTimeBombRegressionTest.php`).

---

## PRIORITAS SEBELUM LIVE

### KRITIS (bisa bikin rugi uang)
- [x] **Upgrade Laravel 11.56 → 12.69.3** (4 Okt 2026): Laravel 11 tidak menerima patch keamanan lagi; celah *high* "CRLF injection pada aturan validasi `email`" (GHSA-5vg9-5847-vvmq) dan *Temporary Signed URL Path Confusion* hanya diperbaiki di 12.x. Ikut naik: `league/commonmark` 2.10.3 (DoS tabel Markdown), PHPUnit 11 (dev). `composer audit`: 0 advisory. Deploy: `composer install`.
- [x] **Alamat di struk salah** (DIPERBAIKI 4 Okt 2026): struk POS Walk-In & Z-Report menulis "Jl. Karang Tengah Raya No. 61, Lebak Bulus" secara hardcode. Sekarang semua struk / invoice salinan admin memakai alamat dari menu **Konten Website** (`CompanyProfileSetting::receiptAddress()`).
- [x] **KRITIS: Midtrans gagal = dianggap lunas** (DIPERBAIKI 30 Sep 2026). Dulu `MidtransService::createSnapTransaction()` menangkap semua error Midtrans (key salah, jaringan putus, request ditolak) atau server key kosong, lalu mengembalikan token palsu `is_mock=true`, sehingga booking padel langsung `PAID` dan membership online langsung aktif gratis.
  - Sekarang fail-closed: token mock hanya di environment `local` (tanpa key) / `testing`. Selain itu checkout ditolak HTTP 503 (`PaymentGatewayUnavailableException`), transaksi DB di-rollback, slot tetap `LOCKED` supaya customer bisa coba lagi, dan error dicatat `[ALERT]` di log.
  - Test: `tests/Feature/Payment/MidtransFailClosedTest.php` (7 test).
  - Catatan: driver `mock` via config `PAYMENT_DRIVER=mock` masih bisa dipakai di server non-production. Pastikan server sandbox / production memakai `PAYMENT_DRIVER=midtrans`.
- [x] Client key cadangan `'SB-Mid-client-demo-61'` dihapus (4 Okt 2026): `snap.js` hanya dimuat dengan `MIDTRANS_CLIENT_KEY` asli (`customer/partials/midtrans-snap.blade.php`); tanpa key, customer diarahkan ke halaman bayar Midtrans (`redirect_url`). Checkout padel dulu menampilkan "pembayaran berhasil" kalau popup Snap tidak termuat — sekarang diarahkan ke halaman bayar.

### URGENT (permintaan PM)
- [x] Modul 16: Activity / Audit Log untuk superadmin.
- [x] Modul 17: Laporan keuangan per modul + tiap transaksi bisa dilacak detail & invoice-nya — Buku Transaksi & Antrian Refund (Fase 2) dan dashboard Analytics dari buku (Fase 3) jalan (4 Okt 2026). Fase 4 (MDR Midtrans, rekonsiliasi mutasi bank) menyusul.
  - [x] Analytics & Keuangan membaca `ledger_entries` (F&B ikut terhitung, angka = Buku Transaksi); rincian per kategori / sumber / metode bayar (klik baris → Buku Transaksi tersaring lewat `?periode=&kategori=&sumber=&metode=`); grafik tren harian (per bulan untuk rentang > 62 hari); okupansi dari jam buka lapangan aktif (dulu tetap 4 × 18 jam). Test: `tests/Feature/Finance/AnalyticsLedgerTest.php`.
  - [x] Dashboard admin memakai data asli (4 Okt 2026) — dulu seluruh isinya contoh mati ("Rp 50.272.597", "132 Bookings", booking "PXDL"). Isi: uang masuk hari ini vs kemarin & bulan ini (dari Buku Transaksi, hanya untuk yang boleh membuka Analytics), booking & okupansi hari ini, customer baru bulan ini vs bulan lalu, member aktif, daftar "Perlu Ditindaklanjuti" (tagihan kasir, refund, booking menunggu bayar), grafik uang masuk 7/30/90 hari, jadwal lapangan hari ini + pencarian. Okupansi Dashboard & Analytics satu rumus (`App\Services\Padel\CourtOccupancy`). Test: `tests/Feature/Finance/AdminDashboardTest.php`.
- [ ] Modul 18: Panel pengaturan invoice untuk semua modul.
- [ ] Rapikan pembayaran membership online di portal customer.
  - [x] Master Fasilitas Membership (menu **Fasilitas Membership**): tambah fasilitas baru (mode check-in / info saja), nama & deskripsi benefit diatur admin; paket punya deskripsi + daftar privilege + catatan per benefit. Halaman membership, My Club, teaser depan, POS Jual Membership & API `/membership/plans` (`benefit_cards`) tidak lagi memakai teks dummy (2 Okt 2026).
  - [x] Check-in generik `POST /api/v1/membership/checkin`; check-in Gym unlimited dulu selalu gagal, paket "diskon saja" dulu bisa check-in gratis — keduanya diperbaiki.
  - [x] Lanjutkan bayar membership PENDING_PAYMENT (4 Okt 2026): tombol "Continue Payment" + ganti metode + "Cancel This Order" di halaman Invoice; checkout paket sama saat masih pending = lanjut bayar order yang sama (tidak ada order dobel), paket beda = ditolak 409 dan diarahkan ke pesanan lama. Notifikasi expire Midtrans ikut membatalkan kartu PENDING; pesanan online yang ditinggal > 24 jam dibatalkan otomatis oleh `membership:sync-expired`. API: `POST /api/v1/membership/purchases/{id}/pay` & `/cancel`, flag `can_pay_online` di `my-purchases`. Test: `tests/Feature/Membership/MembershipOnlinePaymentTest.php`.
- [~] Reset sandi via email: kode, tampilan & email Club 61 siap (4 Okt 2026); tinggal isi SMTP di `.env` server lalu tes `php artisan mail:test`.
- [ ] Reverb untuk update tanpa refresh.

---

## MODUL 01: CORE AUTHENTICATION & MULTI-DOOR ACCESS

### Status: 85%
- [x] Autentikasi multi-guard (Web Session & API Sanctum Bearer Token).
- [x] Login dengan username / email, shortcut `admin`.
- [x] Redirect otomatis berdasarkan `home_route` role (`/admin`, `/pos`, `/kitchen`, `/admin/book-offline-court`, `/dashboard`).
- [x] Staf diblokir dari halaman portal customer (middleware `CustomerPortalOnly`).
- [x] Staf tanpa akses Dashboard diarahkan ke halaman admin pertama yang boleh dibuka.
- [x] Reset sandi: controller jalan, pesan generik (tidak membocorkan email terdaftar), token Sanctum dicabut setelah reset.
- [x] Sesi web di perangkat lain otomatis keluar setelah password diganti / direset (`AuthenticateSession` di grup web; dulu hanya panel admin). Test: `tests/Feature/Auth/SessionInvalidationTest.php`.
- [ ] **Reset sandi via email belum sampai ke user.** `MAIL_MAILER=log`, jadi link reset cuma masuk ke `storage/logs`. Perlu:
  - [ ] Setting SMTP (Gmail App Password / Mailtrap / Resend / SES) di `.env` server.
  - [x] Template email reset bertema Club 61, bahasa Indonesia (`App\Notifications\Auth\ResetPasswordNotification`).
  - [x] Halaman Lupa / Atur Ulang Kata Sandi bergaya Club 61; bisa pakai email ATAU nomor HP.
  - [x] Link reset memakai `APP_URL` (dulu dari header Host — bisa dibelokkan ke domain penyerang karena `trustProxies('*')`).
  - [x] Akun walk-in dengan email placeholder (`*@walkin.club61.internal`, `mbr_*@club61.id`, `corp_*@club61.id`) tidak dikirimi email; halaman mengarahkan ke frontdesk.
  - [x] Batas 5 permintaan/menit per IP; SMTP gagal → pesan jelas + Log Aktivitas KRITIS `auth.password_reset_mail_failed`.
  - [x] Perintah cek SMTP: `php artisan mail:test alamat@email.com`.
  - [ ] Pengirim `MAIL_FROM_ADDRESS` masih `hello@example.com` — isi di `.env` server.
- [ ] **Verifikasi email tidak aktif.** Model `User` tidak mengimplementasikan `MustVerifyEmail`, jadi middleware `verified` di route portal tidak berfungsi.

---

## MODUL 02: PADEL COURT BOOKING ENGINE

### Status: 95%
- [x] Matriks jadwal real-time timezone-aware, tarif Reguler vs Prime Time.
- [x] Jam yang sudah lewat disembunyikan / ditolak (customer, POS walk-in, monitoring).
- [x] Privasi identitas pemain di jadwal publik.
- [x] Katalog sewa alat + pengurangan stok.
- [x] Two-tier concurrency lock (cache lock + `SELECT ... FOR UPDATE`), atomic multi-slot.
- [x] Auto-expiry slot yang tidak dibayar (scheduler tiap 1 menit) dengan anti-premature guard.
- [x] Countdown timer di `/cart` dan `/checkout`.
- [x] Checkout idempotent (`X-Idempotency-Key`).
- [x] Consolidated invoice multi-jam (1 order, 1 boarding pass).
- [x] Pelunasan selisih reschedule (delta) via Midtrans maupun kasir.
- [x] Admin: pindah jadwal, quick settle, batalkan & refund, cek status bayar ke Midtrans.
- [x] Reschedule & selisih bayar (diperbaiki 30 Sep 2026, `tests/Feature/Padel/ReschedulePaymentTest.php`):
  - [x] Benefit membership (kuota / diskon %) & voucher sponsor ikut pindah ke jadwal baru — tidak ada lagi tagih ganda.
  - [x] Modal menampilkan rincian lengkap (tarif, benefit, selisih, pajak, biaya layanan, total) dengan rumus yang sama persis dengan yang ditagih.
  - [x] Bayar di frontdesk wajib shift aktif + bukti bayar (RRN QRIS / slip EDC / referensi transfer), 1 bukti hanya untuk 1 transaksi, uang masuk rekap shift.
  - [x] "Kirim tagihan ke customer": bayar via Midtrans di invoice atau di kasir; rekonsiliasi Midtrans ikut mengecek tagihan selisih.
  - [x] Pindah ke jam lebih murah: selisih HANGUS (kebijakan PM), tercatat di invoice & log, tanpa refund fiktif.
  - [x] Invoice customer menampilkan selisih reschedule (lunas / belum) dan selisih yang hangus.
  - [x] Slot hasil reschedule tidak bisa di-double-book walau cache kunci hilang.
  - [x] Refund dibatasi uang yang benar-benar masuk; opsi "Saldo Deposit Member" (fitur tidak ada) dihapus.
- [x] Check-in via scan QR / ketik kode booking (single-use, toleransi double scan, tolak kalau ada delta belum lunas).
- [x] Invoice customer: pajak, biaya layanan, diskon membership & voucher corporate tampil benar (kartu tiket + PNG e-ticket).
- [x] Arsitektur service modular (5 traits).
- [~] Coach padel: kolom `coach_id` & `coach_fee` ada di skema, **belum ada UI pemilihan pelatih**.
- [x] Checkout ditolak (bukan dianggap lunas) kalau Midtrans error.
- [x] QR e-tiket & kartu member dibuat di browser dengan `public/js/qrcode.min.js` (4 Okt 2026); `api.qrserver.com` (ikut menerima kode akses gate) & CDN qrcodejs tidak dipakai lagi.
- [x] Placeholder `'CLUB61-DEMO'` / `'CLUB61-PASS'` dihapus: tiket tanpa kode QR menampilkan "QR belum tersedia". Halaman `/membership` tanpa paket aktif tidak lagi error 500. Test: `tests/Feature/Payment/CustomerPaymentScriptsTest.php`.

---

## MODUL 03: WELLNESS (SAUNA)

### Status: 35%
- [x] Skema: `wellness_facilities`, `wellness_slots`, `wellness_bookings`, `wellness_waitlists`.
- [x] Seeder fasilitas (Finnish Sauna — Club 61 tidak punya Ice Bath; data Ice Bath lama dibersihkan migration `2026_10_04_100001`).
- [x] API: `GET facilities`, `GET slots`, `POST book`, `POST cancel` (`routes/api/wellness.php`).
- [x] `WellnessBookingService`: lock kuota, potong kuota / diskon membership.
- [ ] **Booking berbayar tidak bisa dibayar:** status tetap `PENDING`, tidak membuat Order / Payment, tidak ada fulfillment handler di `PaymentFulfillmentRegistry`.
- [ ] Waitlist otomatis kalau sesi penuh (tabel ada, logika belum).
- [ ] Halaman customer `/wellness`.
- [ ] **POS Wellness** untuk kasir / resepsionis.
- [ ] Halaman admin **Kelola Club** masih **DUMMY** (4 kartu fasilitas hardcoded, tombol "Jadwal Maintenance" tidak berfungsi).

---

## MODUL 04: SALON & BEAUTY TREATMENT

### Status: 15%
- [x] Skema: `salon_services`, `salon_appointments`, `salon_appointment_services`.
- [x] Seeder layanan & stylist.
- [x] API katalog `GET /api/v1/salon/services`.
- [ ] API daftar stylist.
- [ ] Algoritma duration stacking & anti-overlap stylist.
- [ ] API appointment + pembayaran.
- [ ] Halaman customer `/salon` dan POS salon.

---

## MODUL 05: MEMBERSHIP & GYM PASS

### Status: 75%
- [x] Skema membership (plan, user membership, benefit, saldo kuota, usage log immutable).
- [x] Resource admin **Membership Plan** (CRUD).
- [x] Halaman admin **Jual Membership** (penjualan di kasir, pembayaran & aktivasi).
- [x] Halaman admin **Customer & Member VIP** (read-only, analisa kebiasaan dari data asli).
- [x] API: plans, checkout, my-membership, my-purchases, history, `checkin-gym`.
- [x] Fulfillment handler membership terdaftar (aktivasi otomatis setelah webhook lunas).
- [x] Diskon / kuota membership terpakai di booking padel & wellness.
- [x] Scheduler `membership:sync-expired` harian.
- [x] API katalog paket gym `GET /api/v1/gym/packages` (filter plan dengan benefit GYM).
- [~] **Pembayaran membership online di portal customer** (`customer/membership.blade.php`):
  - [x] Order, pajak & biaya, Midtrans Snap, webhook, aktivasi otomatis sudah asli.
  - [x] Midtrans error tidak lagi mengaktifkan membership gratis.
  - [ ] Rincian tagihan menampilkan harga plan sebagai total, padahal server menambah pajak & biaya admin, jadi nominal di layar beda dengan yang ditagih (`membership.blade.php:546-547`).
  - [ ] **DUMMY:** teks benefit per plan masih hardcoded per tipe plan (`membership.blade.php:~510-543`), bukan dari data benefit plan.
  - [ ] Pilihan metode bayar cuma 2 radio; nilai `MIDTRANS_SNAP` tidak dikenali sehingga semua metode muncul di Snap.
  - [ ] Upgrade / beli plan lain saat masih punya plan aktif belum ditangani.
  - [ ] Membership berstatus `PENDING_PAYMENT` tidak tampil di My Club (customer tidak bisa lanjut bayar).
- [ ] Kartu member digital / QR gate pass gym di portal customer.
- [ ] POS Gym (check-in & jual paket di meja resepsionis).

---

## MODUL 06: CAFE F&B, TABLE QR & KITCHEN KDS

### Status: 45%
- [x] Skema: kategori, menu, modifier, bahan baku, BOM resep, `table_qr_codes`, `kitchen_tickets`.
- [x] Halaman admin **Kelola Menu F&B** (CRUD menu, kategori, modifier, upload gambar aman).
- [x] **POS Kasir F&B** (`/pos`): keranjang, modifier, meja / take away, nama pelanggan, nomor antrian, buka / tutup shift + rekonsiliasi, struk popup, tab Riwayat Transaksi.
- [x] API katalog `GET /api/v1/fnb/menu`.
- [ ] **Kitchen Display System `/kitchen` masih DUMMY:** tiket `#TKT-041` hardcoded, tombol cuma JavaScript lokal, tabel `kitchen_tickets` tidak pernah dibaca / diisi. Pesanan dari POS F&B belum masuk ke dapur.
- [ ] **Pengurangan stok bahan baku (BOM) saat order PAID** (SKEMA SAJA).
- [ ] **Table QR self-order** (SKEMA SAJA): meja di POS masih diketik bebas.
- [ ] **Halaman customer `/cafe` masih kosong** (file 1 baris, tidak ada route).
- [ ] API pemesanan F&B untuk customer / aplikasi mobile.
- [ ] Fulfillment handler F&B di `PaymentFulfillmentRegistry` (dibutuhkan kalau F&B dibayar online).

---

## MODUL 07: MERCHANDISE RETAIL

### Status: 10%
- [x] Skema: `merch_products`, `merch_variants`, `merch_stocks`, `merch_stock_mutations`.
- [x] Seeder produk & varian ukuran.
- [x] API katalog `GET /api/v1/merch/products`.
- [ ] Halaman admin kelola produk & stok.
- [ ] **POS Merchandise** (jual di kasir + mutasi stok).
- [ ] Checkout online + fulfillment handler.
- [ ] Halaman customer `/merch`.

---

## MODUL 08: POS FRONTDESK & SPLIT BILL

### Status: 55%
- [x] Skema: `orders`, `order_items`, `bill_splits`, `bill_split_items`, `payments`, `vouchers`.
- [x] POS Walk-In Padel (`/admin/book-offline-court`) dan POS Kasir F&B (`/pos`).
- [x] Shift kasir (`pos_cashier_shifts`) dengan rekonsiliasi setoran per metode bayar.
- [x] Role **Resepsionis** (POS walk-in + scan / input kode booking).
- [ ] **Riwayat shift kasir:** ringkasan shift cuma muncul sekali di modal saat tutup shift, belum ada halaman daftar shift lama & laporan selisih (over / short).
- [ ] **Split bill** EQUAL & BY_ITEM (SKEMA SAJA).
- [ ] Keranjang terpadu (padel + kafe + raket + merch dalam 1 struk).
- [ ] POS Wellness, Gym, Merchandise (lihat modul masing-masing).
- [ ] Alamat di header struk POS Padel masih hardcoded "Jl. Karang Tengah Raya No. 61" (`book-offline-court.blade.php:1060,1527`). Akan diganti oleh Modul 18.

---

## MODUL 09: ENTERPRISE ROLE & PERMISSION MATRIX

### Status: 100%
- [x] Spatie Permission + Filament Shield, pivot ULID `char(26)`.
- [x] `Club61PermissionMatrix` terpusat, preset permission per role (super_admin, admin, cashier, receptionist, kitchen, customer).
- [x] Daftar izin "backdoor" (refund, reschedule, pajak, harga lapangan, kelola role, hapus user, dll.) disembunyikan dari role admin.
- [x] Resource **Role** kustom (matriks centang per modul, home route).
- [x] `HasPageShield` di semua halaman admin; tombol sensitif dicek di server, bukan cuma disembunyikan.
- [x] Super admin tidak bisa diedit / dihapus oleh non-super-admin.
- [x] Seeder akun demo (`DemoAccessSeeder`) tanpa menimpa edit role manual.

---

## MODUL 10: UNIFIED PAYMENT GATEWAY

### Status: 80%
- [x] `PaymentManager` + driver Midtrans & Mock (mock diblokir di production).
- [x] Webhook Midtrans dengan validasi signature SHA512.
- [x] `PaymentOrchestratorService::markOrderAsPaid` idempoten + fulfillment registry.
- [x] **Rekonsiliasi Midtrans** tanpa webhook: scheduler `payment:reconcile-midtrans` tiap 5 menit, cek saat polling invoice, tombol cek manual untuk staf, auto-tutup order yang ditinggal.
- [x] Pembayaran telat setelah expired tercatat PAID + refund PENDING.
- [~] Fulfillment handler baru untuk **Padel** dan **Membership**. Belum untuk Wellness, Gym, Merch, F&B online.
- [ ] **Xendit belum ada.** Dokumen lama menyebut Xendit sudah jadi, ternyata drivernya tidak ada di kode.
- [x] Fail-closed saat Midtrans error / server key kosong (HTTP 503, tidak ada lagi fallback mock di server).
- [ ] Endpoint `/api/v1/payments/simulate` (khusus local / testing) menandai PAID tanpa menjalankan fulfillment.

---

## MODUL 11: WALK-IN OFFLINE BOOKING (POS PADEL)

### Status: 100%
- [x] Pilih lapangan & jam di kasir, jam lewat disembunyikan.
- [x] Pembayaran tunai / QRIS / transfer / EDC, metode tidak dikenal ditolak.
- [x] Pajak & biaya layanan dari Pengaturan Biaya & Pajak.
- [x] Shift kasir + ringkasan tutup shift.
- [x] Struk cetak.

---

## MODUL 12: SPONSOR / CORPORATE ACCOUNT

### Status: 90%
- [x] Resource admin Sponsor Organization & jadwal akses sponsor.
- [x] Dua jalur membership corporate: dibeli customer, atau diberikan admin (izin `grant_corporate_membership`).
- [x] Pulihkan sponsor lama yang pernah dihapus (tidak error duplikat).
- [x] Dashboard PIC corporate (`/corporate`): kelola anggota, import CSV, rilis / cabut voucher.
- [x] Halaman admin **Sponsor Team** (pratinjau dashboard PIC, dikunci izin sendiri).
- [~] Sebagian keputusan PM masih menunggu (lihat PRD Modul 12 §8).

---

## MODUL 13: MULTI-BRANCH TENANCY

### Status: 0% (PRD saja)
- [x] PRD draft `docs/PRD_MODUL_13_MULTI_BRANCH_TENANCY.md`.
- [ ] Belum ada kolom `branch_id`, model Branch, atau scoping query di kode.

---

## MODUL 14: COMPANY PROFILE / KONTEN WEBSITE

### Status: 100%
- [x] Halaman admin **Kelola Konten Website** (profil, lokasi, footer, fasilitas, value props, terjemahan Inggris).
- [x] Landing page `welcome` membaca konten dari database.

---

## MODUL 15: MANAJEMEN MENU F&B

### Status: 100%
- [x] CRUD kategori, menu, modifier group (relasi many-to-many), gambar via `SecureImageUploader`.
- [ ] Catatan server: upload gambar butuh ekstensi PHP `gd` (`php8.3-gd`). Belum dicantumkan di `composer.json` (`"ext-gd": "*"`).

---

## MODUL 16: ACTIVITY / AUDIT LOG (URGENT)

### Status: 85% (dibangun sendiri, tanpa package)
- [x] PRD `docs/PRD_MODUL_16_ACTIVITY_AUDIT_LOG.md` (keputusan §9 pakai usulan default: simpan 24 bulan, super_admin saja, customer ikut dicatat, akses baca halaman tidak dicatat).
- [x] Tabel `activity_logs` + model **immutable** (tidak bisa diedit / dihapus dari aplikasi, termasuk super_admin).
- [x] Satu pintu tulis `ActivityLogger`: snapshot nama & role pelaku, IP, perangkat, halaman asal, batch per request.
- [x] Rahasia tidak pernah disimpan (password, token, key, hash QR, payload gateway) — diganti `[disembunyikan]`.
- [x] Otomatis: perubahan lapangan, alat sewa, menu F&B, pajak & biaya (KRITIS), voucher, paket membership, sponsor, user, role, konten website.
- [x] Transaksi: setiap pembayaran lunas di semua modul (walk-in, booking online, F&B, membership, pelunasan kasir, webhook, rekonsiliasi Midtrans) lengkap dengan item, metode bayar, meja, antrian, kasir.
- [x] Aksi sensitif: refund / batal (KRITIS), reschedule, check-in, selesai, retur alat, membership corporate gratis (KRITIS), perubahan izin role (KRITIS kalau izin backdoor), ganti role user.
- [x] Shift kasir buka / tutup, ditandai kalau ada selisih setoran.
- [x] Keamanan: login, logout, gagal login (tanpa password), lockout, reset sandi, **setiap percobaan akses tanpa izin (403)**.
- [x] Aksi sistem (scheduler, webhook) tercatat sebagai SISTEM / WEBHOOK, bukan dibebankan ke user yang kebetulan membuka halaman.
- [x] Panel **Log Aktivitas** (super_admin): filter tanggal, pengguna, modul, tingkat, channel, jenis aksi, pencarian; detail sebelum → sesudah; export CSV (izin terpisah, aman dari formula injection).
- [x] Retensi otomatis `audit:prune` harian (menolak konfigurasi 0 bulan).
- [x] Test: `tests/Feature/Audit/ActivityLogTest.php` (21 test).
- [ ] Hash berantai (deteksi manipulasi langsung di database).
- [ ] Alert otomatis ke super_admin (refund besar, perubahan pajak).
- [ ] Tombol "Riwayat" per booking / menu / sponsor di halaman masing-masing.

---

## MODUL 17: LAPORAN KEUANGAN & RIWAYAT TRANSAKSI TERPADU (URGENT)

### Status: 75%
Sumber pendapatan yang harus masuk laporan: POS Walk-In Padel, Booking Online Padel, Membership (online & kasir), POS F&B, POS Wellness, Gym, Merchandise.

PRD: [`PRD_MODUL_17_BUKU_TRANSAKSI_TERPADU.md`](PRD_MODUL_17_BUKU_TRANSAKSI_TERPADU.md) (draft 1 Okt 2026; pertanyaan §10 memakai usulan default).

- [x] **Fase 1 — Fondasi data** (4 Okt 2026):
  - Tabel `ledger_entries` + model immutable `LedgerEntry`: satu baris per kategori (Sewa Lapangan / Add-on Padel / Membership / F&B) per pembayaran atau refund, dengan snapshot order, customer, kasir, shift, metode & bukti bayar.
  - `LedgerWriter` dipanggil di semua cabang `markOrderAsPaid` (lunas, kelebihan bayar, pembayaran ganda `DUPLICATE`, uang masuk untuk tagihan tertutup) dan saat refund jadi `PROCESSED` — di transaksi DB yang sama; gagal tulis buku = pelunasan ikut gagal.
  - Pembagian diskon/pajak/biaya layanan proporsional per kategori, pelunasan selisih reschedule dari `payload_log`-nya sendiri, total baris = `payments.amount` persis (dihitung dalam sen). Transaksi `MOCK` & catatan `legacy_backfill` tidak dicatat.
  - Item sewa alat kini `item_type = EQUIPMENT` (data lama dikonversi migration); kolom `payments.paid_at` (data lama dari `updated_at`), dipakai riwayat & struk POS Walk-In.
  - Command `ledger:backfill {--from=} {--dry-run}` (idempoten) dan `ledger:verify {--date=} {--days=}` (terjadwal 01:15, selisih → Log Aktivitas KRITIS).
  - Test: `tests/Feature/Finance/LedgerTest.php` (18 test).
- [x] **Fase 2 — Halaman Buku Transaksi & Antrian Refund** (4 Okt 2026), grup menu **Keuangan**:
  - **Buku Transaksi**: satu baris per pembayaran / refund, kartu ringkasan (penjualan bersih, biaya layanan, pajak terkumpul, refund, total uang masuk bersih + info benefit, hangus, refund menunggu), filter periode (preset WIB), sumber, kategori, metode, kasir, shift, status, pencarian (no. order / kode booking / nama / HP / RRN).
  - Detail slide-over: pembagian per kategori, bukti bayar, riwayat pembayaran & refund order, booking terkait, log aktivitas order (+ tautan `Log Aktivitas?cari=`).
  - Invoice **SALINAN ADMIN**: struk POS Walk-In untuk pembayaran kasir padel; invoice ringkas dari buku untuk online / membership / F&B / refund. Tercatat `ledger.invoice_viewed`.
  - Tombol **Export** (dropdown): Excel (lembar *Transaksi* + *Rincian Kategori*) & PDF (ringkasan, rekap per kategori, daftar transaksi; maks. 1.500 baris, `barryvdh/laravel-dompdf`) lewat route `admin.buku-transaksi.export` — dikecualikan dari mode SPA panel supaya file terunduh (dulu isi XLSX tampil sebagai teks). Anti formula injection, tanpa HP/email, tercatat `ledger.exported`.
  - **Antrian Refund**: proses (metode + nomor referensi → baris buku negatif) / tolak (alasan wajib), row lock anti diproses dua kali, Log Aktivitas KRITIS (`refund.processed` / `refund.rejected`). Kolom baru `refunds.refund_method`, `refund_reference`, `processed_by_id`, `admin_notes`.
  - Izin backdoor (hanya super_admin): `View:BukuTransaksi`, `export_ledger`, `view_ledger_invoice`, `process_refund_queue`.
  - Test: `tests/Feature/Finance/BukuTransaksiTest.php` (14 test).
- [ ] **Fase 3 — Dashboard**: rincian per kategori/sumber/metode, grafik, Analytics membaca dari buku.

- [~] Halaman **Analytics & Keuangan** sudah query data asli, tapi:
  - [ ] **Pendapatan F&B tidak dihitung sama sekali.**
  - [ ] Wellness, Gym, Merch belum ada (modulnya belum jalan).
  - [ ] Okupansi masih rumus tetap 4 lapangan x 18 jam; filter "ALL" sebenarnya cuma 30 hari.
  - [ ] Belum ada pilih rentang tanggal, grafik, dan export (Excel / PDF).
- [x] Tab Riwayat Transaksi di POS F&B (khusus F&B, 50 transaksi terakhir).
- [ ] **Buku transaksi terpadu**: semua order & payment lintas modul dalam satu tabel, filter (modul, tanggal, metode bayar, kasir, status), klik untuk lihat detail item + pembayaran + refund.
- [ ] **Lihat / cetak ulang invoice** untuk setiap transaksi dari panel admin (sekarang invoice cuma ada di portal customer untuk padel & membership, dan struk kasir cuma sekali tampil setelah bayar).
- [ ] Nomor invoice resmi yang tersimpan (sekarang pakai `order_number`).
- [ ] Laporan per modul (harian / bulanan) + rekap per metode bayar.
- [ ] Laporan shift kasir & selisih setoran.
- [ ] Laporan refund.

---

## MODUL 18: PENGATURAN INVOICE / STRUK TERPUSAT

### Status: 0%
- [ ] Panel pengaturan: logo, nama usaha, alamat, telepon, NPWP, header & footer, catatan kaki per modul.
- [ ] Dipakai oleh semua invoice & struk (POS Padel, POS F&B, Jual Membership, invoice customer, modul lain nanti).
- Kondisi sekarang: tabel `club_finance_settings` hanya menyimpan pajak & biaya admin; alamat struk hardcoded di blade.

---

## MODUL 19: REALTIME (LARAVEL REVERB)

### Status: 0%
- [ ] Belum terpasang: tidak ada `laravel/reverb`, `laravel-echo`, `config/broadcasting.php`, `routes/channels.php`, maupun event `ShouldBroadcast`.
- Kondisi sekarang pakai polling:
  - Monitoring Lapangan (`booking-system.blade.php`) `wire:poll.10s`.
  - Invoice customer polling status bayar maksimal 10 kali.
  - Notifikasi navbar customer dibangun dari data booking, status "dibaca" cuma di localStorage.
- Kandidat pertama realtime: KDS dapur (pesanan baru dari POS F&B), monitoring lapangan, status bayar invoice, notifikasi customer.
- Catatan server: Reverb butuh proses yang jalan terus (supervisor / systemd) + konfigurasi proxy websocket di Nginx.

---

## MODUL 21: KEBIJAKAN REFUND, NO-SHOW & PEMBAYARAN BERMASALAH

### Status: 80% (Tahap 1 selesai 5 Okt 2026, pembayaran bermasalah ditunda)

PRD: [`PRD_MODUL_21_REFUND_NO_SHOW_PEMBAYARAN_BERMASALAH.md`](PRD_MODUL_21_REFUND_NO_SHOW_PEMBAYARAN_BERMASALAH.md) — keputusan PM di §0.

- [x] Refund dua langkah: kasir / resepsionis / admin mengajukan dari Kelola Pemesanan (booking langsung batal, refund penuh tanpa potongan), superadmin menyetujui di Antrian Refund.
- [x] Refund ditolak → uangnya jadi voucher saldo customer (+ email). Customer tidak bisa mengajukan refund sendiri.
- [x] Kunci refund begitu jam main dimulai; reschedule paling lambat 2 jam sebelum main; booking hangus final.
- [x] Voucher dipakai di checkout online & POS Walk-In; halaman Daftar Voucher (Keuangan).
- [ ] Menu Pembayaran Bermasalah (saldo customer terpotong tapi uang belum masuk) — ditunda PM.

---

## MODUL 22: VOUCHER SALDO & VOUCHER PROMO MARKETING

### Status: 30% (fondasi jalan, menunggu arahan PM)

PRD: [`PRD_MODUL_22_VOUCHER_DAN_PROMO.md`](PRD_MODUL_22_VOUCHER_DAN_PROMO.md) — 11 pertanyaan untuk PM.

- [x] Tabel & mesin voucher satu untuk online dan kasir (`VoucherService`), voucher saldo dari refund ditolak, Daftar Voucher.
- [ ] Voucher saldo **sekali pakai, sisa hangus** (keputusan PM 5 Okt 2026 — yang berjalan sekarang masih menyimpan sisa).
- [ ] Halaman Marketing: buat / kelola kode promo nyata (sekarang tampilan contoh) + batas per akun.
- [ ] Skema sebar promo (kode umum sosmed / kode unik / klaim ke akun / otomatis) — menunggu arahan PM.

---

## MODUL 23: STRUKTUR PORTAL CUSTOMER (MEMBERSHIP, MY CLUB, PROFILE, VOUCHER SAYA)

### Status: 0% (PRD disetujui arahnya, belum dikerjakan)

PRD: [`PRD_MODUL_23_STRUKTUR_PORTAL_CUSTOMER.md`](PRD_MODUL_23_STRUKTUR_PORTAL_CUSTOMER.md)

- [ ] Navigasi: tab Membership menggantikan Profile di navigasi bawah HP; menu Membership di navbar desktop; Profile + Voucher Saya + Logout di dropdown akun.
- [ ] My Club jadi profil klub (compro): baris status member + 3 paket paling laku + "Lihat semua paket"; kartu member, voucher & katalog lengkap dipindah.
- [ ] Profile: kartu membership aktif (QR + kuota), menu Voucher Saya, pengaturan akun dirapikan.
- [ ] Voucher Saya gaya Shopee (voucher saldo + voucher jam corporate, tab Tersedia / Riwayat) + endpoint riwayat voucher saldo.
- [ ] Pindahkan link lama (`/my-club#corporate-vouchers`, redirect setelah beli membership, kartu Upgrade di Home).

---

## MODUL 24: COACHING (BOOKING SESI COACH, SETORAN LAPANGAN & PAYOUT COACH)

### Status: 0% (PRD draft, menunggu jawaban PM)

PRD: [`PRD_MODUL_24_COACHING.md`](PRD_MODUL_24_COACHING.md)

- [ ] Data coach partner (profil, harga sesi, jadwal tersedia, rekening, akun login) + migrasi `padel_bookings.coach_id` ke tabel `coaches`.
- [ ] Booking coaching customer & mode Coaching di POS Walk-In (lapangan + jadwal coach dikunci bersamaan, maks 2 murid).
- [ ] Buku Transaksi: setoran lapangan = pendapatan Club, bagian coach = utang ke coach; baris Coaching di Analytics.
- [ ] Payout coach per periode + export.
- [ ] Portal coach (jadwal, check-in murid, pendapatan).

---
## MODUL 20: HALAMAN ADMIN PENDUKUNG

### Status: 0% (semua DUMMY)
| Halaman | Kondisi | Yang dibutuhkan |
| :--- | :--- | :--- |
| **Dashboard** | **DUMMY**: angka "Rp 50.272.597", "132 transaksi", grafik SVG statis, daftar booking palsu | Ringkasan asli hari ini: pendapatan per modul, booking, okupansi, shift aktif |
| **Kelola Club** | **DUMMY**: 4 kartu fasilitas hardcoded | Kelola fasilitas wellness & jadwal maintenance |
| **Kelola Karyawan** | **DUMMY**: "Coach Budi Santoso", "Siti Hair Stylist" hardcoded, tombol Rekrut tidak jalan | Pakai `StaffProfile` / `StaffSchedule` (model sudah ada) |
| **Kelola Turnamen** | **DUMMY**: kartu statis "Segera Hadir" | Model & alur turnamen belum ada |
| **Marketing** | **DUMMY**: voucher CLUB61 / HAPPYHOUR hardcoded, tombol Buat Voucher tidak jalan | Pakai model `Pos/Voucher` (sudah ada) |

### Bagian DUMMY di Portal Customer
- [ ] Dashboard customer: statistik "3 Active Courts / 1000 Lux / Wellness" teks statis.
- [ ] Dashboard customer: kartu "Upgrade to Diamond Club" hardcoded, mengarah ke WhatsApp, bukan ke `/membership`.
- [ ] Dashboard customer: info venue & nomor "0812-6161-PADEL" statis, belum dari Konten Website.
- [ ] Halaman `home`, `padel`, `cafe` di `resources/views/customer` kosong (file 1 baris, tidak ada route).
- [ ] My Club: fasilitas, etiket, "privilege standards" masih teks marketing statis (boleh dibiarkan kalau memang copy tetap).

---

## Catatan Infrastruktur Server
- [x] Scheduler: `padel:release-expired-slots` (tiap menit), `payment:reconcile-midtrans` (tiap 5 menit), `membership:sync-expired` (harian). Butuh cron `* * * * * php artisan schedule:run` sebagai `www-data`.
- [x] Queue worker belum dibutuhkan (belum ada job antrian). Akan dibutuhkan saat email & Reverb aktif.
- [ ] Daftarkan URL notifikasi Midtrans di dashboard Midtrans.
- [ ] Setting SMTP untuk email.
- [ ] Ekstensi PHP `gd` wajib terpasang di server.
