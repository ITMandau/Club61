# Plan — Update Realtime (Reverb + Cadangan Polling)

> **Status 2026-10-14: selesai.** 14 test baru + full suite 827 lulus; smoke test nyata (Reverb lokal + client Node) lulus:
> event diterima, event palsu dari browser ditolak (4301), koneksi tersambung lagi sendiri setelah Reverb mati-hidup.

Tujuan: layar jadwal / TV / POS / Kelola Pemesanan / Booking System / halaman Book Court customer / status layar Signage
ikut berubah sendiri saat ada booking, bayar, check-in, selesai, batal, refund — tanpa refresh manual dan tanpa polling
cepat yang membebani server. Kalau Reverb mati, semua layar otomatis kembali ke polling (perilaku lama), tidak ada yang
rusak.

## Prinsip

1. **Sinyal saja, bukan data.** Event hanya berisi `dates` (tanggal booking yang berubah) — tanpa nama, nomor HP, kode
   booking. Penerima mengambil ulang datanya sendiri lewat endpoint/komponen yang sudah ada (hak akses tetap sama).
   Karena itu channel boleh publik (`booking-board`, `signature`) tanpa membocorkan data.
2. **Dikumpulkan lalu dikirim sekali per request / job / command**, setelah respons terkirim ke user
   (`app()->terminating`, `Queue::after`). Checkout walk-in 3 jam = 1 sinyal, bukan 3.
3. **Reverb mati tidak boleh menggagalkan transaksi.** Broadcast dibungkus try/catch, timeout pendek (1–2 dtk),
   peringatan di log dibatasi 1x per 5 menit.
4. **Tidak ada paket JS baru.** Panel admin memakai Echo + Pusher yang sudah dibundel Filament
   (`public/js/filament/filament/echo.js`). TV & halaman customer memakai client ES5 kecil (protokol Pusher, ±100 baris)
   karena pemutar TV memang ES5.
5. **Tidak menyentuh `.env`.** Hanya `.env.example` + dokumentasi deploy.

## Paket

- `laravel/reverb` **^1.12** (v1.12.0, rilis 2026-09-22; dukung Laravel 12). Ikut menarik `pusher/pusher-php-server`.
- Redis **tidak** dipakai (Reverb 1 server per instance).

## Sumber sinyal (server)

| Sumber | Cara |
|---|---|
| `PadelBooking` dibuat / diubah / dihapus / dipulihkan (Eloquent) | Observer → tanggal booking (+ tanggal lama kalau reschedule) |
| `PadelCourt` diubah / dihapus | Observer → semua tanggal (`*`) |
| `ManagesCheckInAndTurnstile` 45, 116, 271, 313, 330 | Sinyal manual (update query builder tidak memicu observer) |
| `PadelFulfillmentHandler` 62 (jadi PAID) | Sinyal manual |
| `ManagesScheduleAndSlots` 595 (hold/tagihan hangus) | Sinyal manual (623 = order ikut batal, booking-nya sudah tersinyal) |
| `ManagesCheckoutAndPayments` 1293 (auto check-in walk-in) | Sinyal manual (1252 cuma memperpanjang `expires_at`, tidak tampil di papan → dilewati) |
| `BookOfflineCourt` 1804 (auto check-in setelah lunas) | Sinyal manual |
| `RefundQueueService` 126 | Sinyal manual |
| `SignatureService::putOnAir` (Tayangkan / Kembalikan) | Event `signature` kind `published` |
| `SignatureController::live` saat `last_seen_at` disimpan | Event `signature` kind `seen` (+ id layar & waktu) |

Saat sinyal booking dikirim, cache `kelola_pemesanan_tab_counts` ikut dihapus (sekarang bisa basi 60 dtk).

## Penerima & cadangan

| Layar | Reverb hidup | Reverb mati / tidak dipasang |
|---|---|---|
| TV Signage | event → ambil data live saat itu juga; polling rutin 60 dtk (jam, hitung mundur, heartbeat) | polling 12 dtk (seperti sekarang) |
| Booking System (admin) | event; polling jaga-jaga 60 dtk | polling 10 dtk (seperti sekarang `wire:poll.10s`) |
| Kelola Pemesanan | event | polling 30 dtk |
| POS Walk-in (BookOfflineCourt) | event | polling 30 dtk |
| Book Court customer | event (hanya tanggal yang sedang dibuka), ambil ulang diam-diam tanpa spinner | polling 60 dtk, hanya saat tab terlihat |
| Status layar di admin Signage | event `seen` + timer di browser (Offline setelah 2 menit tanpa sinyal) | panggil method renderless tiap 60 dtk |

Aturan bersama penerima admin: refresh **ditunda** selama popup/aksi terbuka atau request Livewire sedang berjalan
(form kasir tidak tertimpa); saat koneksi tersambung lagi → refresh sekali (mengejar event yang terlewat); polling
hanya saat tab terlihat.

## File

Baru:
- `config/broadcasting.php`, `config/reverb.php`
- `routes/channels.php` (channel privat user — dipakai notifikasi Filament begitu Echo aktif)
- `app/Support/Realtime.php` — `enabled()` + konfigurasi client (host/port/scheme publik, default dari `APP_URL`)
- `app/Services/Realtime/RealtimeSignal.php` — kumpulkan & kirim sinyal
- `app/Events/BookingBoardChanged.php`, `app/Events/SignatureChanged.php` (`ShouldBroadcastNow`)
- `app/Observers/PadelBookingObserver.php`, `app/Observers/PadelCourtObserver.php`
- `resources/views/filament/realtime-script.blade.php` — jembatan Echo → event browser + Alpine `club61LiveRefresh`
- `public/js/club61-live.js` — client ES5 (TV + customer)
- `tests/Feature/Realtime/RealtimeSignalTest.php`, `tests/Feature/Realtime/RealtimeReceiversTest.php`
- `docs/DEPLOY_REVERB.md` — production & sandbox (port 8080/8081, key beda, supervisor, nginx, allowed origins)

Diubah:
- `composer.json` / `composer.lock`, `bootstrap/app.php` (`withBroadcasting`), `AppServiceProvider` (observer,
  flush, konfigurasi Echo Filament), `AdminPanelProvider` (render hook script), `.env.example`
- 8 titik query builder di atas
- `SignatureService`, `SignatureController`, `signature/player.blade.php`, `Signature.php` + blade-nya (status layar)
- `booking-system.blade.php`, `kelola-pemesanan.blade.php`, `book-offline-court.blade.php`,
  `customer/booking.blade.php`

## Verifikasi

1. Test baru: sinyal dari tiap sumber, 1 event per request, Reverb mati tidak melempar error, realtime nonaktif tidak
   mencoba broadcast, channel user hanya untuk pemiliknya, halaman memuat konfigurasi client hanya saat aktif.
2. Smoke test nyata: `reverb:start` lokal + client `club61-live.js` dijalankan di Node → booking dibuat → event
   diterima.
3. Full suite + `npm run build`.
