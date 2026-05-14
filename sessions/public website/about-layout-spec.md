# About Page — Layout Specification

This document specifies the structural shape of the About page. It is the companion to `home-layout-spec.md` — same conventions, same authority hierarchy, same gap-surfacing protocol.

The About page is the deeper read for visitors who clicked "More about how this works →" from the home page. It is not a sales page. It is the founder's argument for why this product exists, executed as one short business case followed by one short execution note. The home page has already done the introduction; About goes the next level down.

## Vocabulary

Same as `homepage-layout-spec.md` — band, cell, heading sizes, image audit, etc. Reference that document for any definition not redefined here.

**Link convention for this page.** Several CTAs and inline links on this page point to destinations that may not yet exist (docs subdomain, etc.). For any link target that does not yet exist in the system, use `#` as the href. Do not log these as gaps; the user is handling outbound links manually before the site goes live.

**The Cherny pull-quote slot in Band 2 is a placeholder.** The user will replace it with the verbatim quote and attribution after watching the source video. The spec frames the band so it reads cleanly even if the quote slot is temporarily empty — the surrounding argument carries the band on its own.

## Page-level structural plan

Three bands replace the current six-section stack:

1. **Hero** — preserved in place (existing copy and intent), structural layout updated to a 2-column shape consistent with the home page hero.
2. **The business case** — why small software can compete with SaaS now. New argument, partly drawn from the random-copy doc but written fresh. Houses the Cherny pull-quote.
3. **How this gets built** — agentic coding posture, security posture, audit-scheduled-July. Compresses and reframes the current "How the work gets done" / "Security" / "The code" sections.

The existing "Not a SaaS company," "What I don't do," and link-row sections are cut from About. The first two are redundant with the home page. The link row is folded into the closing CTA pattern of Band 3.

---

## Band 1 — Hero

Copy carries over from the current About page; the layout is updated to a 2-column shape consistent with the home hero, and the heading anatomy is normalized (one H1, no page-title duplication of the nav crumb).

**Layout:** 2-column grid, `grid_template_columns: "3fr 2fr"` (60/40 split, text left, image right). Same proportions as the home hero — establishes a structural rhyme between the two pages' top bands.

**Background:** the existing gradient — `linear-gradient(135deg, #0a2540, #60a5fa)`. Same as the home hero.

**Padding:** 100px top, 200px bottom on the layout. Matches what shipped on the home hero after the layout revision.

**Fullscreen behavior:** none. The Hero widget's `fullscreen` attribute is not used on this band; band height is controlled by layout padding alone. The full-bleed-on-any-widget feature is on the roadmap but is post-v1, so the spec does not depend on it. Do not enable `overlap_nav` (gap G12 still applies).

**Left cell:**
- Text widget. White text color via `appearance_config.text.color`. Copy carries over from the current About page; the heading anatomy is normalized below.
- H1: *My name is Al.*
- Body: *I am a developer. I have been building websites and web applications for 30 years, working with CMS's and CRM's that whole time. I built this product because the tools that exist for nonprofits aren't good enough, and I had a clear idea of what a better one would look like.*
- Body: *I have run large systems, managed teams, and worked at Silicon Valley SaaS companies. I have also done a lot of consulting for nonprofits — migrations, integrations, fixing other people's messes. This product comes out of both sides of that experience.*

Note: the page title "About" is not rendered as an H1 inside the hero. The nav crumb and browser tab already establish what page the visitor is on; duplicating it in the hero gives the page a different hero anatomy than the home page (which has a single H1 lede) and visually wastes the H1 slot on a redundancy.

**Right cell:**
- Image widget. Aspect ratio 4:3. Label: `about-hero-portrait`. Placeholder from the sample image library; the eventual swap is a founder portrait that pairs with but does not duplicate the home hero's founder portrait.

---

## Band 2 — The business case

