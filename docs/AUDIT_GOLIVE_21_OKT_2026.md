# Audit Go-Live — 21 Okt 2026

Audit read-only seluruh aplikasi terhadap **Club 61 — Go-Live Checklist** (11 bagian). Pendamping
[AUDIT_QA_18_OKT_2026.md](AUDIT_QA_18_OKT_2026.md); temuan di sana tidak diulang kecuali statusnya berubah.
Tidak ada file kode yang diubah, `.env` tidak dibaca, database tidak disentuh.

Kolom **Cek**: **✔** = dicocokkan ulang ke baris kode saat dokumen ini ditulis · **A** = ditemukan agen audit, belum dicek
ulang baris per baris. Nomor baris bisa bergeser.

---

## Jawaban singkat

**Sistem antrian (queue)?** Tidak ada antrian sungguhan. Tidak ada `app/Jobs`, `ShouldQueue`, `Mail::queue`, atau Horizon.
- Notifikasi DOKU (Checkout & SNAP) diproses `dispatch(fn)->afterResponse()`, yaitu di proses PHP yang sama setelah respons
  200 terkirim, selalu `sync` apa pun isi `QUEUE_CONNECTION`.
- Webhook Midtrans, pembayaran kasir, email invoice/voucher semuanya sinkron di dalam request.
- `QUEUE_CONNECTION` (default `database`) hari ini tidak berpengaruh; `queue:work` belum dibutuhkan.
- **Cadangan kalau proses mati / notifikasi hilang = scheduler** (`payment:reconcile-doku`, `payment:reconcile-midtrans`,
  `padel:release-expired-slots`, `pos:sweep-auto-payments`) — dan **scheduler belum terpasang di server** (item lama).
  Ini membuat beberapa temuan di bawah jadi lebih berbahaya.

**Query yang jalan bareng & bentrok (double booking)?** Desain lock-nya benar untuk MySQL InnoDB: baris lapangan dikunci
(`lockForUpdate`) lalu overlap dicek dengan locking read, urutan lock sama di hold online, walk-in, reschedule, dan import;
batas slot end-eksklusif; webhook hanya mengubah LOCKED/PENDING → PAID; pelepas slot memakai update bersyarat.
Kekurangan: tidak ada UNIQUE di DB sebagai pertahanan terakhir, dan satu-satunya test concurrency berjalan berurutan di
SQLite (lock tidak berbuat apa-apa) — **belum ada bukti paralel sungguhan**. Bug nyata yang ketemu: G8 (kunci cache slot
tidak dilepas saat QR kasir dibatalkan).

