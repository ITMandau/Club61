# Club 61 Business Rules & Operational Invariants

## 1. Padel Booking & Concurrency Guard
- **Prime Time Pricing:** 17:00 - 22:00 weekdays and all weekend slots apply `hourly_rate_prime`.
- **Anti-Double Booking:** Handled via two-tier concurrency guard: Distributed Cache Lock (`SETNX` with sorted keys) + Database Pessimistic Lock (`SELECT ... FOR UPDATE` with open intervals `start_time < req_end` AND `end_time > req_start`) for status IN ('PAID', 'LOCKED', 'CHECKED_IN').
- **Coach Anti-Overlap:** A coach cannot be booked simultaneously on Court 1 and Court 2 during overlapping time windows.
- **Temporary Slot Hold (Cart):** 10-minute lock in Cache (TTL 600s). Released automatically if unpaid after 10 minutes by background cron (`padel:release-expired-slots`).
- **Reschedule Slot Hold (24-Hour TTL):** When a booking is rescheduled with an unpaid price delta, the new slot is locked in Cache with a **24-hour TTL (86,400s)** and status `LOCKED`.
- **Garbage Collection Anti-Premature Expiry Rule:** Background garbage collector (`releaseExpiredLocks` / `syncExpiredAndCompletedBookings`) strictly ignores bookings with `reschedule_count > 0` OR any existing `SUCCESS` payment. Rescheduled bookings are NEVER prematurely expired by the 10-minute cart cleaner.
- **Turnstile QR Gate Lock:** Bookings with an unpaid delta have their turnstile QR hash revoked (`qr_code_hash = null`). Gate access remains locked until the delta is settled.

## 2. Wellness Capacity & Waitlist
- **Hard Quota Constraint:** `CHECK (booked_count >= 0 AND booked_count <= max_capacity)`.
- **Atomic Booking:** `UPDATE wellness_slots SET booked_count = booked_count + $1 WHERE id = $2 AND booked_count + $1 <= max_capacity`.
- **Waitlist Priority Window:** When a slot opens, queue #1 gets a 10-minute exclusive claim window before passing to #2.

## 3. Salon Stylist Stacking
- **Multi-Service Calculation:** When multiple treatments are selected (e.g. Haircut 45m + Coloring 90m = 135m), the backend blocks the stylist's timeline continuously.
- **Anti-Overlap:** Handled via stylist schedule overlap check (`start_time < req_end AND end_time > req_start`).

## 4. Cafe KOT & Bill of Materials (BOM)
- **Automatic Stock Deduction:** When an order is marked `PAID`, each F&B item queries `recipe_boms` and decrements `raw_materials.current_stock`.
- **Non-Negative Stock Invariant:** `CHECK (current_stock >= 0)`.
- **KOT Station Routing:** Drink items route to `BAR`, food items route to `KITCHEN`.

## 5. POS Split Bill & Remainder Math
- Supports `EQUAL` split and `BY_ITEM` split.
- **Rounding Handling:** When dividing uneven amounts (e.g. 100,000 / 3), person 1 pays 33,334 and persons 2-3 pay 33,333 so the sum exactly matches `grand_total`.

## 6. Cancellation & Reschedule Rules
- **Free Cancel Window:** Cancellations >= 24 hours prior to booking start time are eligible for refund logged in `refunds`.
- **Admin Concierge Reschedule (Duration-Lock & Contiguous Availability):**
  - Moving schedule preserves the exact session duration (e.g., a 3-hour booking remains 3 contiguous hours).
  - Validates contiguous slot availability across DB and Cache.
  - Rejects past dates (must be today or future).
- **Price Delta Settlement & Financial Invariants:**
  - **Kurang Bayar ($\Delta > 0$, Reguler ke Prime):** New slot locked with 24h TTL; booking status set to `LOCKED`; QR code withheld (`null`); supplemental payment record created with status `PENDING`. Delta is payable via Cash at frontdesk or via VA/QRIS under the **same order ID (`order_id`)**.
  - **Lebih Bayar ($\Delta < 0$, Prime ke Reguler):** Balance difference is logged in `refunds` as customer deposit / cash refund.
  - **Tarif Sama ($\Delta = 0$):** Slots migrated atomically and new QR code hash generated immediately.
- **Delta-Only Payment Retry:** Online payment retry (`POST /api/v1/padel/bookings/{id}/retry-payment`) charges only the outstanding delta amount without recalculating the original order total. Webhook settlement immediately converts status to `PAID` and issues the turnstile QR code.
