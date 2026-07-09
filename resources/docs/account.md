---
title: Account
description: The read-only "My Account" page — your plan, subscription status, next invoice, billing contact, and a link to the secure billing portal.
version: "0.367"
updated: 2026-07-08
tags: [settings, billing, account, subscription]
category: settings
routes:
  - filament.admin.pages.account-page
---

# Account

The **Account** page (under **Settings**) shows the billing details for your site's subscription. Everything on it is **read-only** — it reflects the current state of your account as recorded by your provider. To make a change, use the **Manage billing** button, which opens the secure billing portal.

> **Who can see this page.** Access is gated by the `manage_account` permission, which is granted to no role by default — so only a super administrator sees it unless it is deliberately added to a custom role. The page also hides itself entirely on sites that have no billing account (internal or newly set-up sites).

## What the page shows

- **Subscription** — your plan name and price, and a plain-English status:
  - **Active** — your subscription is current.
  - **Payment problem — please update your card** — a recent payment didn't go through.
  - **Trial — N days left** — you're in a trial period.
  - **Canceled** — the subscription has ended.
- **Next invoice** — the date and amount of your next bill, itemized (your subscription plus any project-work hours).
- **Billing contact** — the email address on file for billing notices. This is read-only here; change it in the billing portal.

## Managing billing

The **Manage billing** button opens Stripe's secure, hosted billing portal in a new tab. Everything money-related happens there — never on this site:

- Update the card on file.
- Change the billing contact email.
- Download receipts and past invoices.

Your card details are entered only on Stripe's own pages; this site never sees or stores them.

## Payment problems and grace period

If a payment fails, you'll see a warning banner on this page — and a slim reminder across the admin panel — well before anything is interrupted. Your card is retried automatically for a couple of weeks; if it still can't be collected, a **14-day grace period** begins, and the banner shows the date admin access will be paused. Resolving the payment through the billing portal clears the warning and keeps (or restores) access right away.

If admin access is ever paused for a billing reason, **your public site, donation processing, and member portal keep running** — only the back-office admin area is affected, and nothing is deleted.

## Freshness

Billing information is refreshed about once a day, so the page footer notes when it was last updated. A payment you just made can take up to a day to appear here — the receipt in the billing portal is always immediate.
