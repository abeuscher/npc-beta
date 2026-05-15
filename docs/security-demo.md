# Demo Auto-Login Security Reference

Internal developer reference. Not intended for public distribution.

Covers the form-less public demo auto-login surface introduced in session 292 (`GET /demo/enter` on the demo server). Pre-registered here as the surface ships, the way `docs/security-forms.md` pre-registered the notification surface — the pre-GA audit consumes this register.

---

## What this surface is

A public, unauthenticated `GET /demo/enter` route. A click creates-or-reuses a single shared `Demo User` row, `Auth::login()`s it, and redirects into the admin panel. No form, no email, no token, no password prompt — the architecture (`memory/project_demo_page_architecture.md`, user-locked at the s289 close) deliberately removed the email/form gate in favour of share-and-wipe. This converts "anonymous visitor" into "authenticated admin-panel session as a shared, tightly-scoped account" in one request.

**Framing for the auditor:** the blast radius is not "an account leaks" — the account is intentionally public. The blast radius is *what that account can reach*. The load-bearing control is the role scope, not the authentication step.

---

## What is protected

### Demo-server-only guard
`DemoLoginController` calls `abort_unless(isDemoMode(), 404)` before any user is created or authenticated. `isDemoMode()` keys on `app()->environment('demo')` — the same signal `AppServiceProvider` and `GeneralSettingsPage` already gate on. On any non-demo install the route returns 404 and never touches auth. The shared `Demo User` row is likewise seeded **only** when `isDemoMode()` (`DatabaseSeeder`), so a production database carries no public auto-login account even latently. Asserted in Pest (`tests/Feature/DemoLoginTest.php`).

### Dedicated, tightly-scoped role — never `super_admin`
The shared account is bound to a purpose-built `demo` role (`PermissionSeeder`). It is granted the CMS + CRM surfaces a prospect needs to feel the product (full pages/posts/forms/collections/products/navigation, full contacts/orgs/households/memberships/notes/tags/mailing-lists, read-only finance + events, list filters, form-submission viewing). It is **explicitly never granted** (binding deny-list): user/role management, mail settings, email-template management, financial settings, routing-prefix management, theme/SCSS editing, CMS settings, API keys, and any secret / integration / instance-configuration / real-data-exfiltration surface. It is never `super_admin` (which would bypass every policy via the `Gate::before` hook). The controller calls `syncRoles(['demo'])` on every entry, so the shared row re-pins to exactly the demo role each visit and cannot accumulate privileges through tampering. The deny-list is asserted by representative-ability Pest cases.

### Per-IP rate limiting
`throttle:10,1` on the route — 10 requests per minute per IP, the established sensitive-route limit (matches the form-submission and event-registration routes). A real visitor needs one request; the limit blunts scripted session-creation floods without ever blocking a legitimate click. Deeper abuse is mitigated by the wipe cadence, not by harder gating, per the architecture. Asserted in Pest (11th request in a minute → 429).

### Shared-session model bounded by the 24h wipe
The account is shared by construction (the architecture forbids per-visitor identity — that would reintroduce the removed email/form). Cross-visitor state exposure is accepted and bounded by the 24h wipe cadence + the demo-data-only role scope, not by isolation.

---

## Known limitations and accepted risks

### Shared-session cross-visitor visibility (Low — accepted by design)
Because every visitor authenticates as the same row, one visitor's in-flight demo data (draft pages, test contacts) is visible to the next until the 24h wipe. **Disposition:** accepted by design per `[[demo-page-architecture]]` — per-visitor isolation was explicitly rejected (it reintroduces the email/form the architecture removed). Residual is bounded by demo-data-only role scope + wipe cadence. Not "won't fix" — the wipe cadence *is* the mitigation.

### Rate limiting behind a reverse proxy (Low)
`throttle:10,1` keys on `$request->ip()`. Behind a proxy (Cloudflare, nginx upstream) all requests appear to share the proxy IP and the limit becomes global. **Fix before fronting the demo with a proxy:** populate `TrustProxies`. Same caveat as documented in `docs/security-forms.md`; carried here for the same reason.

### No CAPTCHA (Low — accepted by design)
Anti-abuse is rate-limit only, no challenge. **Disposition:** explicit architecture decision (lower friction wins; mean-actor data is mitigated by the wipe, not by gating). Accepted.

### Mean-actor data inside the demo (Low — accepted, wipe-bounded)
A visitor can enter offensive or junk content into the shared demo. **Disposition:** accepted; the 24h wipe is the mitigation. Aggressive wipe was floated and the user pushed back — meaningful abuse traffic is itself the signal to revisit, and is part of the Fleet Manager abuse-alerting arc below.

### Resource-exhaustion via repeated entry from distributed IPs (Low–Medium)
The per-IP throttle is trivially beaten by distributed requests; each entry is a cheap `firstOrCreate` + login + redirect (no mail, no heavy I/O — materially cheaper than the form-notification surface). **Disposition:** known; partially mitigated by the throttle; residual accepted for the demo tier. Mitigation path = the Fleet Manager abuse-alerting arc (outbound/volume alerting on the demo instance specifically).

---

## Cross-session arc (out of scope for 292 — flagged, not resolved)

Per `[[demo-page-architecture]]`, the Fleet Manager shared-contract work is a deliberate multi-session arc and is **not** in 292: (a) demo-server identity known to Fleet Manager so the wipe/reset cron targets the right instance; (b) Fleet Manager abuse alerting on the demo instance; (c) wipe/reset coordination. Until that lands, the demo's abuse posture rests on the per-IP throttle + role scope + the (manually-run, for now) 24h wipe. The audit should treat the demo instance's abuse alerting as a known open arc, not a 292 gap.

---

## Finding register

| # | Title | Severity | Status |
|---|-------|----------|--------|
| 1 | Public unauthenticated auto-login into admin panel | High (by design) | Shipped 292 — scoped by dedicated non-`super_admin` `demo` role + demo-server-only guard; role deny-list is the load-bearing control |
| 2 | Off-demo exposure of the auto-login route | High (if present) | Closed in v1 — `abort_unless(isDemoMode())` + demo-only seeding; asserted in Pest |
| 3 | Privilege bleed via the shared account | Medium | Mitigated — `syncRoles(['demo'])` re-pins every entry; never `super_admin`; deny-list asserted |
| 4 | Shared-session cross-visitor state visibility | Low | Accepted by design — bounded by demo-data role scope + 24h wipe cadence |
| 5 | Distributed resource-exhaustion past the per-IP throttle | Low–Medium | Accepted for the demo tier; mitigation path overlaps the Fleet Manager abuse-alerting cross-session arc |
| 6 | Mean-actor / offensive data in the shared demo | Low | Accepted — 24h wipe is the mitigation; abuse volume is itself the revisit signal (FM arc) |
