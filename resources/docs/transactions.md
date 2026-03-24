---
title: Transactions
description: How financial transactions are recorded, where they come from, and when to enter one manually.
version: "0.73"
updated: 2026-03-24
tags: [finance, transactions]
routes:
  - filament.admin.resources.transactions.index
  - filament.admin.resources.transactions.create
  - filament.admin.resources.transactions.edit
---

# Transactions

The Transactions ledger records every financial event tracked by the system — money in and money out. Most records are created automatically. The manual entry form exists for a specific, narrow purpose described below.

## Where transactions come from

**Stripe (automatic).** When a payment is completed through the system's Stripe integration, a transaction is recorded automatically via webhook. Refunds issued through the Stripe dashboard are also recorded automatically — the system receives a notification from Stripe and creates the corresponding outbound record without any action required. You do not need to enter Stripe payments or refunds manually; the system handles both.

**Manual entry.** Use the Create form for transactions that occurred entirely outside any connected system — for example, a grant received by cheque, a cash expense paid against a project budget, or a manual fund adjustment. If it went through Stripe, do not enter it here.

## The Transactions list

The list shows all transactions regardless of origin. The **Source** badge indicates whether a record was created by Stripe or entered manually. Stripe-originated records are read-only — they cannot be edited or deleted from within the system, since they reflect events that happened in Stripe.

## Creating a manual transaction

The form is scoped to off-system entries only.

- **Type** — the nature of the transaction: Grant (incoming funds from a grant), Expense (an outlay against a project or fund), or Adjustment (a manual correction to the ledger).
- **Direction** — In for money received, Out for money paid out.
- **Amount** — the transaction amount in dollars.
- **Status** — Pending until the transaction is confirmed or cleared; Cleared once settled.
- **Date** — when the transaction occurred.
- **QuickBooks reference** — an optional field to record the corresponding transaction number assigned by QuickBooks. When QuickBooks sync is available, this field will be populated automatically for connected transactions. For manual entries, your accountant can fill this in after recording the matching entry in QuickBooks, giving you a cross-reference between the two systems.

## Editing and deleting

Manual transactions can be edited or deleted. Stripe-originated transactions cannot — if a Stripe record needs to be corrected, the correction should be made in Stripe itself (which will trigger a new webhook event) or through a manual Adjustment entry.
