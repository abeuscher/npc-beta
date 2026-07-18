# Track: Security Hardening

**Status snapshot (2026-07-17, session 372):** Track opened at the session-369 launch replan. Five sessions, S1–S5. **S1 complete (session 370)** — the perimeter security-headers layer + enforced public CSP (admin Report-Only), self-hosted Quill (2.0.3), enforced prod-hardening defaults, the Stripe live-key generator guard, and the demo-role seeding gate all shipped; a same-branch follow-on made the CSP host allow-list admin-editable (CMS Settings → Site → Allowed External Hosts). **S3 complete (session 371)** — the XSS write-path audit closed the one live bypass (event-registration rich-text custom fields imported under `withoutEvents()`) via a reusable `EventRegistration::sanitizeRichTextCustomFields()` helper, gave `SiteSetting` a save-time sanitizer backstop (was `set()`-only), neutralized CSV/XLSX formula-injection across the list-export family (leading-apostrophe escape, numbers preserved), proved member-page data widgets scope to the viewing member (admin widgets fail closed; portal widgets self-scope), and absorbed **#32c Path A** — the audit found sensitive CRM data has no path to a public page (structural exclusion), so delivery was the write-time `allowedSlots` enforcement in the page-builder add-widget API + reactive publish/collection warnings + a "Public-exposure controls" section in the permission-matrix runbook, **with no field-level sensitivity registry (owner decision — liability/maintenance burden)**; non-boundary, contract untouched at v2.6.0. **S2 complete (session 372)** — the track's **one** boundary-touching session: added an independent app-layer second lock (`VerifyFleetAgent` middleware) behind the nginx mTLS termination on all five FM `/api/*` endpoints — a per-install shared-secret `X-Fleet-Gate-Key` header (`FLEET_GATE_SECRET`), constant-time-checked, **TLS-independent** (not cert-derived) so it survives an nginx regression / misconfig / location-shadow the single per-location `if` would not; worst-first (`/api/backup/blob` + `/api/admin/recover` proven first, then all five); **additive / no-flag-day** (absent secret = gate inert = exactly v2.6.0 behaviour, same shape as `SUSPENSION_STATE` absent = none); enforced failure = app-layer JSON `401 {error: fleet_gate_unauthorized}` distinct from nginx's plain-HTML `403`/`400`. Contract **v2.6.0 → v2.7.0** (additive; shared-secret chosen over cert-fingerprint forwarding, which re-trusts the nginx-populated value the lock backstops); FM absorbs by sending the header first, then pushing the secret (no flag day). `/api/logs` raw-log PII tension resolved as a conscious acceptance (residual below). **Next in the track: S4** (launch-schedule position 11), after the Gate-2 money-path sessions (positions 4–10; C3b at session 373 is next overall). S4, S5 not started. **Open Gate-3 residuals from S1** (for the closing findings register): (a) the **admin-panel CSP ships Report-Only**, not enforced — Filament/Alpine/Livewire enforcement deferred, env-flippable via `SECURITY_CSP_ADMIN_REPORT_ONLY=false` once browser-validated; (b) external-analytics hosts (GTM/GA) are now managed via the admin allow-list / env floor rather than being blocked outright. **Open Gate-3 residuals from S3** (all consciously accepted by design): help-article content rendered unsanitized (developer-authored via HelpSync, not a user write path); page `head_snippet`/`body_snippet` raw by design; email-template scalar `{{tokens}}` substituted without escaping (traced — no contact-custom-field token path exists, HTML-block tokens `e()`-escaped, plain-text into email); WidgetPreset has no independent sanitizer (derived from already-sanitized config, re-sanitized on apply). **Open Gate-3 residual from S2** (consciously accepted by design): `/api/logs` returns raw Laravel log lines (possible PII / stack traces) — now behind **two** independent locks (mTLS + the app-layer gate), the FM operator is the node's own data controller with full node access regardless, and logging-hygiene-at-the-write-site is the real control, not lossy read-time redaction that would gut the endpoint's debugging value; redaction lands as a separate session only if a concrete leak is surfaced. Evidence base: the session-368 security surface map, folded in below as Appendix A. CRM contract at **v2.7.0** (bumped at S2, additive); **S2 was the track's one boundary-touching session** (complete). This track *is* Gate 3 of the launch plan.

