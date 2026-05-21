# Refactor track — Deliberately Kept register

Enumerated list of patterns that look like Code Shape & Fit findings (Cardinality / Locality / Directness / Necessary-vs-accidental / Seam) but are intentional architectural decisions. Audit sessions consult this register and skip findings that match a registered pattern. Apply walkthroughs do not relitigate registered carve-outs.

See [`sessions/tracks/code-shape-and-fit.md`](tracks/code-shape-and-fit.md) for the track shape and the register's lifecycle + retirement rules.

---

## Carve-outs

### Per-namespace `FieldMapper` services

- **Looks like:** a Directness or Cardinality finding. Six near-identical per-importer mapper services (`ContactFieldMapper`, `EventFieldMapper`, `DonationFieldMapper`, `MembershipFieldMapper`, `InvoiceFieldMapper`, `NoteFieldMapper`) — the duplication is real and the abstraction-to-one shape is sitting right there.
- **Why kept:** the user has an explicit preference for uniform per-type UX over per-type plumbing consolidation. The decision is captured in the `feedback_uniform_ux_over_per_type_plumbing` memory entry — paying per-type plumbing cost is the chosen tradeoff to keep the UX uniform; only carve exceptions for genuine functional differences. The mappers share a shape on purpose; collapsing them would push divergence into the UX layer instead of the implementation layer.
- **Established at:** session 259 (importer auto-mapping pattern lift). See `sessions/259. Importer Auto-Mapping Pattern Lift — Log.md`.

### Page vs View distinction

- **Looks like:** a Locality or Seam finding. Pages and DashboardView / RecordDetailView share enough conceptual surface that someone reading the code without context would propose flattening them into a single primitive.
- **Why kept:** the functional and security differences are load-bearing — Page carries public-render concerns, draft/publish flow, slug-based routing, and SEO metadata that the View family doesn't; the View family carries widget-on-record placement and permission scoping that Page doesn't. Captured in the `feedback_page_is_not_a_view` memory entry. Reject framings that flatten the two.
- **Established at:** documented as a standing rule in user feedback; predates the track.

### Inline-eligibility per-widget roster

- **Looks like:** a Cardinality or Directness finding. The roster of which widgets are eligible for in-page inline text editing is a hand-maintained per-widget allow-list rather than a derived property — it accretes one entry at a time as widgets are vetted, and the count of entries changes with every inline-editing iteration.
- **Why kept:** which widgets can be safely inline-edited is a design call per widget, not a property mechanically derivable from widget shape. Data-driven widgets (events_listing, etc.) carry text that's a template, not a value, and inline-editing those would corrupt rendered output. The roster is the explicit record of those calls. Trying to derive it (e.g. "all widgets without `{{item.*}}` interpolation") would silently sweep widgets in and out as their templates change.
- **Established at:** session 304 (Page Builder inline-editing Phase 2); refined across sessions 305–308.

---

## Retirement log

*(Empty — no carve-outs retired yet. Format: one entry per retirement, citing the session that retired it and what changed in the codebase or user preference that removed the original justification.)*
