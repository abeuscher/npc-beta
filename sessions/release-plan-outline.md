# Release Plan Outline — Pre-Beta Rehearsals

## Purpose

Release is gated on passing a selected subset of the rehearsals below. Not all will be executed — the next step is to vet each candidate inside the app for confidence-per-hour ROI and pick the working set. Everything not selected becomes post-1.0 hardening.

Each selected rehearsal needs a written success criterion **before** it runs. "It worked" is not a gate; a specific, measurable outcome is.

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