# Launch Plan — One-Page Summary

*Ratified at session 369 (2026-07-17). This is the steering copy — the canonical schedule lives in `sessions/release-plan.md` § Launch schedule; the security detail in `sessions/tracks/security-hardening.md`.*

## The target

Launch by end of July 2026: the public site and demo taking paid ad traffic, and the product ready to hold one real client's donations and donor data. **Nothing goes live until Gates 2 and 3 are both passed.** "Beta 1" is retired as the target.

## The three gates

1. **Looks professional to paid traffic** — essentially passed. One floating session remains: tighten the demo once designer feedback arrives, combined with your final review of the pages and components. It slots in whenever that feedback lands.
2. **Safe to hold one client's money and PII** — donations work end to end with an automatic tax receipt, events sell tickets including free/comp tickets, backup and restore are proven on production-shape servers, and Privacy Policy + Terms are live (Stripe requires both URLs — **counsel needs to keep moving now**, this is the one thing outside session control).
3. **You believe it's secure** — a five-session hardening track built from the security survey: lock down the site's browser-security perimeter, add a second lock on the fleet endpoints that can reach the donor database, prove nothing can slip past the content sanitizer, then two adversarial passes that attack the public forms, the member portal, and every permission gate. It ends with a findings walkthrough where every item is either fixed or consciously accepted by you. Internal review is the bar; no paid audit.

## The schedule (16 sessions + 1 floating)

Security perimeter work runs **first** so everything afterward is rehearsed against the final, hardened surface: perimeter/CSP → sanitizer audit → fleet second lock → tax receipts → free tickets → server setup → backup drill → donation rehearsal → event rehearsal → payment test suite → two adversarial security passes → help docs → license audit → integration retest → final cleanup/squash. The floating demo-review session slots in on feedback arrival.

## What was deferred (not cut — parked until a real customer exists)

Publish audit trails and "someone else is editing this" indicators (solo-run install), the large-scale load rehearsal, the full browser/accessibility program (its two launch-relevant slices moved into the schedule), extra importer test fixtures, the test-suite review, and the QuickBooks leg of the donation loop (billing convenience — the money path is Stripe → CRM → receipt). **Nothing was cut outright.**

## Budget and pace

17 sessions against your 30–40 budget — roughly double headroom for splits, emergent work, designer iterations, and Fleet Manager attention. At 3–4 sessions/day that's well inside July. Fleet Manager needs nothing new for launch; client billing stays on its own FM-side track (first client can be billed by hand in Stripe).

**Next session: 370 — security perimeter (headers, CSP, self-hosted editor). Prompts are drafted.**
