# Widget Primitive — Migration Sketch

## Status

Companion to `widget-primitive.md`. Not a commitment, not a spec, not a sequenced roadmap ready for execution. A sketch of the shape a transition would take if executed, capturing the decisions and priorities aligned on during scoping conversations so they survive into whatever a real plan eventually looks like.

## Stance

- **This is a 2.0 track, not a v1 feature.** The existing codebase ships to beta and 1.0 on its current architecture. The widget primitive transition happens after 1.0.
- **Pragmatic, not purist.** Filament stays. It is excellent at CRUD — resources, forms, tables, relation managers, policy gates, bulk actions. Rebuilding those abstractions has no product payoff and enormous cost.
- **The widget primitive is for surfaces where composition is the point.** Dashboard grid, record-detail sidebar, public page builder, eventually email blocks. Not Filament resource edit pages.
- **Evolution, not rewrite.** Filament continues where it's good. A slot layer is added alongside it. Existing CRM widgets in `app/Widgets/*` become the contract-bound tile primitive for new slot surfaces.
- **No framework preference.** Vue stays where interactivity demands it (page builder, theme editor). Livewire/Blade stays where Filament's abstractions are load-bearing. The admin stack stays mixed, which is fine.

## What this is optimizing for

1. **Solo-developer maintainability over years.** Bounded reasoning — the ability to come back to one part of the system in six months and understand it without having to remember how it touches everything else.
2. **Faster iteration after release.** New features become "declare a contract, write a widget" rather than "trace through every layer of the app." Atomic construction as a maintenance strategy.
3. **Security posture.** Fail-closed reads. Static auditability. Explicit declaration of what every surface touches.
4. **Composability.** The same data surface rendered in multiple slots without the host knowing. A "recent donations" tile appears on the dashboard, in a contact sidebar, and in an email — one widget, three mount points.

## Preparation — inside the v1 budget

Cheap, non-blocking alignment moves. Done inside the ~40 sessions between beta-1 and 1.0, not before beta-1.

### The discipline moves (essentially zero cost)

Applied as rules during other sessions, not as discrete deliverables:

- **Polymorphic owner discipline.** Anything widget-shaped uses the existing `page_widgets` / `page_layouts` tables with new owner types. No parallel tables for "dashboard widgets" or "email blocks" if that work happens in v1.
- **Reference by handle, not UUID.** Widget types, collection handles, template keys. Handles survive schema rewrites.
- **Keep `appearance_config` slot-neutral.** Session 207 established the pattern by moving `full_width` to `layout_config` because it's canvas-specific. Preserve the split: appearance = "how it looks," layout_config = "how this slot arranges it."
- **No private extension paths.** If a feature is tempting to build as a Filament-specific JSON blob, reconsider — store it widget-shaped if it's widget-shaped.

### The `dataFields()` declaration pass (1–2 sessions)

Add a `dataFields(): array` method to `WidgetDefinition` and populate it for all ~20 widgets in `app/Widgets/*`. No resolver consumes it yet. The artifact is the point — when the 2.0 resolver lands, widget data requirements are already declared, not reconstructed from template archaeology.

### Custom fields tightening (3–5 sessions, optional)

Make custom fields typed and addressable by handle rather than opaque JSONB blobs. Has independent v1 value for users. Roughly a wash as pure 2.0 prep — either pay it in v1 or pay it in v2 plus a data migration.

### Budget impact

Cheap package (discipline + `dataFields`) is 1–2 sessions = 2–4% of the beta-to-1.0 budget. Saves an estimated 5–11 sessions at 2.0 transition time. Clear win.

## The 2.0 transition — rough sequence

Executed after 1.0 ships. Each phase gates the next; Phase 1 is load-bearing for the decision to continue at all.

### Phase 1 — Data contract prototype (1–2 sessions)

Pick two real widgets (the premise doc suggests "recent donations list" and "donor lifetime value tile" — these hold up). Write their `DataContract` declarations by hand. Build the resolver that turns contracts into DTOs. No UI yet. This is the session that tells you whether the premise survives contact with reality. **If the contract language isn't expressive enough for real cases without becoming a second ORM, stop here.**

### Phase 2 — Slot taxonomy (1–2 sessions)

Declare slots formally. For each: ambient context, layout constraints, config surface. Minimum set for v2.0: dashboard grid, record-detail sidebar, public page-builder canvas (already exists — retrofit under the new abstraction).

### Phase 3 — First new slot: dashboard grid (3–5 sessions)

Admin dashboard as the inaugural non-page-builder slot. Rendered inside Filament's existing admin chrome. Two or three stock widgets at launch. Proves the primitive works outside the page builder.

### Phase 4 — Retrofit existing widgets onto contracts (5–8 sessions)

~20 widgets in `app/Widgets/*` migrated to declared contracts. Templates stop walking relationships. The resolver becomes the only data path. Tests updated. Also within this phase: migrate existing `collection_items` rows under widget-declared content type ownership per the Content shapes decision below, and retire the admin UI for creating arbitrary Collections.

