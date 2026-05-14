# Public Website Build — Brief

## What this is

A description of work for an agent operating inside the NonprofitCRM codebase. The goal is to build the public marketing website for NonprofitCRM using the product's own page builder and import/export format — both to ship a marketing site and to dogfood the CMS surface that nonprofit customers will use.

This document, the copy document, the reference images, and the existing page export are the complete input. The agent should read all of them, plan a work sequence, and produce JSON page exports plus a gap report.

## What the agent should read before doing anything

In order:

1. This file end to end.
2. `copy.md` for the words.
3. `existing-home-page-export.json` to confirm the JSON format.
4. The current `About` and partial `Pricing` page exports from the system (export from admin, place in this folder as `existing-about-page-export.json` and `existing-pricing-page-export.json`).
5. `resources/docs/widget-development.md` in the codebase for widget definitions, config schemas, and the appearance system.
6. The typography system file(s) and the button style definitions (locate these in the codebase — likely under `resources/scss/` and the Hero/buttons widget definitions). Report back with the list of available type scales and button styles before generating pages.
7. The reference images in `references/` — index in `references.md`.

After reading, the agent should produce a short understanding summary (page list, system conventions, open questions) before writing any JSON.

## Project context

NonprofitCRM is a Laravel/Filament/PostgreSQL/Blade application targeting small nonprofits as an open-source alternative to Wild Apricot and Neon CRM. It is built and supported by one developer. The public website is being built inside the product's own CMS using the existing widget and page builder system.

The buyer is a small-nonprofit executive director or operations lead. They are not a designer or a developer. The voice the site needs to maintain is plain-spoken, signed-by-a-person, deliberately anti-SaaS — closer to Cal.com or Plain in tone than to Clay, Gong, or Attio.

The visual grammar the site is mimicking — section-as-slab with alternating background bands, generous whitespace, restrained type scale, real product screenshots, no animations — is a deliberate borrow from current B2B SaaS marketing pages. The structural conventions are being copied; the surface decoration (custom illustration, marketing-bright color saturation, abstract metaphor imagery) is not.

## Page list

Five marketing pages:

1. **Home** — exists in system, copy is finalized, may need structural cleanup to align with conventions below.
2. **About** — exists in system. Expands on the "This is not a SaaS company" theme. Links to the two in-product demo LPs as live feature demonstrations.
3. **Pricing** — partial in system. Elaborates on the $150/month flat pricing and the à la carte options.
4. **Contact** — new build. Email-and-short-message destination, deliberately simple.
5. **Demo** — new build. The conversion point. A form that lets visitors either access an anonymous sandbox or provide email for follow-up.

Two in-product demonstration landing pages live separately, inside the product CMS under demo nonprofit accounts. They are not in the main site navigation:

- **My Nonprofit** — donation campaign LP demo.
- **My Nonprofit Workshop** — event LP demo.

These exist as functional demonstrations of the product, not as marketing pages. About links to them. The agent should not wire them into the main site nav.

## System conventions

These are decided. Do not re-decide them per page.

**Section bands.** Major content sections are full-bleed background-color slabs. Background lives on the layout container (the `type: "layout"` block), not on the widgets inside it. Backgrounds on inner widgets are only used when contrast *within* a row is the intentional effect.

**Section padding.** 150px top and 150px bottom for major bands. 25px top and 50px bottom for widgets inside column layouts (matching the pattern in the existing home page export).

**Background tones.** Alternate between white (`#ffffff`), a light tinted band, and a dark band for emphasis. The home page already establishes `#d4d4f2` (the "What it does" tinted band) and `#373c44` (the "This is not a SaaS company" dark band). Reuse these. Do not introduce new background colors without flagging.

**Type and buttons.** The typography system and the button styles (primary, secondary, text-only) are fixed. Do not extend them. If a layout needs a button variant that does not exist (e.g. ghost-on-dark), log it as a gap rather than inventing.

