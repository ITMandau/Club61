# Runbook Operasional
## Migrasi Database Production Existing Menjadi "Cabang Pertama" di Arsitektur Multi-Tenant
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `RUNBOOK-MIGRASI-CABANG-PERTAMA` |
| **Versi** | `v1.0.0-DRAFT` |
| **Status** | **Disiapkan lebih awal — BELUM MENDESAK, dieksekusi menjelang go-live Modul 13** |
| **Dokumen Induk** | `PRD_MODUL_13_MULTI_BRANCH_TENANCY.md` — lihat §7 "Ruang Lingkup & Batasan" |
| **Trigger Eksekusi** | Hanya dijalankan setelah Modul 13 (`stancl/tenancy`) selesai dibangun **dan** database Club 61 saat ini sudah/akan menyimpan data transaksi customer sungguhan (production). Selama masih data dev/testing, runbook ini tidak perlu dijalankan. |

---

## 1. Kenapa Ini Dokumen Terpisah, Bukan Bagian dari PRD Modul 13

PRD Modul 13 (FR-03) mendefinisikan alur **provisioning cabang baru**: database kosong → migration dari nol → seed RBAC → aktif. Alur itu **tidak berlaku** untuk database Club 61 yang sekarang, karena database ini:

- Sudah punya skema yang identik dengan target (bukan kosong).
- Berpotensi sedang menyimpan data transaksi *live* (booking, payment, shift kasir) pada saat migrasi ke arsitektur baru ini harus dijalankan.
- Kesalahan di sini berarti **kehilangan/kerusakan data yang sudah terjadi secara nyata**, bukan "provisioning gagal, tinggal ulang" seperti cabang baru.

Karena kelas resikonya berbeda, migrasi ini butuh jendela maintenance, backup terverifikasi, dan rencana rollback tertulis — bukan sekadar centang di checklist fitur.

---

## 2. Prasyarat Sebelum Runbook Ini Boleh Dieksekusi

Jangan mulai Bagian 3 sampai semua ini terpenuhi:

- [ ] Modul 13 (`stancl/tenancy`, panel Landlord, tabel `tenants`) sudah selesai dibangun dan lolos seluruh test plan di §6 PRD Modul 13, diverifikasi di environment staging dengan **≥ 2 tenant dummy** (bukan cuma 1).
- [ ] Sudah ditentukan `subdomain` definitif untuk cabang pertama ini (misal `jakarta`), dan sudah dikoordinasikan dengan tim yang pegang DNS domain `club61.id`.
- [ ] Sudah diinventarisasi semua tempat yang meng-hardcode domain/URL lama: webhook Midtrans (dashboard merchant), aplikasi mobile (base URL API), bookmark staf, dokumen internal.
- [ ] Sudah ada jendela waktu low-traffic yang disepakati (lihat Bagian 4) dan diumumkan ke staf operasional H-1 minimal.
- [ ] Kredensial MySQL dengan privilege `CREATE DATABASE`/`ALTER` untuk proses ini **berbeda** dari kredensial aplikasi runtime harian (prinsip least-privilege yang sama seperti FR-03).

---

## 3. Persiapan (H-7 s.d. H-1)

1. **Freeze fitur baru** ke database production selama window migrasi — tidak ada migration/deploy lain yang jalan bersamaan dengan proses ini.
2. **Full backup** database Club 61 saat ini (`mysqldump` lengkap, termasuk struktur + data), simpan di lokasi terpisah dari server aplikasi.
3. **Uji restore backup** tersebut ke database terpisah (bukan simulasi di kepala) — pastikan file backup benar-benar bisa dipulihkan dan datanya utuh sebelum dianggap "aman".
4. **Dry-run di staging**: duplikasi database production (dari backup di atas) ke environment staging, lalu praktikkan seluruh Bagian 5 di staging dulu sampai lancar tanpa kejutan.
5. **Cek transaksi yang berpotensi "mengambang"** menjelang window migrasi: booking dengan status `LOCKED`/`PENDING_PAYMENT`, shift kasir (`pos_cashier_shifts`) yang `OPEN`. Idealnya window dipilih saat jumlah ini serendah mungkin (misal tengah malam, tidak ada jam operasional lapangan).
6. **Siapkan halaman maintenance mode** (Laravel `php artisan down` dengan pesan yang jelas ke customer) untuk dipasang selama eksekusi.

---

## 4. Pemilihan Jendela Maintenance

- Pilih waktu di luar jam operasional lapangan (tidak ada booking aktif berjalan) dan di luar jam kerja kasir.
- Estimasi durasi window: cukup untuk Bagian 5 + verifikasi Bagian 6, plus buffer 2x lipat untuk skenario rollback.
- Umumkan ke seluruh staf (kasir, admin cabang) minimal H-1, termasuk instruksi "jangan buka shift/terima booking manual selama window ini".

---

## 5. Eksekusi Migrasi (Urutan Wajib, Jangan Dilompat)

