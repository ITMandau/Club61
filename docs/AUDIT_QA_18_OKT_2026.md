# Audit QA & Keamanan — 18 Okt 2026

Audit read-only seluruh aplikasi: payment gateway (Midtrans, DOKU, checkout, webhook, rekonsiliasi), login & hak akses
(API, Livewire admin, upload), halaman (XSS, data bocor, JS), dan logika bisnis / bom waktu. Urusan env (APP_ENV,
mock/simulate, debug, timezone `.env`) **di luar cakupan** — urusan owner.

Kolom **Cek**:
- **✔** = dicocokkan ulang ke baris kode saat penulisan dokumen ini.
- **A** = ditemukan & dicek agen audit, belum dicek ulang baris per baris.

Nomor baris bisa bergeser setelah ada perubahan.

## Status (18 Okt 2026, belum di-commit)
**Sudah diperbaiki** (full suite 932 passed; test `tests/Feature/Padel/BenefitValidityOnPlayDateTest.php`,
`tests/Feature/Sponsor/SponsorOrganizationApiTest.php`; uji mutasi: 7 dari 10 test baru gagal di kode lama):
- **T1** — benefit dinilai per **tanggal main**: kartu member yang habis sebelum tanggal main tidak dipakai (deteksi otomatis
  memilih kartu yang masih berlaku di tanggal itu), voucher sponsor harus masih berlaku saat jam main dimulai. Berlaku di
  checkout online, kasir walk-in, preview, dan layar kasir.
- **T3** — jam akses paket dihitung dalam menit (24:00 benar); satu helper `isOutsideBenefitTimeWindow` dipakai checkout,
  walk-in, preview, dan reschedule. Paket malam (s.d. 00:00) sekarang bisa dipakai di slot 18:00–23:00.
- **Bonus (bug lama yang ketemu saat fix):** member paket berjam akses yang booking di luar jam paket **tidak bisa checkout
  online sama sekali** (preview "tidak berlaku" → server tetap mendeteksi kartu → 422). Sekarang deteksi otomatis yang tidak
  berlaku = harga normal + keterangan di layar checkout; kartu yang dipilih eksplisit tetap ditolak 422.
- **T2 (sebagian)** — organisasi **Ditangguhkan**: semua jalur rilis jam ditolak, voucher karyawan tidak bisa dipakai
  (checkout/walk-in), kartu voucher customer tampil "Deactivated", popup voucher di Home tidak muncul, banner di portal PIC.
  Paket corporate **tanpa kuota jam PADEL** tidak bisa merilis jam lagi (dulu tanpa batas; teks "Unlimited" di portal diganti).
  **Belum:** kontrak habis (menunggu PM — opsi A/B/C di bagian Keputusan).

**Gelombang 2** (full suite 940 passed; test `tests/Feature/AuditFixesOct18Test.php`, 8 test — semuanya gagal di kode lama):
- **S1** — stok bola dikembalikan saat booking batal/expired (semua jalur lewat `reverseBookingBenefits`, idempoten);
  checkout online maks. 10 per alat + satu baris per alat (UI ikut dibatasi 10).
- **T4** — kasir F&B menolak RRN QRIS / approval+trace EDC yang sudah pernah melunasi transaksi.
- **S4** — path foto fasilitas (Konten Website) & foto menu F&B diambil dari database, bukan dari browser.
- **S6** — preview voucher sponsor di kasir walk-in memilih satu voucher dengan aturan yang sama dengan server.
- **S2** — paket membership yang masih dipakai kartu ACTIVE/PENDING_PAYMENT/PENDING tidak bisa dihapus (pesan
  "nonaktifkan saja"; model juga menolak); kartu lama tetap bisa membaca paket yang sudah terhapus.
- **S9** — Jadwal Akses Sponsor diberi label "Proses" + keterangan "sedang dalam proses pembuatan" (aturan belum ditegakkan).
- **R12** — KDS jadi halaman "sedang dalam pengerjaan" (tiket contoh dihapus; pesanan tetap lewat slip stasiun).
- **Dibiarkan atas keputusan user:** sembunyikan email di respons PIC (T5), tab Anggota Tim Sponsor (S5).

