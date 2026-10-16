# Product Requirements Document (PRD)
## Modul 26: Signature — Layar TV Konten & Monitoring Lapangan
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-26-SIGNATURE` |
| **Versi** | `v0.1.0-DRAFT` |
| **Status** | **Tahap 1 selesai (12 Okt 2026), memakai usulan default §8 sambil menunggu konfirmasi PM. Tahap 2 selesai (13 Okt 2026): konten YouTube, transisi antar konten, template custom drag & drop.** Dicoret owner (13 Okt 2026): jadwal tayang per konten, konten link halaman, QR booking di kartu lapangan kosong (tetap teks "Booking sekarang di front desk"), dan cache offline lewat service worker (tetap cache browser + tanda "Koneksi terputus"). File upload: yang masih tayang tidak pernah dihapus; yang dibuang dari library dihapus setelah 7 hari tidak tayang (§9). |
| **Sumber Requirement** | Sketsa owner (12 Okt 2026): template (3 layout), slider, running text, konten video / gambar / YouTube / link halaman, sponsor, monitoring lapangan di TV 96 inch. |
| **Dependensi Teknis** | `PRD_MODUL_02_PADEL_BOOKING.md` (jadwal & check-in), `PRD_MODUL_12_SPONSOR_CORPORATE_ACCOUNT.md` (data Sponsor Corporate), `PRD_MODUL_09_DYNAMIC_RBAC_FILAMENT_SHIELD.md` (hak akses), `PRD_MODUL_16_ACTIVITY_AUDIT_LOG.md` (log perubahan). |
| **Prinsip Utama** | **SEMUA ISI ADALAH "JENIS KONTEN"** (ide baru = jenis konten baru, template & layar tidak berubah); **TV HANYA MENAMPILKAN VERSI YANG SUDAH DITAYANGKAN** (edit = draft); **DATA PRIBADI MINIMUM** di layar publik; **TV TETAP JALAN SAAT INTERNET PUTUS**. |

---

## 1. Ringkasan

**Signature** adalah menu admin untuk mengatur tampilan TV 96 inch di venue. TV menayangkan konten bergantian: video, gambar, promo, sponsor, dan **monitoring lapangan** (siapa main, sisa waktu, booking berikutnya, slot kosong). Kalau tidak ada konten lain, TV otomatis menampilkan monitoring lapangan.

Semua tampilan diatur dari admin tanpa mengubah kode: template, warna, konten, running text, sponsor, dan suara. Setiap perubahan bisa dilihat dulu lewat **Preview** sebelum ditayangkan ke TV.

---

## 2. Keputusan Owner (12 Okt 2026)

| # | Topik | Keputusan |
| :--- | :--- | :--- |
| 1 | Layar | **1 TV (Smart TV, 96 inch).** Konten tayang bergantian; kalau kosong → monitoring lapangan. |
| 2 | Data customer di monitoring | Tampilkan **nama** ("Booked: Budi Santoso"). **No. HP & email tidak pernah tampil.** Masih dikonfirmasi ke PM → dibuat pengaturan (§8 no. 1). |
| 3 | Template | 3 preset dari sketsa **+ template custom drag & drop**. |
| 4 | Sponsor | **Dinamis**: sponsor brand (input manual) **dan/atau** Sponsor Corporate yang sudah ada di sistem. |
| 5 | Running text | **Otomatis dari sistem dan/atau ditulis manual.** |
| 6 | Suara | **Diatur per konten** (ada suara / mute, volume). |
| 7 | Preview | **Wajib ada Preview sebelum disimpan / ditayangkan.** |
| 8 | Library drag & drop | **Gridstack.js** (MIT, tanpa dependency) untuk editor template custom. |

---

## 3. Konsep & Istilah

| Istilah | Arti |
| :--- | :--- |
| **Layar** | Satu TV. Punya link rahasia (`/signature/{kode}`) yang dibuka fullscreen di browser TV — tanpa login. Link bisa dibuat ulang kalau bocor. |
| **Template** | Susunan tampilan: pembagian **zona**, warna, font, logo. Preset atau custom. |
| **Zona** | Kotak di dalam template, berisi satu playlist (mis. zona utama = slider, zona kanan = sponsor, zona bawah = running text). |
| **Konten** | Satu item: gambar, video MP4, link YouTube, link halaman, atau **widget sistem** (Monitoring Lapangan, Jadwal Hari Ini, Sponsor, Jam & Tanggal, …). |
| **Playlist** | Urutan konten di satu zona + durasi tiap konten. |
| **Running Text** | Teks berjalan: manual dan/atau otomatis dari sistem. |
| **Draft / Tayang** | Semua perubahan tersimpan sebagai draft. TV hanya menampilkan versi **Tayang**. |

---

## 4. Fitur

### 4.1 Layar (TV)
- Nama layar, link rahasia, template yang ditayangkan, resolusi tampilan (default 1920×1080, ikut melar ke 4K).
- **Mode kosong:** kalau semua playlist kosong / di luar jadwal → tayang Monitoring Lapangan layar penuh.
- **Status TV:** TV melapor "masih hidup" tiap menit → admin melihat *Online* / *Offline sejak 19:02*.
- Pengaturan suara global (master on/off) di atas pengaturan suara per konten.

### 4.2 Template
**Preset dari sketsa:**

| Preset | Susunan |
| :--- | :--- |
| A — Utama + Panel + Teks | Zona utama besar (slider / monitoring), panel kanan (logo / sponsor / jam), running text di bawah. |
| B — Full Slider | Satu zona penuh berisi slider. |
| C — Full + Teks | Zona penuh + running text di bawah. |

- **Tambah, edit, duplikat, hapus** template. Template yang sedang tayang tidak bisa dihapus.
- **Warna per template:** latar, aksen, teks, latar running text; logo & font (pilihan font web yang terbaca dari jauh).
- **Template custom (Tahap 2):** kanvas 16:9 dengan grid 24 × 24; zona digeser & diubah ukurannya (drag & drop, Gridstack.js). Jenis zona: **zona konten** (playlist sendiri per zona), **panel samping** (maks 2), **running text** (maks 1). **Maksimal 6 zona**, minimal 1 zona konten, tidak boleh menumpuk / keluar layar, ukuran minimum per jenis; **hanya satu zona yang bersuara**. Posisi disimpan dalam satuan grid dan dikirim ke TV dalam persen → hasil di TV sama dengan editor. Mulai custom = salinan preset yang sedang dipilih.
- **Transisi antar konten (Tahap 2):** per template — pudar, geser dari kanan, zoom halus, atau tanpa animasi (CSS ringan untuk Smart TV).

### 4.3 Konten
| Jenis | Keterangan | Tahap |
| :--- | :--- | :--- |
| Gambar | JPG / PNG / WEBP, diproses ulang (aman, ukuran pas layar). | 1 |
| Video MP4 | Upload ke server; batas ukuran per file (§8 no. 3). Suara on/off + volume per video. | 1 |
| Monitoring Lapangan | Widget sistem (§4.4). | 1 |
| Jadwal Hari Ini | Daftar slot terisi / kosong per lapangan. | 1 |
| Sponsor | Logo & nama sponsor bergantian (§4.6). | 1 |
| Jam & Tanggal | Untuk panel samping. | 1 |
| YouTube | Link YouTube (watch / youtu.be / shorts / embed / live); yang disimpan **hanya ID video** — link dari domain lain ditolak. Diputar lewat youtube-nocookie sampai selesai atau sampai **durasi maksimal** (wajib untuk live streaming); gagal mulai dalam 15 detik (internet putus / video dihapus) → dilewati. Bisa muncul iklan / logo YouTube & butuh internet stabil → untuk konten tetap disarankan upload MP4. | 2 |
| ~~Link Halaman~~ | Dicoret owner (13 Okt 2026) — hanya link YouTube. | — |

Pengaturan per konten: **durasi tampil**, **suara & volume** (video MP4 & YouTube), **aktif / nonaktif**. ~~Jadwal tayang per konten~~ dicoret owner (13 Okt 2026).

### 4.4 Monitoring Lapangan
Per lapangan, diperbarui otomatis tiap 10–15 detik:

| Kondisi | Tampilan |
| :--- | :--- |
| Sedang main | "Booked: {nama}" sesuai pengaturan §8 no. 1, jam main, **hitung mundur sisa waktu** |
| Berikutnya | Jam booking berikutnya (+ nama sesuai pengaturan) |
| Kosong | "Tersedia — booking sekarang" + QR ke halaman booking |

- **Notifikasi** di atas konten yang sedang tayang (tanpa memotong video) saat ada **booking baru** atau **check-in**.
- Data yang dikirim ke TV hanya yang tampil di layar — **tanpa No. HP, email, nominal pembayaran**.

### 4.5 Running Text
- **Manual:** teks bebas, bisa banyak baris bergiliran.
- **Otomatis:** kalimat template yang bisa diedit, dengan isian dari sistem:

| Pemicu | Contoh kalimat (bisa diubah) |
| :--- | :--- |
| Check-in | "Selamat bermain {nama} di {lapangan}!" |
| Booking baru | "Booking baru: {lapangan} jam {jam}." |
| Slot kosong hari ini | "Masih tersedia hari ini: {daftar_slot}. Booking di club61.id" |
| Sponsor | "Didukung oleh {sponsor}." |

- Pengaturan per template: font, ukuran, warna, warna latar, **kecepatan**, arah, pemisah antar kalimat.

### 4.6 Sponsor
- Satu daftar **Sponsor Signature**. Tiap entri:
  - **Sponsor brand** — nama + logo di-upload; atau
  - **Dari Sponsor Corporate** — pilih perusahaan yang sudah ada; nama ikut otomatis. *Sponsor Corporate belum punya logo di sistem → logo tetap di-upload di menu Signature.*
- Urutan, durasi tampil, aktif / nonaktif, periode tayang (mis. selama kontrak).
- Tampil di panel sponsor, di sela playlist, dan/atau di running text.

### 4.7 Draft → Preview → Tayangkan
1. Semua perubahan (template, playlist, running text, sponsor, pengaturan) tersimpan sebagai **draft** — TV tidak berubah.
2. Tombol **Preview** menampilkan hasil persis seperti di TV (16:9, animasi & running text berjalan, data monitoring asli) **sebelum disimpan**.
3. Tombol **Tayangkan** mengirim versi itu ke TV (TV mengambil versi baru dalam ±15 detik).
4. **Riwayat versi** + **Kembalikan ke versi sebelumnya**.
5. Setiap Tayangkan / Kembalikan tercatat di Log Aktivitas.

### 4.8 Hak Akses
| Izin | Default |
| :--- | :--- |
| Lihat menu Signature & Preview | super_admin, admin |
| Ubah template / konten / sponsor / running text | super_admin, admin (§8 no. 4) |
| Tayangkan ke TV & buat ulang link layar | super_admin, admin |

---

## 5. Catatan Smart TV (wajib diuji di TV sebenarnya)

Owner memilih **Smart TV langsung** (tanpa Android box / mini PC). Konsekuensinya:

| Hal | Konsekuensi | Penanganan |
| :--- | :--- | :--- |
| **Video bersuara** | Browser (termasuk browser Smart TV) melarang video **autoplay dengan suara** sebelum ada interaksi. | Saat TV dinyalakan, layar Signature menampilkan "Tekan OK untuk mengaktifkan suara" — **tekan OK sekali di remote**. Tanpa itu, video tetap jalan tanpa suara. |
| **Buka otomatis saat TV nyala** | Smart TV rumahan biasanya tidak bisa membuka link otomatis saat dinyalakan. | Buka link sekali lalu biarkan; atau pakai fitur "URL Launcher" / mode signage kalau TV-nya seri komersial. Cek model TV. |
| **Performa** | Browser Smart TV lebih lambat dari PC: video 4K + animasi bisa patah-patah. | Video dikompres ke 1080p; animasi ringan (CSS). Uji di TV asli sebelum go-live. |
| **Screensaver / auto-off** | TV bisa mati / screensaver sendiri. | Matikan auto power off & screensaver di pengaturan TV. |
| **Internet putus** | — | Konten di-cache: slider tetap jalan; data monitoring berhenti update dengan tanda kecil "data terakhir 19:02". |

**Rencana cadangan:** kalau hasil uji di Smart TV tidak lancar, pakai Android TV box dengan Chrome mode kiosk — tidak ada perubahan di sistem, hanya perangkat pemutarnya.

---

## 6. Batasan Lingkup (Tidak Termasuk)
- Lebih dari satu layar dengan konten berbeda (struktur data sudah mendukung, menu multi-layar menyusul kalau dibutuhkan).
- Kontrol TV fisik (nyala / mati, volume TV) dari admin.
- Statistik "berapa kali konten tayang" (bisa ditambah kemudian).

---

## 7. Tahapan & Estimasi

| Tahap | Isi | Perkiraan |
| :--- | :--- | :--- |
| **1** | Menu Signature; Layar (link rahasia, status online, mode kosong → monitoring); 3 preset + warna / logo / font; konten gambar & MP4 + suara per konten; widget Monitoring Lapangan, Jadwal Hari Ini, Sponsor, Jam; notifikasi booking / check-in; running text manual + otomatis; sponsor dinamis; **Draft → Preview → Tayangkan** + riwayat versi; uji di Smart TV | ± 6–7 hari kerja |
| **2** | Editor template custom drag & drop (Gridstack.js, maks 6 zona); konten YouTube; transisi animasi tambahan. ~~Link halaman, jadwal tayang, QR booking, cache offline~~ dicoret owner. | Selesai 13 Okt 2026 |

---

## 8. Pertanyaan untuk PM

| # | Pertanyaan | Usulan default |
| :--- | :--- | :--- |
| 1 | Di monitoring lapangan, nama customer tampil **lengkap**, **inisial**, atau hanya **"Booked"**? (No. HP & email tidak pernah tampil.) | Nama lengkap — bisa diganti kapan saja di pengaturan. |
| 2 | Jam tayang TV: menyala sesuai jam operasional (06:00–23:00) saja? | Ya — di luar jam operasional tampil logo + "Sampai jumpa besok". |
| 3 | Batas ukuran video per file? | 100 MB (diturunkan dari 200 MB supaya storage tidak cepat penuh), resolusi 1080p. |
| 4 | Siapa yang boleh mengubah & menayangkan konten: admin saja, atau perlu role Marketing? | super_admin & admin. |
| 5 | Running text otomatis "Selamat bermain {nama}" saat check-in — boleh menampilkan nama? | Ikut keputusan no. 1. |

---

## 9. Catatan Teknis untuk Tim Dev

- **Halaman TV:** route publik `/tv/{kode}` (alamat pendek yang ditampilkan di admin) dan `/signature/{token}` (alamat lama, tetap berlaku), tanpa login, hanya baca. Sejak 16 Okt 2026 kode baru 10 karakter tanpa huruf/angka yang mirip (`abcdefghjkmnpqrstuvwxyz23456789`) supaya bisa diketik pakai remote; link lama 48 karakter tetap berlaku sampai **Buat Ulang Link**. Tombol OK di remote juga meminta **layar penuh** (Fullscreen API) selain menyalakan suara; preview admin tidak. Data monitoring lewat endpoint JSON per token dengan **polling 10–15 detik** (tanpa Reverb / WebSocket), throttled.
- **Sumber data monitoring:** jadwal booking padel yang sama dengan layar kasir walk-in (status `PAID` / `CHECKED_IN`, jam main, nama customer) — dihitung di server, hanya field tampilan yang dikirim.
- **Data:** `signature_screens`, `signature_templates` (layout zona dalam persen + tema), `signature_contents`, `signature_playlist_items`, `signature_running_texts`, `signature_sponsors` (jenis `BRAND` / `CORPORATE` → `sponsor_organizations`), `signature_versions` (snapshot JSON tiap Tayangkan; draft vs published).
- **Upload:** gambar lewat `SecureImageUploader` (re-encode); video disimpan di disk `public` dengan validasi tipe `video/mp4` & batas ukuran (perlu cek `upload_max_filesize` / `post_max_size` server).
- **Cache di TV:** konten di-cache di browser (Cache Storage / service worker kalau browser TV mendukung) supaya playlist tetap jalan saat offline.
- **Gridstack.js:** v14.0.0 (21 Sep 2026), MIT, 0 dependency, ± 620 rb unduhan / minggu, repo aktif — dipasang lewat npm (versi dikunci), dimuat hanya di halaman Signature lewat entry Vite `resources/js/signature-editor.js`. Label zona dipasang lewat textContent (opsi `content` Gridstack tidak dipakai).
- **Zona custom:** `signature_templates.layout = CUSTOM`, `zones` = daftar zona satuan grid; aturan di `SignatureService::normalizeCustomZones()` (dipakai simpan, Preview, dan saat menyusun tampilan TV). Playlist per zona di `signature_playlist_items.zone_key`.
- **File upload:** `SignatureFileJanitor` — file yang dipakai library atau tayang di versi mana pun dalam 7 hari terakhir disimpan; selain itu langsung dihapus saat diganti / dihapus, dan disapu tiap malam (`signature:clean-files`, 03:00). "Kembalikan versi" ditolak kalau file versi itu sudah dihapus. Batas upload video = min(100 MB (`Signature::VIDEO_MAX_MB`), `upload_max_filesize`, `post_max_size`).
- **Audit:** Tayangkan, Kembalikan versi, buat ulang link layar, hapus template → `ActivityLogger`.
