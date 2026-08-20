# ADR 0006 — Plugins communicate only through core-owned events and capabilities

**Status:** accepted
**Date:** the dependency rule at session 376 (plan § 6.2, owner guideline); the event inversion proven at session 381 (events slice) and session 389 (donations slice — the first cross-repo inversion)

## Context

The Stripe rails (checkout, webhooks, customer records) were carved into a Payments *foundation plugin*, but its webhook handler still reached directly into vertical domain code: settling a checkout promoted donations, invoice events advanced recurring gifts, and event-registration checkouts confirmed registrations. Any such reach makes verticals dependent on Payments internals — and worse, makes Payments dependent on every vertical it fulfills, which cannot survive verticals becoming removable packages.

The owner guideline (376): **hard dependencies point only at core; verticals never depend on each other.** Foundation plugins are optional capabilities a vertical detects and degrades without (Events without Payments = free events only — a real use case).

## Decision

Cross-plugin interaction has exactly two channels, both core-owned:

1. **Core-owned events for inbound flow.** The Payments webhook verifies and normalizes, then dispatches core events — `App\Payments\Events\CheckoutSettled`, `InvoiceSettled`, `InvoicePaymentFailed` — for *every* relevant occurrence, reading no vertical models. Vertical listeners own fulfillment. **Every listener self-filters on its own routing key** (checkout metadata, its own subscription ids) **before any read** — normative since the donations inversion, because multiple verticals listen to the same event and dispatch-always is only safe when each listener cheaply no-ops on traffic that isn't its own.
2. **Capability detection for soft dependencies** (core `CapabilityRegistry`): a vertical asks "is payments enabled?" and degrades honestly rather than requiring the foundation plugin.

Idempotency guards travel with the listeners (transaction-existence checks, per-gift acknowledgment markers), so replays and late deliveries are safe.

## Consequences

- **Verticals are removable without touching Payments**: with a vertical absent, a late webhook dispatch finds no listener and writes nothing — proven by the removal mirrors on both the events and donations sides.
- **Adding a vertical adds a listener, not a Payments branch.** After extraction, though, inverting an *existing* branch is a cross-repo change and ships as a plugin release (the first: `crm-plugin--payments` v0.2.0, session 389) — vendor code is never edited (ADR 0004).
- The sibling lesson (389): tests in one plugin's suite must not encode "I am the only listener/contributor" — a second vertical's arrival falsified an Events assertion and shipped as the v0.1.1 rider. Exclusivity assertions are now checked for at carve time.
- Not yet inverted: the membership branch remains inline in the Payments webhook until the Memberships block (its subscription ids are disjoint, so dispatch-always stays safe meanwhile).

## References

- `docs/plugin-contract.md` surfaces 10 (events/hooks, incl. the self-filter rule) and 13 (capability detection) — current authority.
- `sessions/plugin-architecture-plan.md` § 6.2 (foundation plugins; verticals never depend on each other).
- Session 381 and 389 logs — the two inversion slices.
