# Deploy Update Realtime (Reverb) — Production & Sandbox

Reverb membuat papan jadwal (Booking System), TV Digital Signage, POS Walk-in, Kelola Pemesanan, halaman Book Court
customer, dan status layar di admin Signage ikut berubah sendiri saat ada booking / bayar / check-in / batal / refund.

**Reverb opsional.** Kalau belum dipasang atau sedang mati, semua layar otomatis kembali ke polling (TV 12 dtk, Booking
System 10 dtk, Kelola Pemesanan & POS 30 dtk, Book Court 60 dtk saat tab terlihat). Transaksi tidak pernah gagal karena
Reverb — sinyal dikirim setelah respons, error hanya dicatat di log (paling sering 1x per 5 menit).

## Cara kerja singkat

```
Browser / TV ──wss://domain/app/KEY──▶ Nginx :443 ──▶ Reverb 127.0.0.1:8080 (prod) / :8081 (sandbox)
Laravel (PHP-FPM) ──http://127.0.0.1:8080──▶ Reverb   (kirim sinyal, tidak lewat internet)
```

- Isi sinyal hanya tanggal yang berubah (`{"dates":["2026-10-14"]}`) — tanpa nama, nomor HP, atau kode booking.
  Layar mengambil ulang datanya sendiri lewat halaman / API yang sudah ada.
- Browser tidak bisa menyiarkan event sendiri (`accept_client_events_from = none`).
- Hanya halaman dari domain `APP_URL` yang boleh tersambung (`REVERB_ALLOWED_ORIGINS`).
- Tanpa Redis (satu server Reverb per instance).

## Production vs sandbox di satu VPS

Dua instance **tidak bentrok** selama nilai berikut berbeda:

| Setting | Production | Sandbox |
|---|---|---|
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | acak sendiri | acak sendiri (jangan disalin dari production) |
| `REVERB_SERVER_PORT` & `REVERB_PORT` | `8080` | `8081` |
| Program supervisor | `club61-reverb-production` | `club61-reverb-sandbox` |
| Nginx `location /app/` | di server block domain production → `:8080` | di server block domain sandbox → `:8081` |
| `APP_URL` (dasar alamat browser & allowed origins) | domain production | domain sandbox |

`php artisan reverb:restart` memakai cache aplikasi. Selama production & sandbox memakai database (atau prefix cache)
yang berbeda, restart di satu instance tidak mematikan yang lain.

## Langkah pasang (ulangi untuk sandbox dengan port 8081)

### 1. Kode

```bash
git pull
composer install --no-dev --optimize-autoloader
```

### 2. `.env` (diisi di server, jangan di-commit)

Buat key: jalankan `php artisan reverb:install` di folder instance itu (tiap instance punya `.env` sendiri, jadi key
production & sandbox otomatis beda). Atau buat manual:

```bash
php -r 'echo "id=", random_int(100000, 999999), "\nkey=", bin2hex(random_bytes(16)), "\nsecret=", bin2hex(random_bytes(32)), "\n";'
```

Lalu pastikan isinya seperti ini. `reverb:install` menulis `REVERB_HOST="localhost"` → ganti ke `127.0.0.1` (server
Reverb hanya mendengar di 127.0.0.1; "localhost" bisa diarahkan ke IPv6 `::1`). Sandbox: port 8081.

```dotenv
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=<id>
REVERB_APP_KEY=<key>
REVERB_APP_SECRET=<secret>

REVERB_HOST=127.0.0.1
REVERB_PORT=8080          # sandbox: 8081
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080   # sandbox: 8081

# Kosongkan: browser memakai domain APP_URL port 443 lewat Nginx.
REVERB_CLIENT_HOST=
REVERB_CLIENT_PORT=
REVERB_CLIENT_SCHEME=
REVERB_ALLOWED_ORIGINS=
```

```bash
php artisan optimize
```

### 3. Supervisor — `/etc/supervisor/conf.d/club61-reverb-production.conf`

```ini
[program:club61-reverb-production]
command=php /var/www/club61-production/artisan reverb:start
directory=/var/www/club61-production
user=www-data
autostart=true
autorestart=true
stopwaitsecs=10
redirect_stderr=true
stdout_logfile=/var/www/club61-production/storage/logs/reverb.log
minfds=10000
```

Sandbox: salin file ini, ganti nama program jadi `club61-reverb-sandbox` dan semua path ke folder sandbox.
Path `/var/www/...` dan user `www-data` di atas contoh — sesuaikan dengan folder & user PHP-FPM di VPS.

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status club61-reverb-production
```

### 4. Nginx — di dalam `server { ... }` domain production (HTTPS)

```nginx
# WebSocket Reverb. Hanya /app/ yang dibuka ke internet; API kirim sinyal (/apps/) cukup dari 127.0.0.1.
location /app/ {
    proxy_pass http://127.0.0.1:8080;      # sandbox: 8081
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 120s;
}
```

```bash
sudo nginx -t && sudo systemctl reload nginx
```

### 5. Cek

1. `sudo supervisorctl status` → `RUNNING`.
2. Buka admin → Booking System, DevTools → Network → WS: ada koneksi `wss://domain/app/...` berstatus 101.
3. Buat booking walk-in di tablet lain → papan & TV berubah dalam ±1–2 detik tanpa refresh.
4. Matikan sementara: `sudo supervisorctl stop club61-reverb-production` → layar tetap berubah (lebih lambat, polling).
   Nyalakan lagi: `start` → layar tersambung lagi sendiri.

## Setiap deploy berikutnya

```bash
git pull && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan optimize
php artisan reverb:restart     # Reverb memuat kode & konfigurasi baru (supervisor menyalakannya lagi)
```

## Mematikan realtime

`BROADCAST_CONNECTION=log` lalu `php artisan optimize`, dan `supervisorctl stop` program Reverb-nya. Semua layar memakai
polling cadangan.

## Lokal (laptop developer)

**Key dibuat dengan `php artisan reverb:install`** (aman: hanya menulis `REVERB_APP_ID/KEY/SECRET` acak + host/port ke
`.env` dan `BROADCAST_CONNECTION=reverb`; config & `routes/channels.php` yang sudah ada tidak ditimpa). Baris
`VITE_REVERB_*` yang ikut ditulis tidak dipakai, boleh dihapus.

**Jangan jalankan `php artisan install:broadcasting`.** Setup-nya sudah ada di kode; installer itu menambah
`resources/js/echo.js` + paket npm `laravel-echo`/`pusher-js` ke bundle customer (Echo tanpa key → error JS yang
mematikan Alpine di halaman customer) dan mendaftarkan `routes/channels.php` dua kali.

Tanpa Nginx, browser langsung ke Reverb:

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=61
REVERB_APP_KEY=localkey
REVERB_APP_SECRET=localsecret
REVERB_CLIENT_HOST=127.0.0.1
REVERB_CLIENT_PORT=8080
REVERB_CLIENT_SCHEME=http
REVERB_ALLOWED_ORIGINS=localhost,127.0.0.1
```

Jalankan `php artisan reverb:start` di terminal terpisah. Halaman yang dibuka lewat ngrok (https) tidak bisa memakai
`ws://127.0.0.1` — di situ layar memakai polling cadangan, dan itu normal.
