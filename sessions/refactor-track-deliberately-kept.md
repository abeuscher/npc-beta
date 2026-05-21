# Refactor track — Deliberately Kept register

Enumerated list of patterns that look like Code Shape & Fit findings (Cardinality / Locality / Directness / Necessary-vs-accidental / Seam) but are intentional architectural decisions. Audit sessions consult this register and skip findings that match a registered pattern. Apply walkthroughs do not relitigate registered carve-outs.

See [`sessions/tracks/code-shape-and-fit.md`](tracks/code-shape-and-fit.md) for the track shape and the register's lifecycle + retirement rules.

---

## Carve-outs

### Per-namespace mapper helpers

- **Looks like:** a finding that says "you have six near-identical helper files, collapse them into one." The contacts mapper lives at `app/Services/Import/FieldMapper.php` (unprefixed legacy name, mirroring the `ImportProgressPage` naming asymmetry); the other five at `app/Services/Import/MembershipFieldMapper.php`, `DonationFieldMapper.php`, `NoteFieldMapper.php`, `InvoiceFieldMapper.php`, `EventFieldMapper.php`. The duplication is real and an abstraction-to-one shape is sitting right there.
- **Why kept:** the user has an explicit preference for uniform per-type UX over per-type plumbing consolidation. The decision is captured in the `feedback_uniform_ux_over_per_type_plumbing` memory entry — paying per-type plumbing cost is the chosen tradeoff to keep the UX uniform; only carve exceptions for genuine functional differences. The mappers share a shape on purpose; collapsing them would push divergence into the UX layer instead of the implementation layer.
- **Established at:** session 259 (importer auto-mapping pattern lift). See `sessions/(archived/)259. Importer Auto-Mapping Pattern Lift — Log.md`.
- **Open gap:** the Organizations importer landed at session 256 but no matching `OrganizationFieldMapper` was ever added. Session 313 walkthrough confirmed this is most likely an oversight rather than a deliberate exclusion; the missing-helper task is filed in `sessions/housekeeping-inbox.md` for a future low-risk batch.

### Page vs View distinction

- **Looks like:** a Locality or Seam finding. Pages and DashboardView / RecordDetailView share enough conceptual surface that someone reading the code without context would propose flattening them into a single primitive.
- **Why kept:** the functional and security differences are load-bearing — Page carries public-render concerns, draft/publish flow, slug-based routing, and SEO metadata that the View family doesn't; the View family carries widget-on-record placement and permission scoping that Page doesn't. Captured in the `feedback_page_is_not_a_view` memory entry. Reject framings that flatten the two.
- **Established at:** documented as a standing rule in user feedback; predates the track.

### `PageBuilderApiController` — per-action endpoints, not bulk save

- **Looks like:** a finding that says "you have twenty-two near-identical small HTTP endpoints on one controller, collapse them into one bulk-save endpoint." Each Vue store action in the page builder maps 1:1 to its own controller endpoint (widget store / update / destroy / copy / reorder / preview; layout store / update / destroy; image upload + remove; appearance-image upload + remove; updateColorSwatches; data lookups).
- **Why kept:** the per-action shape is what supports optimistic UI updates, per-action retry, per-action revert / undo, and aligns with the rest of the admin's per-action REST patterns. Collapsing to one bulk endpoint per save transaction would be a regression in all of those dimensions. The cardinality is real but it's load-bearing for the editor's interaction model.
- **Established at:** session 273 (Pinia-store extraction established the per-action dispatch pattern); ratified as a register entry at session 313 walkthrough after the 312 audit briefly considered (and rejected) the collapse-to-bulk move.

### `ImportContactsPage` — long file kept as-is

- **Looks like:** a "this file is way too long, split it up" finding. The contacts importer page sits at ~1014 lines, several times the average across its sibling importer pages.
- **Why kept:** the file's length comes from contact-specific behavior — custom-field collision detection, duplicate-contact matching, contact-only sentinel handling — that doesn't cleanly lift into the shared per-type pattern the other importer pages use. The standing decision (originally cleanup Cycle 1 session 206, re-affirmed at cleanup Cycle 2 session 273) is to keep the page intact rather than chase the symmetry; converting it would require synchronizing 100+ Pest tests and four Playwright specs for a refactor whose forcing function never materialized. Aligns with the same per-type-UX preference that keeps the mapper helpers separate.
- **Established at:** session 206 (originally framed as "Flag B" in the cleanup track), re-affirmed at session 273, promoted to this register at session 313 to retire its floating won't-fix status.

### Inline-eligibility per-widget roster

- **Looks like:** a Cardinality or Directness finding. The roster of which widgets are eligible for in-page inline text editing is a hand-maintained per-widget allow-list rather than a derived property — it accretes one entry at a time as widgets are vetted, and the count of entries changes with every inline-editing iteration.
- **Why kept:** which widgets can be safely inline-edited is a design call per widget, not a property mechanically derivable from widget shape. Data-driven widgets (events_listing, etc.) carry text that's a template, not a value, and inline-editing those would corrupt rendered output. The roster is the explicit record of those calls. Trying to derive it (e.g. "all widgets without `{{item.*}}` interpolation") would silently sweep widgets in and out as their templates change.
- **Established at:** session 304 (Page Builder inline-editing Phase 2); refined across sessions 305–308.

---

## Retirement log

*(Empty — no carve-outs retired yet. Format: one entry per retirement, citing the session that retired it and what changed in the codebase or user preference that removed the original justification.)*
