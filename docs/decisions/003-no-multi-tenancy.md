# 003 — Single-Tenant Architecture (No Multi-Tenancy)

**Date:** March 2026
**Status:** Decided

---

## Context

The platform is designed to serve individual nonprofit organizations. A shared multi-tenant deployment would allow multiple organizations on one database, reducing infrastructure cost. However, nonprofits handle sensitive personal, financial, and grant data. Data bleed between tenants — even partial — would be a serious compliance and trust failure.

## Decision

One installation per client. One server, one database, one codebase instance. No multi-tenancy at the application layer.

## Rationale

- Eliminates the risk of data bleed between organizations entirely — isolation is enforced by infrastructure, not application logic
- Simplifies the data model significantly. No `tenant_id` columns, no tenant-scoped query scopes, no risk of scope bypass bugs
- Simplifies deployment — each client is a standard Laravel Forge provisioned server
- Simplifies offboarding — a client's entire installation can be shut down and their data exported or deleted without touching any other client
- Managed hosting per client is the business model. Infrastructure cost is passed through; simplicity is the product advantage

## Consequences

- No "app store" style shared SaaS deployment. Each client requires a provisioned server
- Pricing and sales must account for per-client infrastructure costs
- Upgrades must be rolled out per-client. An upgrade pipeline (Envoyer) is part of the deployment strategy