**Keputusan user 18 Okt:** T5 sesuai skema (akun yang sudah ada memang ditautkan) — opsional sembunyikan email;
S3 sesuai desain (admin boleh memberi role apa pun selain super_admin); S9 → label "sedang dalam proses pembuatan";
R12 KDS → tampilan "sedang dalam pengerjaan"; S5 sumber roster asli = anggota tim corporate.

---

## 🔴 Tinggi — uang / data bisa bocor sekarang

| # | Masalah | Lokasi | Skenario | Cek |
|---|---|---|---|---|
| T1 | Kuota member & voucher sponsor tetap dipakai untuk **tanggal main setelah masa berlakunya habis** | `ManagesCheckoutAndPayments.php` ±1498-1525 (`end_date >= hari ini`, `isActive()` = hari ini), ±1679 (`expires_at > now()`) | Membership habis 20 Okt → tanggal 19 Okt booking 30 Nov, gratis pakai kuota. Melanggar aturan "benefit habis → harga normal". Jalur reschedule sudah benar (`memberBenefitInvalidReason`), checkout/walk-in belum | ✔ |
| T2 | Kontrak corporate **SUSPENDED / expired tidak pernah diblokir** | `SponsorOrganizationService` `releaseVoucher`/`deductFromOrganizationQuota`, `SponsorOrganizationPolicy`, redeem voucher di checkout. `SUSPENDED` hanya ada di opsi form | Kontrak berhenti → PIC masih rilis jam & karyawan masih main gratis. Paket corporate tanpa saldo PADEL HOURS → rilis **tanpa batas** | ✔ (status) / A (tanpa saldo) |
| T3 | Booking yang selesai **24:00 lolos cek jam paket** member | `ManagesCheckoutAndPayments.php` ±1535-1541 (`end_time` jadi `00:00:00`) | Paket 06:00–16:00 dipakai untuk slot 23:00–24:00. Kebalikannya: paket malam (selesai 00:00) selalu ditolak. Reschedule sudah dibetulkan, checkout/walk-in belum | ✔ |
| T4 | Kasir **F&B tidak cek bukti bayar dipakai ulang** (RRN QRIS / approval EDC) | `FnbPosService.php` ±139-158 — tidak memanggil `PosPaymentProof` (jalur padel, reschedule, membership sudah) | Satu RRN asli diketik ulang di banyak order → semua LUNAS, dapur cetak, rekap shift menghitung, uang tidak ada | ✔ |
| T5 | PIC sponsor bisa **mengintip nama + email akun siapa pun** dari nomor HP, dan mendaftarkannya tanpa izin | `SponsorOrganizationService::findOrCreateUserByPhone` ±503-516; respons `user:id,name,phone,email` di `SponsorOrganizationController` ±55, ±101 | PIC (customer luar) tambah nomor → dapat nama & email (termasuk akun staf/admin) → revoke → ulangi; impor CSV = massal | ✔ |

## 🟠 Sedang

