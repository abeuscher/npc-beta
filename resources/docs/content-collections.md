---
title: Content Collections
description: How to create and manage content collections that group reusable content items for use in page builder blocks and widgets.
version: "0.24"
updated: 2026-03-16
tags: [cms, collections, content, widgets]
routes:
  - filament.admin.resources.browse-collections.index
  - filament.admin.resources.browse-collections.items
---

# Content Collections

Content Collections group related content items that can be referenced by page builder blocks and widgets. Examples include a "Board Members" collection, a "Testimonials" collection, or a "Sponsors" collection.

## Collections List

The Collections index shows all content collections with their name and number of items.

## Creating a Collection

- **Name** (required) — a descriptive label for the collection (e.g., "Staff Profiles").
- **Items** — add individual items to the collection. Each item typically has a title, description, image, and optional link depending on how the collection is used.

## Using Collections on Pages

Once a collection is created, it can be referenced by a compatible page builder block (e.g., a "Cards Grid" or "Team Members" block). Select the collection from the block's settings to pull in all items automatically.

## Adding and Reordering Items

Open a collection to add new items or change their order. Items can be dragged to reorder them within the collection. The order here determines the order in which they display on the front end.
