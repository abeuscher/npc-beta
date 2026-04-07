---
title: Templates
handle: templates
description: Manage page templates (colors, fonts, SCSS, header, footer) and content templates (widget stack presets).
routes:
  - filament.admin.resources.templates.index
  - filament.admin.resources.templates.create
  - filament.admin.resources.templates.edit-content
  - filament.admin.resources.templates.edit-page
category: cms
---

# Templates

**CMS > Templates** is the central place to manage both page templates and content templates. It replaces the former Site Theme page.

---

## Content Templates tab

Content templates are reusable widget stack presets. When creating a new page, you can pick a content template to pre-populate the page with a set of widgets.

- **Name** and **Description** can be edited inline.
- **Widget count** shows how many widgets are in the template definition.
- Content templates are created from the page builder using the "Save as Template" action.
- System content templates cannot be deleted.

---

## Page Templates tab

Page templates control the visual wrapper around a page: colors, fonts, SCSS, header, and footer.

- One template is marked as **Default** — it provides the base values for all pages.
- Non-default templates can **inherit** from the default for any section (colors/fonts, SCSS, header, footer) by leaving fields null.
- Click a template row to open its editor.
- Use "New Page Template" to create additional templates.
- Deleting a page template causes pages using it to fall back to the default.

### Colors & Fonts

Set brand color, header/footer background, nav link colors, and heading/body fonts. Non-default templates show an "Inherit from Default" toggle.

### SCSS

Developer-level SCSS editor (requires `edit_theme_scss` permission). For the default template, Save & Build writes to `resources/scss/_custom.scss` and runs a Vite build.

### Header & Footer

Edit the header and footer widget stacks using the page builder (requires `edit_site_chrome` permission). Available on the default template; non-default templates inherit header/footer from the default.
