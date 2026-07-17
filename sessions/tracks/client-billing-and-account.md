# Track: Client Billing & Account

How client organizations pay for the product, see their account, and get suspended or offboarded when they don't — designed end to end and decomposed into buildable sessions. Fleet Manager (FM — the operator's separate control-panel application that provisions and monitors every client server) owns billing centrally; Stripe holds the money and the risk; each client's CRM node gets a read-only "My Account" window. This document is the planning output of session A012 (agentic, planning-only, 2026-07-08). **Nothing here is built yet** — the owner reviews this doc, answers the open questions below, and then the build sessions get drafted.

The architecture direction was settled with the owner in conversation on 2026-07-08 and is treated as locked throughout (the "Settled direction" section of `sessions/A012. Client Billing & Account — Planning.md` is the canonical list). This doc designs *within* those decisions.

---

## Open questions for the owner

Every judgment call in the design that needs your ratification, with my recommendation. Everything else in the doc follows the locked decisions or established project grain.

1. **Suspension timing.** Working default: a failed payment runs through Stripe's automatic retry window (about 2–3 weeks, configured in the Stripe dashboard), then a **14-day grace period** tracked by Fleet Manager, then the node's admin panel locks automatically (with an email alert to you); the public site and donation processing stay up; full site shutoff is always a manual action you take in Fleet Manager. I also recommend **automatic unlock** the moment a payment recovers — it's a reversible flip and the client just paid. Confirm the 14-day grace length, the auto-lock, and the auto-unlock.
2. **Trial policy for prospect nodes.** Recommendation: a prospect's node carries a **30-day trial clock** tracked in Fleet Manager, extendable per client with one click; you get an alert 3 days before expiry, and at expiry the node auto-locks with reason "trial expired" (same reversible mechanism as delinquency — converting or extending unlocks it). Confirm the 30 days and the auto-lock-at-expiry.
3. **Where hourly project work gets logged.** Two shapes designed below (§ Design decision 6). Recommendation: **start Stripe-native** — you add hours as invoice items directly in the Stripe dashboard, they land on the client's next monthly invoice, and Fleet Manager just displays them read-only. An FM-side hours-logging surface is a later session if volume ever justifies it. Confirm or pick the FM-side shape.
4. **How clients pay.** Recommendation: default to **card on file with automatic charging** — at conversion, Fleet Manager generates a Stripe-hosted checkout link, you send it to the billing contact, they enter a card once, and Stripe charges and retries automatically from then on. A per-client alternative ("email me an invoice" mode, paid on Stripe's hosted invoice page) exists for orgs that can't do auto-charge — note its dunning is reminder-emails rather than card retries. Confirm the default.
5. **Who edits the billing contact.** Recommendation: the billing contact email lives on the Stripe customer record as the single source of truth; the client updates it themselves through Stripe's hosted billing portal, or asks you and you change it in Fleet Manager. The node's account page displays it read-only. There is deliberately **no node-side edit path** — that would require a node-to-FM write channel that doesn't exist and shouldn't (see § Design decision 3). Confirm this reading of "the account page's editable parts" — the page's edit affordances are hand-offs to the Stripe portal, not local forms.
6. **What stays up when admin locks.** Recommendation: during an admin lock, the **member portal stays up** along with the public site and donations — the lock punishes the org's back office, never its constituents. And after a cancellation, the public site stays up for a **30-day wind-down** before you manually shut it off. Confirm both.
7. **Who sees the account page.** Recommendation: seed a single `manage_account` permission granted to **no shipped role by default** — meaning only super-admins (and anyone a client deliberately grants it to via a custom role) can see the account page. This is the deliberate version of a pattern the permission audit once flagged as an accident; the track doc and permission matrix will document it as intentional. Confirm.
8. **Slotting.** Recommendation: the whole track runs **immediately after Beta 1, right after multi-node operational readiness** (release-plan item A3 — the "four production nodes" session) and the backup-drill session (A4), and **before the first real prospect is promised a conversion** — conversion machinery existing is the precondition for saying yes to a first customer. If a prospect materializes sooner, the two foundation sessions (the contract bump and Fleet Manager's Stripe sync) can run early as agentic sessions without disturbing the numbered stream. Decide the slot.

---

## Status snapshot

**Last update:** 2026-07-17 (session 368 close — **CB3 withdrawn** and § Design decision 4 corrected; no code changed, contract stays v2.6.0). **The CRM lane of this track is complete at CB1 + CB2** — session detail compressed into the Phase Retrospectives below.

**Active:** **nothing CRM-side — the track's CRM lane is closed.** FM-side: **FM-B1** (Stripe sync) can start now; **FM-B2** (push + verify) consumes the shipped v2.6.0 contract and is what **makes CB2's Account page visible on real nodes** — until FM pushes a document, the page is inert and hidden everywhere, by design. **FM-B4 must be re-read before it is built** — its scope shrank materially at 368 (Appendix B). *(Post-368 context: the launch replan makes FM's billing lane non-launch-gating — a first client can be billed by hand in Stripe; see the session-369 plan.)*

**Cross-repo coordination state:** the CRM↔FM contract (`docs/fleet-manager-agent-contract.md`) is now at **v2.6.0** — bumped additively at session 366 (the billing-state document, the `SUSPENSION_STATE` flag semantics, and the `suspension` health subcheck are all authored there). **FM-side absorption is pending at FM-B2** (refresh the cached contract copy, then write the document + push the flag). The § Design decision 7 sketch is now realized as contract text.

**Slotting:** resolved — CB1–CB3 folded into `sessions/release-plan.md` under the new `first-customer` gate at the 366 close; the owner sequenced CB1 (366) ahead of the rest of Phase A (A3 multi-node / A4 drill deferred). CB3 was withdrawn at 368, leaving CB1 + CB2 as the whole CRM lane.

**Blocked on:** nothing CRM-side — the lane is closed. Open questions 1 + 6 answered at the 366 gate (14-day grace framing; member portal stays up under admin lock); **questions 5 + 7 answered at the 367 gate** — the account page's editable parts are all read-only hand-offs to the Stripe-hosted portal (no local forms, no node→FM write path), and `manage_account` is granted to **no shipped role** (super-admin-only by default, documented as intentional). Questions **2 / 3 / 4 / 8** (trial policy, hourly-work logging shape, payment default, overall slotting) remain owner-facing and gate **FM-side** work, not CRM.

---

## Phase Retrospectives

### CRM lane (node half) — closed at session 368

**Sessions:** 366 (CB1), 367 (CB2), 368 (CB3 withdrawn).

**Outcomes:** CB1 (366) shipped the additive contract bump v2.5.0 → v2.6.0 — the billing-state document schema, the `SUSPENSION_STATE` enforcement flag with its one middleware (`admin_locked` → 403 admin lock, public site / donations / member portal stay up; `site_off` → 503 public maintenance, FM `/api/*` up; absent = none, unrecognized fails safe), the display-only reader stack (`BillingStateReader` / `BillingState` / `SuspensionState`), and the never-red `suspension` health subcheck. CB2 (367) shipped the read-only Account page (`App\Filament\Pages\Settings\AccountPage`, renders exclusively from the pushed document, self-hides without one), the two `manage_account`-gated banners, the `manage_account` permission (no shipped role — super-admin-only, documented intentional), the mechanical two-Stripes convention-drift guard, and the help doc. CB3 (368) was withdrawn on a false premise — a prospect's node is an ordinary production node, never a demo node — and the withdrawal corrected § Design decision 4 (the § Vocabulary table), shrank the conversion sequence 7 → 4 steps, and dissolved the FM-side `is_demo` relaxation. Fast Pest across the lane: 3025 → 3045 → 3045, all green. No vendor-Stripe anything CRM-side, enforced by test.

**Carry-forwards:** (1) row-deleting any `User` who authored a page/event violates `ON DELETE RESTRICT` on `pages.author_id` / `events.author_id` — verified empirically at 368; nothing hits it today, but future user-deletion work must reassign authorship first (`Page` soft-deletes; trashed rows still hold the constraint). (2) The `demo` **role** is seeded on every node while the demo **account** is correctly `isDemoMode()`-gated → housekeeping inbox at the 368 close. (3) FM-B4's scope shrank; the contract doc carries the warning for the FM repo.

---

## The problem this track closes

The product has no commercial layer:

- A client organization has **no "My Account" surface** — nowhere to see what plan they're on, whether their payment went through, or who their billing contact is.
- There is **no mechanism to convert a prospect's trial node** into a paying client, and nothing that happens at trial end except a human remembering.
- The operator has **no visibility into delinquency** — Fleet Manager knows a node's disk usage but not whether its invoice bounced.
- The owner's **hourly project work has no billing home** — it should land on the same invoice as the subscription.
- **Suspension doesn't exist** — the only lever today is destroying a node, which is unacceptable (a nonprofit's donation page must never go dark over the owner's invoice, and nothing is ever auto-deleted).

---

## Design decisions

### 1. Billing architecture — Fleet Manager + Stripe, polling, no inbound surface

**Stripe holds everything money-shaped.** One vendor Stripe account. One Stripe Customer per client organization, keyed to Fleet Manager's existing per-client record. Subscriptions, per-client prices, coupons for discounts, card storage, automatic retries (Stripe Smart Retries / dunning), receipts, and the hosted billing portal are all Stripe-native — **no bespoke pricing engine anywhere**. Standard tiers exist as ordinary Stripe Prices; a client with negotiated pricing gets a dedicated Price object; discounts are Coupons. Fleet Manager stores the resulting IDs, never the logic.

**Fleet Manager is the operator's window and the pusher of state.** FM runs on the owner's local machine, outbound-only, deliberately unreachable from the internet — so FM learns of payment events by **polling Stripe's API**, not webhooks. Billing polling joins FM's existing scheduler at a **daily cadence** (billing truth fresh-to-the-day is sufficient for delinquency; the per-minute health poll is not the right bus for this), plus an on-demand "refresh now" and an immediate re-sync after any operator billing action. *If FM later moves to a hosted box, the poll swaps to Stripe webhooks with no other design change — the sync job's write path is identical either way.*

**The credential is a restricted Stripe API key in FM's existing vault.** Scoped to the customer / subscription / price / coupon / invoice / checkout-session families only — no charges, no refunds, no payouts, no account or Connect scopes. It lives under FM's established off-filesystem-key vault encryption exactly like the per-node secrets FM already keeps (mTLS keys, node APP_KEYs, DB passwords). The full-access key never leaves the Stripe dashboard. **Card data never touches owner infrastructure** — cards are entered on Stripe-hosted pages only.

**What FM persists locally vs reads live:** FM persists a per-client billing snapshot (Stripe customer/subscription/price IDs, collection mode, subscription status, next-invoice date and amount, upcoming line items, billing contact email, trial clock, grace clock, suspension state + reason, last-synced-at) refreshed by the daily sync. Live API reads happen only inside operator actions (attach, convert, refresh). The snapshot — not the Stripe API — is what FM's UI renders and what gets pushed to nodes, so a Stripe outage degrades freshness, never availability.

**FM operator UI follows the existing Filament grain:** the per-client view gains a billing panel + header actions (attach subscription, convert demo, suspend / unsuspend, refresh billing); the fleet-wide list gains a billing-status column next to the existing version / demo / public-site columns; a delinquency filter surfaces every past-due or suspended client in one view. Non-commercial nodes (the marketing site, the test instance, the shared demo) carry billing status "internal" and are excluded from delinquency surfaces.

**Every billing action joins FM's audit ledger** with the same discipline as provisioning and upgrades: customer attached, subscription created, trial extended, suspension pushed, unlock pushed, conversion executed, cancellation recorded. Payment-event *observations* (dunning started, dunning exhausted) are recorded too, so the ledger tells the whole story of a delinquency without opening Stripe.

**Email alerts join FM's existing alerting** (one email per state transition, no re-sends mid-incident): first payment failure, dunning exhausted / grace started, three days before grace ends, admin lock pushed, payment recovered / unlocked, trial expiring in three days, trial expired.

### 2. The node's "My Account" page — a window, not an engine

A new Filament page, **Settings group → "Account"**, on every client node. It renders exclusively from a **billing-state document that Fleet Manager pushes to the node** (§ Design decision 7) — the node never calls the vendor Stripe API and holds zero vendor-Stripe credentials. If the document has never been pushed (internal nodes, fresh installs), the page hides itself from navigation.

**What it shows:** plan name and price, subscription status as a plain-English badge ("Active", "Payment problem — please update your card", "Trial — N days left"), next invoice date and amount with its line items (subscription + any project-work hours), the billing contact email (read-only), and a **"Manage billing" button** that links to Stripe's hosted customer portal. The portal link is Stripe's no-code portal login page (the client enters the billing email, Stripe sends a magic sign-in link) — so the node needs no API call and no secret to hand the client full self-service: card updates, receipt history, billing-email changes.

**Staleness is honest and explicit.** The page footer reads "Billing information as of {relative time}" from the pushed document's timestamp, with a note that payments made just now can take up to a day to appear. Fresh-to-the-day is the designed contract, so the page says so.

**Grace and lock warnings surface early.** When the pushed state is past-due or in-grace, the account page shows a prominent banner with the date the admin panel will lock and the portal link to fix it — and a slimmer version of that banner appears across the whole admin panel, so the person who can fix it finds out before the lock, not at it.

**Permission:** a single new `manage_account` ability gates the page, seeded through the existing permission seeder exactly like `manage_cms_settings` (a `canAccess()` override on the page; no new role system, no migration — permissions are seeded rows here). Per open question 7, it is deliberately granted to no shipped role, making it super-admin-only unless a client grants it explicitly. The permission matrix runbook and its probe test get the matching rows.

### 3. The suspension lifecycle — an explicit state machine

Suspension state lives in Fleet Manager (derived from Stripe by the daily sync plus FM's own clocks) and is **enforced on the node by a single pushed flag**. Nothing in this lifecycle deletes anything, ever; every transition is reversible except the ones a human performs deliberately.

**States** (per client, in FM):

| State | Meaning | Node effect |
|---|---|---|
| `internal` | Not a commercial node (marketing site, test box, shared demo) | none |
| `trialing` | A prospect's node inside its trial clock | none |
| `active` | Paying, current | none |
| `past_due` | A payment failed; Stripe's retry/dunning window is running (~2–3 weeks) | none — banners only |
| `grace` | Dunning exhausted; FM's grace clock (default 14 days) is running | none — stronger banners |
| `admin_locked` | Grace expired (or trial expired, or operator choice) | **admin panel locked; public site, donations, member portal stay up** |
| `site_off` | Manual operator decision only, never automatic | public site replaced by a maintenance notice; FM's monitoring endpoints stay up |
| `canceled` | Subscription ended (offboarding) | admin-locked at period end; public site up through the wind-down window |

**Reason codes** travel with every suspension: `delinquent`, `trial_expired`, `canceled`, `manual`. Same mechanism, different words on the node's lock screen.

**Transitions:** `active → past_due` when the sync sees Stripe retrying; `past_due → active` when payment recovers mid-dunning (banners clear on next push). `past_due → grace` when Stripe's dunning gives up (subscription goes unpaid) — FM stamps the grace deadline and alerts the operator. `grace → admin_locked` automatically at the deadline, with an alert (per open question 1). `admin_locked → active` **automatically when the sync sees payment recovered** — the client can self-cure through the portal link on the lock screen without waiting for the operator. `trialing → admin_locked (trial_expired)` at the trial deadline; converting or extending flips it back. `→ site_off` and `site_off →` anything are **manual operator actions in FM only**. Un-suspension is always just another state push.

**What enforcement looks like on the node — env-derived flag, code-level hard gate**, deliberately the same grain as demo mode (`APP_ENV=demo` + `isDemoMode()` hard gates): FM pushes a single `SUSPENSION_STATE` key (`none` / `admin_locked` / `site_off`) through its existing on-demand config-push channel (the machinery that already sets a single `.env` key over SSH and recreates containers — shipped FM-side for the public-website flag). Node-side, one middleware reads it (absent key = `none`, so every existing install is unaffected — additive by construction):

- `admin_locked`: every admin-panel route (and the admin's supporting API routes, which live inside the same panel middleware) renders a suspension notice instead — reason-appropriate copy, the billing portal link, and the billing contact on file, so the lock screen itself is the path to self-cure. Login to the admin is blocked behind the same notice. **Public pages, donation/event/membership checkout (the client org's own Stripe), and the member portal are untouched.** Backups, the scheduler, and all five FM contract endpoints keep running — a suspended node is still monitored, still backed up, still recoverable.
- `site_off`: all public routes render a static maintenance notice (503); FM's `/api/*` endpoints stay up. This is the deliberate nuclear option and only a human pushes it.

**Enforcement rides the env flag; display rides the pushed document.** If the billing-state document is missing but the flag says locked, the node still locks (generic copy). That split keeps the security-relevant gate on the same hard, config-derived footing as demo mode, while the human-facing detail stays in data.

### 4. Trial → paying conversion — attaching billing to an ordinary node

> **Corrected at session 368.** An earlier draft of this section described a prospect's server as a "personalized demo" — a *demo-profile* node (`APP_ENV=demo`) that conversion would flip to production, then sweep clean with a node-side cleanup command. **That was wrong**, and it conflated two unrelated things. See § Vocabulary below. The corrected design is simpler: a prospect's node is an ordinary production node, so conversion has nothing to flip and nothing to clean up. The FM-side conversion sequence shrinks from seven steps to four, and the CRM-side cleanup command (entry CB3) is **withdrawn — there is no work in it.**

#### Vocabulary — "demo" means exactly one thing

**Every node in the field is a normal, functional, production server.** The system carves out exactly three exceptions, and none of them is a client:

| Exception | What it is | How the code knows |
|---|---|---|
| **The demo server** | The single shared public sandbox anyone can walk into. Rebuilt nightly from a curated baseline. | `APP_ENV=demo` → `isDemoMode()` |
| **The public-website server** | The marketing site. | `config('site.public_website')` → `isPublicWebsite()` |
| **The deploy server** | Part of the dev process; no real data. | *(incidental — no flag)* |

`isDemoMode()` is the **only** demo switch in the CRM, and it governs five behaviours, all of which exist for the shared public sandbox and for nothing else: the `/demo/enter` auto-login (which creates the shared `demo@demo.local` account), the two-factor exemption, outbound mail forced to the `log` driver, three frozen URL-prefix settings, and the "Re-enter the demo" button on the login form. The blanket page lock is not a demo-mode property at all — it is something `demo:reset` and `demo:restore` *do*, and both refuse to run outside demo mode.

**A prospect's node is none of that.** It is provisioned exactly like any other client node, `APP_ENV=production`, with real email, real 2FA, an unlocked CMS, and no auto-login account. It is a "trial node" only in the billing sense: FM holds a trial clock for it, and its billing state is `trialing`. Nothing on the node itself knows or cares.

#### Conversion is one operator-driven FM action

Because the node is already a production node, conversion is purely a billing act. Four steps:

1. Operator confirms plan, price, any coupon, and the billing contact email in FM.
2. FM creates the Stripe customer + subscription; for card-on-file mode it generates the Stripe-hosted checkout link for the operator to send (the subscription activates when the card lands — FM's sync observes it; nothing blocks on it server-side).
3. FM pushes the initial billing-state document with state `active`; the node's Account page comes alive.
4. The whole sequence lands in the audit ledger as a `conversion` event chain.

**Nothing is pushed to the node but the document.** No `APP_ENV` change, no container recreation, no cron removal, no SSH command, no cleanup. The node is not touched at conversion beyond the one file FM already knows how to write.

**Trial expiry without conversion** takes the other branch, and it is the mechanism CB1 already shipped: the trial clock fires the suspension flag with reason `trial_expired` — the prospect's admin panel locks, their public site and donations stay up, nothing is deleted, and converting or extending un-suspends it. A cold prospect's node is eventually destroyed manually, never automatically.

**The `is_demo` wrinkle dissolves too.** FM's client registry marks `is_demo` as write-once; an earlier draft called conversion the legitimate exception that forces a relaxation. It isn't — a prospect's node was never `is_demo` in the first place, so conversion never writes that field. FM's `is_demo` keeps its write-once guarantee untouched, and it means what it says: *this is the shared demo server.*

### 5. Hourly project work — same customer, one invoice

Both shapes put hours on the **same Stripe customer** as ordinary invoice items so subscription + hours arrive as one invoice; they differ only in where hours are *entered*.

- **Shape A — hours logged in FM:** a small per-client hours log (date, description, hours, rate) with a "push to Stripe" action creating the invoice items. FM becomes the system of record for work performed; the audit ledger sees every push; the account page can show hours detail even before invoicing. Cost: a new FM surface (table + UI + push + tests) — roughly a session of work — and a second place where billing data is authored.
- **Shape B — hours entered in Stripe directly:** the operator adds invoice items in the Stripe dashboard (which the account owner uses anyway); FM's daily sync picks the pending items up as upcoming-invoice lines and displays them read-only; they flow to the node inside the billing-state document like any other line item.

**Recommendation: Shape B now.** It is zero build (the upcoming-invoice lines are already in the sync's read set for the account page), keeps a single authoring surface for money, and matches the track's "Stripe is the engine, FM is the window" posture. Shape A remains the named upgrade path if hours volume or client-visibility needs grow — nothing in Shape B's data flow has to change, only where entry happens.

Either way the client sees the line items on the node's account page under "Next invoice," and on Stripe's receipt/invoice itself.

### 6. Two Stripes, strictly separate — by construction, plus a guard

The app's existing Stripe integration is the **client organization's own** Stripe account, for **their donors**: donation checkout, event tickets, product purchases, memberships. Concretely (mapped this session): credentials live in three encrypted CMS settings (`stripe_publishable_key`, `stripe_secret_key`, `stripe_webhook_secret`) injected at boot into the `services.stripe.*` config namespace; a single shared checkout service (`app/Services/StripeCheckoutService.php`) builds every hosted-checkout session; one webhook route (`/webhooks/stripe`) receives their events; the Finance settings page (gated `manage_financial_settings`) manages it all.

**Vendor billing shares none of that — by construction it cannot:** the vendor Stripe account is touched **only by Fleet Manager, in the FM repo**. The CRM node holds no vendor credential, no vendor config key, no vendor SDK call, and receives no vendor webhook. The two integrations live in different codebases on different machines. The full separation statement:

| | Client org's donation Stripe | Vendor billing Stripe |
|---|---|---|
| Whose account | The nonprofit's | The owner's |
| Code lives in | CRM repo (`StripeCheckoutService`, checkout controllers, `StripeWebhookController`) | FM repo only (new `StripeBillingClient` service) |
| Credentials | Encrypted CMS SiteSettings on each node | Restricted key in FM's vault |
| Config namespace | `services.stripe.*` (CRM) | FM-side `services.stripe_billing.*` — the name is deliberately disjoint |
| Events | Webhook to the node (`/webhooks/stripe`) | FM polls; no webhook, no inbound anything |
| What the node knows | Everything (it runs the donor checkout) | A pushed JSON document + one portal URL string |

**The guard against future co-mingling** is mechanical, CRM-side, added in the account-page build session as new cases in the existing convention-drift test (`tests/Feature/ConventionDriftTest.php`, the standing conventions guard from the last code-review cycle):

1. No CRM code outside the mapped donation surface may reference a vendor-billing identifier (`stripe_billing`, `vendor_stripe`, `STRIPE_BILLING*` — the FM-side names are reserved and banned CRM-side).
2. The CRM's billing/account code (the billing-state reader service and the Account page) may not reference `services.stripe.*`, `StripeClient`, or any Stripe SDK class. The account page renders from the pushed document alone — the word "Stripe" appears in its code only as the label on the portal link.

An FM-side mirror rule (FM never gains a donation-Stripe key) goes in the FM handoff.

*(Incidental finding from the mapping, parked for housekeeping, no code changed this session: `DashboardIntegrationStatusWidget` reads `config('services.stripe.key')` — a key nothing ever sets; the donation surface's real key is `services.stripe.secret`, so that widget's Stripe status is silently always-unconfigured.)*

### 7. The contract-surface sketch — what a future v2.6.0 adds (sketched, not authored)

Billing state crossing the FM↔node boundary is contract surface. The first CRM-side build session authors this as an **additive bump of the CRM↔FM contract, v2.5.0 → v2.6.0**, with CHANGELOG and Cross-Repo block updates per the Two-Repo Coordination Protocol. The shape, so both repos can see what's coming:

1. **The billing-state document** — a JSON file FM pushes over its existing SSH provisioning channel (the same channel that writes `.env` and pushes the demo baseline blob; *not* a new HTTP endpoint — the node's five mTLS endpoints gain no sibling and FM gains no inbound surface). Path: under the node's fleet-metadata directory (`storage/app/private/fleet/billing-state.json`) — deliberately the directory that is **excluded from backups**, same rationale as the backup success-record file: per-node metadata must never travel inside a blob and land on another node via restore (and the demo reset, which restores DB + public media only, never touches it). Written atomically (temp + rename). Sketched fields: a schema version, `as_of` timestamp, plan (name / amount / currency / interval), status, next invoice (date / amount / line items), billing contact email, the portal login URL, suspension (state / reason / since / grace-ends), trial (ends-at). Display-only — the node treats it as data, never as instruction.
2. **The suspension flag** — the `SUSPENSION_STATE` env key (`none` / `admin_locked` / `site_off`), pushed via the existing single-key config-push machinery. Absent = `none`, so the bump is forward-compatible with every running node. This is the enforcement half; the document above is the display half.
3. **A new `/api/health` subcheck, `suspension`** — reports the node's currently-enforced suspension state and the billing-state document's `as_of`, so FM gets read-back verification that a push took effect (the same verify-after-acting grain as upgrades). Informational, never red, **excluded from the worst-of overall status** exactly like the data-hygiene subcheck — a deliberately suspended node is not an unhealthy node. Value is a state string + timestamp: no email, no amount, nothing personal — within the contract's counts-only/no-PII wire discipline.

**What is deliberately absent:** any node endpoint that returns billing (or any) client data to FM — the standing privacy boundary ("FM is never built to read node data") holds; billing flows FM→node only. No card data, no Stripe key, and no webhook anywhere on the node. No new inbound FM surface of any kind.

### 8. Offboarding — cancellation rides the existing exporter

Cancellation is operator-driven in FM: cancel the Stripe subscription at period end (audited), and the node runs normally through what's paid for. At period end the standard suspension mechanism fires with reason `canceled` — admin locks, public site stays up through the wind-down window (open question 6). **The client is offered their data via the machinery that already exists**: the full-site export bundle (the admin's site export surface) covers their CMS content and design end-to-end, and a full backup blob (database + media) is available on request for a complete raw copy — the operator triggers it with the existing backup endpoints and hands over the file. Nothing new is built; the offboarding flow is a runbook section, not a feature. Node destruction afterward is a manual operator act, never automatic, never inside the billing machinery.

---

## Two-repo responsibility split

Same protocol as everything else that crosses the boundary (the Two-Repo Coordination Protocol in `sessions/fleet-manager-planning-spec.md`); the contract doc remains the single source of truth and the CRM authors it.

**Fleet Manager repo owns:** the vendor Stripe integration entire (restricted key in vault, sync job, state derivation, clocks); the per-client billing model and operator UI; suspension decisions and pushes; the demo trial + conversion flow; billing alerts; the audit events; the billing-operations runbook. FM consumes contract v2.6.0.

**CRM repo owns:** the contract bump itself (v2.6.0 authoring — document schema, flag semantics, subcheck); the node-side enforcement middleware; the billing-state reader; the Account page + `manage_account` permission; the convention-drift guard cases; node-side tests. **That is the whole CRM lane, and it is complete** — conversion touches no node-side code (§ Design decision 4).

**Boundary artifacts, per bump discipline:** contract doc body + CHANGELOG + version field; Cross-Repo block in both repos' session outlines; FM refreshes its cached contract copy at its absorbing session.

---

## Forward plan — session decomposition

Ordered, with dependencies. Names are descriptive; sizes are honest estimates. CRM sessions are drafted for the numbered stream after owner review (draft release-plan entries in Appendix A); FM sessions go to the FM repo via Appendix B.

| # | Session | Repo | Size | Depends on |
|---|---|---|---|---|
| 1 | **Contract v2.6.0 + node suspension gate** — authors the bump (billing-state document schema, `SUSPENSION_STATE` semantics, `suspension` subcheck); ships the enforcement middleware, the state-file reader service, the subcheck, tests | CRM | 1 session | — |
| 2 | **Billing foundation — Stripe sync** — vault key, `StripeBillingClient`, per-client billing table, daily sync + state derivation + clocks, audit events, alerts | FM | 1–2 sessions | can run parallel with 1 |
| 3 | **Billing push + verification** — billing-state document push over SSH, suspension flag push, read-back verify via the new subcheck, auto-lock/auto-unlock flows | FM | 1 session | 1, 2 |
| 4 | **"My Account" page + manage-account permission** — the Filament page, banners, portal handoff, permission seeding, permission-matrix + convention-guard updates, help doc | CRM | 1 session | 1 (parallel with 3) |
| 5 | **Operator billing UI** — per-client billing panel + actions (attach, suspend/unsuspend, refresh, checkout-link generation), fleet billing-status column, delinquency filter | FM | 1 session | 2, 3 |
| 6 | **Trial clocks + conversion flow** — trial clocks + expiry automation, the conversion orchestration (attach → push → audit). No node-side piece; no `is_demo` relaxation (§ Design decision 4, corrected at 368) | FM | 1 session | 3, 5 |
| 7 | **First-conversion drill** — end-to-end rehearsal on real infrastructure with a Stripe test-mode key: personalize a demo, convert, force a failed payment, watch dunning → grace → lock → self-cure unlock; artifact: the billing-operations runbook | both (operator-driven) | 1 session | all above |

Total ≈ **6–7 sessions across both repos** (**2 CRM-side, both shipped**; ≈4–5 FM-side including the drill).

**Slotting recommendation** (owner decides — open question 8): the track is **not Beta-1-blocking** — Beta 1 is product readiness; this is commercial readiness. Slot it directly after Beta 1's multi-node operational readiness (release-plan position 47) and the backup-recovery drill (position 48), i.e. the head of the first-customer stage: it needs the multi-node fleet and FM operational maturity those deliver, and it must be **done before the first prospect is promised a conversion**. Escape hatch if a prospect shows up early: sessions 1 and 2 (contract + FM foundation) are self-contained and can run ahead as agentic sessions.

---

## Appendix A — Draft release-plan entries (CRM-side)

*Written in the release plan's house style, ready for the owner to approve and a later local session to fold into `sessions/release-plan.md` (this session does not edit that file). Proposed gate label `first-customer` — these do not block Beta 1.*

#### CB1. Client Billing — Contract v2.6.0 + Node Suspension Gate ✅ *(shipped at session 366)*

- **gate:** first-customer
- **prerequisites:** owner sign-off on the Client Billing & Account track doc (`sessions/tracks/client-billing-and-account.md`) open questions 1, 6, 8; A3 multi-node readiness recommended first (not a hard prerequisite for the code).
- **success criterion:** Additive CRM↔FM contract bump v2.5.0 → v2.6.0 shipped: (a) the billing-state document schema (FM-pushed JSON at `storage/app/private/fleet/billing-state.json`, atomic write, display-only, excluded-from-backup path) documented in `docs/fleet-manager-agent-contract.md` with CHANGELOG + Cross-Repo block updates; (b) `SUSPENSION_STATE` env key (`none`/`admin_locked`/`site_off`, absent = none) enforced by node middleware — admin panel (and its in-panel API routes) render a suspension notice with reason-appropriate copy + billing-portal link under `admin_locked`; public site, donation/event/membership checkout, and member portal stay up; `site_off` renders a public maintenance notice with `/api/*` fleet endpoints untouched; (c) new informational `suspension` subcheck on `/api/health` (state + billing-state `as_of`; never red; excluded from worst-of, mirroring `data_hygiene`); (d) tests across all three surfaces; every existing install unaffected with the key absent.
- **artifact:** the contract doc at v2.6.0 + the enforcement middleware + subcheck.
- **estimated time cost:** 1 session.

#### CB2. Client Billing — "My Account" Page + Manage-Account Permission ✅ *(shipped at session 367)*

- **gate:** first-customer
- **prerequisites:** CB1 (the pushed-document schema it renders).
- **success criterion:** Filament page (Settings group, "Account") rendering exclusively from the pushed billing-state document — plan, status badge, next invoice with line items, read-only billing contact, Stripe-hosted portal link, "as of {relative time}" staleness footer; page self-hides when no document exists; past-due/grace banner on the page plus a slim panel-wide warning banner; new seeded `manage_account` permission (granted to no shipped role — deliberately super-admin-only by default, documented as intentional in `docs/runbooks/permission-matrix.md` + probe test); convention-drift guard cases banning vendor-billing identifiers CRM-wide and banning Stripe SDK/config references in the billing/account code; help doc; tests.
- **artifact:** the page + permission + guard cases.
- **estimated time cost:** 1 session.

#### CB3. Client Billing — Demo Conversion Cleanup Command (node half) ❌ *(withdrawn at session 368 — no work in it)*

Withdrawn, not deferred. The entry rested on the premise that a prospect's node runs in demo mode and gets flipped to production at conversion — so something would need to sweep up the demo login account and the pages `demo:reset` had locked. **A prospect's node is an ordinary production node** (§ Design decision 4, § Vocabulary): it never had a `demo@demo.local` account, `demo:reset` never ran on it, and its pages were never locked. The command would have had nothing to remove and nothing to unlock.

Verified against the code at 368: `isDemoMode()` is the sole demo switch; `/demo/enter` (the only thing that creates the demo account) 404s unless it is true; and both commands that lock pages hard-gate on it. Two incidental findings, recorded because they outlive this entry:

- **The command would have failed anyway.** `pages.author_id` and `events.author_id` are `NOT NULL` with `ON DELETE RESTRICT` against `users`, and the `demo` role holds full create rights on both. So deleting `demo@demo.local` raises a foreign-key violation the moment that account has authored one page. Confirmed empirically against the running database. Nothing hits this today: the shared demo server rebuilds via `migrate:fresh` / `db:wipe`, which drop the tables rather than deleting the row. **Anything that ever tries to row-delete a user must reassign `pages.author_id` + `events.author_id` first** (and remember `Page` soft-deletes, so trashed pages still hold the constraint).
- **The `demo` role is seeded on every node**, not just the demo server — `PermissionSeeder` creates it with no environment check, unlike the demo *account*, which `DatabaseSeeder` correctly gates on `isDemoMode()`. Inert (granted to nobody), but it is the last place "demo" leaks onto a client node. Owner-logged at 368 for a future housekeeping pass; folds into `sessions/housekeeping-inbox.md` at this session's close.

**CB1 + CB2 are therefore the complete CRM lane of this track.**

---

## Appendix B — FM-side handoff

*Session outlines for the Fleet Manager repo's planning docs, in its stub shape — copy into the FM repo at a local session; the cloud session cannot write there. FM consumes contract v2.6.0 (authored CRM-side at CB1); refresh the cached contract copy at the first absorbing session below.*

**FM-B1. Billing foundation — Stripe sync.** Restricted vendor-Stripe key (customers/subscriptions/prices/coupons/invoices/checkout-sessions scopes only) stored in the existing vault; `StripeBillingClient` wrapper (the only class that touches the key); `client_billing` table 1:1 with clients (Stripe IDs, price, collection mode, status, next-invoice snapshot incl. line items, billing email, trial clock, grace clock, suspension state + reason, last-synced-at); daily scheduled sync deriving the state machine in the track doc (§ Design decision 3) with clocks for grace and trial; billing events into the existing audit ledger; transition-driven email alerts (one per transition). Guard: FM never gains a donation-Stripe credential — the vendor key is the only Stripe anything FM-side. *Dependencies: none (can precede the CRM contract session). Size: 1–2 sessions.*

**FM-B2. Billing push + verification.** Push the billing-state document (schema per contract v2.6.0) over the existing SSH provisioning channel, atomic write; push `SUSPENSION_STATE` via the existing single-key config-push machinery; read back the new `suspension` health subcheck to verify a push took effect; automatic lock at grace/trial expiry and automatic unlock on payment recovery, both audited + alerted. *Dependencies: FM-B1 + CRM CB1. Size: 1 session.*

**FM-B3. Operator billing UI.** Per-client billing panel + header actions (attach subscription with hosted-checkout-link generation and copy affordance, suspend/unsuspend with confirmation, refresh billing); fleet list billing-status column beside version/demo/public-site; delinquency filter view; internal nodes excluded. *Dependencies: FM-B1, FM-B2. Size: 1 session.*

**FM-B4. Trial clocks + conversion flow.** Trial clock on prospect-node provisioning (default per owner answer to track-doc open question 2), one-click extend, T-3 alert, auto-lock at expiry (reason `trial_expired`); the conversion orchestration in the track doc's corrected order (billing attach → initial state push → audit chain). **Corrected at CRM session 368 — read § Design decision 4 before building this.** A prospect's node is an ordinary production node, so conversion performs **no** reset-cron removal, **no** `APP_ENV` flip, **no** node-side cleanup command, and **no** `is_demo` write-once relaxation (a prospect's node was never `is_demo`; that flag continues to mean *the shared demo server* and keeps its write-once guarantee). Nothing is pushed to the node but the billing-state document. *Dependencies: FM-B2, FM-B3. No CRM dependency. Size: <1 session — smaller than originally scoped.*

**FM-B5. First-conversion drill (operator-driven, cross-repo).** Stripe test-mode key in vault; personalize a demo → convert → force a failed payment → observe dunning → grace → auto-lock → pay via portal → auto-unlock; cancel + offboard with the export handover. Artifact: `docs/runbooks/billing-operations.md` (FM repo) covering conversion, delinquency handling, manual suspend/unsuspend, full shutoff, offboarding. *Dependencies: everything above. Size: 1 session.*

---

## Stance

- **Stripe is the engine; Fleet Manager is the window and the switch; the node is a display.** Money logic lives in exactly one place, state flows in exactly one direction (Stripe → FM → node), and every enforcement flip is reversible.
- **No new attack surface.** FM stays outbound-only; the node gains no endpoint, no credential, no webhook; the one new hard gate rides the same env-derived grain as demo mode.
- **The donation page never goes dark over the owner's invoice.** Suspension means the back office, manually and reversibly — never the constituents, never the data.
- **Operational simplicity beats sophistication at this fleet size** — polling over webhooks, pushed files over APIs, Stripe-native pricing over engines, runbooks over automation for the rare destructive acts.