**Layout:** 3-column grid, `grid_template_columns: "1fr 2fr 1fr"`. The outer cells are empty spacers; the center cell holds the band's content. This constrains the reading width to approximately half the container width (~600px at the default 1200px container), which is the right shape for the argumentative passage that lives here. The CMS does not currently expose a per-band inner-content max-width control; the spacer-cell pattern is the cleanest workaround using existing primitives.

**Background:** white (`#ffffff`) on the layout container.

**Padding:** 150px top, 100px bottom.

**Left and right cells:** empty. No widget; the cells exist solely to constrain the center column's width.

**Center cell:** four separate Text (text_block) widgets in vertical order. Splitting the band's content across multiple widgets (rather than packing it all into one rich-text blob) is what makes the pull-quote treatment work — the pull-quote's distinct appearance comes from being its own widget with its own appearance_config, not from a Quill blockquote inside surrounding paragraphs. Order and content of the four widgets:

**Widget 1 — Section heading and lead paragraph.** Black text.
- H2: *Small software can do this now.*
- Body paragraph: *For most of the last decade, building software was expensive enough that small shops couldn't seriously compete with venture-backed SaaS companies on feature scope. That changed in 2025. AI-assisted development crossed the threshold where a single developer with the right tools can build and maintain software at a scope that previously required a team. The implication for niche software markets — including nonprofit tools — is that the cost advantage of large SaaS providers has collapsed. A single developer can now build a focused product, charge a tenth of what the incumbents charge, and still make a living.*

**Widget 2 — Pull-quote.** Its own text_block widget. Visually distinguished via `appearance_config`: left padding to indent the block, a left border (1-4px solid in a neutral accent color), increased font weight or italic via Quill formatting inside the content, slightly larger size if available.
- Content: *"[Pull-quote from Boris Cherny, Anthropic, on the trend of small startups disrupting incumbent spaces via AI-assisted development. To be filled in by user after watching source video at https://www.youtube.com/watch?v=SlGRN8jh2RI, ~12:30 timestamp.]"*
- Attribution line below the quote (smaller, right-aligned or left-aligned per available alignment options): *— Boris Cherny, Anthropic*

If the appearance_config available on Text widgets does not include the padding/border combination needed to make the pull-quote visually distinct, log a gap recommending a pull-quote variant in the typography system. Acceptable workaround for this pass: bold + italic content with vertical padding above and below the widget to set it apart from the surrounding paragraphs.

**Widget 3 — Outsourced-providers argument.** Black text.
- Body paragraph: *This product is the version of that thesis for nonprofit software. I have outsourced the parts of the stack that should be outsourced — payments go through Stripe, bookkeeping through QuickBooks, email through Mailchimp. The providers nonprofits already trust handle the parts of the system that need institutional weight behind them. What this product does is provide the unified interface to those tools, plus the CMS, CRM, member portal, and event handling that incumbents charge ten times as much to provide a worse version of.*

**Widget 4 — Closing paragraph.** Black text.
- Body paragraph: *That's the business case. One developer, focused product, real integrations with the providers you already use, flat pricing because there's no growth-at-any-cost machine behind the scenes.*

---

## Band 3 — How this gets built

**Layout:** 2-column grid, `grid_template_columns: "2fr 3fr"` (image left smaller, text right). Mirrors the home page's Small Data band proportions — creates a structural echo for "principles" content.

**Background:** `#373c44` (the dark band). Pulling this band into dark serves two purposes: it visually separates "the business case" from "how it works under the hood" as two distinct chapters of the About page, and it gives the page a heavy closing visual moment instead of trailing off on white.

**Padding:** 150px top, 150px bottom.

**Left cell:**
- Image widget. Aspect ratio 1:1. Label: `about-execution-supporting-image`. Placeholder from the sample image library.

