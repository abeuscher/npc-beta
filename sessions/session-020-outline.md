# Session 020 Outline — Help System

> **Session Preparation**: This is a planning outline, not a complete implementation prompt.
> At the start of this session, review the full admin UI as it exists. The help system should
> be designed around the actual screens and workflows users encounter, not a theoretical list.
> Walk through the admin panel before writing the prompt.

---

## Goal

Build the skeleton of a context-sensitive help system for the admin UI. The goal is not to fill in all the content — content comes later — but to establish the architecture: where help lives, how it's triggered, how it's stored, and how it can be maintained without a code deployment.

---

## Key Decisions to Make at Session Start

- **Help content storage**: In the database (admin-editable via Filament), in Markdown files (developer-maintained, version-controlled), or both? Database is more flexible for non-developers; files are simpler to maintain and version. A hybrid (files with DB override) is possible.
- **Trigger mechanism**: Help icon (?) next to fields/sections that opens a panel or tooltip? Dedicated "Help" sidebar panel? Inline collapsed accordion? Decide the UX pattern.
- **Scope**: Admin UI only, or also public-facing help (FAQ, knowledge base for members)? Likely admin only for this session.
- **Standard or custom**: Is there a Filament help/documentation plugin worth evaluating, or do we build a simple custom implementation? Check available packages at session start.
- **Context specificity**: Help can be global (one article per page), per-resource, or per-field. Decide the granularity level for v1.

---

## Scope (draft — refine at session start)

**In:**
- Help content storage mechanism (DB, files, or hybrid — decided at session start)
- Context-sensitive help trigger in the Filament admin UI (icon or panel)
- At least the skeleton structure seeded with placeholder content for 5-10 key screens
- An admin interface for editing help content (if DB-based)
- A clear extension pattern so content can be filled in without a developer

**Out:**
- Public-facing knowledge base or FAQ (future)
- Search across help content (future)
- Video embeds or interactive walkthroughs

---

## Rough Build List

- Help content storage: model/migration (if DB) or `resources/help/` directory (if files)
- Help panel or tooltip Blade component
- Filament layout modification to inject help trigger into resource pages
- Seed/create placeholder content for key screens
- If DB: Filament resource for managing help content (admin only)
- Tests: help content resolves for a given context key; missing content degrades gracefully

---

## Open Questions at Planning Time

- Who will write the help content — developers, or a non-technical admin? This determines whether DB or files is the right storage.
- Is there a preferred UX pattern from the user (tooltip, sidebar panel, modal)?
- Should help be localisation-aware (multi-language)?

---

## What This Unlocks

- Help content can be filled in alongside any future feature session
- Non-developer staff can maintain help content without code changes (if DB-based)
- Foundation for a public knowledge base if needed later
