# ADR 010 — Admin Component Strategy

**Status:** Decided
**Date:** March 2026 (Session 004)

---

## Context

Session 003 resolved the public frontend component question (Alpine-first, optional Pico CSS, no component library selected yet). Session 004 raised the same question for the admin panel: do we need a component library on the admin side?

---

## Decision

**No additional component library for the admin. Filament is the component library.**

---

## Rationale

Filament ships with a complete, production-quality set of UI primitives. Everything the admin needs is already in `vendor/filament`:

**Forms**
- Text inputs, textareas, rich editor, select, checkbox, toggle, date/time pickers
- File upload, repeater, builder
- Color picker, key-value, tags input

**Tables**
- Text, badge, boolean, icon, image, date, color columns
- Sortable, searchable, filterable, paginated

**Actions**
- Modal actions, slide-over actions, confirmations
- Bulk actions, header actions, table row actions

**Infolists**
- Read-only detail views with the same layout DSL as forms
- Supports all column types as entries

**Layout**
- Section, Grid, Fieldset, Tabs, Wizard steps, Split, Card

**UI**
- Notifications, modals, slide-overs — all built in
- Dark mode, responsive sidebar — all built in

Filament's CSS is its own compiled Tailwind build, separate from any app CSS pipeline. We do not add Tailwind to the app to style the admin — the admin panel styles itself. There is no CSS to maintain, no build pipeline to configure, and no conflict between admin and frontend styles.

---

## When to Add Something Beyond Filament's Native Set

Only when a specific, justified need exists:

- **A Filament plugin**: e.g., a TipTap rich editor plugin, a chart/stats widget, a calendar view. These are added per-need when built-in components are genuinely insufficient.
- **A custom Filament component**: Written as a proper Livewire component following Filament's component API.

Nothing is added preemptively. No component library is installed "just in case."

---

## Public Frontend

Unchanged from Session 003. Alpine-only to start. Pico CSS is optional via `THEME_PICO`. Custom CSS via `@stack('styles')`. No component library selected. That decision is deferred until there is real UI to style.

---

## Consequences

- Admin UI is entirely self-contained in `vendor/filament`. Zero admin CSS to maintain.
- No risk of style conflicts between admin and public frontend.
- Custom admin UI needs are addressed through Filament's own extension points.
- Adding a Filament plugin later is a `composer require` and registration — no structural change.
