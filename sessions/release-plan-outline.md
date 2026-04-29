# Release Plan Outline — Pre-Beta Rehearsals

## Purpose

Release is gated on passing a selected subset of the rehearsals below. Not all will be executed — the next step is to vet each candidate inside the app for confidence-per-hour ROI and pick the working set. Everything not selected becomes post-1.0 hardening.

Each selected rehearsal needs a written success criterion **before** it runs. "It worked" is not a gate; a specific, measurable outcome is.

---

## Pre-release requirements (non-rehearsal)

These are gate items framed as release-blocking capabilities or operational readiness, not as timed rehearsal scenarios. The vetting session decides per item whether it (a) becomes its own session, (b) folds into an existing track or stub, or (c) is bundled into a rehearsal that already exercises it.

### Fleet Manager — node operations parity

Fleet Manager needs to be able to **install, backup, restore, and read logs** from nodes before Beta-1 ships. The CRM-side contract surface is at v1.2.0 (CRM reports `last_backup_at` against a threshold-driven success record); the FM-side capability set that consumes the contract and drives node operations needs:

- **Install** — provision a fresh CRM node from a clean droplet to a working install end-to-end.
- **Backup** — trigger and verify a backup against a node, surfacing failure modes the agent endpoint reports.
- **Restore** — restore a node from a backup blob (CRM-side has the manual procedure documented per sessions 242 + 243; FM-side needs the operator-facing equivalent).
- **Read logs** — fetch and surface application logs from a node without operator SSH.

These four capabilities partially overlap existing Fleet Manager Agent carry-forwards (`backup-restore tooling`) and partially expand scope (install, log-reading). The FM track is currently marked "substantially complete" — this requirement re-opens it as Beta-1-blocking for a specific capability subset.

### Multi-node operational readiness

By release we need to be running, on production infrastructure:

- **Marketing site** — the public-facing site that explains the product to prospects.
- **Demo install** — a clean, populated CRM install we can show in a sales pitch.
- **Test / deploy instance** — a staging install we deploy to and exercise before promoting to production.
- **A fourth node, ready** — pre-provisioned for the first customer signup, so onboarding is "transfer ownership" rather than "spin up infrastructure during the customer's pitch."

This is operational provisioning, not a rehearsal. The vetting decides whether this lands as its own session, as work bundled into a Fleet Manager install drill, or as ongoing infrastructure work outside the release-gate plan.

---

## Categories

- **Onboarding rehearsals** — correctness under realistic client data messiness.
- **Capsize drills** — deliberate breakage and recovery, timed.
- **Scale rehearsals** — empirical capacity ceilings.
- **Workflow rehearsals** — full end-to-end user scenarios across multiple subsystems.
- **Compatibility rehearsals** — environment and access variation.

---

## Candidate scenarios

### Onboarding

- **Custom fields + public collection ingestion.** Client arrives with custom fields on contact and event records plus a public event-photos collection. Ingest cleanly at the node level.
- **Migration in from messy CSV.** Real-shape Neon or Wild Apricot export with duplicates, inconsistent dates, missing fields, custom-field drift across rows. Clean import without dev intervention.
- **Migration out.** Departing client requests a complete export. Data comes out usable.

### Capsize drills

- **DB wipe + backup recovery.** Client accidentally destroys the database. Recover from backup, system back online, recovery time documented.

### Scale

- **Very large dataset performance.** Synthetic data at 10x / 100x / 1000x of assumed ceiling. Find where Postgres, Filament list views, and search start dragging. Output is a sizing document with measured ceilings.

### Workflow

- **Membership renewal cycle.** Compress a year of membership lifecycle: signups, renewals, lapses, grace periods, reactivations, dues changes, payment failures.
- **Donation-to-acknowledgment loop.** Donation → receipt → tax letter → CRM record → QuickBooks sync → year-end statement. Include a refund and a corrected acknowledgment.
- **Event with everything.** Paid tickets, comp tickets, waitlist, tiers, custom registration questions, capacity limits, day-of check-in, post-event sequence.
- **Email at volume.** 5,000-recipient newsletter with personalization, images, unsubscribe links. DKIM/SPF/DMARC verified end-to-end, bounce handling, unsubscribe verification.
- **Concurrent admin editing.** Two staff editing the same contact at the same time. Same for CMS page edits during publish.
- **Permission audit.** Walk same data from volunteer, board read-only, staff admin, and public visitor perspectives. Verify boundaries hold.
- **Accidental public exposure.** Attempt to mark sensitive fields public — home addresses, donor amounts, internal notes. Verify the system makes this hard or visible.
- **Integration retest — coordinated tire-kicking.** Near the end of the final dev cycle, run a single session that exercises every external integration the product depends on (Stripe, Resend, Mailchimp, DigitalOcean Spaces, QuickBooks, Google Calendar — full list confirmed at session time) end-to-end in coordinated fashion. The premise: integrations are the most fragile part of the app — they break silently when an upstream API changes shape, when credentials drift, when a webhook gets reconfigured. A scheduled coordinated retest catches drift before it surfaces in a customer install. The session output is a runbook entry per integration covering the tire-kick steps and the green/red criterion. Timing matters — this rehearsal lands late, against a near-final surface.

### Compatibility

- **Browser bingo.** Admin and public surfaces through Chrome, Safari, Firefox, an old iPad, a Pixel, an aging Windows machine.
- **Accessibility pass.** Public site and admin UI through screen reader and keyboard-only navigation.
- **Flaky connection field test.** Day-of event check-in over bad wifi. Verify graceful timeouts and idempotent check-in actions.

---

## Vetting criteria

For each candidate, decide:

1. How much confidence does passing it actually deliver?
2. How much real-world failure does it prevent?
3. Time cost to set up and run.
4. Whether it produces a reusable artifact (runbook, sizing doc, onboarding playbook) or just a green check.

Bias toward scenarios that produce artifacts. Those double as sales credibility against incumbents.

---

## Out of scope for this release gate

- Anything without a defined success criterion before running.
- Hardening for failure modes not observed in any of the rehearsals.
- Test scenarios for features not in the 1.0 surface.

---

## Not yet decided

- Final selected working set.
- Order of execution.
- Specific success criteria per scenario.