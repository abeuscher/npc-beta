# Fleet Manager Agent Contract

**Contract Version:** `0.0.0`
**Status:** stub — surface not yet authored
**Owner repo:** [npc-beta](https://github.com/abeuscher/npc-beta) (CRM)
**Consumer repo:** Fleet Manager (separate repo, to be created)
**Canonical URL:** `https://raw.githubusercontent.com/abeuscher/npc-beta/main/docs/fleet-manager-agent-contract.md`

---

## What this document is

The single source of truth for the HTTP contract between **NonprofitCRM installations** (which expose an authenticated health endpoint) and the **Fleet Manager** operational tool (which polls every install on a schedule).

Both the CRM and Fleet Manager implement against this contract. The CRM emits the surface; Fleet Manager consumes it. When the contract changes, both sides update before the next boundary-touching session in either repo. See `sessions/fleet-manager-planning-spec.md` ("Two-Repo Coordination Protocol" section) for the discipline.

---

## Why this document exists *before* the contract is defined

The contract surface is unbuilt as of session 237. The first real version (`1.0.0`) lands in the CRM-side **Fleet Manager Agent — Phase 1** session, which authors the `/api/health` route, the auth middleware, the response schema, and writes those into this doc.

This stub exists today so:

- The path is reserved (`docs/fleet-manager-agent-contract.md`) and the canonical URL is stable.
- Both repos' prompt templates can reference the doc as a real file.
- The Cross-Repo block in `sessions/session-outlines.md` has a non-broken pointer.
- The Fleet Manager repo, when created, can `WebFetch` against the canonical URL from day one.

---

## Contract surface (placeholder — to be authored at v1.0.0)

When v1.0.0 lands, this section will describe:

- **Endpoint path** (e.g., `/api/health`)
- **Auth handshake** (bearer token in `Authorization` header per the planning spec lean)
- **Request schema** (likely empty — GET request, no body)
- **Response schema** — top-level fields and per-subcheck shapes:
  - `status` (overall — `green` / `yellow` / `red`)
  - `version` (CRM application version string)
  - `timestamp` (ISO 8601 server time)
  - `subchecks`: object keyed by subcheck name, each carrying `{status, value, threshold, message}` or similar
  - Subcheck names: `app`, `database`, `redis`, `disk`, `last_backup_at`, plus any client-defined custom checks
- **Version negotiation** — how the response includes the contract version it speaks; how Fleet Manager handles older contract versions if the fleet has heterogeneous CRM versions
- **Error envelope** — shape returned on auth failure, on internal error, on disabled-endpoint state
- **HTTP status codes** — when 200 vs 401 vs 503 vs other

---

## CHANGELOG

### `0.0.0` — 2026-04-28 (session 237)

Stub created. Surface not yet authored. The CRM-side Fleet Manager Agent — Phase 1 session creates v1.0.0 with the actual HTTP contract.
