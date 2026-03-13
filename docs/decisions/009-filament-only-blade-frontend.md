# 009 — Filament-Only Admin, Blade + Livewire Public Frontend

**Date:** March 2026
**Status:** Decided — supersedes `docs/decisions/004-twill-plus-filament.md`

---

## Context

Session 002 revealed that running Twill alongside Filament created structural problems that could not be resolved without either inheriting Twill's full model stack or maintaining a growing shim layer. The attempt to unify authentication by pointing Twill's user model config to `App\Models\User` caused repeated `BadMethodCallException` errors as Twill's screens called methods from its own trait stack (`IsTranslatable`, `HasMedias`, `HasPresenter`, etc.) that our model did not have.

This triggered a broader review of whether Twill was the right tool for the must-have features of this product.

## Must-Have Features Reviewed

- Events and event registration
- Member portal / gated content
- Newsletter signup and unsubscribe
- Member signup
- Donation forms with Stripe processing and QuickBooks sync

**Every one of these is a data and transaction problem, not a content editing problem.** They are forms that create records, pipelines that sync data, and authenticated views that display records. None requires a block editor.

The public website for a nonprofit at this scale is primarily a shell hosting these features, with some supporting content (event descriptions, an About page, a News section). Staff updates content a few times a month. This does not justify the complexity of a second admin panel built on a different runtime.

## The Two-Runtime Problem

Filament runs on **Livewire** — server-rendered HTML with reactive updates. Twill is a **Vue SPA** — a compiled JavaScript application that boots in the browser. They cannot share a header, a navigation state, or a session indicator without a full page reload between them. Navigation between the two panels always tears down one runtime and boots the other. Presenting this as a unified product to end users is not achievable.

## Decision

Remove Twill. Use Filament as the sole admin panel for both CRM data and content management. Serve the public website via **Laravel Blade templates** with **Livewire** for interactive components (forms, registration flows, member portal).

## Stack

| Concern | Tool |
|---------|------|
| Admin panel (CRM + content) | Filament PHP |
| Rich text editing | Filament TipTap plugin |
| Block-style page composition | Filament Builder field |
| Public page rendering | Laravel Blade templates |
| Interactive public components | Livewire |
| UI behavior (dropdowns, toggles) | Alpine.js |
| Styling | Tailwind CSS |

## Consequences

- One codebase, one runtime, one auth guard (`web`), one admin panel login
- Content editing experience is adequate (TipTap + Builder) but less visual than Twill's block editor — acceptable for the target user (staff, not a dedicated content team)
- Public page templates are Blade files — the design is controlled by the developer, not the editor. This is intentional for v1.
- 39 packages removed from `composer.json` with Twill
- `cms_editor` role renamed to `staff` — the Spatie role concept survives, only the Twill-specific name changes
