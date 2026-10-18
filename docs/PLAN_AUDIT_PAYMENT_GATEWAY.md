# Plan: Audit Payment Gateway & Scheduler (16 Okt 2026)

Hasil audit QA/pentest jalur pembayaran (Midtrans, DOKU, checkout, webhook, scheduler) — daftar kerja bertahap. Semua
temuan sudah dicocokkan ke baris kode saat audit; nomor baris bisa bergeser setelah ada perubahan.

## Status (18 Okt 2026)
**Sudah dikerjakan** (full suite 901 passed, belum di-commit):
- #4 role staf — check-in API & web pakai izin `checkin_padel_ticket`; retry-payment hanya pemilik booking.
  8 pemakaian `isStaff()`/`isAdmin()` lainnya sudah dipasangkan dengan `can(...)` (aman, tidak diubah).
  Test: `tests/Feature/Security/StaffPermissionGuardTest.php`.
- #3 voucher saldo — hitungan saldo/kuota terpesan pakai locking read saat checkout (`VoucherService::freshRead`).
  Race-nya tidak bisa diuji otomatis (SQLite); uji 2 request paralel di MySQL lokal masih menunggu izin.
- Refund a — popup Snap yang belum dibuka tetap ACTIVE (diawasi), tidak dicatat CLOSED.
- Refund b — `cancelOrderSessions` menutup semua sesi Midtrans tagihan (`SnapSessionGuard::closeOpenSessions`).
- Refund c — **tidak diubah**: kasir sudah menolak (409) saat customer sedang bayar online; sisa celah (popup belum
  dibuka) tertutup oleh poin a + `closeIfStale`.
- Refund e — pembersih slot mengunci order dulu & menghanguskan per order; webhook membaca status booking dengan lock;
  sebagian booking sudah mati saat uang masuk → `[ALERT]` (tidak diam).
- #8 — Midtrans: nominal ≠ tagihan sesi → tidak dilunasi + alert KRITIS sekali per sesi (webhook, rekonsiliasi, sweep);
  sesi yang tidak dimiliki tagihan mana pun tetap dicatat + alert. Membership & slip F&B hanya untuk order PAID.
- Scheduler — `reconcile-midtrans` maks. 100 order/jalan (terlama dulu, DOKU juga); tugas yang memanggil gateway
  `runInBackground`; `padel:release-expired-slots` `withoutOverlapping(5)`.
- Rendah — log webhook tanpa payload/`signature_key`; throttle 300/menit di 3 rute webhook Midtrans; log Status API
  tanpa body; kunci idempotensi checkout diikat ke user + disimpan setelah commit; retry-payment menolak hold yang
  sudah habis; webhook lunas tanpa order → `[ALERT]`. Test: `tests/Feature/Payment/PaymentAuditHardeningTest.php`.

**Belum** — ditunda user (18 Okt 2026), dikerjakan nanti:
| Item | Usulan / status diskusi | Yang ditunggu dari user |
|---|---|---|
| Scheduler di server | Cron atau supervisor `schedule:work` (user `www`), lihat bagian 1 | Pilihan + pemasangan di sandbox |
| #5 Batas hold slot | 4 slot per request, 4 slot ditahan/belum dibayar per akun, maks. 30 hari ke depan; diatur di Pengaturan Waktu Booking; walk-in kasir tidak kena (saat ini tidak ada batas apa pun, termasuk hari ke depan) | Setuju angkanya? |
| #6 Email invoice | Rekomendasi **antrian** (`queue:work` di supervisor: retry saat SMTP gangguan, tahan restart; wajib `queue:restart` tiap deploy). Alternatif `afterResponse` (tanpa worker, email hilang kalau SMTP gagal) | Antrian atau afterResponse |
| #7 `/schedule` read-only + throttle | Hold lewat `expires_at` dianggap kosong, tanpa tulis DB / panggil gateway; throttle 60/menit/IP. **Wajib setelah scheduler jalan** — sekarang `/schedule` satu-satunya pembersih slot di server | Scheduler terpasang dulu |
| #9 `trustProxies` | User pakai nginx. Kalau nginx → PHP-FPM langsung (`fastcgi_pass`), `'*'` membuat `X-Forwarded-For` palsu dipercaya → semua rate limit berbasis IP (login, payment, webhook) bisa dilewati **sekarang**. Usulan: `['127.0.0.1', '::1']`. Cloudflare di depan = butuh daftar IP Cloudflare | Hasil `grep -rn "fastcgi_pass\|proxy_pass"` di config situs nginx + pakai Cloudflare (proxied) atau tidak |
| ~~Pagar mock/simulate~~ | **Di-skip** — user menjamin env server production. Catatan: laptop `APP_ENV=local` terbuka lewat tunnel `lhr.life` selama tunnel nyala (data dummy) | — |

