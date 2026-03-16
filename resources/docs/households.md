---
title: Households
description: How to create and manage household records that group individual contacts sharing an address or family unit.
version: "0.24"
updated: 2026-03-16
tags: [households, crm, contacts]
routes:
  - filament.admin.resources.households.index
  - filament.admin.resources.households.create
  - filament.admin.resources.households.edit
---

# Households

A Household groups individual contacts who share an address or family relationship. Linking contacts to a household makes it easy to send one mailing to a family rather than separate letters to each person.

## Household List

The Households index shows all household records. You can search by household name and see how many contacts are linked to each.

## Creating a Household

A household record requires:

- **Name** — typically a family name (e.g., "Smith Family" or "The Smiths").
- **Address** — the shared mailing address for the household.

After creating the household, open individual contact records and link them to this household using the **Household** field on the contact form.

## Editing a Household

Click any household row to open its detail view. From here you can see all linked contacts and update the household's name or address.

## Use Cases

- Sending a single appeal letter addressed to "The Smith Family."
- Tracking cumulative giving at the household level.
- Deduplicating mailing lists so a family receives one copy, not five.
