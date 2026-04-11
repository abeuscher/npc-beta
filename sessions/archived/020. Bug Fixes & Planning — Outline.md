# Session 020 Outline — Bug Fixes & Planning

> **Session Preparation**: Review the import/export work from sessions 018–019 before starting.
> Walk through the admin UI end-to-end and note anything that feels rough or broken. Pull up
> `future-sessions.md` and the two feature notes below — they affect what the planning portion
> should produce.

---

## Goal

Fix known bugs from sessions 018–019, do a general admin UI quality pass, and produce scoped
session outlines for the two new roadmap items: saved import field maps and custom contact fields.
The planning output from this session becomes the prompts for the sessions that follow.

---

## Known Bugs / Issues to Address

*(Add to this list as bugs are discovered before the session starts.)*

- [ ] Review import flow end-to-end with real Wild Apricot data and note any edge cases missed
- [ ] Confirm `QUEUE_CONNECTION=sync` is set in `.env` and `.env.example`
- [ ] Check that the docker-compose `worker` service does not cause confusion — document or remove
- [ ] Review error handling in `ImportProgressPage::tick()` — verify errors surface cleanly in UI
- [ ] Any other bugs noted since session 019

---

## Planning Work

### Feature 1: Saved Import Field Maps

**The problem:** Hard-coded presets in `FieldMapper.php` don't scale. Every client's CSV is
slightly different, and collecting enough real-world exports to write good presets is impractical.
The real solution is for the person doing the install to map the data once and save it.

**What to spec out:**

- A `FieldMapProfile` model (name/label, model_type, mapping JSON, created_by, timestamps)
- Save button at the end of the Map Columns step — prompts for a label, saves the profile
- On the Upload step, after reading headers: check all saved profiles for a header-set match
  (exact match on normalised sorted headers) and pre-fill the mapping automatically if found
- Profile management UI (list, rename, delete) — probably a simple Filament resource
- What happens on a partial match (some headers match, some don't): suggest the closest profile
  but don't auto-apply silently
- Whether to keep the generic/Bloomerang/Wild Apricot presets as built-ins, or drop them in
  favour of this system entirely

**Open questions to answer during planning:**
- Should profiles be per-user or organisation-wide? (Organisation-wide is almost certainly right.)
- Should a profile be tied to `model_type` (contacts only for now) or generic?
- How do we handle the case where the same org exports the same system but column order changes?
  (Header-set match ignores order — already the right call.)

---

### Feature 2: Custom Contact Fields

**The problem:** During import, some source columns don't map to any standard contact field.
Currently those columns are silently dropped. The importer should offer a "Create field?" option
per column so data isn't lost.

**What to spec out:**

- A `custom_fields` JSONB column on `contacts` (or a separate `contact_custom_fields` table —
  decide at session start based on query needs)
- Admin UI to define custom fields: name, label, type (text, number, date, boolean, select),
  optional select options
- Import integration: on the Map Columns step, columns with no standard mapping show a
  "Create as custom field" option alongside the existing ignore/map choices
- Contact detail view and edit form must surface custom fields dynamically
- Export must include custom field columns
- Whether custom field definitions are per-organisation or global (per-org is right for a
  multi-tenant product, but this is currently single-tenant — decide scope)

**Open questions to answer during planning:**
- Should custom field values be queryable/filterable in the Contacts list? (Probably yes, but
  adds complexity — decide MVP scope.)
- Are custom fields contact-only for now, or should the architecture allow other models later?
  (The user has confirmed contacts-only for now — design for extension but don't build it yet.)
- Field types needed for v1: text is the minimum; decide whether date/select/boolean are in scope.

---

## Outputs Expected from This Session

1. Bug fixes committed to main
2. A written `session-021-outline.md` covering Saved Import Field Maps (scoped and ready to prompt)
3. A written `session-022-outline.md` covering Custom Contact Fields (scoped and ready to prompt)
4. `future-sessions.md` updated to reflect the new sequence

---

## What This Unlocks

- Installers can capture client data mappings once and reuse them — no code changes per client
- No data is silently dropped during import — unknown columns become new fields
- The import flow becomes genuinely self-service for whoever does the initial client setup
