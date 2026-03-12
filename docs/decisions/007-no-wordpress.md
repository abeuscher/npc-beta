# 007 — No WordPress Dependency

**Date:** March 2026
**Status:** Decided

---

## Context

Many nonprofit organizations are familiar with WordPress. An early option considered was building on top of WordPress or making the platform compatible with WordPress installations. This was evaluated and rejected.

## Decision

The platform has no WordPress dependency, no WordPress compatibility layer, and no WordPress packages of any kind. It is a standalone Laravel application.

## Rationale

- WordPress's security surface area is large and requires constant maintenance. Plugins introduce unpredictable vulnerabilities
- Running Laravel as a WordPress plugin creates two full application stacks fighting each other for routing, authentication, and database access
- WordPress's data model (posts, meta, options) is poorly suited to relational CRM data
- Twill provides a Laravel-native CMS experience with a better editor UX for non-technical content editors than WordPress
- Architectural conflict: WordPress's conventions (global state, hooks, procedural patterns) are incompatible with Laravel's service container, Eloquent ORM, and middleware stack

## Consequences

- Content editors familiar with WordPress will require onboarding to Twill. Twill's editor is similar in concept but different in implementation
- The platform cannot be installed as a WordPress plugin or theme
- Any client currently on WordPress will require a migration path — this is a deliberate offboarding from WordPress, not a coexistence strategy