| # | Masalah | Lokasi | Skenario | Cek |
|---|---|---|---|---|
| S1 | Bola **dibeli** (habis pakai), stoknya dipotong saat checkout **sebelum dibayar**; kalau booking batal/expired tanpa bayar, pembelian bola ikut batal tapi **stok tidak dikembalikan**. `quantity` tanpa batas atas | `ManagesCheckoutAndPayments.php` ±102-108, ±173; validasi `equipments.*.quantity` (`PadelBookingController` ±191). Pengembalian hanya ada di `PosMidtransQrisService::cancel()`; `returnEquipment` sengaja melewati BALL | Hold slot, checkout dengan bola = seluruh stok, tidak bayar → stok 0 permanen, semua checkout berbola 422. Tanpa niat jahat pun stok menyusut tiap keranjang ditinggal | ✔ |
| S2 | **Hapus paket membership** → dashboard member 500, kasir walk-in error, aktivasi pembelian yang sudah dibayar gagal | `MembershipPlanResource` Delete/BulkDelete (SoftDeletes, tanpa cek pemakai); `->plan->name` di `dashboard.blade.php` ±269, `BookOfflineCourt.php` ±617; `->plan->duration_days` di `MembershipBalanceService` ±125/180, fulfillment | Admin hapus paket lama "Gold" → semua member Gold tidak bisa buka Home | ✔ |
| S3 | Admin (`update_users`) bisa **naik hak akses lewat role kustom** | `UserResource` ±70-92 (hanya menyaring `super_admin`), `UserPolicy::canTouch` (hanya melindungi akun super_admin) | Super admin bikin role "finance" (refund, buku transaksi) → admin pasang role itu ke dirinya, atau ganti sandi user finance lalu login | ✔ (kode) |
| S4 | **Path file dari browser** dipakai untuk menghapus file | `KelolaKontenWebsite.php` ±355-376 (`$facility['photo_path']`), `KelolaMenuFnb.php` (`existingMenuPhotoPath`) — properti publik tanpa `#[Locked]` | Staf yang cuma punya izin konten website mengubah `photo_path` lewat Livewire → video signage / foto menu terhapus (terbatas di disk public) | ✔ |
| S5 | Tab **"Anggota Tim Sponsor"** (Kustomer) = data karangan + tombol blast WA | `Kustomer.php` ±165-257 (6 karyawan PT Sinar Harapan), `kustomer.blade.php` ±305-323, ±381 (nomor cadangan `081311223344`, teks "108 Jam kuota PT Sinar Harapan") | Staf buka sponsor asli → lihat karyawan palsu → klik Blast → PIC asli dapat pesan "108 jam PT Sinar Harapan", atau nomor orang asing | ✔ |
| S6 | Preview voucher sponsor di kasir **menjumlah semua voucher**, checkout cuma pakai **satu** → selalu 409 "Total tagihan berubah" | `quoteWalkInBenefits` ±1876-1901 vs apply ±1698-1704 | Member punya 2 voucher @1 jam, main 2 jam di kasir → layar gratis, server kurang → transaksi tidak bisa selesai | ✔ |
| S7 | Slot 23:00–24:00 **tidak bisa dibooking online** kalau lapangan tutup 24:00 | `PadelBookingController::hold` `slots.*.end_time` `date_format:H:i` (menolak `24:00`), padahal jadwal mengirim `24:00` | Slot tampil tersedia, klik → 422. Hanya terjadi bila jam tutup diset 24:00 | ✔ |
| S8 | Admin fee **FIXED tetap ditagih pada transaksi Rp0** | `TaxAndFeeService.php` ±44-54 (pajak dijaga `> 0`, admin fee tidak) | Booking 100% ditanggung kuota → grand total Rp2.500 → customer dilempar ke gateway; jalur "Tagihan Rp0" tidak pernah jalan. **Bisa jadi memang aturan bisnis — perlu konfirmasi** | ✔ (kode) |
| S9 | **Jadwal akses sponsor** (hari/jam/maks lapangan) bisa diatur tapi tidak pernah ditegakkan | `SponsorScheduleService`, `SponsorAccessScheduleResource`; checkout/walk-in tidak membacanya | Aturan "sponsor A hanya Sen–Jum 10–15, maks 1 lapangan" → karyawan tetap pakai voucher jam sibuk di semua lapangan | A |
| S10 | **Sponsor signage beku saat Tayangkan** → sponsor yang sudah habis tetap tayang | `SignatureService::buildPayload` ±365 memfilter `isShowing()` saat publish; TV membaca payload | Sponsor berakhir 31 Okt tetap di TV/ticker sampai ada yang klik Tayangkan lagi; sponsor yang mulai nanti tidak muncul sendiri | A |
| S11 | **Upgrade ke paket corporate di kasir** tidak membuat organisasi sponsor; jam rollover bisa hilang | `MembershipBalanceService::upgradeMembership` ±230-285 (dari `JualMembership`) — fulfillment melewati kartu yang sudah ACTIVE | Upgrade individu → corporate dibayar, portal PIC tidak pernah ada; org→org menunjuk kartu lama yang saldonya sudah dipindah | A |

## 🟢 Rendah / bom waktu kecil

