# Housekeeping Incoming

Capture buffer for items noticed mid-session via `npm run logbug -- "…"`. Each
line is stamped with the VERSION marker + date at capture time. This file is
NOT the canonical inbox — at the next session close the close gate digests these
items, verifies each against current code, surfaces anything questionable, and
folds the survivors into `sessions/housekeeping-inbox.md`, then clears this
file back to this header. Do not hand-curate here; capture and move on.

---

- `[0.350.01 · 2026-06-10]` Longform writing in the page editor is cramped: inline editing happens at sub-1:1 zoom, hover chrome competes with the prose, and authoring happens in the design's colors (e.g. white-on-dark). Scoped at the A010 planning conversation — solution shapes (A: focused writing mode full-screen surface; B: quiet-chrome-while-editing state class; C: legibility override, folded into A) and the ProseMirror-survivability constraint are recorded in `sessions/A010. Page Editor Full-Screen & Faithful Viewports.md` § Deferred. ~1 session / 2 iterations when picked up.
