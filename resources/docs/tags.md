---
title: Tags
description: How to create and manage tags for contacts, pages, posts, events, and collection items using the unified Tag Manager.
version: "0.25"
updated: 2026-03-19
tags: [tags, crm, cms, contacts, pages, posts, events, collections]
routes:
  - filament.admin.resources.tags.index
  - filament.admin.resources.tags.create
  - filament.admin.resources.tags.edit
category: crm
---

# Tags

Tags are labels you can apply to contacts, pages, blog posts, events, and collection items to categorize and segment content across the system. Each tag belongs to a specific type so that the right tags appear in the right places.

## Tag Types

| Type | Where applied |
|---|---|
| Contact | Contact edit form |
| Page | Page edit form |
| Post | Blog post edit form |
| Event | Event edit form |
| Collection | Collection item forms |

## Tag Manager

The Tag Manager (Tools → Tags) lists all tags across all types. You can:

- **Filter by type** to focus on a specific tag namespace.
- **Create** a new tag by clicking New Tag and providing a name and type.
- **Edit** a tag to change its name. The slug updates automatically. The type cannot be changed after creation.
- **Delete** a tag. All associations with content records are removed automatically.

## Applying Tags to Content

Open any contact, page, post, event, or collection item and locate the **Tags** field. There are two ways to interact with it:

- **Select an existing tag** — click the field and type to search, or browse the dropdown. Click a tag to apply it. It appears as a removable pill. Click × on any pill to remove it.
- **Create a new tag** — type the label in the **Create tag** field directly below the selector and click the **+** button. The tag is saved immediately and added to the record. You do not need to visit the Tag Manager to create tags this way.

## Tag Conventions

- Keep tag names concise and unambiguous.
- Prefer a small, well-defined set of tags over many overlapping ones.
- Review the tag list periodically and remove tags that are redundant or unused.
- Use tags for stable, meaningful categories — not for temporary notes (use the Notes feature instead).
