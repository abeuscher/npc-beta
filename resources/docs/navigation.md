---
title: Navigation
description: How to manage the public website's navigation menu items, including links, labels, order, and nesting.
version: "0.24"
updated: 2026-03-16
tags: [cms, navigation, menus]
routes:
  - filament.admin.resources.navigation-menus.index
  - filament.admin.resources.navigation-menus.create
  - filament.admin.resources.navigation-menus.edit
category: cms
---

# Navigation

Navigation items control the links that appear in the public website's main menu. You can add links to internal pages or external URLs, control the display label, and set the order.

## Navigation List

The Navigation index shows all menu items in their current order. Items can be reordered by dragging or using the sort controls.

## Creating a Navigation Item

- **Label** (required) — the text displayed in the menu.
- **URL / Page** — either select an internal page from the dropdown or enter an external URL.
- **Sort Order** — determines the position of this item in the menu. Lower numbers appear first.
- **Open in new tab** — enable for external links you do not want to navigate away from your site.

## Nested Items

Navigation supports one level of nesting (parent and children). To create a submenu, assign a parent item to a navigation item. The parent becomes a dropdown trigger in the menu.

## Changes Take Effect Immediately

Navigation changes appear on the public website as soon as they are saved — there is no separate publish step.
