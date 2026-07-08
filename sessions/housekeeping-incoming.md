# Housekeeping Incoming

Capture buffer for items noticed mid-session via `npm run logbug -- "…"`. Each
line is stamped with the VERSION marker + date at capture time. This file is
NOT the canonical inbox — at the next session close the close gate digests these
items, verifies each against current code, surfaces anything questionable, and
folds the survivors into `sessions/housekeeping-inbox.md`, then clears this
file back to this header. Do not hand-curate here; capture and move on.

---


- [0.363.03 · 2026-07-07] Demo-role DashboardView seeds recent_donations + recent_notes into the dashboard_grid slot, but both definitions declare allowedSlots: ['record_detail_sidebar'] only — the seeder bypasses the slot check the dashboard-builder API enforces. Surfaced by Test Audit Cycle 2 while diagnosing the s363 fast-suite intermittent (the seeded rows collided with DashboardBuilderApiControllerTest's not-dashboard-allowed pick). Decide: widen the two definitions' allowedSlots to include dashboard_grid, or drop them from the demo dashboard seed.
- [0.363.03 · 2026-07-07] Coverage gaps recorded by Test Audit Cycle 2 (D4) after deleting false-confidence tests: (1) ProductPriceObserver's Stripe archive-and-recreate flow has no real coverage (the two deleted tests passed with the observer unregistered — needs a Stripe boundary mock); (2) ProductWaitlistController (join-waitlist POST, dedupe) has zero tests (the deleted waitlist tests only echoed Eloquent updates); (3) RoleResource form create/edit paths untested at the resource layer (deleted tests called Spatie APIs directly); (4) EventCancellationTest guards only the retired auto-send — the live Cancel Event wizard send (EditEvent 'Mail::to(...)->send(new EventCancellation)') is untested anywhere. Each is a candidate one-test backfill for a housekeeping batch, per the A005 DonationCheckout precedent.