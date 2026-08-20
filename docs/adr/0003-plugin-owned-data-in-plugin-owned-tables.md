# ADR 0003 — Plugin data lives in plugin-owned tables — and plugin-owned schema

**Status:** accepted
**Date:** the data-shape ruling at session 376 (plan § 6.1, owner ruling); the schema-ownership half at session 383 (arc P7, the first squash-boundary redraw); second redraw consumer session 390

## Context

The #1 open contract question at planning time was contact extension: how does a plugin attach data to the identity layer without either bloating core tables or co-mingling schemaless blobs? A second question followed from the migration-squash discipline (core schema bootstraps from a single dump): if plugins own features, who owns the tables those features write, and how does a fresh install of a composition *without* a plugin avoid creating its tables?

## Decision

**Data shape (owner ruling, 376):** plugins attach data via **plugin-owned tables keyed by `contact_id`** (or other core ids). No columns are added to core tables; no schemaless-blob co-mingling. Corollary: core `Contact` stays minimal identity. Plugin→core foreign keys are legitimate — the plugin owns its tables, not isolation from core's schema.

**Schema ownership (383, extended 390):** core's schema dump covers **core tables only**. A plugin's tables live in its own `database/migrations/` as create-table migrations **authored byte-faithfully from the dump DDL they replace** (raw `DB::unprepared` statements — pg_dump round-trips its own output, so a fresh-install dump diffs clean against the pre-redraw reference; schema-builder calls would risk expression-normalization drift). Install order is deterministic: core dump → enabled plugins' migrations → seeders. Everything the table needs travels with it: indexes, sequences, and constraints — including constraints that *are* behavior (`donation_receipts.contact_id ON DELETE RESTRICT` is the blocks-contact-force-delete rule, and it moved with the table at 390).

**Two vanish semantics:** *disabled ≠ uninstalled*. A disabled plugin keeps its schema and data (activation stripped, nothing deleted); a never-installed composition simply never creates the tables. The first is proven by fixtures that register the plugin's migration path directly; the second by per-composition fresh-install identity checks.

**Models and factories stay core** (deliberately, with no scheduled move) — the model move is deferred until the endgame at the earliest.

## Consequences

- **The composition-safety rule** — the decision's sharpest edge. Because core keeps the models, core code can read a plugin-owned table that does not exist on some composition. Every such read must be gated or provably unreachable. Worked examples: `PageContextTokens` page-type gate (383, found by the first plugin-absent identity check), the setup checklist's fund row gated on route presence (390). Admin-only reaches are recorded, accepted debt.
- **The identity check replaces the single-schema assumption**: (a) a full-composition fresh install must dump byte-identical to the pre-redraw reference modulo migration bookkeeping; (b) a plugin-absent install must boot, seed, and smoke clean with the tables absent. Both are verified runs at each redraw, and a standing guard pins plugin tables out of the core dump.
- **Compliance doors stay open**: plugin-owned tables can be independently encrypted, access-scoped, audited, or moved to their own connection later — the shape a HIPAA-class requirement would need.
- Costs: test fixtures need explicit migrator paths; dump regeneration has a strict procedure (drop plugin tables first; recreate the database, never the schema); and some vertical-named residue remains in core (e.g. per-vertical mapping columns on `import_sources`) — accepted, an endgame cleanup candidate.

## References

- `docs/plugin-contract.md` surface 5 (migrations) — current authority, including the identity-check procedure.
- `sessions/plugin-architecture-plan.md` § 6 dispositions 1 and 7; § 6.7 (events-owned schema annotation).
- Session 383 and 390 logs — the two redraws.