### Phase 5 — Record-detail slot + ambient context refactor (4–6 sessions)

`PageContext` generalizes to `SlotContext` with subtypes (page, dashboard, record-detail). Record-detail sidebar becomes a slot. Widgets that need "the current contact" get it from the slot's ambient context, not from controller plumbing.

### Phase 6 — Page-builder convergence (1–2 sessions)

Ensure the page builder consumes the same primitive. Should be mostly rename/refactor — the page builder is already widget-shaped; the data path becomes contract-bound like every other slot.

### Deferred

- **Email template block slot.** Not before 2.0 is shipped and stable.
- **Form field slot.** Custom field types as widget instances inside constituent forms. Stretch.
- **Write contracts.** The mutation-side analogue of data contracts. Writes continue on Filament's existing paths for 2.0.
- **Public extension API.** Documenting "how to write your own widget" for third parties. Not before the contract has been dogfooded internally for at least one full development cycle.

### Rough transition budget

Phases 1–6 ≈ 20–30 sessions. Can be interleaved with feature work after 1.0 ships, not a single-track blocking initiative.

## Design decisions locked in

- **Reading abstraction first.** The contract layer guards reads. Writes continue through Filament's existing paths until a separate mutation-contract story is built.
- **One resolver, hardened.** Not fifty consumer-side checks. Concentrated enforcement at a single boundary. This is the GNAP posture: one authorization server, not distributed trust.
- **Contracts versioned from day one.** Every contract declares its version. Old instances know what version they resolved against. No untracked drift.
- **DTOs are narrow and short-lived.** Each render cycle produces a fresh, minimal payload scoped to the contract. No long-lived object references handed to widgets.
- **Batching designed in.** Resolver accepts a list of contracts. Twelve widgets on a page = one coordinated resolution, not twelve cascades.
- **Structured, typed declarations.** No stringly-typed scopes. Contracts are typed PHP objects. The declaration surface is a first-class versioned artifact, reviewed like API.
- **Capability grain varies by source.** Fail-closed per-field discipline applies where the data is sensitive and the relationships are walkable. `SOURCE_SYSTEM_MODEL` and `SOURCE_WIDGET_CONTENT_TYPE` keep per-field declaration — widgets must state exactly which columns or content-type fields they read. `SOURCE_PAGE_CONTEXT` treats the source itself as the capability: contracts for this source declare no `fields`, and the resolver returns the full `PageContextTokens::TOKENS` map. The token set is a small, bounded artifact of public page metadata (title, date, author, excerpt, starts_at, location — plus whatever additive tokens land over time). Adding a token is a grep-visible edit to `PageContextTokens`, reviewed there, and made available to every richtext consumer simultaneously. No per-widget union declaration, no drift between widgets, no separate `SOURCE_*` constant for the richtext case. Decided in session 210 after the initial Phase 1 findings flagged the richtext-consumer case — the right read was that per-field granularity had never been meaningful for a six-scalar public-metadata set.
- **Source policy is the write-side complement to the read-side contract.** Reads are gated per-field by `ContractResolver`; writes are gated per-source by a singleton `DataSink::write($modelClass, $source, $payload)`. Sources are a closed set of well-known string constants on `App\WidgetPrimitive\Source` (`HUMAN`, `DEMO`, `IMPORT`, `GOOGLE_DOCS`, `LLM_SYNTHESIS`, `STRIPE_WEBHOOK`) — adding one is a grep-visible boundary edit. Every write target declares its policy model-locally via the `HasSourcePolicy` trait with an `ACCEPTED_SOURCES` constant; `Source::HUMAN` is a universal pass (Filament forms keep writing through Eloquent unchanged). `Collection` overrides the trait's default to read per-row `accepted_sources`, and `CollectionItem` delegates to its parent collection — collections default to `["human"]` and the admin widens by explicit consent at creation. Fail-closed at every target: unknown source, missing trait, or rejected source all throw. No central registry — locality of policy-to-model is the point, and "who reads `Donation`?" remains a `grep Donation` against declared `ACCEPTED_SOURCES`. Scope is boundary, not field-level mutation authorization (that is the 2.0 write-contract story). Decided and landed in session 213.

## Security posture shift

What the contract layer buys:

- **Fail-closed reads.** A widget that forgets to declare a field simply doesn't render that field. Missing data is visible and testable; leaked data is silent. The dominant failure mode shifts from "data leaks silently" to "feature incomplete."
- **Static auditability.** "Who reads `contacts.ssn`?" is a grep against declared contracts, not a runtime instrumentation exercise. Compliance reviews become tractable.
- **Scope-narrowing is free.** Drop a field from a user's permission scope; the resolver stops populating it across every widget simultaneously. No consumer-side audit.
- **Contract diffs as security artifacts.** Widget PR diffs tell you exactly what the widget's data access changed.

What it does not buy:

