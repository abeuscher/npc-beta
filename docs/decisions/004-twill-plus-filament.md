# 004 — Twill (CMS) + Filament (CRM Admin) as Dual Admin Layers

**Date:** March 2026
**Status:** Decided

---

## Context

The platform needs two distinct admin surfaces: one for content editors managing pages, posts, and media, and one for CRM administrators managing contacts, members, donations, and grants. These audiences have different mental models, different permissions, and different workflows. A single admin panel would serve neither well.

## Decision

Twill (Area 17) handles the CMS layer. Filament PHP handles the CRM admin layer. They coexist in the same Laravel application with separate routing namespaces and authentication guards.

## Rationale

- Twill is purpose-built for Laravel content management with a polished editor experience. It handles media, blocks, revisions, and multilingual content natively
- Filament is purpose-built for Laravel admin panels with a component-driven, Livewire-based UI. It handles resource management, filters, forms, and reporting workflows cleanly
- Separating the two panels gives each user type a focused, uncluttered interface
- Both are actively maintained Laravel-native packages with strong communities
- Integration boundaries (events, forms that span both surfaces) are narrow and will be documented per module

## Consequences

- Two admin routing namespaces must be maintained and kept from conflicting
- Authentication guards must be configured so CMS editors cannot access the CRM panel and vice versa unless their role explicitly grants it
- Blade view overrides for each panel live in separate directories (`resources/views/twill/` and `resources/views/admin/`)
- Integration points between Twill content and Filament CRM data must be explicitly documented as each module is built