**Right cell:**
- Text widget. White text color via `appearance_config.text.color`.
- H2: *How this gets built.*
- Body paragraph 1: *This product is built with AI tools in the loop. As of late 2025, agentic coding has been adopted by software teams across most industries — the line where AI-assisted development beats hand-written code on speed and consistency has been crossed, and the practice is no longer experimental. What it means in practice for this product is that a single developer can move at the pace of a small team, with tighter discipline around security and process than most codebases enforce.*
- Body paragraph 2: *The whole repo is public. You can read what's there. Nothing about how this product is built is hidden behind a "trust us" claim.*
- H3 below paragraph 2: *Security posture*
- Body paragraph 3: *The shortest version of good security hygiene is: store as little data as possible, be transparent about what you do store, and put a real wall around it. This product is built on that principle. The system rejects data that pattern-matches sensitive PII (SSNs, routing numbers, card numbers) at the input layer. Refunds are recorded but issued through Stripe — the app cannot move money outward, which is the most common abuse vector for systems like this. Each customer gets a single-server instance that backs up daily. It's a small target with a real wall in front of it.*
- Body paragraph 4: *A full third-party security audit is scheduled before the product's general-availability release on July 1. The audit report will be published on this site once complete.*
- CTA row below body: `[Read the repo]` (dark-readable secondary variant, → `https://github.com/abeuscher/npc-beta/`) and `[Read the docs]` (dark-readable secondary variant, → `#`) and `[Try the demo]` (primary, → `/demo`).

CTA button styling on the dark background: gap G3 is open — the `text` button variant uses blue text designed for light backgrounds and reads poorly on `#373c44`. Do not use the `text` variant on this band. Both non-primary CTAs (`Read the repo` and `Read the docs`) should use the dark-readable secondary variant if one exists; if the button system does not yet have a dark-background secondary variant, use the standard secondary variant and log the regression against G3 rather than substituting the unreadable `text` variant. The primary CTA (`Try the demo`) is unaffected; the primary button works on both light and dark backgrounds.

Three CTAs is one more than the home page's bands use, but it's defensible here because each CTA points to a meaningfully different destination (proof, depth, action). If the existing button styles don't pair well at three-across on the dark background at the target viewport, acceptable workaround: stack them vertically or wrap to two rows.

---

## Cross-band rhythm

| Band | Layout shape | Background | Visual weight |
|---|---|---|---|
| 1. Hero | 2-col, text-image | Gradient | Heavy |
| 2. Business case | 3-col with spacer cells (1fr 2fr 1fr) | White | Medium |
| 3. How this gets built | 2-col, image-text | Dark | Heavy |

Three bands is the right length for an About page. The current page has six small sections stacked single-column; three larger sections with distinct backgrounds and column shapes will read substantially differently — less like documentation, more like a brief argument with a beginning, a middle, and an end.

## Copy redundancies that are intentionally cut

The following content exists on the current About page and is intentionally not carried into the new version:

- **"Not a SaaS company"** subsection — the home page already has a full band titled "This is not a SaaS company." with the same argument. Repeating it on About is redundant.
- **"What I don't do" list** — same three bullet points appear on the home page. Cut entirely from About; the argument is made via the home page link and via Band 2's third paragraph here ("there's no growth-at-any-cost machine behind the scenes").
- **"The code" section** — the substance is absorbed into Band 3's "The whole repo is public" sentence and the closing CTA row.
- **`[Repo] · [Docs] · [Contact]` link row at page bottom** — replaced by the three-CTA row at the end of Band 3.

If the agent encounters resistance from the existing About export's structure (e.g. the gap-surfacing protocol flags "existing pages are ground truth #1"), this spec overrides that authority for the About page. Same as the home spec did for the home page. Build the three bands above; the existing About page export is reference material for valid JSON, not a constraint on structure or content.

## Authority

Same hierarchy as the home spec. This document overrides layout interpretations from `brief.md` and `copy.md` for the About page only. Voice and tone match the current home page — plainspoken-anti-SaaS, not snarling. The random-copy document in the project folder is not a tone source; arguments may be drawn from it but the register matches the home page voice.