---

## Charter and the bar

Real donations and real donor PII are held from day one, and the public site + demo are exposed to paid ad traffic — so security confidence is **launch-gating**, not post-release polish (this supersedes the pre-369 "security findings are not gating" posture and promotes the old post-Beta `── SECURITY AUDIT READY ──` milestone into the launch plan).

**The bar is a self-driven internal review:** enumerate every vulnerability we can name ourselves, then fix it or consciously accept it on the record. A paid external audit is unaffordable and explicitly **not** the bar; external help, if any arrives, is a favor and a bonus. Gate 3 closes when all five sessions have run and the owner has walked the resulting findings register — every item fixed or accepted, none silently open.

Standing decision rule (project memory): when security and usability directly conflict, choose security; move the friction to the UX layer, not the protection layer.

---

## The five sessions

Scopes below are canonical (release-plan entries point here). Per Rule 11, any session may split if it surfaces more than one context can hold.

### S1 — Perimeter: headers, CSP, editor self-hosting ✅ *(complete — session 370)*

Targets Appendix A risks #3 and #5, plus the CSP half of #2.

- **Security-headers layer, enforced not conventional:** HSTS, `X-Frame-Options`/`frame-ancestors`, `X-Content-Type-Options`, `Referrer-Policy`, and a Content-Security-Policy — delivered via app middleware plus `docker/nginx/prod.conf` (and `default.conf` parity where sensible). Today **no security headers exist anywhere**.
- **CSP as the XSS backstop:** the app's XSS defense is single-layer (save-time sanitization, raw render). A CSP is the second layer. Rollout strategy decided in-session (public surface vs admin panel likely diverge; report-only staging acceptable as an intermediate, enforcement is the goal).
- **Self-host the admin editor assets:** every admin page loads Quill from `cdn.jsdelivr.net` (`AdminPanelProvider` render hooks) — a CDN compromise runs script inside authenticated PII sessions, and the external origin blocks any strict CSP. Vendor the assets locally.
- **Enforced prod-hardening defaults:** `SESSION_SECURE_COOKIE` gets a safe default rather than a per-node `.env` decision; sanity-check the `APP_DEBUG`/`.env.example` posture so a bad `.env` can't leak stack traces with credentials.
- **Folded small items:** (a) the **Stripe test-mode detection guard** (the long-standing "release blocker" stub — the Random Data Generator must refuse to render, or hard-gate, when the install is configured against a live `sk_live_` Stripe key); (b) the **demo-role seeding gate** (housekeeping inbox: `PermissionSeeder` creates the `demo` role unconditionally on every node — wrap it in `isDemoMode()`, minding the demo server's own re-run path and the tests that reference the role).

### S2 — Second lock on the fleet endpoints ✅ *(complete — session 372; boundary-touching, contract v2.6.0 → v2.7.0 additive)*

Targets Appendix A risk #1 — the worst concentration.

- All five FM `/api/*` endpoints (`/health`, `/logs`, `/backup/trigger`, `/backup/blob`, `/admin/recover`) currently hang on a single nginx `if ($ssl_client_verify != "SUCCESS")` block per location; the app layer deliberately trusts the connection. Any nginx regression, misconfig, or location-shadowing collapses the only defense on the **whole donor DB** (blob) and **admin credential reset** (recover).
- Add an **app-layer second gate** behind the mTLS termination, worst-first (`/api/backup/blob`, `/api/admin/recover`). Mechanism decided in-session with FM coordination in mind (e.g. a shared-secret header FM already possesses, or client-cert fingerprint forwarding) — additive contract bump, FM-side absorption follows the standard Two-Repo Coordination Protocol.
- Resolve the `/api/logs` raw-log-tail tension: it ships raw application log lines (possible PII / stack traces) against the counts-only privacy boundary the fleet design holds elsewhere.

### S3 — XSS containment + exposure audit ✅ *(complete — session 371)*

Targets Appendix A risk #2 (the sanitizer-gap half) and absorbs release-plan item **#32c** (accidental public exposure).

- **Write-path audit:** verify every rich-text write path funnels through `HtmlSanitizer` — model mutators are the convention, so the audit hunts paths that *skip* mutators (importer especially, plus seeders, API endpoints, bulk operations). A sanitizer bypass = stored XSS in an authenticated admin session = full PII/money access.
- **Member-page widget scoping:** the open check from the surface map — `type=member` CMS pages render widgets that may pull CRM data behind only "logged-in + verified"; verify data widgets on member pages scope to the viewing member's own contact.
- **CSV formula injection** on every export surface (the 261/262 export family) — cells beginning `=`, `+`, `-`, `@` must not execute on re-open in Excel.
- **#32c absorbed, without the C3a prereq:** sensitive fields (home addresses, donor amounts, internal notes) either cannot be flipped public or hit a warning gate; per-field protection documented in the permission matrix doc's data-classification section; public-content indicator on leak-capable surfaces. The accountability/audit-trail feature half (C3a) stays deferred — protection doesn't need the paper trail.

### S4 — Adversarial pass 1: public write surface + portal scoping *(absorbs F2)*

Targets Appendix A risk #4; attacker-goal-driven rather than checklist-driven.

- **Probe the public anonymous write surface** — forms, portal signup, all checkout paths create `Contact` rows unauthenticated. Existing defenses (throttles, honeypots, time-trap, `PiiScanner`, no-enumeration signup) get adversarial re-verification, not re-implementation. The **form-spam / CAPTCHA** inbox item lands here.
- **The checkout `Referer`-derived redirect** low-risk note gets a look (attacker-influenced post-payment redirect).
- **F2 absorbed:** the on-demand Playwright portal-scoping suite — every portal route walked from two contact fixtures; Bob can never reach Alice's data even via URL fishing; reset/verification/token flows; artifact `docs/runbooks/portal-security-audit.md`. The portal's relationship-derived scoping looked clean in the map; this is the proof.

### S5 — Adversarial pass 2: permission matrix + demo server *(absorbs F3)*

- **F3 absorbed:** the on-demand Playwright permission-matrix suite — every role × resource × action cell asserted at both UI and controller layers against `docs/runbooks/permission-matrix.md`; matrix wins on contradiction, fixes land in-session (audit-style per Rule 2).
- **Demo-server hardening:** the demo invites anonymous strangers in by design — re-verify the `demo` role lockdown (Pest guard exists from 321/329), upload blocking, rate-limiting on `/demo/enter`, blast radius of a hostile demo session, and the daily-restore recovery guarantee. Gate-3 work scheduled here, but conceptually the demo is a conversion surface — treat findings as launch-gating.
- **Panel-entry gate:** consider tightening `User::canAccessPanel()` beyond `is_active` (Appendix A risk #5's second half); per-resource spatie checks are the real authorization layer, so this is defense-in-depth, decided on its merits in-session.
- **Track close-out:** assemble the findings register across S1–S5 for the owner's Gate-3 walkthrough — every enumerated item fixed or consciously accepted.

---

## Sequencing within the launch plan

S1 → S3 run **first** in the launch schedule, before the Gate-2 rehearsals — rehearsals must prove flows against the final hardened surface (a CSP or sanitizer change after a rehearsal invalidates it; same logic as the "integration retest runs last" rule, applied at the front). S2 runs third — internal-facing, grouped with its track, not ordering-critical. S4/S5 run after the money-path rehearsals (positions 11–12), probing the surface as it will ship.

---

## Absorptions and folds (so nothing is double-scheduled)

| Absorbed item | Lands in |
|---|---|
| #32c Accidental public exposure (protection audit, Path A) | S3 |
| F2 On-demand E2E — portal contact-scoping | S4 |
| F3 On-demand E2E — permission/role-gate matrix | S5 |
| Stripe test-mode detection stub (outlines "release blocker") | S1 |
| Demo-role seeding inbox item (`[0.367.02 · 2026-07-09]`) | S1 |
| Form-spam / CAPTCHA inbox item | S4 |
| `/api/logs` PII tension (surface-map low-risk note) | S2 |

---

## Appendix A — Evidence: security attack-surface map *(captured at session 368; verified against code then — re-verify only what a session directly depends on)*

Context that set the bar: real donations + real donor PII from day one; public site + demo exposed to search and paid ad traffic; single-tenant per node (no cross-tenant blast radius).

Architectural facts that shape everything:

- **Two auth guards** (`config/auth.php`): `web` (staff → Filament admin, model `User`) and `portal` (constituents, model `PortalAccount`). Separate providers and password-reset brokers.
- **XSS defense is save-time sanitization, not output escaping** — content renders raw (`{!! !!}` / Vue `v-html`) and is cleaned on write by `app/Support/HtmlSanitizer.php`.
- **CSRF** on everywhere except `/webhooks/*` (`bootstrap/app.php`).
- **No security-headers layer anywhere** — neither app middleware nor `docker/nginx/prod.conf`.

### Top-5 risk concentration (this track's spine)

1. **Fleet `/api/backup/blob` + `/api/admin/recover` — mTLS-only, no app-layer auth.** The whole donor DB (blob) and admin credential reset (recover) each hang on a single nginx `if ($ssl_client_verify != "SUCCESS")` block (`docker/nginx/prod.conf`); the app layer deliberately trusts the connection. Any nginx regression/misconfig/shadowing collapses the only defense. → **S2.**
2. **Single-layer stored-XSS posture.** One bespoke (good) sanitizer + 46 raw `{!! !!}` Blade sites + `v-html` in `PreviewCanvas.vue`/`PreviewRegion.vue`/`NoticeField.vue` + **no CSP**. A sanitizer gap or any write path that skips the model mutator = stored XSS in an authenticated admin session → session theft → full PII/money access. → **S1 (CSP backstop) + S3 (write-path audit).**
3. **Missing security headers + config-dependent prod hardening.** No HSTS / X-Frame-Options / X-Content-Type-Options / Referrer-Policy / CSP. `SESSION_SECURE_COOKIE` has no default; `APP_DEBUG` correctness is a per-node `.env` decision (a bad `.env` leaks stack traces with DB creds). → **S1.**
4. **Public anonymous write surface into the donor DB.** Forms, portal signup, and all checkout paths create `Contact` rows unauthenticated. Existing defenses are decent (throttles 5–20/min, honeypots, time-trap, `PiiScanner`, no-enumeration signup) — this is the surface to re-check as forms grow, not a current hole. → **S4.**
5. **Admin supply chain + panel-entry gate.** Every admin page loads Quill from `cdn.jsdelivr.net` (`AdminPanelProvider.php` render hooks) — a CDN compromise runs script in authenticated PII sessions, and it blocks any strict CSP. `User::canAccessPanel()` gates on `is_active` alone (authorization is per-resource spatie checks). → **S1 (self-host) + S5 (panel gate).**

### Surface-by-surface map

**1. Unauthenticated public surface (`routes/web.php`)**

| Endpoint | Guard / protection |
|---|---|
| `GET /`, `/{slug}` catch-all (`PageController`) | public; `type=member` pages gated portal-auth+verified; `_`-prefixed slugs blocked |
| `POST /forms/{handle}` (`FormSubmissionController`) | throttle 10/min, honeypot, `PiiScanner`, portal-collision check; creates/updates `Contact` |
| `POST /donate/checkout` | throttle 20/min; amount client-supplied, bounded 1–10000 by design |
| `POST /products/checkout`, `/products/waitlist` | throttle 20/10; amount server-side ✓ |
| `POST /membership/checkout`, `/signup` | throttle 10/min, honeypot + time-trap, min:12 pw, no email enumeration; amount server-side ✓ |
| `POST /events/{slug}/register` | throttle 10/min |
| `GET /api/events.json` | public; `strip_tags` on description ✓ |
| `GET /widget-thumbnails/…` | traversal-safe (regex-locked filename, registry-resolved folder) ✓ |
| `GET /demo/enter` | `abort_unless(isDemoMode())`; inert on real nodes ✓ |

Low-risk note: checkout success/cancel URLs built from the `Referer` header → attacker-influenced post-payment redirect. → **S4.**

**2. Rich text / page builder.** `app/Support/HtmlSanitizer.php` — DOM-based allow-list (strips script/style/iframe/object/embed, all `on*`, scheme allow-lists, strict target/rel) — genuinely solid. Applied at save via model mutators: `Note`, `Event`, `EmailTemplate`, `SiteSetting`, `CollectionItem`, `PageWidget` (schema-walked), custom fields. SVG uploads sanitized by `app/Services/Media/MediaSvgSanitizer.php`. Rendered raw at 46 Blade sites + 3 Vue `v-html` sites. Low-risk note: `<img style>` allowed → CSS-injection flavor, no script exec.

**3. File upload + media.** Admin-auth-gated routes only (`InlineImageUploadController`, `PageBuilderApiController::uploadImage`, Filament `FileUpload`). Validation `image`+mimes+10 MB; SVG sanitized at add. `BlockDemoUploads` blocks the demo role. Storage: `public` disk at `/storage`, S3/Spaces in prod. Note: nginx `client_max_body_size 768M` (admin-only large-body DoS surface).

**4. Member portal.** Scoping is **relationship-derived, not request-param** — every read/write via `Auth::guard('portal')->user()->contact`; no contact query keyed on a request-supplied ID found. Household-head address cascade scoped via `household_id` relationship. Email change signed + 60-min TTL + ownership recheck; password change verifies current + `logoutOtherDevices`. **Open check → S3:** `type=member` CMS pages render widgets that may pull CRM data behind only "logged-in + verified" — verify data widgets on member pages scope to the viewer's contact.

**5. Authentication + admin.** Filament path `env('ADMIN_PATH','admin')`. Mandatory TOTP 2FA (`EnsureTwoFactorAuthenticated`; demo + opt-out testing bypasses only). Invite-only admin creation. Separate reset brokers, 60-min expiry, throttled. Session: DB driver, `http_only`, `same_site=lax`, `encrypt=false`, **`secure` has no default** (medium risk → S1). Panel entry = `is_active` only (low risk; resources individually permission-checked, 55 `can(` sites + policies → S5).

**6. Stripe / payments — least-risky money surface.** Hosted Checkout everywhere; card data never touches app or DB. Webhook signature **verified** (`Webhook::constructEvent`, 400 on failure), idempotent by `stripe_id`. Amount trust boundary correct (only donations take a client amount, bounded, by design).

**7. Fleet / API surface.** `GET /health`, `GET /logs`, `POST /backup/trigger`, `GET /backup/blob`, `POST /admin/recover` — throttled; auth = nginx mTLS per-location `if` only; **no app-layer re-verification** (explicit trust-the-connection model). See top-5 #1 → **S2.** Low-risk note: `/api/logs` ships raw log tail (possible PII/stack traces) — tension with the counts-only privacy boundary → **S2.**

**8. Importer.** Admin-panel-only (Filament pages + `app/Importers/`). Rich-text custom fields route through the sanitizer via mutators. **Not deep-audited:** CSV formula-injection on re-export → **S3.**

**9. Config / infra.** CSRF on (webhooks exempt). Rate limits on all public POSTs + fleet endpoints. `APP_DEBUG` defaults false / `APP_ENV` defaults production ✓ (but `.env.example` ships debug=true — per-node `.env` is the control → S1). `PublicDevAuth` optional HTTP-Basic staging gate. MailChimp webhook auth = shared `?secret=` query param, not a signature (low risk; only flips opt-out).

---

## Appendix B — Genuinely well-built (do not re-audit; low priority for the pass)

Stripe integration (hosted checkout, verified webhook, correct amount boundary) · portal record scoping (no IDOR found — S4 proves it with E2E rather than re-auditing) · public file serving (traversal-safe) · SVG sanitization · mandatory admin 2FA · invite-only admin creation · public-form spam defenses (S4 adversarially re-verifies rather than re-implements).
