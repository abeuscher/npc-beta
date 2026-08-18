# Housekeeping Incoming

Capture buffer for items noticed mid-session via `npm run logbug -- "…"`. Each
line is stamped with the VERSION marker + date at capture time. This file is
NOT the canonical inbox — at the next session close the close gate digests these
items, verifies each against current code, surfaces anything questionable, and
folds the survivors into `sessions/housekeeping-inbox.md`, then clears this
file back to this header. Do not hand-curate here; capture and move on.

---

- [0.376.01 · 2026-08-18] WidgetManifestTest screenshot-path check still assumes app/Widgets/{Folder}; a plugin widget declaring screenshots() would mis-resolve. LogoGarden declares none, so inert today — fold into P2's widget-boundary pass (same thumbnailDir()-style resolution from the definition's directory).