1. **Aktifkan maintenance mode** di aplikasi existing (`php artisan down`) — hentikan seluruh trafik tulis (booking baru, pembayaran, login kasir).
2. **Backup final** (bukan backup H-7, tapi backup detik-terakhir sebelum eksekusi) — ini adalah titik kembali (rollback point) yang paling valid.
3. **Verifikasi tidak ada proses background yang masih menulis**: cek queue worker (`horizon`/`queue:work`) dan scheduled command yang sedang berjalan; hentikan sementara jika perlu.
4. **Daftarkan database existing sebagai tenant di tabel `tenants`** (database Landlord) — **BUKAN** lewat job `ProvisionNewBranchJob` (yang mengasumsikan database kosong dan akan menjalankan migration dari nol). Insert manual/seeder khusus satu-kali dengan:
   - `db_name` = nama database existing yang sudah ada (apa adanya, tidak dibuat baru).
   - `subdomain` = subdomain definitif yang sudah disepakati di Bagian 2.
   - `status` = `ACTIVE` langsung (skip `PROVISIONING`, karena migration/seed RBAC sudah lama berjalan di database ini).
   - `provisioned_at` = timestamp eksekusi hari ini (untuk keperluan audit, bukan tanggal database ini sebenarnya dibuat).
5. **Update DNS/konfigurasi domain**: arahkan subdomain baru (`jakarta.club61.id`) ke aplikasi yang sama, sambil (untuk periode transisi) domain lama tetap redirect/alias ke subdomain baru — jangan langsung dimatikan.
6. **Update URL webhook Midtrans** di dashboard merchant ke domain/subdomain baru. Ini yang paling rawan terlewat — kalau tidak diupdate, notifikasi pembayaran dari Midtrans akan gagal masuk meski aplikasi sudah pindah domain.
7. **Restart queue worker & scheduled command** agar berjalan dalam konteks tenant yang baru terdaftar.
8. **Matikan maintenance mode** (`php artisan up`) hanya setelah Bagian 6 (verifikasi) lolos semua — jangan dibuka ke publik sebelum diverifikasi.

---

## 6. Verifikasi Pasca-Migrasi (Wajib Sebelum Buka ke Publik)

- [ ] Request ke subdomain baru berhasil resolve ke database yang benar (`test_tenant_resolution_switches_database_connection_correctly` dari PRD Modul 13, dijalankan manual terhadap data real).
- [ ] Booking yang sebelumnya `LOCKED`/`PENDING_PAYMENT` sebelum migrasi masih ada dan statusnya tidak berubah/rusak.
- [ ] Shift kasir yang sebelumnya `OPEN` masih bisa diakses dan ditutup normal oleh kasir terkait.
- [ ] Staf bisa login dengan kredensial lama mereka tanpa perlu reset password.
- [ ] Kirim 1 transaksi test payment lewat Midtrans sandbox/live-kecil untuk memastikan webhook baru benar-benar diterima aplikasi.
- [ ] Cek jumlah baris di tabel-tabel kunci (`padel_bookings`, `orders`, `payments`, `users`) sama persis dengan angka sebelum migrasi (bandingkan dengan snapshot dari backup final di langkah 5.2).
- [ ] Panel Landlord (`panel.club61.id`) menampilkan cabang ini dengan status `ACTIVE` dan metadata yang benar.

Jika salah satu poin gagal → **jangan lanjut buka ke publik**, lanjut ke Bagian 7 (Rollback).

---

## 7. Rencana Rollback

Rollback dipicu jika Bagian 6 gagal, atau muncul error tak terduga di tengah Bagian 5.

1. Aktifkan kembali maintenance mode jika sempat dimatikan.
2. Kembalikan konfigurasi DNS/domain ke keadaan semula (domain lama langsung ke aplikasi, tanpa lapisan tenancy).
3. Jika backup final (langkah 5.2) sempat di-restore/dimodifikasi secara keliru: restore ulang dari backup final tersebut ke database production.
4. Hapus/nonaktifkan entri tenant yang baru didaftarkan di tabel `tenants` (set `status` bukan `ACTIVE`, atau hapus barisnya jika belum ada dependensi lain).
5. Kembalikan URL webhook Midtrans ke domain lama.
6. Matikan maintenance mode, verifikasi aplikasi berjalan seperti sebelum migrasi dimulai.
7. Dokumentasikan apa yang gagal sebelum mencoba eksekusi ulang — jangan retry langsung tanpa tahu akar masalahnya.

---

## 8. Sign-Off

Migrasi ini baru dianggap selesai (bukan hanya "sudah dijalankan") setelah:

- [ ] Seluruh checklist Bagian 6 lolos.
- [ ] Tidak ada laporan staf/customer terkait anomali dalam 24 jam pertama pasca-migrasi.
- [ ] Domain lama (jika masih dipakai sebagai alias sementara) dijadwalkan untuk dinonaktifkan penuh setelah periode transisi disepakati (misal 2 minggu), agar tidak ada trafik yang masih "nyasar" ke jalur lama.