- **Writes.** Separate concern. Filament's mass-assignment + policy story still governs mutations.
- **IDOR.** Resolver must be context-ownership-aware ("does this user actually own this contact context?"). Doesn't fall out of the contract alone.
- **Classical web vulns.** Injection, timing, logging leaks — all orthogonal.
- **Resolver bugs.** One hardened thing is better than fifty remembered checks, but bugs in the resolver have universal blast radius. The resolver itself must actually be hardened.

## The Filament question, resolved

Filament stays for CRUD. The pragmatic version is the decision. Specifically:

- Filament Resources, Pages, forms, tables, relation managers, policies — unchanged.
- The widget primitive and its slots sit **alongside** Filament, not inside or over it.
- New slot surfaces (dashboard, record-detail sidebar) are hosted inside Filament's admin chrome via lightweight mount points but consume the widget primitive, not Filament's own widget/form/table abstractions.
- The only scenario that re-opens the Filament question: if the Phase 1 prototype shows the contract layer cannot coexist with Filament's Livewire model. Low probability, worth naming.

## Content shapes — how users get new content types

Complementary to the Filament decision, resolved during scoping conversations for session 209. The widget primitive implicitly retires today's half-built "user creates an arbitrary Collection with whatever fields" capability. What replaces it is a three-way carve-up:

- **Widget-declared content types (the default).** Every widget that consumes collection-shaped content declares the content type it owns. Installing a widget registers its content type. Users populate items within that typed shape. Binding is by construction — no runtime validation step, no field-mapping UI, no schema drift.
- **Custom fields on system models (the augmentation path).** Data that augments existing entities — additional fields on Contact, Event, Organization — goes through the existing `custom_field_defs` / `custom_fields` pattern, extended outward from today's Contact-only implementation.
- **LLM-assisted widget authoring (the escape hatch).** Genuinely custom content shapes that no stock widget declares and no system-model augmentation fits: user describes the shape to an LLM, gets a scaffolded widget, installs it. The escape hatch is itself a widget — the architecture stays uniform. On-brand with the LLM-assisted data-prep path already on the roadmap.

The admin UI for creating arbitrary Collections is a half-built feature that was never delivered as a full self-service capability. Retiring it costs nothing the user could have done anyway. The `collections` / `collection_items` tables stay; their ownership migrates to widget-declared content types during Phase 4.

### Revenue model alignment

The three paths map onto three revenue surfaces. Stock widgets are product value. Custom widgets for paying clients are consulting revenue, contributable back to core without licensing friction because widgets are just code implementing an open primitive. LLM-assisted self-service handles the long tail at zero marginal cost. The primitive is the pricing surface, not the individual widgets — customization flows out to the broader audience without contractual or licensing entanglement.

## Known risks and open questions

Carried from the premise document and scoping conversations, worth naming without solving:

- **Contract expressiveness.** Can the declaration language describe nested relationships, aggregates, computed fields, time-series without becoming a second ORM? Phase 1 answers this or kills the project.
- **Configuration UI.** Admin needs to configure widgets in slots without writing code. Second-hardest design problem after the contract itself.
- **Asset scoping.** Blade does not scope CSS the way Vue SFCs do. Naming convention + build-step enforcement. Existing widget SCSS discipline already approximates this.
- **Upgrade safety.** When a widget contract changes between versions, what happens to configured instances? Versioning is the answer; the operational details aren't worked out.
- **Performance budget.** Twelve-widget dashboards cannot fire twelve query cascades. Batching has to be real from day one, not added later.

## Forward hooks — elective, not required

Options the architecture makes available but that no phase currently commits to. Named so the phases downstream know they exist and can reach for them if a concrete pain surfaces.

- **Appearance as a shared contract.** The typed-contract shape currently describes widget data only. `WidgetDefinition::defaultAppearanceConfig()` is already an informal declaration — a map of "which appearance fields this widget honors, with these defaults." Formalizing it into a versioned `AppearanceContract` inherited from the base class (overridable per widget) would give appearance the same auditability, slot-level enforcement, and feature-gating story the data contract has. "Which widgets honor `border_radius`?" becomes a grep across declared contracts. Phase 2 slots could declare "this slot forbids full-width" or "this slot overrides `background.color` from the grid theme" and enforce against the contract. Does not buy security (appearance isn't sensitive) or performance (`AppearanceStyleComposer` is already cheap). The right moment to formalize is when slot taxonomy (Phase 2) surfaces a slot-level appearance constraint the informal declaration can't express. Until then: elective.

## The honest framing

This is a digression, not a pivot. The product does not get better for users because of this work — it gets better for the solo developer maintaining it. The reason to do it is long-term maintainability and security posture, not feature velocity in any individual quarter. If Phase 1 succeeds, the payoff compounds over years. If it fails or the contract proves intractable, the preparation work done in v1 was cheap enough to absorb.

The architectural prize, if it works: a product where "can it do X" is by default yes, because X is a widget and widgets are how the product is built. Rimworld posture, stated plainly in the premise. This document is one possible path to earning that posture without rewriting v1.
