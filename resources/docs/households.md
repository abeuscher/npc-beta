---
title: Households
description: How household grouping works — linking contacts who share an address or family unit via the self-referential household_id field.
version: "0.71"
updated: 2026-03-28
tags: [households, crm, contacts]
routes:
  - filament.admin.resources.contacts.index
  - filament.admin.resources.contacts.edit
---

# Households

A Household groups individual contacts who share an address or family relationship. Contacts are linked to a household head using the **Household Head** field on each contact record. There is no separate Households resource — household membership is managed entirely from the contact edit form.

## How it works

Every contact has a `household_id` that points to their household head. By default, each contact is their own household head (solo). When you link a contact to another contact as their head, they become part of that contact's household.

The household head's address is automatically copied to any contact who is newly assigned to their household.

## Setting a Household Head

1. Open a contact record and go to **Edit**.
2. Expand the **Household** section (collapsed by default).
3. Use the **Household Head** selector to choose another contact. Clearing the field makes this contact solo again.
4. Save the record.

## Household display in the contact list

The Contacts index shows a **Household** column. Solo contacts show `—`. Contacts linked to a household show the household name (based on the head's last name, e.g. "Smith Household").

## Use Cases

- Sending a single appeal letter addressed to "The Smith Family."
- Tracking cumulative giving at the household level.
- Deduplicating mailing lists so a family receives one copy, not five.
