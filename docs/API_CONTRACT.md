# Club 61 API Reference & Contract

Base URL: `http://localhost:8080/api/v1`
Interactive Swagger UI: `http://localhost:8080/swagger/index.html`

## Authentication
All protected endpoints require the HTTP Authorization Header:
`Authorization: Bearer <JWT_TOKEN>`

### User Roles:
- `SUPERADMIN` : Full venue master control
- `MANAGER`    : Reports, staff scheduling, promo management
- `CASHIER`    : POS orders, unified cart, split bill, payments
- `KITCHEN`    : Kitchen Order Tickets (KOT), recipe inventory
- `STYLIST`    : Salon service queue & appointments
- `TRAINER`    : Padel coaching schedules
- `CUSTOMER`   : Online booking, QR tickets, digital orders

---

## Standard JSON Response Envelope

### Success Response:
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

### Error Response:
```json
{
  "success": false,
  "error": "Error description message",
  "code": 400
}
```

---

## Key API Endpoints Summary

### 1. Auth (`/api/v1/auth`)
- `POST /register` : Register new customer
- `POST /login` : Authenticate user & receive JWT token
- `GET /profile` : Get current user details (Protected)

### 2. Padel Courts & Booking (`/api/v1/padel`)
- `GET /courts` : List all courts (indoor/outdoor, regular & prime rates)
- `GET /schedule?date=&timezone=` : Real-time hourly slot availability (06:00 - 23:00 WIB, masked privacy)
- `GET /equipments` : List rental equipment (rackets, balls, fresh pack) & coaching add-ons
- `POST /hold-slot` : Atomic multi-slot temporary reservation (distributed cache lock). Lama tahan slot diatur admin (default 10 menit) — pakai `data.expires_at` / `data.hold_seconds_remaining` dari respons untuk countdown, jangan hardcode.
- `POST /release-slot` : Voluntarily release held slot from customer cart
- `POST /checkout` : Idempotent checkout with `X-Idempotency-Key` (Midtrans Snap / Xendit / Mock)
- `POST /bookings/{id}/retry-payment` : Retry payment for booking; handles **pending price delta ($\Delta$)** when rescheduled to Prime Time under the same `order_id`
  - Batas bayar dihitung sejak checkout (klik bayar) dan tersimpan di booking (`expires_at`, default 15 menit, diatur admin). Bayar ulang / ganti metode **tidak memperpanjang** batas ini: sesi Midtrans baru hanya diberi sisa waktunya. Sisa < 2 menit → `422` ("Batas waktu pembayaran booking ini sudah habis"), arahkan customer membuat booking baru. Countdown di aplikasi pakai `expires_at` dari `GET /bookings/{id}/ticket`.
- `GET /my-bookings` : Player booking history categorized by status (`UPCOMING`, `COMPLETED`, `CANCELLED`)
- `GET /bookings/{id}/ticket` : Customer boarding pass ticket payload; includes `has_pending_delta`, `unpaid_delta`, `total_paid`, and turnstile `qr_code_hash` (held `null` while delta is unpaid)
- `POST /bookings/{id}/refund` : Customer self-service refund (applicable >= H-24 before kickoff)
- `POST /check-in` : Gate cashier / turnstile QR single-use validation (anti-replay attack)

### 3. Wellness & Waitlist (`/api/v1/wellness`)
- `GET /facilities` : List facilities (Sauna)
- `GET /slots?facility_id=&date=` : List session slots & remaining headcounts
- `POST /bookings` : Book session ticket(s)
- `POST /waitlist` : Join waitlist if slot capacity is full

### 4. Salon & Appointments (`/api/v1/salon`)
- `GET /services` : List salon treatments with dynamic durations
- `GET /stylists` : List available stylists & schedules
- `POST /appointments` : Book multi-treatment appointment stacked with stylist

### 5. Gym Membership (`/api/v1/gym`)
- `GET /packages` : List membership options (unlimited / visit packs)
- `POST /memberships` : Purchase membership
- `POST /checkin` : Gate check-in via QR Pass

### 6. Cafe F&B & KOT (`/api/v1/fnb`)
- `GET /categories` : List menu categories
- `GET /menus` : List digital menu with modifiers (sugar/milk/ice)
- `POST /orders` : Table QR ordering (Dine In / Take Away / Delivery)
- `GET /kot` : Real-time KOT tickets for Kitchen / Bar display
- `PATCH /kot/:id/status` : Update cooking status (`QUEUED` -> `COOKING` -> `READY`)

### 7. Merchandise Retail (`/api/v1/merch`)
- `GET /products` : List apparel & padel gear with variants (size/color)
- `POST /checkout` : Standalone retail purchase

### 8. POS Kasir, Split Bill & Payment (`/api/v1/pos`)
- `POST /orders` : Unified order (Padel + Cafe + Merchandise)
- `POST /split-bill` : Create split bill (`EQUAL` or `BY_ITEM`)
- `POST /payments/charge` : Request payment gateway charge (QRIS, VA)
- `POST /payments/simulate` : Development simulator for instant payment confirmation
- `POST /payments/webhook` : 3rd party webhook notification receiver

### 9. Metode Pembayaran Online (`/api/v1/payment-methods`)
- `GET /payment-methods?amount={total}` : Daftar metode pembayaran online yang **sedang aktif** (diatur admin di menu *Metode Pembayaran Online*), urut sesuai pengaturan. Publik, read-only.
  - `amount` (opsional) = total tagihan; metode di luar batas nominal tidak ikut (mis. QRIS maks Rp10.000.000 per transaksi — ketentuan BI).
  - Item: `code`, `name`, `note`, `badge`, `group` (`QRIS` / `VA` / `CARD`), `min_amount`, `max_amount` (null = tanpa batas).
  - **Aplikasi mobile WAJIB memakai endpoint ini** — jangan menulis daftar metode sendiri. Nilai `code` dikirim sebagai `payment_method` ke `POST /padel/checkout`, `POST /padel/bookings/{id}/retry-payment`, dan `POST /membership/checkout`.
  - Server menolak (422) metode yang tidak dikenal, sedang nonaktif, atau di luar batas nominal.

### 10. Membership: Paket, Benefit & Check-in Fasilitas (`/api/v1/membership`)
- `GET /plans` : Paket aktif + `benefits` (data mentah) + **`benefit_cards`** (siap tampil: `code`, `badge`, `title`, `details[]`, `description`). Nama & deskripsi fasilitas diatur admin di menu **Fasilitas Membership** — aplikasi mobile **wajib** menampilkan `benefit_cards`, jangan menulis teks benefit sendiri. Field paket baru: `description`, `perks[]` (daftar privilege).
- `POST /checkin` `{ "facility": "GYM" | <kode fasilitas mode check-in>, "balance_id"?: string }` : Check-in fasilitas bermode check-in (Gym + fasilitas baru), memotong 1 kunjungan. `422` kalau kuota habis, paket hanya diskon (tanpa akses masuk), fasilitas bukan mode check-in, atau `balance_id` bukan untuk fasilitas itu.
- `POST /checkin-gym` : Alias lama untuk `POST /checkin` dengan `facility=GYM`.
