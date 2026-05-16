# Public Website Build — Summary

Close-out for the Public Marketing Website track (sessions 284–293). Companion to `gap-report.md` (the per-gap detail) and the per-session logs. This file is the durable record of what shipped, what was deferred, and what needs the user before the marketing site is launch-ready.

---

## What was completed

### The five marketing pages — built inside the product's own CMS

All five are live published pages on the local instance at their final URLs, each importable from its committed JSON with zero importer warnings, each screenshot-captured in `screenshots/`.

| Page | URL | JSON | Built | Shape |
|------|-----|------|-------|-------|
| **Home** | `/` | `home.json` | 286 | 8-band marketing page (hero → Small Data → What it Does → not-a-SaaS dark band → Pricing → What it doesn't do → Try it CTA) |
| **About** | `/about` | `about.json` | 287 | hero → "Small software can do this now" + pull-quote → "How this gets built" dark band + security posture + 3 CTAs |
| **Pricing** | `/pricing` | `pricing.json` | 288 (widget) + 289 (page) | hero → `pricing_chart` comparison (3 tiers, Monthly recommended) → À la carte 2×2 → "What I won't build" dark band → final CTA |
| **Contact** | `/contact` | `contact.json` | 290 | single gradient hero with embedded `web_form` (name/email/phone/message + demo-interest) + portrait + mailto fallback |
| **Demo** | `/demo` | `demo.json` | 292 | single-band 50/50 dark/light split; dark side H1 + placeholder copy, white side one "Click Me" CTA → `/demo/enter` form-less auto-login |

The layout-spec-driven build pattern (introduced at 286, re-validated at 287/289) held across every content page: bands rendered in spec order, zero importer warnings on first import, cross-section rhythm matching the spec's prediction.

### Code lifted from the gap report (separate approved sessions, not track-internal)

The track surfaces gaps; it does not resolve them. Three were lifted by the user into their own sessions and shipped:

- **285 — CMS fixes:** layout `appearance_config` round-trip (G1), TextBlock CTAs (G2), `secondary-dark` button variant (G3 interim), typography defaults (G13/G14), Image `aspect_ratio`/`max_width`.
- **288 — `pricing_chart` widget:** new widget end-to-end, plus a generic `repeater` config field type.
- **290 (in-session, user-approved) — `text.link_color`:** widget appearance control via CSS-variable indirection (G22 resolved).
- **291 — form-submission notifications:** operator-editable `form_submission` System Email + observer + graceful mail-failure handling (G23 resolved). FM pre-Beta pseudo-versioning folded in here (the `VERSION` file + deploy-pipeline discipline).

### The page-capture harness (session 293)

- `scripts/generate-page-screenshots.js` — standalone host-run ESM Node script, mirrors `scripts/generate-thumbnails.js`'s invocation ergonomics (`--base-url` default `http://localhost`, global-Playwright `NODE_PATH` fallback). Captures the five published URLs at a 1280-wide desktop viewport, `fullPage: true`, waits for network-idle + `document.fonts.ready`, strips the dev Debugbar overlay before capture so the record is clean. Run on demand — **not** added to CI or the Pest/Playwright suite, per the brief.
- Output: `sessions/public website/screenshots/page-{home,about,pricing,contact,demo}.png`, committed so the pages can be reviewed without running the script.
- `VERSION` bumped `0.292.1 → 0.293.1` (pre-Beta pseudo-versioning discipline; deploy pipeline hard-fails a forgotten/duplicate bump).

---

## What was deferred (open gaps — user's per-gap lift decision now)

The track is closing; these are no longer track-owned. Each is the user's per-gap call: accept the degraded form, or lift a follow-on session. Full detail in `gap-report.md`.

| Gap | Surface | Status |
|-----|---------|--------|
| **G3 (long-term)** | per-variant "regular + night-mode" button colorways (interim `secondary-dark` shipped at 285) | open — interim path accepted |
| **G5** | no closing "Try the demo →" CTA below Home's "What it does" grid | open — accepted as degraded |
| **G6** | no tabbed-feature-explainer widget (brief's forecast missing widget) | open — stacked-text substitute held; never blocked a page |
| **G7** | no headline-only widget between `text_block` and `hero` | open — `hero` coverage judged good enough |
| **G8** | sample-image library has no product-screenshot category | open — still-photos stand in until real photography |
| **G11** | Hero CTA alignment locked to headline-position knob (`str_contains` bug) | open — visual workaround held |
| **G12** | logo contrast on overlap-nav heroes; Hero↔Nav cross-widget restyle scope | open — overlap-nav disabled site-wide as the convention |
| **G15 / G16** | Hero-in-cell nested `.site-container`; Hero `fullscreen` stretches a cell to 100vh | open — Text+Image cell pattern side-stepped both on every page |
| **G17** | TextBlock needs non-empty content to render cleanly as a CTA-only band | open — placeholder pattern; rarely exercised |
| **G18** | Image widget has no per-cell width/placement controls | open — full-cell-fill default; revisit with real photography |
| **G19 / G-pricing-1** | `appearance_config` has no border knob (pull-quote + pricing-card emphasis) | open — two consumers waiting; one extension lights both up |
| **G20** | global `.widget--text_block { height:100% }` collides with stacked-sibling text widgets | open — sibling-layout split pattern is the standing workaround |
| **G21** | form labels hardcode light-bg color (form-on-dark) | open — white-card workaround; proven path is the G22 mechanism generalized |
| **G-demo-1/2/3** | no large `primary` size variant; no viewport-height layout sizing; full-bleed-cell separation | open — accepted spec workarounds; none blocked the Demo page |

Resolved over the track (no longer open): **G1, G2, G9, G10, G13, G14** (285); **G22** (290); **G23** (291).

---

## What needs the user before launch (milestone-close gates — not 293 deliverables)

These are the gates between "track closed" and "marketing site is launch-ready." None are track work; they return to the Public Website Complete milestone order.

1. **Privacy Policy + Terms of Use pages** must land. Stripe Dashboard requires both URLs. Pairs with the E16 header/footer overhaul.
2. **Real photography** must replace every sample-image placeholder across all five pages (founder-as-subject staged-stock style; placeholders deliberately don't anticipate that aesthetic).
3. **Demo dark-side real copy** replaces the lorem `pending-copy` placeholders (visible in `screenshots/page-demo.png` — expected, not a defect).
4. **The real `mailto:` address** (G4) replaces `mailto:al@example.com` — one-line fix across every page that carries an Email CTA. Single open item, user supplies.
5. **The Fleet-Manager shared-contract arc** (demo-server identity, abuse alerting, wipe/reset coordination). The `sessions/fm-client-upgrade-handoff-answers.md` CRM-side answers feed this arc; it lands across its own sessions. CRM contract stays v2.3.0 — none of this is contract-touching from the CRM side.
6. **`AppVersionStampTest` carry-forward (doc-hygiene, user-owned decision):** the fast suite carries one known pre-existing red — the 291 contract-doc self-collision in `docs/fleet-manager-agent-contract.md:452` (a CHANGELOG sentence reproduces the literal phrase the invariant test forbids). No contract surface, no runtime effect; `HealthController::CONTRACT_VERSION` stays v2.3.0. The one-line fix is rewording that sentence so it stops quoting the forbidden literal. **Disposition: left flagged — not folded into 293** (the user did not direct the fold-in before close; per the base-prompt default it remains a known carry-forward here). The one-line reword stays available to fold into any later session on the user's word.

---

## Test posture

No new Pest from 293 — the harness is run-on-demand tooling, verified by "the five PNGs exist, are non-trivial, render the correct page" (agent-verified) plus the user's visual review of the committed images. Fast-suite regression target: the 292 baseline of **2446 / 1** (the single red being the pre-existing 291-doc carry-forward above), or **green** if the one-line doc fix is folded in. No positive Pest delta is expected from this session.
