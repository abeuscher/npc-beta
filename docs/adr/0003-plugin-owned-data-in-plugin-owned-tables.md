# ADR 0003 — Plugin-owned data in plugin-owned tables

**Status:** Accepted — 2026-08-18

## Context

The #1 open contract question at planning time was contact extension: how does a plugin attach data to the identity layer without either bloating core tables or co-mingling schemaless blobs? A second question followed from the migration-squash discipline (core schema bootstraps from a single dump): if plugins own features, who owns the tables those features write, and how does a fresh install of a composition *without* a plugin avoid creating its tables?

The data-shape half was settled first; the schema-ownership half landed with the first boundary redraw (commit `4942967d`, three event tables) and was exercised again at larger scale with the four donation tables (`449adb9e`, 2026-08-19).

## Decision

**Data shape:** plugins attach data via **plugin-owned tables keyed by `contact_id`** (or other core ids). No columns are added to core tables; no schemaless-blob co-mingling. Corollary: core `Contact` stays minimal identity. Plugin→core foreign keys are legitimate — the plugin owns its tables, not isolation from core's schema.

**Schema ownership:** core's schema dump covers **core tables only**. A plugin's tables live in its own `database/migrations/` as create-table migrations **authored byte-faithfully from the dump DDL they replace** (raw `DB::unprepared` statements — pg_dump round-trips its own output, so a fresh-install dump diffs clean against the pre-redraw reference; schema-builder calls would risk expression-normalization drift). Install order is deterministic: core dump → enabled plugins' migrations → seeders. Everything the table needs travels with it: indexes, sequences, and constraints — including constraints that *are* behavior (`donation_receipts.contact_id ON DELETE RESTRICT` is the blocks-contact-force-delete rule, and it moved with the table).

**Two vanish semantics:** *disabled ≠ uninstalled*. A disabled plugin keeps its schema and data (activation stripped, nothing deleted); a never-installed composition simply never creates the tables. The first is proven by fixtures that register the plugin's migration path directly; the second by per-composition fresh-install identity checks.

**Models and factories stay core** for now, deliberately and with no scheduled move — deferred to the end of the decomposition plan at the earliest.

## Consequences

- **The composition-safety rule** — the decision's sharpest edge. Because core keeps the models, core code can read a plugin-owned table that does not exist on some composition. Every such read must be gated or provably unreachable. Two worked examples so far: a page-context token read that 500'd every public page on the first plugin-absent install (fixed with a page-type gate), and a setup-checklist row now gated on route presence. Admin-only reaches are recorded, accepted debt.
- **The identity check replaces the single-schema assumption**: (a) a full-composition fresh install must dump byte-identical to the pre-redraw reference modulo migration bookkeeping; (b) a plugin-absent install must boot, seed, and smoke clean with the tables absent. Both are verified runs at each redraw, and a standing guard pins plugin tables out of the core dump.
- **Compliance doors stay open**: plugin-owned tables can be independently encrypted, access-scoped, audited, or moved to their own connection later — the shape a HIPAA-class requirement would need.

Costs:

- **Composition-safety is an open-ended obligation, not a one-time fix.** Any future core code touching plugin models re-creates the hazard, and only the plugin-absent identity check catches it — a check that must actually be re-run at each boundary change.
- Test fixtures need explicit migrator paths, and every extraction re-points them; dump regeneration has a strict procedure (drop plugin tables first; recreate the database, never the schema) that is easy to get subtly wrong.
- **The models-stay-core compromise is the root of most of the above.** Moving the models would dissolve the composition-safety class entirely; we have deliberately not paid that cost yet, so this ADR's obligations persist until a future record supersedes the compromise.
- Some vertical-named residue remains in core (e.g. per-vertical mapping columns on `import_sources`) — accepted, a cleanup candidate for the decomposition endgame.

## References

- `docs/plugin-contract.md` — the migrations surface is the current authority, including the procedure for checking that a moved table is the same table.
- `sessions/plugin-architecture-plan.md` — the settled question of how a plugin extends a contact (through its own tables keyed by contact, never by adding columns to core tables), and the open question of how plugin-owned tables come out of core's squashed schema.
- Commits `4942967d` (first boundary redraw, 2026-08-18), `449adb9e` (second redraw, four tables, 2026-08-19).
