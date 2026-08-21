# ADR 0006 — Cross-plugin communication through core-owned events

**Status:** Accepted — 2026-08-18

## Context

The Stripe rails (checkout, webhooks, customer records) were carved into a Payments *foundation plugin*, but its webhook handler still reached directly into vertical domain code: settling a checkout promoted donations, invoice events advanced recurring gifts, and event-registration checkouts confirmed registrations. Any such reach makes verticals dependent on Payments internals — and worse, makes Payments dependent on every vertical it fulfills, which cannot survive verticals becoming removable packages.

The governing rule: **hard dependencies point only at core; verticals never depend on each other.** Foundation plugins are optional capabilities a vertical detects and degrades without (Events without Payments = free events only — a real use case). The first inversion covered the events slice (`3767702a`); the donations slice (`7d2dc087`) was the first to require it across repository boundaries.

## Decision

Cross-plugin interaction has exactly two channels, both core-owned:

1. **Core-owned events for inbound flow.** The Payments webhook verifies and normalizes, then dispatches core events — `App\Payments\Events\CheckoutSettled`, `InvoiceSettled`, `InvoicePaymentFailed` — for *every* relevant occurrence, reading no vertical models. Vertical listeners own fulfillment. **Every listener self-filters on its own routing key** (checkout metadata, its own subscription ids) **before any read** — normative, because multiple verticals listen to the same event and dispatch-always is only safe when each listener cheaply no-ops on traffic that isn't its own.
2. **Capability detection for soft dependencies** (core `CapabilityRegistry`): a vertical asks "is payments enabled?" and degrades honestly rather than requiring the foundation plugin.

Idempotency guards travel with the listeners (transaction-existence checks, per-gift acknowledgment markers), so replays and late deliveries are safe.

## Consequences

- **Verticals are removable without touching Payments**: with a vertical absent, a late webhook dispatch finds no listener and writes nothing — proven by the removal mirrors on both the events and donations sides.
- **Adding a vertical adds a listener, not a Payments branch.**

Costs:

- **Core owns a growing event vocabulary.** Every new inbound flow means a new core event class — designed, named, and versioned in core before any vertical can consume it. The events are small, but they are contract surface forever.
- **Inverting an existing branch after extraction is a coordinated cross-repo change** — it ships as a plugin release with a same-commit pin bump in core, not an edit. The first inversion cost two releases: the payments release itself, plus a rider release for a sibling plugin whose tests had encoded "I am the only listener" (an exclusivity assumption we now check for whenever a new vertical arrives).
- **Dispatch-always trades CPU for decoupling**: every listening vertical pays a no-op invocation on every relevant webhook, and correctness rests on routing keys staying disjoint. One branch (memberships) is still inline in the webhook, safe only because its subscription ids are disjoint — a standing asymmetry until that vertical's turn.
- Event payload changes are breaking changes to every listener, in every repo, at once.

## References

- `docs/plugin-contract.md` — the events-and-hooks surface (including the rule that a plugin ignores its own emissions) and the capability-detection surface; current authority.
- `sessions/plugin-architecture-plan.md` — the settled question establishing foundation plugins: hard dependencies point only at core, and verticals never depend on each other.
- Commits `3767702a` (the first inversion, events slice, 2026-08-18), `7d2dc087` (the donations slice — the first cross-repo inversion, 2026-08-19).