Deadlock urutan lock jalur kasir/reschedule & pembersihan tabel `cache` masih tercatat di temuan rendah.

---

## 1. Scheduler di server (keputusan: cron ATAU supervisor — belum dipilih)

**Kondisi sekarang:** di server sandbox (`/var/www/club61-sandbox`) hanya ada `club61-reverb-sandbox` di supervisor,
tidak ada cron. `php artisan schedule:list` membaca 10 tugas + `inspire`, tapi **tidak ada yang mengeksekusi**.

**Tanpa scheduler, yang tidak pernah jalan:**
- `payment:reconcile-midtrans` / `payment:reconcile-doku` — cadangan webhook hilang, ulang notifikasi DOKU yang gagal
  diproses (DOKU sendiri hanya kirim ulang 3x), dan `sweepPaidSiblings` (deteksi bayar dobel lewat sesi lain —
  satu-satunya pemanggilnya dua command ini).
- `pos:sweep-auto-payments` — Bayar Otomatis kasir yang ditinggal tetap UNPAID selamanya.
- `membership:sync-expired` — membership lewat tanggal tetap ACTIVE di database + pesanan membership online yang
  ditinggal tetap "Menunggu Pembayaran".
- `ledger:verify`, `audit:prune`, `model:prune` (DokuNotification), `signature:clean-files`.
- `padel:release-expired-slots` — ini masih "tertolong" pembersih yang numpang di halaman jadwal/admin, tapi bebannya
  pindah ke request pengunjung dan saat sepi (malam) tidak ada yang jalan.

**Aturan:** pilih SATU (cron atau supervisor, jangan dua-duanya → tugas jalan dobel). Jangan jalankan sebagai root
(file log/cache jadi milik root → web gagal menulis). User = `www` (sama dengan Reverb; cek dengan `id www`).
Kalau server lebih dari satu, scheduler hanya di satu server.

### Opsi A — Cron (paling ringan, tidak ada proses yang nyala terus)
```bash
crontab -e -u www
# tambah satu baris:
* * * * * cd /var/www/club61-sandbox && php artisan schedule:run >> /dev/null 2>&1
```

### Opsi B — Supervisor (program SENDIRI, terpisah dari Reverb)
`/etc/supervisor/conf.d/club61-scheduler-sandbox.conf`:
```ini
[program:club61-scheduler-sandbox]
command=php /var/www/club61-sandbox/artisan schedule:work
directory=/var/www/club61-sandbox
user=www
autostart=true
autorestart=true
stopwaitsecs=60
stdout_logfile=/var/www/club61-sandbox/storage/logs/scheduler.log
redirect_stderr=true
```
```bash
supervisorctl reread && supervisorctl update && supervisorctl status
```
Jangan digabung ke `command` Reverb: restart Reverb saat deploy ikut memotong tugas yang sedang jalan, dan supervisor
tidak bisa memantau dua proses dalam satu program. `schedule:work` tidak perlu di-restart setelah deploy (tiap menit
menjalankan `schedule:run` baru dengan kode terbaru).

### Sebelum & sesudah pasang
- Cek dulu tidak ada cron lama: `crontab -l -u www` dan `crontab -l` (root).
- Verifikasi ±6 menit setelah pasang: `tail -n 40 storage/logs/scheduler.log` (opsi B) dan
  `grep "\[ALERT\]" storage/logs/laravel.log | tail -n 20`.
- **Jalan pertama memproses tumpukan lama:** slot nyangkut dilepas (kuota member/voucher sponsor dikembalikan),
  tagihan PENDING dicek ke gateway (bisa agak lama), membership lewat tanggal jadi expired, `ledger:verify` bisa
  mengeluarkan log KRITIS, `signature:clean-files` (03:00) **menghapus permanen** file signage yang 7 hari tidak
  dipakai & tidak tayang.