| # | Masalah | Lokasi | Cek |
|---|---|---|---|
| R1 | Batas percobaan login **API** per akun bisa dilewati dengan variasi format nomor HP (`0812-…`, `+62…`) — login web sudah benar | `AppServiceProvider` ±123-130 (kunci = teks mentah) | A |
| R2 | **Verifikasi email tidak aktif** — `User` tidak `MustVerifyEmail`, middleware `verified` tidak berbuat apa-apa; `POST register` web tanpa throttle | `app/Models/User.php`, `routes/auth.php` ±18 | ✔ |
| R3 | Admin bisa mengganti **PIC sponsor** ke akun mana pun (termasuk dirinya) lalu rilis jam klien lewat API | `SponsorOrganizationResource` `sponsor_admin_user_id` | A |
| R4 | Kuota **voucher promo** terhitung dobel untuk order PARTIALLY_PAID (promo belum bisa dibuat → belum aktif) | `VoucherService.php` ±71-77 | A |
| R5 | `signature_key` Midtrans ikut tercatat di log jalur rekonsiliasi (order_id tidak cocok — jarang) | `MidtransReconciliationService.php` ±140 | A |
| R6 | Membership berlaku **1 hari lebih** dari `duration_days` (30 hari → 31 hari dipakai). Perlu konfirmasi aturan | `MembershipBalanceService` ±124, `MembershipFulfillmentHandler` ±61 + `isActive()` inklusif | A |
| R7 | Benefit "Booking hingga H-N lebih awal" ditampilkan di paket tapi tidak ditegakkan; booking ke depan tanpa batas (terkait #5 lama) | `MembershipFacilityService` ±121 | A |
| R8 | Migrasi data menulis `company_profile_settings` lewat `DB::table` → cache `rememberForever` tidak terhapus | migrasi `2026_10_12_110001…`, `2026_09_29_000005…` | A |
| R9 | Cek bukti bayar kasir memindai **semua** payment SUCCESS tanpa index/jendela waktu → kasir makin lambat seiring data | `PosPaymentProof.php` ±196-215 | A |
| R10 | API sauna (`/api/v1/wellness/book`) hidup tanpa pembayaran/expiry → kapasitas bisa dihabiskan gratis (hanya kalau slot sauna ada) | `routes/api/wellness.php`, `WellnessBookingService` | A |
| R11 | Nonaktifkan lapangan / ubah jam tidak mengecek booking yang sudah dibayar ke depan → booking hilang dari grid & signage tanpa peringatan | `MasterData.php` ±258-277 | A |
| R12 | Halaman **Kitchen Display** (`/kitchen`) masih mock statis (tiket #TKT-041…), padahal jadi beranda akun dapur | `resources/views/kitchen/kds.blade.php` | ✔ |
| R13 | TV signage default menampilkan **nama lengkap** customer di URL publik | `name_display` default `FULL` | A |
| R14 | **Lonceng notifikasi**: menampilkan booking paling jauh (bukan terdekat), jam pakai jam HP berlabel WIB, nama lapangan selalu "Padel Court" | `navigation.blade.php` ±113, ±343, ±368 | ✔ |
| R15 | Tab tanggal Book Court pakai tanggal HP, tanggal terpilih pakai WIB → HP zona lain/tengah malam tidak cocok | `booking.blade.php` ±228, ±317 | A |
| R16 | Link WhatsApp di Kustomer `wa.me/08…` tidak terbuka (butuh 62…) | `kustomer.blade.php` ±75, ±381, ±587 | ✔ |
| R17 | Teks "Gedung Indosat Medan" masih tertulis di kode (login, Home hero, navbar, POS) — tidak ikut Konten Website | beberapa view | A |
| R18 | Daftar yang terus tumbuh dimuat penuh: daftar member Kustomer; `my-bookings` tanpa batas & dipanggil lonceng di **setiap** halaman customer | `Kustomer.php` ±91-113, `ManagesTicketsAndRefunds.php` ±20-37 | A |
| R19 | Inisial avatar Home rusak untuk huruf non-Latin (`substr` byte) | `dashboard.blade.php` ±261 | A |
| R20 | Laten (jalur mati hari ini): `markOrderAsPaid` dengan `transaction_id` baru jatuh ke tagihan PENDING terbaru order | `PaymentOrchestratorService.php` ±77-83 | A |
| R21 | `?booking_id=` invoice masuk path `fetch` tanpa `encodeURIComponent` (dampak kecil) | `invoice-scripts.blade.php` ±554, ±606 | A |

## ✅ Sudah aman (dicek audit ini)
- **Webhook:** Midtrans memakai SHA512 `hash_equals` dan "lunas" dikonfirmasi lewat Status API. DOKU memeriksa tanda tangan atas raw body, dedupe Request-Id, lalu konfirmasi status dan nominal. Notifikasi berulang idempoten; bayar dobel dicatat DUPLICATE lalu direfund.
- **Uang:** pajak, fee, dan diskon dihitung server dalam rupiah integer. Ledger seimbang dan memakai dedupe. Refund dibatasi uang yang belum dialokasikan.
- **Double booking:** baris lapangan dikunci, overlap dicek dengan locking read, dan kunci diambil berurutan. `booking_code` dan `order_number` unik di DB.
- **IDOR:** tiket, my-bookings, retry, membership, voucher, dan sponsor semuanya dibatasi ke pemilik.
- **Admin:** setiap aksi Livewire yang memindahkan uang mengecek `can()` di server. Total walk-in dihitung ulang (409 kalau beda). Properti penting kasir memakai `#[Locked]`.
- **Halaman:** tidak ada XSS. Raw output hanya konstanta server, dan nama customer dibatasi huruf. CSRF lengkap. Link dari CMS disaring http/https. Upload gambar di-encode ulang, video dicek MP4. Ekspor CSV/XLSX menetralkan formula.
- **Login:** web login di-throttle per akun dan IP. Reset password tidak membocorkan akun dan URL-nya dari `APP_URL`. Ganti sandi mencabut token.

---

## Catatan audit lama (16 Okt 2026) — masih terbuka
Detail di [PLAN_AUDIT_PAYMENT_GATEWAY.md](PLAN_AUDIT_PAYMENT_GATEWAY.md) (sekarang jadi arsip/catatan). Yang sudah dikerjakan 18 Okt
tidak diulang di sini.

| Item | Status |
|---|---|
| Scheduler di server (cron vs supervisor, user `www`) | Menunggu pilihan & pemasangan |
| #5 Batas hold slot (usul 4/request, 4/akun, 30 hari) | Menunggu angka — terkait R7 |
| #6 Email invoice (antrian vs afterResponse) | Menunggu pilihan |
| #7 `/schedule` read-only + throttle | Setelah scheduler jalan (agen halaman juga menandai ini) |
| #9 `trustProxies('*')` | **Risiko aktif** — menunggu hasil grep `fastcgi_pass`/`proxy_pass` + info Cloudflare. Juga memperlemah R1 |
| Deadlock urutan lock kasir vs webhook, tabel `cache` tidak dibersihkan | Dicatat |

## Keputusan yang dibutuhkan
1. **S8** — admin fee tetap ditagih kalau tagihan Rp0 (booking full kuota)? Usul: gratis total (tanya PM).
2. **R6** — paket 30 hari = berlaku 30 atau 31 hari? Sekarang mulai hari beli s.d. hari ke-31 (tanya PM).
7. **T2 kontrak habis** — A: tidak bisa rilis lagi + masa voucher dipotong s.d. tanggal kontrak (usul); B: masa tenggang; C: biarkan (tanya PM).
3. **S9** — jadwal akses sponsor ditegakkan sekarang, atau layar admin diberi label "belum berlaku"?
4. **S5 / R12** — tab Anggota Tim Sponsor & Kitchen Display: diisi data asli atau dijadikan "Segera Hadir" dulu?
5. **T5** — PIC sponsor: akun yang sudah ada tetap boleh ditautkan (tanpa email ditampilkan), atau wajib konfirmasi dari pemilik akun?
6. **R2** — verifikasi email diaktifkan (customer wajib klik link email) atau `verified` dihapus saja?

## Urutan kerja yang diusulkan
1. T1, T3 (satu helper cek benefit per booking — dipakai checkout, walk-in, preview), S6, S7.
2. T2 + R3 (status & masa kontrak sponsor), T4 (bukti bayar F&B), S1 (stok bola + batas quantity).
3. T5, S3, S4 (hak akses & path dari browser).
4. S2 (blok hapus paket + `withTrashed`), S5/R12 sesuai keputusan, S10, S11.
5. Rendah sesuai sempat; #9 begitu info nginx masuk.
