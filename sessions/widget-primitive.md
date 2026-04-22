# The Widget Primitive: A Unified Extension Architecture for NonprofitCRM

## Status

Premise document. Not a spec, not a commitment. A direction worth pressure-testing against real v1 work, with the goal of informing architectural decisions before they calcify.

## The Core Premise

NonprofitCRM should be built around a single component primitive — call it a **widget** — that renders anywhere a component-shaped hole exists in the product. The admin dashboard, record detail pages, the public page builder, email templates, and eventually form fields are all just *slots* that accept widgets with compatible data contracts.

One shape. Many slots. No privileged core.

## The Rimworld Lesson

The game Rimworld is frequently cited as having an unusually healthy modding ecosystem. The common reading is "the devs made mods powerful." The actual mechanism is more interesting: the core game content is built using the same `Def` system that modders use. There is no privileged internal API hiding behind the public one. Stock content has no shortcuts. When a modder replaces a weapon, they are editing the same kind of object the developer originally shipped — because that is the only kind of object the engine knows how to render.

This has two consequences worth stealing:

1. **The public API is credible because it is the only API.** The core team cannot accidentally ship features that bypass the extension system, because the extension system *is* the feature delivery system.
2. **Dogfooding is not optional.** Every stock widget is a test of the widget contract. If the contract is painful to use, the core team feels it first.

The goal for NonprofitCRM is the same architectural posture: build the stock product out of the widget primitive, delay opening it as a public extension API until the contract is proven internally, and let that discipline shape v1 itself.

## Anatomy of a Widget

A widget has roughly four parts:

1. **A PHP class** (likely a Livewire component or a thin wrapper over one) that declares its configuration schema and its data contract.
2. **A Blade view** for rendering.
3. **Optional scoped JS and SCSS** that travel with the widget and do not leak into the host page.
4. **A manifest** declaring which slots the widget is valid for, what data it needs, and what configuration options it exposes.

The PHP class, the view, the assets, and the manifest travel together as a single unit — a directory, a package, eventually a distributable artifact.

## The Data Contract (The Interesting Part)

This is the heart of the premise and the part that has to be right.

Widgets **do not receive raw Eloquent models**. Handing a dashboard widget a `Constituent` model means that widget can walk relationships into donation history, notes, tags, and custody fields it has no business reading. It also means the widget's queries are opaque to the framework, which kills caching and makes N+1 bugs endemic.

Instead, widgets **declare what they need**:

```php
public function dataContract(): DataContract
{
    return DataContract::for(Constituent::class)
        ->fields(['first_name', 'last_name', 'total_given_ytd', 'last_gift_date'])
        ->requires('view-donor-summary');
}
```

The framework resolves this contract against the current user's permissions, the ambient context of the slot, and the underlying data model, then injects a scoped DTO into the widget. The widget never touches the model directly.

Three things fall out of this for free:

- **Security.** The widget literally cannot see fields it did not ask for. Permission checks happen at contract resolution, not inside widget code, so widgets cannot accidentally leak data by forgetting a check.
- **Performance.** The framework knows the query shape up front. It can batch across widgets on the same page, cache by contract hash, and surface N+1 problems as contract violations rather than runtime mysteries.
- **Composability.** Any two widgets that declare the same contract are drop-in interchangeable. A third-party "donor summary" widget can replace the stock one without the host page knowing.

## Slots: The Dual Primitive

If widgets are what gets rendered, **slots** are where they render. A slot declares:

- What contextual data is ambient (e.g., "the current Constituent record," "the current user's scope," "no record context — this is a dashboard").
- What layout constraints apply (grid cell dimensions, email-client HTML restrictions, public-page SEO considerations).
- What configuration surface the host provides to the widget (admin UI for arranging widgets, page builder canvas, etc.).

The set of slots in v1 is probably:

- **Dashboard grid** — admin landing page, arrangeable tiles.
- **Record detail sidebar** — context-aware tiles on a Constituent, Event, Campaign, etc.
- **Record detail main** — larger blocks within a record page.
- **Page builder canvas** — public-facing site blocks.
- **Form field** (stretch) — custom field types rendered inside constituent forms.
- **Email block** (v2) — building blocks for transactional and campaign emails.

The payoff is architectural: the page builder's blocks and the admin's widgets are *the same thing*. Building a donation form for the public site and building a donation stats tile for the admin dashboard are the same skill with different data bindings. Two systems collapse into one.

## What This Buys v1

