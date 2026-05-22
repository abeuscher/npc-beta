# Housekeeping Incoming

Capture buffer for items noticed mid-session via `npm run logbug -- "…"`. Each
line is stamped with the VERSION marker + date at capture time. This file is
NOT the canonical inbox — at the next session close the close gate digests these
items, verifies each against current code, surfaces anything questionable, and
folds the survivors into `sessions/housekeeping-inbox.md`, then clears this
file back to this header. Do not hand-curate here; capture and move on.

---
- [0.316.01 · 2026-05-22] Do we have custom 404s built if not that is probably pre beta and also pretty easy