# 005 — Stripe as Source of Financial Truth

**Date:** March 2026
**Status:** Decided

---

## Context

The platform handles donations, membership payments, event ticket sales, and product commerce. Financial data must be accurate, auditable, and synchronized with QuickBooks. A decision was required about where the authoritative record of each financial transaction lives.

## Decision

Stripe is the source of financial truth. The platform maintains a local mirror of Stripe transaction state for reporting and QuickBooks sync. Webhooks keep the mirror current.

## Rationale

- Stripe's records are the legal and financial authority. Treating the local database as primary would create reconciliation risk
- A local mirror enables fast reporting queries without hitting the Stripe API on every report
- Webhooks provide near-real-time sync. Webhook processing is idempotent and logged
- QuickBooks receives outbound sync from the local mirror — it does not write back to the platform

**Correction (session 380, August 2026):** Laravel Cashier was never actually used — no `Billable` trait, no Cashier config or subscription tables ever existed; the integration talks to Stripe directly through `stripe/stripe-php` (now a direct composer dependency; Cashier removed). The Stripe rails (checkout-session service, webhook controller, price observer, live-mode sniff) live in the Payments foundation plugin at `plugins/Payments/`, consumed by verticals through the core capability API and the core-owned `CheckoutProvider` contract (`docs/plugin-contract.md` surface 13). The decision itself — Stripe as financial source of truth, local mirror via idempotent webhooks — stands unchanged.

## Consequences

- Local transaction records must never be manually edited — they are Stripe mirrors, not platform-of-record data
- Webhook handling must be robust: idempotent, logged, and surfaced in the system activity panel
- The `transactions` table schema mirrors Stripe's data model closely
- All financial reporting is run against the local mirror, not the Stripe API directly
- Grant allocation and QuickBooks sync operate against the local mirror
