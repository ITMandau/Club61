# Club 61 Business Rules & Operational Invariants

## 1. Padel Booking & Concurrency Guard
- **Prime Time Pricing:** 17:00 - 22:00 weekdays and all weekend slots apply `hourly_rate_prime`.
- **Anti-Double Booking:** Handled via PostgreSQL `EXCLUDE USING gist (court_id WITH =, slot_range WITH &&)` where status IN ('PAID', 'LOCKED', 'PENDING').
- **Coach Anti-Overlap:** Handled via `EXCLUDE USING gist (coach_id WITH =, slot_range WITH &&)`. A coach cannot be booked simultaneously on Court 1 and Court 2.
- **Temporary Slot Hold:** 5-minute lock in Redis (`SETNX` with TTL 300s). Released automatically if unpaid after 15 minutes by background cron.

## 2. Wellness Capacity & Waitlist
- **Hard Quota Constraint:** `CHECK (booked_count >= 0 AND booked_count <= max_capacity)`.
- **Atomic Booking:** `UPDATE wellness_slots SET booked_count = booked_count + $1 WHERE id = $2 AND booked_count + $1 <= max_capacity`.
- **Waitlist Priority Window:** When a slot opens, queue #1 gets a 10-minute exclusive claim window before passing to #2.

## 3. Salon Stylist Stacking
- **Multi-Service Calculation:** When multiple treatments are selected (e.g. Haircut 45m + Coloring 90m = 135m), the backend blocks the stylist's timeline continuously.
- **Anti-Overlap:** Handled via `EXCLUDE USING gist (stylist_id WITH =, slot_range WITH &&)`.

## 4. Cafe KOT & Bill of Materials (BOM)
- **Automatic Stock Deduction:** When an order is marked `PAID`, each F&B item queries `recipe_boms` and decrements `raw_materials.current_stock`.
- **Non-Negative Stock Invariant:** `CHECK (current_stock >= 0)`.
- **KOT Station Routing:** Drink items route to `BAR`, food items route to `KITCHEN`.

## 5. POS Split Bill & Remainder Math
- Supports `EQUAL` split and `BY_ITEM` split.
- **Rounding Handling:** When dividing uneven amounts (e.g. 100,000 / 3), person 1 pays 33,334 and persons 2-3 pay 33,333 so the sum exactly matches `grand_total`.

## 6. Cancellation & Reschedule Rules
- **Free Cancel Window:** Cancellations >= 24 hours prior to booking start time are eligible for full refund logged in `refunds`.
- **Reschedule Limit:** Maximum 1 reschedule allowed up to 12 hours prior to scheduled time. Price difference applies if moving from regular to prime time.