**Keamanan aplikasi?** Tidak ada SQL injection (semua raw query konstanta/whitelist), tidak ada mass assignment dari input
user, tidak ada IDOR (termasuk endpoint baru DOKU SNAP, `pay_display`, voucher, `my-purchases`), Livewire/Filament/Laravel
versi terbaru, upload aman, export dibatasi + dicatat, audit log aksi sensitif lengkap. Yang kurang: G1 (webhook mock — keputusan lama, aman selama APP_ENV=production),
MFA, maker-checker refund, CSP, `trustProxies('*')` (#9 lama).

**Query yang membebani server?** Tidak ada yang berbahaya di skala satu venue; ada 3 pola boros (G35–G37, lihat bagian
"Beban query"). Catatan: `->where(...)->first()` di Laravel menjadi SQL `... LIMIT 1` — pencarian dikerjakan MySQL (lewat
index kalau ada), Laravel tidak memeriksa baris satu per satu. Yang memberatkan adalah: menarik semua baris lalu disaring di
PHP, query di dalam loop (N+1), kolom tanpa index / dibungkus fungsi (`DATE(created_at)`), dan daftar tanpa batas.

---

## 🔴 Tinggi — perbaiki sebelum dibuka ke publik

| ID | Temuan | Lokasi | Skenario | Cek |
|---|---|---|---|---|
| G2 | **DOKU SNAP: jawaban VA berawalan `404` dianggap "sesi tidak ada"** | `DokuSnapService.php:370` (`str_starts_with(responseCode,'404')`), `DokuSessionGuard.php:108,243,284` | Kode SNAP `404xx…` bisa berarti hal lain (mis. "paid bill", atau request kita ditolak). Akibat: sesi ditandai tertutup tanpa delete-va, sesi dipakai ulang, atau pembayaran tidak pernah dikenali → uang mengendap tanpa catatan / bayar dobel tanpa refund. Fix: NOT_FOUND hanya untuk kode yang terbukti di sandbox, sisanya ERROR; `close()` tetap delete-va. | ✔ |
| G3 | **`payment:reconcile-doku` mati total kalau hanya kunci SNAP yang diisi** | `ReconcileDokuPayments.php:35` (cek `DokuClient::isConfigured()` = kunci Checkout saja) | Saat kunci non-SNAP dikosongkan (rencana takedown), rekonsiliasi DOKU "dilewati" tiap 5 menit: notifikasi RETRY tidak diulang, VA lama yang dibayar setelah ganti metode tidak tercatat DUPLICATE/refund. Fix: pakai `DokuCheckoutService::isConfigured()`. | ✔ |
| G4 | **Notifikasi DOKU "SUCCESS" yang tidak pernah tuntas berhenti diam-diam** | `DokuNotificationProcessor.php:25-58`, `ReconcileDokuPayments.php:42-48` (berhenti setelah 30x / 48 jam) | Status API tidak pernah menyatakan lunas, atau tagihan tidak ketemu (UNMATCHED) → tidak ada [ALERT], tidak ada refund, hanya log `info`. Midtrans untuk kasus serupa sudah `Log::critical`. Fix: [ALERT] + log aktivitas KRITIS saat batas tercapai / UNMATCHED. | ✔ |
| G5 | **Scheduler belum terpasang** (item lama, prioritas naik) | `routes/console.php`; audit 18 Okt "Catatan audit lama" | Semua cadangan G3/G4, pelepas slot, pembersih QR kasir, prune notifikasi & audit log bergantung padanya. | A |
| G6 | **Tidak ada halaman Syarat & Ketentuan / Privasi / Refund, dan teks kebijakan di checkout salah** | `checkout.blade.php:430-431` vs `PadelBookingService.php:31`; `membership.blade.php:307` | Checkout menulis reschedule "hanya s.d. H-3" padahal sistem mengizinkan s.d. **2 jam**; "No refunds" padahal sistem membuat refund. Halaman legal juga diperiksa gateway saat review merchant. | ✔ |
| G7 | **Tidak ada backup otomatis / error tracking** | `composer.json` (tanpa paket backup, Sentry/Flare) | Data rusak / disk penuh tidak bisa dipulihkan; error production tidak ketahuan. | A |

## 🟠 Sedang

| ID | Temuan | Lokasi | Skenario | Cek |
|---|---|---|---|---|
| G1 | **Webhook `mock` bisa melunasi tanpa uang** kalau `APP_ENV` bukan persis `production` — **dulu sengaja dilewati atas keputusan user (16 Okt)**. Production & pre-prod saat ini `APP_ENV=production` (log pre-prod `production.INFO`), jadi tidak terbuka selama itu | `PaymentManager.php:35` (`! environment('production')`), `routes/api/padel.php:19` `/webhook/{driver}` publik | Kalau suatu server publik di-set `staging`/`local` → `POST /api/v1/padel/webhook/mock` melunasi order. Saran tetap: allowlist `local,testing` (3 baris), seperti `/payments/simulate`. | ✔ |
| G8 | **QR kasir dibatalkan → slot tetap terkunci di cache** sampai masa hold habis | `PosPendingOrderCanceller.php:50` tidak melepas `padel_lock:*` (bandingkan `ManagesScheduleAndSlots.php:402-411`) | Kasir batalkan QRIS lalu bayar EDC → "Slot sedang ditahan pemain lain"; customer online juga 409 padahal grid AVAILABLE. | ✔ |
| G9 | **Kasir membuat akun dobel kalau HP diketik tanpa 0** | `ManagesCheckoutAndPayments.php:985-994` (beda dengan `PhoneNumber::normalize`) | `812…` → `812…` (bukan `0812…`) → akun member lama tidak ketemu → harga normal + akun walk-in baru. | ✔ |
| G10 | **UNIQUE email/HP di `users` tidak efektif** | `0001_01_01_000000_create_users_table.php:24-25` (`unique([phone, deleted_at])`, `deleted_at` NULL dianggap berbeda di MySQL) | Daftar dobel paralel / tap ganda → 2 akun HP sama; login `first()` → booking & membership terpecah. | ✔ |
| G11 | **Refund tanpa maker-checker** | `RefundQueueService.php:29-46` | Satu akun yang punya izin ajukan + proses bisa membatalkan booking lalu memproses refund-nya sendiri. | ✔ |
| G12 | **Admin ganti password customer, token aplikasi lama tetap hidup 30 hari** | `User.php:64-78` (token dicabut hanya saat nonaktif) | HP hilang → admin ganti sandi → pencuri tetap pakai token lama. | ✔ |
| G13 | **Akun siapa pun (termasuk admin) bisa dikunci dari luar terus-menerus** | `LoginRequest.php:48-49,112,120` (batas per akun lintas IP) | 20 sandi salah per 15 menit dari IP mana pun → pemilik akun ikut terkunci. | A |
| G14 | **Panggilan HTTP DOKU SNAP di dalam transaksi DB yang mengunci baris** (timeout 20 dtk + retry 401) | `DokuSnapClient.php:37,142-180`; dipanggil dari checkout `ManagesCheckoutAndPayments.php:321`, bayar ulang `:719` | DOKU lambat → request lewat batas PHP → rollback, padahal QR/VA lama sudah di-expire di DOKU; lock tertahan (webhook & checkout lain menunggu). Checkout DOKU sengaja 10 dtk. | A |
| G15 | **Belum ada MFA panel admin**; Filament ≤5.8.2 punya advisory medium (MFA tanpa reauth) | `AdminPanelProvider.php` | Password super_admin bocor → semua izin (refund, gateway, role). Aktifkan MFA setelah upgrade Filament ≥5.8.3. | A |
| G16 | **Timezone bawaan UTC** | `config/app.php:68`, `.env.example:5` | Kalau server UTC: slot yang sudah lewat masih bisa dibooking (s.d. 7 jam), no-show/reschedule/email invoice bergeser 7 jam. Cek `php artisan about`. | ✔ |
| G17 | **Batas hold per user & jumlah slot per request** (#5 lama, P0 checklist) | `PadelBookingController.php:89`, `AppServiceProvider.php:144` | Satu request bisa menahan semua lapangan seharian, diulang tiap habis. Menunggu angka. | A |
| G18 | **Akun demo bersandi tetap di repo** bisa ada di sandbox / production | `DemoAccessSeeder.php`, `DatabaseSeeder.php:104` | Sandbox satu VPS & publik. Cek DB, hapus / ganti sandi akun seed. | A |
| G19 | **Parameter `timezone` di `/schedule` publik tidak divalidasi** | `PadelBookingController.php:28,32` | `?timezone=abc` → 500 di endpoint tanpa login; HP zona WITA → jam meleset. | A |
| G20 | **Biaya admin ikut dibebankan pada QRIS** — perlu dicek ke aturan BI (MDR QRIS tidak boleh dibebankan ke customer) | `TaxAndFeeService.php:44-54` | Keputusan finance / konsultan. | A |

## 🟢 Rendah / P1

- **G21** Tidak ada Content-Security-Policy (`SecurityHeaders.php`) — hardening, belum ada XSS. (A)
- **G22** Webhook ikut terblokir `artisan down` — tambah `preventRequestsDuringMaintenance(except: [...webhook...])` atau jalankan reconcile manual setelah `up`. (A)
- **G23** Tidak ada UNIQUE slot di DB (pertahanan terakhir) dan test concurrency sungguhan di MySQL. (A)
- **G24** API check-in membalas "Akses gate dibuka" untuk QR yang sudah dipakai — penting kalau turnstile hanya membaca `success`. (A)
- **G25** Dedupe notifikasi SNAP hanya pakai `X-EXTERNAL-ID` (unik per hari menurut DOKU). (✔ — sudah dicatat sebelumnya)
- **G26** `.gitignore` tidak memuat `*.pem` (kunci SNAP kebetulan di `/etc/club61`, tapi jaga-jaga). (A)
- **G27** Kalau customer tanpa HP, nomor venue dikirim sebagai HP customer ke gateway. (A)
- **G28** Daftar tanpa pagination: `my-purchases`, `vouchers/mine`, `wellness/facilities` (R18 lama untuk `my-bookings`). (A)
- **G29** `sanctum:prune-expired` tidak dijadwalkan; `payments.payload_log` & tabel `cache` tidak pernah dibersihkan. (A)
- **G30** Hapus akun = soft delete, PII tetap; tidak ada versi API (UU PDP, kebijakan store). (A)
- **G31** Index `payments(payment_gateway, status)` belum ada (rekonsiliasi tiap 5 menit) — aman untuk volume awal. (A)
- **G32** `.env.example` `LOG_STACK=single`, `LOG_LEVEL=debug` → di server pakai `daily` + `info`. (A)
- **G33** Kalau `CACHE_STORE=redis`, membaca kunci lock lewat `Cache::get` bisa 500 di `/schedule` & grid kasir. Default `database` aman. (A)
- **G34** Request-Timestamp notifikasi DOKU Checkout tidak dicek umurnya — dampak kecil (dedupe + konfirmasi Status API). (A)

## ⚙️ Beban query (performa) — ditambahkan 21 Okt 2026

Dicek dengan grep pola + detektor N+1 bawaan Laravel (`Model::preventLazyLoading()` dipasang sementara hanya saat test,
lalu dicabut). Full suite 982 lolos; hanya 1 lazy load tertangkap (`Role::permissions` di dalam paket spatie, jumlah role
sedikit → abaikan). Detektor hanya melihat jalur yang punya test, dengan data test yang kecil.

| ID | Temuan | Lokasi | Seberapa sering / dampak | Cek |
|---|---|---|---|---|
| G35 | **Notifikasi VA SNAP tanpa `trxId`: semua tagihan DOKU 7 hari ditarik lalu nomor VA dicocokkan satu per satu di PHP** | `DokuSnapNotificationController.php:126-142` (`invoiceForVa`) | Hanya cadangan saat DOKU tidak mengirim `trxId`, setelah token DOKU lolos. Jarang, tapi tumbuh seiring jumlah tagihan DOKU per minggu (+ kolom JSON `payload_log` ikut ditarik). | ✔ |
| G36 | **Rekonsiliasi DOKU tiap 5 menit menarik semua tagihan DOKU lunas 48 jam lalu menyaring di PHP**; langkah 4 + 1 query `exists` per tagihan | `ReconcileDokuPayments.php:91-101`, `DokuSessionGuard.php:264-268` | Ratusan baris per jalan untuk volume awal → kecil. Tidak ada index `payments(payment_gateway, status)` (G31). | ✔ |
| G37 | **`whereDate('created_at')` di tabel `orders` yang tidak punya index `created_at`** → MySQL menyisir semua order sepanjang masa untuk menghitung order hari ini | `BookOfflineCourt.php:2445,2456` (statistik walk-in, cache 10 dtk), `FnbPosService.php:90-93` (nomor antrian tiap order F&B, + `whereHas items`) | Makin lambat seiring data menumpuk. Perkiraan (bukan diukur): ±50 rb order/tahun → belasan–puluhan ms per hitungan. Paling layak dibenahi dari tiga ini. | ✔ |

Sudah dicek dan aman:
- `/schedule` publik: 3 query (lapangan, booking aktif sekali tarik, kunci cache `Cache::many`). GC yang ikut jalan hanya
  menyisir booking berstatus aktif → tertutup index `padel_bookings(status, expires_at)`.
- Loop query di checkout/kasir hanya per item keranjang satu transaksi (sengaja `lockForUpdate` satu per satu).
- Kolom relasi bertitik di tabel Filament di-eager-load otomatis oleh Filament.
- Polling: Reverb dulu; cadangan 30–60 dtk hanya saat layar terlihat; invoice 3→10 dtk lalu berhenti; QR kasir 3 dtk hanya
  selama popup terbuka.
- `LIKE '%kata%'` hanya di pencarian panel admin → wajar untuk skala ini.

## Status per bagian checklist

| Bagian | Ringkas |
|---|---|
| 1. Booking & slot | ✅ end eksklusif, harga server + snapshot, walk-in & online satu inventory, TTL hold ≥ sesi gateway. ⚠️ timezone (G16), aturan booking (tanpa batas hari ke depan / cutoff), tarif libur ❓ data. ❌ UNIQUE DB (G23), batas hold (G17), test paralel. |
| 2. Pembayaran | ✅ nominal server integer, jumlah item = total, bayar setelah hold habis → refund, halaman "menunggu" + polling, anti klik ganda, sanitasi nama. ⚠️ bayar dobel di SNAP (G2/G3), fee QRIS (G20). ❌ SOP refund VA, rekonsiliasi settlement harian. |
| 3. DOKU / Midtrans / switching | ✅ signature raw body + path, `override_notification_url`, token B2B cache + refresh, X-EXTERNAL-ID unik, verifikasi callback SNAP (RSA + JWT + konfirmasi status), gateway per transaksi, ganti gateway hanya izin + audit log. ⚠️ timestamp Checkout (G34). ❌ runbook gateway down. ❓ key & URL di dashboard. |
| 4. Webhook, queue, rekonsiliasi | ✅ tanpa CSRF/auth, idempoten, status code, nominal, state machine, log tanpa signature/key, `Http::timeout` semua panggilan, rekonsiliasi per gateway + `withoutOverlapping`. ⚠️ maintenance (G22), alert notifikasi gagal (G4), SMTP `timeout null` (#6 lama). ❌ scheduler terpasang (G5). N/A queue worker (tidak ada job). |
| 5. Keamanan aplikasi | ✅ raw query, sort whitelist, mass assignment, IDOR, Livewire versi, upload, `public/` bersih, debug di `require-dev`, tidak ada key asli di repo. ⚠️ npm audit high hanya di tooling build; webhook mock (G1, keputusan lama). ❌ CSP (G21), `trustProxies('*')` (#9). |
| 6. Filament & Finance | ✅ `canAccessPanel` staf saja, Policy/izin tiap Resource & Page, bulk/force delete dibatasi, export dibatasi + dicatat, audit log aksi sensitif. ⚠️ role Finance belum ada (izin keuangan hanya super_admin). ❌ maker-checker (G11), MFA (G15). |
| 7. Akun & API | ✅ throttle login web & reset, pesan tidak bocor, token 30 hari, field sensitif hidden. ⚠️ R1 lama, enumerasi lewat register, pagination (G28), token setelah ganti sandi oleh admin (G12), lockout (G13). ❌ verifikasi HP/email (R2), hapus akun API (G30). N/A OTP (tidak ada fitur OTP). |
| 8. Membership & QR | ✅ kuota jam & voucher tahan race (lock + log), QR padel HMAC + jendela waktu + update bersyarat. ⚠️ endpoint sauna/gym hidup tanpa flag (R10 lama), QR bekas di API (G24), QR member pass tidak divalidasi di mana pun. N/A poin (tidak ada sistem poin). |
| 9. Infrastruktur | ✅ halaman 503, migrasi Okt hanya menambah. ❌ backup + error tracking (G7). ⚠️ log (G32), index (G31), query boros (G35–G37). ❓ NTP, APP_ENV/DEBUG, firewall, SSL, resource sandbox (sandbox & production satu VPS). |
| 10. Data & legal | ❌ halaman legal + teks kebijakan (G6). ⚠️ akun demo (G18), retensi (G29). ❓ data master final. |
| 11. Operasional & tes | Test otomatis sudah ada untuk: webhook duplikat (berurutan), bayar saat hold habis, signature salah, nominal dimanipulasi, notifikasi diputar ulang, percobaan lama dibayar, akses per role. Belum: webhook paralel sungguhan, worker mati di tengah job (N/A, tanpa queue). Sisanya tindakan pemilik. |

## Yang harus dicek / dilakukan pemilik di server (❓)

```bash
timedatectl                                   # System clock synchronized: yes
sudo -u www-data php artisan about --only=environment   # Production, Debug OFF, Timezone Asia/Jakarta, Config/Routes/Views cached
crontab -u www-data -l                        # harus ada: * * * * * cd /path && php artisan schedule:run
sudo -u www-data php artisan schedule:list
ufw status verbose                            # hanya 22/80/443
ss -tlnp | grep -E '3306|6379|8080'           # DB/Redis/Reverb hanya 127.0.0.1
df -h; certbot renew --dry-run
```
- `.env` server: `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Asia/Jakarta`, `LOG_STACK=daily`, `LOG_LEVEL=info`,
  `SESSION_SECURE_COOKIE=true`, `CACHE_PREFIX` beda antara sandbox & production.
- DB production & sandbox: cari akun seed (`@club61.com`, `budi@gmail.com`) dan transaksi `MOCK`/sandbox; hapus / ganti sandi.
- Document root nginx = `public/`; `public/hot` tidak ikut ter-deploy.
- Backup harian DB + `storage/app` + kunci DOKU ke luar server, dan tes restore sekali.
- Operasional: training per role, SOP sistem down, kontak darurat gateway/hosting, soft launch, transaksi uang asli kecil per metode + 1 refund.

## Rencana perbaikan

Aturan tiap tahap: tulis test yang gagal dulu → perbaiki → test terkait + full suite (angka pasti dilaporkan) → **tidak
di-commit sebelum diminta**. Migrasi hanya **menambah** (index / kolom / tabel), dijalankan pemilik di server
(`php artisan migrate`), tidak pernah dijalankan ke DB lokal/server oleh agen. Tahap 1–2 tidak butuh keputusan; tahap 3–4
menunggu jawaban di "Keputusan yang dibutuhkan".

### Tahap 1 — Uang & DOKU (tanpa keputusan, ±1 sesi)

**Status 21 Okt 2026: G3, G4, G2, G35, G36 (+G31) dan G37 SUDAH diperbaiki (belum di-commit). G1 dilewati (keputusan
user).** Migrasi baru `2026_10_21_120001_add_lookup_indexes_and_doku_snap_va_numbers` (tabel `doku_snap_va_numbers` + 3
index) — **dijalankan pemilik di server**; kode tetap jalan sebelum migrasi (pencatatan nomor VA gagal diam-diam →
pencarian cara lama). Catatan G35–G37:
- G35: nomor VA dicatat saat dibuat; pencarian lama (7 hari) hanya cadangan untuk VA yang dibuat sebelum rilis.
- G36: langkah 4 disaring di SQL (`payload_log->confirmed_via`) + 1 query notifikasi; sweep sibling memuat `order`
  sekaligus. Penyaring jumlah sesi tetap di PHP (aturan `sessionsOf` menggabung 2 field JSON).
- G37: "hari ini" = rentang hari WIB → index terpakai, dan **sekalian membetulkan** nomor antrian F&B yang salah hitung
  untuk order 00:00–07:00 WIB di server ber-zona UTC (test dijalankan dengan zona UTC).

Catatan G2–G4:
- G2: kode 404 VA tidak ada di daftar kode VA DOKU → dipakai kode kasus SNAP BI (01/12 tidak ada, 19 kedaluwarsa, 14 Paid
  Bill = ERROR + [ALERT]); QRIS hanya 404xx01 & status 07 (sesuai dokumentasi Query QR). Jawaban lain = ERROR + log
  `doku` "tidak dikenali". `close()` tetap menghapus VA walau dijawab "tidak ditemukan"; hapus VA yang DITERIMA DOKU =
  tertutup walau cek status sesudahnya tidak terbaca (jawaban DOKU untuk VA terhapus tidak didokumentasikan — supaya
  ganti metode tidak 503).
- G4: hasil baru `exhausted` (final). Rilis pertama di server: notifikasi lama yang tidak tuntas (≤90 hari) ikut
  dilaporkan sekali (maks 50 per putaran) — wajar kalau muncul beberapa [ALERT] sisa uji coba.
| ID | Perbaikan | File | Test |
|---|---|---|---|
| G3 | Gerbang `payment:reconcile-doku` = "DOKU dipakai" (Checkout **atau** SNAP terkonfigurasi), bukan kunci Checkout saja. Langkah khusus Checkout (sweep sibling Checkout) tetap dicek per langkah. | `ReconcileDokuPayments.php:35` | Konfigurasi SNAP saja → notifikasi RETRY tetap diproses ulang. |
| G4 | Saat batas 30x / 48 jam tercapai atau hasil UNMATCHED untuk notifikasi "SUCCESS": `[ALERT]` di log `doku` + log aktivitas KRITIS, sekali per notifikasi. | `DokuNotificationProcessor.php`, `ReconcileDokuPayments.php:42-48` | Notifikasi lunas yang tak pernah tuntas → 1 alert, tidak berulang. |
| G2 | `404xx` dari SNAP tidak lagi otomatis NOT_FOUND: hanya kode "VA/QR tidak ditemukan" yang tercantum di dokumentasi DOKU SNAP (dicek dulu daftarnya); sisanya ERROR (dicoba lagi, sesi tidak dianggap tertutup). `close()` tetap memanggil delete-va. | `DokuSnapService.php:~370`, `DokuSessionGuard.php:108,243,284` | Kode 404 lain → ERROR, sesi tidak dipakai ulang; kode "not found" resmi → NOT_FOUND. |
| G35 | Pasangan nomor VA → invoice disimpan saat VA dibuat (tabel kecil `doku_snap_sessions`: invoice, va_number ber-index, payment_id — **migrasi baru**), `invoiceForVa` jadi 1 query. Pencarian lama jadi cadangan terakhir dengan kolom terbatas. | `DokuSnapService::createVa`, `DokuSnapNotificationController.php:126` | Notifikasi VA tanpa `trxId` tetap ketemu tagihannya. |
| G36 + G31 | Saring `confirmed_via` & jumlah sesi di SQL / ambil kolom yang perlu saja; cek notifikasi yang hilang pakai 1 query `whereIn` (bukan 1 query per tagihan); index `payments(payment_gateway, status, updated_at)` — **migrasi baru**. | `ReconcileDokuPayments.php:91-101`, `DokuSessionGuard.php:264` | Hasil sama dengan test rekonsiliasi yang sudah ada. |
| G1 | *Opsional — hanya kalau keputusan 16 Okt diubah:* driver `mock` hanya untuk `local,testing` (allowlist, 3 baris). | `PaymentManager.php:35` | Env `staging` → webhook mock ditolak. |

### Tahap 2 — Kasir & akun (tanpa keputusan, ±1 sesi)

**Status 21 Okt 2026: G8, G9, G12 SUDAH diperbaiki (belum di-commit), G37 selesai di tahap 1.** Full suite 1000 lolos.
- G8: helper `releaseSlotCacheLocks()` (3 salinan loop digabung) + dipanggil setelah pembatalan QR kasir tersimpan.
- G9: `normalizePhoneNumber` = `PhoneNumber::normalize`; bukan nomor HP → 422, tidak pernah `where phone = ''`.
  Pencarian = 1 query lewat index `users(phone, deleted_at)` (dibuktikan EXPLAIN di test); input HP kasir `wire:model`
  biasa (bukan live) → tidak query per ketikan. **Belum dicek: akun lama ber-HP tanpa 0 / dobel di DB production.**
- G12 (koreksi): ganti sendiri di profil & reset email **ternyata sudah** mencabut token; yang bolong hanya admin
  mengganti sandi. Sekarang dicabut di model `User` (semua jalur). Sesi web perangkat lain sudah ditangani
  `AuthenticateSession`. Efek samping kecil: kalau `BCRYPT_ROUNDS` diubah, login web pertama me-rehash sandi →
  token aplikasi user itu ikut dicabut sekali.
| ID | Perbaikan | File | Test |
|---|---|---|---|
| G8 | Pelepasan kunci cache `padel_lock:*` dipindah ke satu helper (sekarang hanya ada di pelepas slot), dipakai juga saat QR kasir dibatalkan. | `ManagesScheduleAndSlots.php:402-411`, `PosPendingOrderCanceller.php:50` | Batalkan QRIS → slot langsung bisa dibayar EDC / dibooking online. |
| G9 | `normalizePhoneNumber` memakai `PhoneNumber::normalize` (`812…` → `0812…`), pencarian akun walk-in cocok dengan akun yang sudah ada. **Sebelum rilis:** pemilik cek DB apakah sudah ada akun ber-HP tanpa 0 / dobel (query disiapkan, read-only). | `ManagesCheckoutAndPayments.php:985-994` | HP `812…` → menemukan member `0812…`, tidak membuat akun baru. |
| G12 | Password diganti (oleh admin) → semua token API user itu dicabut + sesi web di tabel `sessions` dihapus (kecuali sesi yang sedang dipakai kalau user mengganti sendiri). | `User.php:64-78` | Ganti sandi → token lama 401. |
| G37 | `whereDate('created_at', hari ini)` → rentang `created_at` 00:00–23:59 (zona aplikasi) + index `orders(created_at)` — **migrasi baru** (digabung dengan G31/G35 jadi satu migrasi). | `BookOfflineCourt.php:2445,2456`, `FnbPosService.php:90-93` | Statistik walk-in & nomor antrian F&B tetap sama. |

### Tahap 3 — Butuh keputusan (dikerjakan setelah dijawab)
| ID | Yang dikerjakan setelah ada jawaban |
|---|---|
| G6 | Halaman Syarat & Ketentuan / Privasi / Refund (isi dari PM/legal) + samakan teks checkout & membership dengan aturan sistem (atau ubah aturan sistemnya). |
| G11 | Refund butuh 2 orang: pengaju ≠ pemroses (dicek di `RefundQueueService`), dicatat di log aktivitas. |
| G17 | Batas slot per request, hold aktif per akun, maksimal hari ke depan (usulan lama 4 / 4 / 30 hari) di hold online + validasi API. |
| G20 | Biaya admin QRIS: tetap / dihapus / ditanggung venue (`TaxAndFeeService`). |
| G13 | Lockout login: ganti blokir per akun lintas IP jadi jeda bertahap per akun+IP (atau CAPTCHA). |
| G10 | UNIQUE email/HP efektif (kolom bantu "aktif" + unique). **Didahului** cek data dobel di DB production (query read-only, pemilik yang jalankan). |
| G15 | MFA panel admin — butuh upgrade Filament ≥5.8.3 (**upgrade dependency, minta izin dulu**). |
| G14 | Panggilan HTTP SNAP dikeluarkan dari transaksi yang mengunci baris (pola 2 fase) — perubahan desain, dibahas dulu. |

### Tahap 4 — Server (pemilik)
G5 scheduler (cron `schedule:run` atau supervisor `schedule:work`), G7 backup harian + tes restore + error tracking, G16
`APP_TIMEZONE=Asia/Jakarta`, #9 `trustProxies`, plus daftar "Yang harus dicek / dilakukan pemilik di server" di atas.
G5 sebaiknya **sebelum** tahap 1 dirilis, karena G3/G4/G36 hanya berguna kalau scheduler jalan.

### Tahap 5 — Rendah (sesuai sempat)
G21 CSP, G22 webhook saat maintenance, G23 UNIQUE slot + test paralel MySQL, G24 respons QR bekas, G26 `.gitignore *.pem`,
G27–G30, G32–G34.

## Keputusan yang dibutuhkan
1. **G17** — batas hold: berapa slot per request, hold aktif per akun, maksimal hari ke depan?
2. **G6** — isi Syarat & Ketentuan, Privasi, Refund (reschedule s.d. 2 jam atau H-3? refund boleh atau tidak?).
3. **G20** — biaya admin untuk QRIS: tetap, hapus, atau ditanggung venue?
4. **G11** — refund wajib 2 orang berbeda? (butuh minimal 2 akun berizin di lapangan)
5. **G15** — MFA wajib untuk super_admin & keuangan mulai kapan?
6. **G13** — lockout akun: tetap blokir, atau ganti jeda/CAPTCHA?
