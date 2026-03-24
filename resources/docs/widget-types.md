---
title: Widget Types
description: Developer-managed widget type definitions — configuring server-rendered and client-rendered widgets available in the page builder.
version: "0.68"
updated: 2026-03-23
tags: [admin, cms, widgets, developer]
routes:
  - filament.admin.resources.widget-types.index
  - filament.admin.resources.widget-types.create
  - filament.admin.resources.widget-types.edit
---

# Widget Types

Widget Types defines the reusable building blocks available in the page builder. Each widget type is a template with a unique handle, a render mode, optional singleton configuration fields, rendering code, and scoped CSS. When a widget is placed on a page, an instance is created from the type definition.

This is a developer-facing tool. Poorly written render code can break public pages. Access requires the `view_any_widget_type` permission.

Some built-in widget types are **pinned** — they cannot be deleted. Pinned widgets are part of the system's core functionality.

## Basic Fields

- **Label** — the human-readable name shown in the page builder widget picker.
- **Handle** — a unique machine-readable identifier, auto-generated from the label on creation. Cannot be changed after creation. Used in CSS scoping (`.widget--{handle}`) and in templates.
- **Render mode** — how the widget is rendered: **Server** (Blade template, evaluated at request time) or **Client** (arbitrary JavaScript, evaluated in the browser).
- **Collections** — optional list of collections this widget reads from. Selecting a collection makes its data available inside the render template.

## Singleton Fields

Singleton fields are per-instance configuration options that page editors can set when placing the widget on a page. For example, a "Hero" widget might have a singleton field for the heading text.

Each field has a key, label, and type (`text`, `textarea`, `richtext`, `url`, `number`, `toggle`). These values are passed into the render template as variables.

## Server Mode

When render mode is **Server**, the widget is rendered by a Blade template on the server at request time.

- **Blade template** — the Blade/HTML template for the widget. Has access to singleton field values and any data from linked collections.
- **JavaScript** — optional JavaScript that runs when the widget is rendered on the page.

## Client Mode

When render mode is **Client**, the widget renders entirely in the browser via JavaScript.

- **Variable name** — the JavaScript variable name exposed to the render code (e.g. `widget`). Contains the singleton field values and collection data.
- **Code** — arbitrary JavaScript that runs in the browser. This executes with full DOM access — use with care and only with trusted code.

## CSS

The **CSS** field accepts scoped styles for this widget type. Styles are automatically scoped to `.widget--{handle}` at render time, so they cannot leak out and affect other elements on the page.