**Color in content.** The existing home page mixes two color models — inline `style="color:rgb(...)"` inside Quill content and `appearance_config.text.color`. Standardize on `appearance_config.text.color` as the source of truth. Inline color in content is only for inline emphasis (a single highlighted phrase), not for setting the section's text color.

**No animations.** No parallax. No scroll-triggered reveal animations. The bar chart's load animation and the existing carousel autoplay are the only motion permitted.

**No invented widgets.** If a section would benefit from a widget that does not exist (a tabbed feature explainer is the most likely candidate), build the section in a degraded form using existing widgets and log a gap with the recommendation.

**Images.** Use the sample image library as placeholders. Mark each placeholder image in the page JSON with a note in the widget label or a custom field so a later pass can swap them for real photography. The eventual photos are staged-stock-photo-style images with the founder as the only subject — placeholders should not try to anticipate that aesthetic.

**CTAs.** Every marketing CTA across all pages routes to `/demo`. Exceptions: the "See the Code" CTA routes to the public GitHub repo, and the "Email me" CTA is a `mailto:` link.

## JSON format reference

Format version 1.1.0. Payload contains `templates` (empty for marketing pages) and `pages`. Each page has metadata fields plus a `widgets` array.

Two block types appear in the widgets array:

- `type: "widget"` — a content widget. Has `handle`, `label`, `config`, `query_config`, `appearance_config`, `sort_order`, `is_active`, `media`.
- `type: "layout"` — a column container. Has `label`, `display` (`grid` or `flex`), `columns` (1–12), `layout_config` (gap, alignment, grid_template_columns), `sort_order`, and `slots` (an array of arrays; one inner array per column slot).

Widgets inside a layout's slots have a `column_index` field and lock `full_width: false`. The `existing-home-page-export.json` is the canonical reference.

Text widget content is Quill HTML — preserve the `data-list="bullet"` and `data-list="ordered"` markup and the `<span class="ql-ui" contenteditable="false"></span>` spans inside list items. These are required by the editor.

## Authority hierarchy

When sources conflict:

1. Existing exported pages in the system (Home, About, partial Pricing) are ground truth for what the system actually produces. Preserve their structure when extending.
2. Written copy in `copy.md` is the words. Do not paraphrase.
3. Reference images are structural specs only — for section ordering, column counts, padding rhythm. Not for color choices, font choices, or imagery style.
4. The agent's own design instinct is the last resort. Substantive choices made at this level are flagged as gaps for review.

## Gap-surfacing protocol

All gaps land in a single `gap-report.md` file in this folder. Each gap entry has four short fields:

- **What was attempted** — the section, widget, or pattern.
- **What blocked it** — missing widget, missing config option, missing style, missing collection field, etc.
- **Workaround used (if any)** — what the agent did instead to keep moving.
- **Recommended action** — build a widget, extend a config, add a button style, accept the degraded version, etc.

Gaps are surfaced for discussion, not unilaterally resolved. The agent should not extend the widget system, the typography scale, the button styles, or the appearance config schema without an explicit go-ahead.

## Definition of done

The build is complete when:

- Five page JSON files are present in this folder, each valid against format version 1.1.0 and importable without errors.
- All copy from `copy.md` is placed in the appropriate widgets.
- `gap-report.md` exists and lists every gap encountered, even if the workaround is satisfactory.
- A Playwright capture of each rendered page (via the dev environment) is committed as `screenshots/page-{slug}.png`. The existing `scripts/generate-thumbnails.js` is the reference for how to invoke Playwright in this codebase — extend it or write a parallel page-capture script.
- A short `build-summary.md` lists what was completed, what was deferred, and what needs the user's input before going further.

## Files in this folder

- `brief.md` — this file.
- `copy.md` — page-by-page marketing copy.
- `references.md` — index of reference images and what each is a reference for.
- `references/` — reference images, named by function.
- `existing-home-page-export.json` — current home page, exported from the system, canonical example of valid JSON.
