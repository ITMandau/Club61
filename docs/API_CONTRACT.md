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
- `GET /slots?court_id=&date=` : Get real-time slot availability for specific date
- `POST /lock-slot` : Temporary slot reservation (5-minute hold in Redis)
- `POST /bookings` : Create padel court booking (+ equipment + coach)

### 3. Wellness & Waitlist (`/api/v1/wellness`)
- `GET /facilities` : List facilities (Cold Plunge & Sauna)
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