Even before any of this is exposed as a public extension API, the internal benefits are real:

- **Consistency.** Every configurable surface in the app is configured the same way. Admins learn the pattern once.
- **Testability.** Widgets are isolated, contract-bounded units. They are the natural test boundary.
- **Page builder leverage.** The page builder work already in progress is the first real consumer of this primitive. Doing it right once means not rewriting it when the dashboard lands.
- **A coherent story for investors and clients.** "Every screen in NonprofitCRM is built from the same extensible components we will eventually let you write yourself" is a much stronger pitch than "it has a page builder and also a dashboard."

## What This Does Not Mean for v1

This is a v1 *architectural* goal, not a v1 *feature* goal. Specifically:

- **No public extension API in v1.** Documenting "how to write your own widget" locks the contract. The contract needs at least one full development cycle of internal use before it can be made public safely.
- **No plugin marketplace, no third-party widget loading, no signed packages.** All widgets in v1 ship in the core repo.
- **No visual widget builder.** Widgets are code. The page builder arranges and configures them; it does not author them.
- **No "write a widget from the admin UI" feature.** That is a v3+ conversation, if ever.

The discipline is: build the primitive, ship the stock product on top of it, keep the contract honest, and earn the right to open it later.

## Hedges and Future Moves

A few things this architecture quietly hedges:

- **The no-LLM-in-v1 stance.** When AI features arrive in v2, they slot in as widgets over the same data contracts. No re-architecture; new widgets with new contracts. An "AI-drafted donor thank-you" widget reads `last_gift_date` and `first_name` from the same contract surface a stock widget would.
- **Per-client customization.** A client who needs a specific dashboard tile gets a widget in their deployment. The contract keeps the customization scoped and auditable, rather than letting bespoke code leak into the core.
- **The support/ticketing idea.** Client-reported issues as a first-class feature of NonprofitCRM is a natural fit — a ticket list is a widget, a ticket detail is a slot, a "recent tickets" dashboard tile is another widget over the same data.

## Risks and Open Questions

Worth naming, not solving, before sessions on this:

- **Contract expressiveness.** Can the contract language describe every real case — nested relationships, aggregates, computed fields, time-series — without becoming a second ORM? Where is the line?
- **Livewire vs. server-rendered Blade vs. Alpine-only.** The widget class needs to accommodate interactivity without forcing every widget to be a Livewire component. Default to dumb Blade, upgrade path to Livewire when needed?
- **Asset scoping.** Blade components do not scope CSS the way Vue SFCs do. SCSS needs a naming convention and probably a build step that enforces it.
- **Configuration UI.** The admin needs to configure widgets in slots without writing code. This is its own design problem — probably the second-hardest part of the system after the data contract.
- **Upgrade safety.** When a widget contract changes in a minor version, what happens to existing configured instances? Contract versioning is a real concern even before external extensions exist.
- **Performance budget.** A dashboard with twelve widgets cannot fire twelve independent query cascades. The batching story has to be real.

## Suggested Next Sessions

1. **Data contract prototype.** Pick two real v1 widgets (e.g., "recent donations list" and "donor lifetime value tile"), write their contracts by hand, and build the resolver that turns contracts into DTOs. No UI yet — just the contract mechanics. This is the session that tells us whether the premise survives contact with reality.
2. **Slot taxonomy.** Enumerate every place in the v1 UI that should accept a widget. Decide which are v1 and which are deferred. Define the ambient context and layout constraints for each.
3. **Widget / page builder block unification.** Walk the existing page builder work and identify where it diverges from the widget model. Decide what to refactor now vs. later.
4. **The "one widget, three slots" exercise.** Take a single piece of data (say, "upcoming events for this constituent") and render it as a dashboard tile, a record sidebar block, and a public page block. If that exercise is painful, the primitive is wrong.

## The Ambition, Stated Plainly

NonprofitCRM should be a product where the answer to "can it do X?" is, by default, yes — because X is a widget, and widgets are how the product is built. Not because the core team built every X, but because the primitive is good enough that building X is a weekend of focused work rather than a roadmap item.

That is the Rimworld move. It is attainable. It requires discipline in v1 to avoid shortcuts that would undermine the premise, and it requires treating the widget contract as a first-class architectural artifact, not an afterthought bolted onto Filament.

The prize, if it works: a product whose differentiator is not any individual feature but the fact that every feature is built the same way, and the shape of that way is legible, teachable, and eventually open.