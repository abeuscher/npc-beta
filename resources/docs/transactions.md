---
title: Transactions
description: How to view and manage financial transaction records that underpin donations and other financial activity.
version: "0.24"
updated: 2026-03-16
tags: [finance, transactions]
routes:
  - filament.admin.resources.transactions.index
  - filament.admin.resources.transactions.create
  - filament.admin.resources.transactions.edit
---

# Transactions

Transactions are the underlying financial records that support donations and other financial activity in the system. While Donations represent the donor relationship side of a gift, Transactions represent the financial event.

## Transactions List

The Transactions index shows all financial transactions with their date, amount, type, and linked records. Use the date and type filters to narrow the view.

## Creating a Transaction

In most cases, transactions are created automatically when a donation is recorded. You may need to create a transaction manually for adjustments, refunds, or non-donation financial events.

- **Amount** — the transaction amount. Use a negative value for refunds or reversals.
- **Date** — when the transaction occurred.
- **Type** — the transaction category (donation, refund, adjustment, etc.).
- **Reference** — an optional external reference number (check number, payment processor ID, etc.).

## Reconciliation

Transaction records support periodic reconciliation with your accounting system. Export transactions by date range using the CSV export on the list view.
