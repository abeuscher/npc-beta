# Housekeeping Inbox

Small items noticed between sessions. One bullet per item, free-form text. When the inbox accumulates 5–10 items, batch them into a housekeeping session (e.g. Phase E's E12 "Housekeeping Batch 2"). Items that grow into "own session" shape get promoted to `release-plan.md` entries.

This file is the only home for items that are *small enough to bundle but large enough to break scope when absorbed mid-session by a feature*.

---

## Inbox

*(Items destined for the next housekeeping batch session. Items default to **pre-Public-Website-Complete** unless tagged `[post-milestone]` — see Rule 12 in `release-plan.md`.)*

- Hero widget: button-group alignment control (left / right / center).
- Text editor: changing text color should not change the editor's own preview color — white text becomes illegible on the light editor background.
- Default paragraph and list-item padding: replace the zero-reset with ~6px top / ~12px bottom on `p` and `li`.
- Hero widget: expose a control for how the hero text block's max-width is bound inside the widget.
- `[post-milestone]` Random Data Generator widget: add Organizations to the entity-generation list. *(Admin tool — doesn't block the public website demo.)*
- Logo widget: respond to the hero widget's text-color override when the logo sits on a full-bleed hero (so the logo doesn't fight the chosen text color).
- Logo widget: default `href` = site home, with override available in the inspector.
- Default logo image: ship a placeholder logo asset so a fresh install has one.
- Logo widget: text field → rich text + appearance control (small scope; just enough to set color / weight / size).
- Default button style refresh — quick visual pass (not blue-on-blue, gentle gradients, hover states). Pre-design-system-editor stopgap.
- Column layouts: default `layout_config.background_full_width` to `true` for parity with widget defaults shipped at E10 (verified s282 — widget_types ships bg=true/content=false; column layouts still fall back to bg=false in `AppearanceStyleComposer::resolveColumnLayoutFullWidth`).
- Link-color mechanism consolidation (s290): there are now **three** link-recolor mechanisms and they need a clarity pass. (1) Nav widget's own `--nav-link-color` (Nav inspector, sets its own link color). (2) Hero's `nav_link_color` / `nav_hover_color` reach-over — a Hero knob that restyles the *site-chrome nav* (a sibling widget) but only when `overlap_nav: true`; effectively dead on this site because G12 forced overlap off everywhere (logo-contrast). (3) New generic `text.link_color` → `--np-link-color` (s290 G22 resolution) — recolors content links inside any widget; does NOT reach the nav. These are not duplicates (they target different elements), so the s290 link-color work did **not** make #2 removable — #2 is the real smell (a widget reaching across to restyle another widget), and that decision belongs to **G12** (Hero↔Nav overlap-nav / logo-contrast coupling), not to the link-color feature. Also a real UX-confusion point: a Hero inspector showing both "Link Color" (#3, hero content) and "Nav Link Color" (#2, site chrome, overlap-only) will confuse operators — fix is labeling + the G12 overlap decision. Cross-ref G12/G21/G22 in `sessions/public website/gap-report.md`.
- Contact hero readability overrides (s290, related): the hero also stacks two coexisting "make content readable on the gradient" workarounds — the WebForm widget's white-card `background.color` (G21 form-label workaround) and the email-fallback widget's `text.link_color: #ffffff` (G22 resolution). Both work side by side, not urgent. Same housekeeping pass: once G21 lands the same `--np-*-color` var pattern the white card may be unnecessary; either way annotate the remaining overrides in `sessions/public website/contact.json` so a future editor understands *why* the hero carries them.

---

## Promotion candidates (need release-plan.md entries)

*(Items too big to bundle. Need their own entry before scheduling.)*

- ~~**Table widget.**~~ Promoted at 282 audit → **E15** in `release-plan.md`. Pre-Public-Website-Complete.
- ~~**Header / footer defaults overhaul.**~~ Promoted at 282 audit → **E16**. Pre-Public-Website-Complete.
- ~~**Borders on widget controls + columns.**~~ Promoted at 282 audit → **E17**. Pre-Public-Website-Complete (may fold into Design System Editor track if it lands first).

---

## Folded into existing entries

*(Items absorbed by an existing release-plan entry — captured here as a paper trail.)*

- **Text editor reachability fix + Quill full-screen button + Playwright usability test** → folded into **E8 (UI/UX Sprint)**. E8 already covers Quill drag-resize handle; expand its scope at session start.
- **Default button style long-term parameterization** → folded into the **Design System Editor** track (already on radar — "buttons first").

---

## Recently dispositioned

*(Log of what left the inbox and where it went. Keep the last ~2 batches' worth.)*

- **2026-05-13 (session 282 close):** 16 housekeeping items surfaced from operator running list:
  - 11 → Inbox (above)
  - 3 → Promotion candidates (Table widget; Header/footer overhaul; Borders pass)
  - 2 → Folded (E8 absorbs text-editor scope; Design System Editor absorbs button parameterization)
  - 1 → Verified as already shipped at E10 for widgets (s267); column-layouts parity gap lifted into Inbox as its own item.

---

## Disposition rules

When walking the inbox at session start / close, each item leaves via one of:

- **(a)** Fold into the next available housekeeping batch session (the default for inbox items).
- **(b)** Fold into an existing planned entry — note here under "Folded into existing entries" and cross-ref in the target entry's session prompt.
- **(c)** Promote to its own `release-plan.md` entry — move from Inbox to "Promotion candidates" first, then add the entry, then remove from this file.
- **(d)** Drop as no-longer-relevant — note briefly under "Recently dispositioned" with the date.

No states, no priorities, no timestamps beyond "Recently dispositioned." The list is the system.