- Pasang di **sandbox dulu**, amati 1–2 hari, baru production.
- `queue:work` **belum perlu** (tidak ada job ShouldQueue di kode). Baru perlu kalau email invoice dipindah ke antrian
  (poin 4 #6); kalau dipasang: `php artisan queue:restart` setiap deploy + `--max-time=3600`.

### Beban server & gateway
Beban scheduler mengikuti jumlah **tagihan PENDING**, bukan jumlah pengunjung: tagihan lunas/batal/expired tidak
ditanya lagi, tagihan baru ditunggu 2 menit (beri webhook kesempatan), jarak tiap 5 menit. Status API memang dibuat
untuk cadangan webhook. Yang mengikuti jumlah pengunjung justru `/schedule` publik & email di dalam request webhook
(poin 4 #6–#7).

---

## 2. Temuan & plan perbaikan

### 🔴 Kritis — mock webhook & simulator (status: aman di production, opsional diperkuat)
- `POST /api/v1/padel/webhook/mock` — [MockSimulatorDriver.php](../app/Services/Payment/Drivers/MockSimulatorDriver.php)
  selalu "valid, PAID"; [PaymentManager.php](../app/Services/Payment/PaymentManager.php) hanya menolak kalau env
  persis `production`.
- `POST /api/v1/payments/simulate` — [routes/api/pos.php](../routes/api/pos.php), tanpa login, aktif di `local`/`testing`.
- [MidtransService.php](../app/Services/Payment/MidtransService.php) `verifySignature`: server key kosong + env bukan
  production → tanda tangan dianggap valid (notifikasi expire/cancel palsu bisa membatalkan order).
- **Status:** production sudah `APP_ENV=production` (dikonfirmasi). Masih terbuka di server sandbox kalau env-nya bukan
  `production`, dan di laptop saat tunnel localhost.run nyala.
- **Plan (opsional, kecil):** mock & simulate hanya `local`/`testing` **dan** request dari `127.0.0.1`; key Midtrans
  kosong = tolak di semua env selain `testing`.

### 🟠 #3 — Voucher saldo bisa terpakai dua kali (checkout paralel)
- [VoucherService.php](../app/Services/Finance/VoucherService.php) `resolve()` (kuota promo) & `availableBalance()`
  (saldo terpesan) pakai SELECT biasa → di REPEATABLE READ (default MySQL/MariaDB, tidak diubah di config) request
  kedua tidak melihat order request pertama.
- **Cakupan:** voucher **promo** belum bisa dibuat (tidak ada menu & seeder; Daftar Voucher hanya lihat/nonaktifkan)
  → race kuota promo belum mungkin terjadi. Voucher **saldo `KR-…`** dibuat otomatis setiap admin **menolak refund**
  (`RefundQueueService::reject`, opsi voucher default nyala) → jalur ini sudah hidup.
- **Plan (opsi C, rekomendasi):** dua hitungan "sudah terpesan" diubah jadi baca yang mengunci (`lockForUpdate`).
  `orders.voucher_code` sudah ber-index. Ditolak: ubah isolation level global (efek samping seluruh app), kolom
  "saldo terpesan" (semua jalur batal/expired/lunas harus disentuh).
- **Uji:** tidak bisa lewat test otomatis (SQLite tanpa isolation level) → uji dua request paralel di MySQL lokal
  (minta izin dulu).
- Cek isolation level server: `SELECT @@transaction_isolation;`

### 🟠 #4 — Role non-frontline dianggap "staf" (bom waktu)
- [User.php](../app/Models/User.php) `isAdmin()` = semua role yang **bukan** customer/cashier/kitchen/receptionist →
  trainer, stylist, dan role baru bikinan admin otomatis staf/admin. Pemakaian: 6 `isStaff()` + 4 `isAdmin()` di 6 file.
- Check-in API ([PadelBookingController.php](../app/Http/Controllers/Api/V1/Padel/PadelBookingController.php)
  `checkIn`) hanya cek `isStaff()` → trainer bisa check-in booking siapa pun + dapat nama pemain; resepsionis
  (punya izin) malah ditolak.
- Retry-payment ([ManagesCheckoutAndPayments.php](../app/Services/Padel/Concerns/ManagesCheckoutAndPayments.php)
  `retryPayment`) melewati cek pemilik untuk "staf" → VA/QRIS customer lain ditutup, sesi baru atas nama staf, link
  bayar & total bocor.
- **Plan:** (1) check-in pakai `can('checkin_padel_ticket')` seperti rute web; (2) hapus jalan pintas staf di
  retry-payment (pastikan dulu tidak ada layar staf yang memanggilnya); (3) cek 8 pemakaian lain — izin aksi →
  `can('permission')`, routing halaman boleh tetap.

### 🟡 Sedang
| # | Masalah | Plan | Keputusan yang dibutuhkan |
|---|---|---|---|
| 5 | `hold-slot` tanpa batas jumlah slot (`slots` tanpa `max`) | Batas slot per request, slot ditahan per akun, maksimal hari ke depan — bisa diatur di Pengaturan Waktu Booking | Angka batasnya |
| 6 | Email invoice (PDF + SMTP) dikirim di dalam request webhook; timeout SMTP `null` | Antrian (`queue:work`) atau `afterResponse`; timeout SMTP 10 detik | Antrian vs afterResponse |
| 7 | `GET /api/v1/padel/schedule` publik tanpa throttle, memicu pembersih + Status API gateway | Jadwal cukup lihat timestamp (tanpa tulis DB / panggil gateway), pembersihan diserahkan ke scheduler; throttle 60/menit/IP | — (butuh scheduler jalan dulu) |
| 8 | Midtrans tetap melunasi walau nominal beda (cuma log); membership & F&B aktif walau PARTIALLY_PAID | Samakan dengan DOKU: nominal beda → tidak dilunasi + alert; fulfillment hanya saat order PAID | — |
| 9 | `trustProxies(at: '*')` → `X-Forwarded-For` bisa dipalsukan kalau server diakses langsung (rate limit IP bisa dilewati) | Isi dengan IP proxy asli | Hosting di belakang nginx / Cloudflare / LB? |

### Plan minim refund
| Sumber refund | Status | Plan |
|---|---|---|
| a. Bayar dobel lewat sesi lama (ganti metode, popup lama di tab lain) | Popup yang belum pernah dibuka (Status API 404) dianggap "tutup" | Sesi 404 tetap dianggap bisa dibayar sampai waktunya habis; notifikasi "pending" dari sesi lama langsung ditutup |
| b. Sesi bayar ulang tidak ikut dibatalkan saat booking dilepas/expired | `OnlineGatewayRouter::cancelOrderSessions` hanya membatalkan nomor order polos untuk Midtrans | Batalkan **semua** sesi Midtrans tagihan itu (seperti DOKU) |
| c. Kasir terima EDC/tunai saat customer sedang bayar online | Sesi online dibatalkan **setelah** kasir melunasi | Balik urutan: tutup & pastikan sesi online mati dulu, baru kasir boleh terima uang |
| e. Race pembersih slot vs webhook | `isPaymentForInactiveTarget` baca status booking tanpa lock → uang masuk, booking EXPIRED, **tanpa refund & tanpa alert** | Perbaiki urutan lock; kalau tetap terjadi → refund + alert (tidak boleh diam) |
| ~~d. Pembayaran telat setelah expired~~ | **Dicoret** — sudah benar: sesi gateway berakhir tepat di batas bayar (bayar ulang dibulatkan ke bawah, selalu sebelum batas), slot baru dilepas ≥3 menit setelahnya dan hanya kalau gateway memastikan semua sesi mati; ragu → ditahan s/d 15 menit. Mau customer cuma 18 menit → ubah Batas Bayar di pengaturan | — |

Sisa celah kecil (dicatat saja): gateway tidak bisa dihubungi selama 15 menit penuh → slot tetap dilepas tanpa
konfirmasi.

### 🟢 Rendah
- Webhook Midtrans tanpa throttle; tanda tangan salah → seluruh payload dicatat ke log termasuk `signature_key`
  ([PaymentWebhookController.php](../app/Http/Controllers/Api/V1/Payment/PaymentWebhookController.php)) → log cukup
  `order_id`, `status_code`, `ip` + throttle longgar.
- Scheduler: `payment:reconcile-midtrans` tanpa batas jumlah per jalan (DOKU 100) → batasi 100; tugas yang memanggil
  gateway pakai `runInBackground`; `padel:release-expired-slots` tambah `withoutOverlapping`.
- Kunci idempotensi checkout tidak diikat ke user → tambah `user_id` (pengamanan tambahan).
- Retry-payment untuk hold LOCKED tidak mengecek hold sudah lewat → owner bisa memperpanjang hold.
- Webhook PAID valid tanpa order yang cocok dibalas 200 tanpa alert → tambah `Log::critical`.
- Deadlock urutan lock (webhook: Order→Payment→Booking; kasir/cancel/reschedule: Booking→Order) → webhook 503 lalu
  dikirim ulang, kasir klik ulang. Uang aman; rapikan saat menyentuh jalur kasir.
- Tabel `cache` (store database) tidak pernah dibersihkan dari key kedaluwarsa.

### ✅ Sudah aman (diverifikasi)
Tidak ada IDOR antar customer (tiket, my-bookings, retry, membership pay/cancel, voucher, sponsor, gym); harga, pajak,
diskon dihitung server; double-click dikunci baris DB; `markOrderAsPaid` idempoten (bayar dobel → DUPLICATE + refund);
booking EXPIRED/CANCELLED tidak pernah hidup lagi (tidak ada double booking); Midtrans "lunas" dikonfirmasi Status
API; DOKU cek tanda tangan + nominal + Status API; `signature_key` & data customer DOKU tidak disimpan; `payload_log`
tersembunyi dari JSON; index webhook lengkap.

---

## 3. Keputusan yang masih ditunggu
1. Scheduler: cron (opsi A) atau supervisor (opsi B).
2. Env server sandbox apa? Pagar kedua mock/simulate jadi dipasang atau tidak.
3. Angka batas hold slot (#5).
4. Email invoice: antrian atau afterResponse (#6).
5. Hosting di belakang nginx / Cloudflare / load balancer (#9).

## 4. Urutan kerja yang diusulkan
1. Pasang scheduler (sandbox → production).
2. #4 role staf.
3. #3 voucher saldo.
4. Plan refund b → c → e → a.
5. Temuan sedang #5–#9.
6. Temuan rendah.
