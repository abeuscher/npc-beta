# Session 017 Outline — Import/Export: Extended

> **Session Preparation**: This is a planning outline, not a complete implementation prompt.
> At the start of this session, review the import/export infrastructure built in session 016.
> The goal is to extend it to more data types and more source system presets without
> rebuilding the core. Session 016's architecture should be designed with this extension in mind.

---

## Goal

Extend the import/export system to cover additional data types (Donations, Memberships, Events registrants) and add presets for more specific source systems. Also address any usability problems discovered after clients used the session 016 importer.

---

## Key Decisions to Make at Session Start

- **Which data types to add**: Based on what clients actually need. Likely: Donations (with fund/campaign mapping), Memberships (with tier/status), EventRegistrations. Prioritise by client demand.
- **Donation import complexity**: Donations link to Contacts, Funds, and Campaigns. The importer must handle lookups (find or create the linked record). Decide how much auto-creation of linked records is acceptable.
- **More presets**: Which source systems remain from session 016? Add the next most-requested ones. Common candidates: Bloomerang full export, QuickBooks contacts, DonorSnap, NeonCRM.
- **Export improvements**: Are there export formats beyond CSV needed? (e.g. JSON for API consumers, XLSX for finance staff)
- **Import history**: Should there be an audit log of past imports (who imported what, when, how many records)?

---

## Scope (draft — refine at session start)

**In:**
- Import for 2-3 additional data types (priority determined at session start)
- Additional source system presets
- Import history / audit log (if not built in session 016)
- Export for additional data types
- Any fixes/improvements from session 016 based on real usage

**Out:**
- Real-time sync with external systems (API-based, not file-based — future feature)
- Custom field creation on import (unless deferred from session 016)

---

## Rough Build List

- Extend ImportJob to handle additional models
- New FieldMapper presets for additional source systems
- Import history model and Filament view
- Additional export actions on relevant resources
- Tests for new data types and presets

---

## Open Questions at Planning Time

- What import problems did clients encounter after session 016? Fix those first.
- Is XLSX support worth adding, or is CSV sufficient?

---

## What This Unlocks

- Full data portability for all core entities
- Client onboarding is self-service for common source systems
