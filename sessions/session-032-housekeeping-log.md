# Housekeeping — Session Roadmap Consolidation — Log

## What was done

Consolidated the session planning files into a single editable reference document and cleaned up the sessions folder.

### Files created

- `sessions/session-outlines.md` — single canonical roadmap. Contains a completed sessions table (001–032 by title) followed by all future sessions grouped by track, with unnumbered title headers so they can be freely rewritten and reordered.
- `sessions/base-prompt.md` — rewritten. New flow: user provides a session title → agent reads the stub from session-outlines.md → discussion → agent writes full prompt in conversation → user confirms → execution proceeds. Process and style rules preserved from the old version.

### Files deleted

- `sessions/000. Table of Contents.md` — superseded by session-outlines.md
- `sessions/future-sessions.md` — superseded by session-outlines.md
- `sessions/031. Navigation - Change to Support Multiple Menus.md` — stray draft, superseded by the actual 031 prompt
- All 39 individual numbered stub files (033–071) — consolidated into session-outlines.md

### Roadmap additions discussed and added

- **Volunteer Management** group with four sessions: Volunteer Profile & Hours Tracking, Volunteer Scheduling, Volunteer Communication & Recognition, Volunteer Portal
- **Contact Record — Birthday & Age Fields** — small session stub, contacts-level change required for volunteer age verification
- **Pledge Tracking** — added to Finance group between Recurring Donations and Tax Receipts
- **Privacy & Legal Footer Example** — added to Infrastructure Finishing; no analytics or cookie consent (out of scope by design), just a reference footer component with legal copy slots

### Decisions recorded in outlines

- Document handling (waivers, agreements) is explicitly out of scope project-wide; external tools recommended
- Analytics and cookie consent are out of scope by design; no plans to build them in
- Volunteers are contacts — contact type tag, no separate model
- Queue/background job architecture will be addressed inside the Mailing List Manager session (first place it becomes necessary)
- Pledges grouped with Finance; share the donor contact and transaction records with donations but are architecturally distinct (promise vs payment)
