# 008 — Spatie Package Ecosystem for Cross-Cutting Concerns

**Date:** March 2026
**Status:** Decided

---

## Context

Several cross-cutting concerns require package support: role-based access control, activity logging, media management, custom fields, and URL slugs. These could be implemented from scratch or sourced from the Laravel package ecosystem. Spatie's packages were evaluated as a cohesive suite.

## Decision

Spatie packages are the standard solution for permissions, activity logging, media, custom fields, and sluggable behavior across the platform.

## Rationale

- Spatie packages are among the most downloaded and best-maintained Laravel packages. They are actively developed, well-documented, and widely deployed in production
- The packages are designed to work together within a Laravel application without conflicts
- `spatie/laravel-permission` is the de facto standard for RBAC in Laravel
- `spatie/laravel-activitylog` provides the event capture layer for the system transparency requirement with minimal configuration
- `spatie/laravel-medialibrary` handles file uploads, conversions, and storage with a clean Eloquent interface
- `spatie/laravel-schemaless-attributes` provides the JSONB custom fields interface
- `spatie/laravel-sluggable` handles URL slug generation for content entities

## Consequences

- Spatie packages become a dependency that must be kept current. Their upgrade paths are generally smooth but must be tracked
- The activity log must be configured carefully to capture meaningful events only — not page views or reads — to keep the log useful and the database size manageable
- Media library storage configuration (local vs. S3) must be set per deployment
