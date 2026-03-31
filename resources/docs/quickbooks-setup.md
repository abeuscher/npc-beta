---
title: Setting up QuickBooks
description: How to connect QuickBooks Online for transaction sync — developer portal setup, OAuth credentials, environment configuration, and account selection.
version: "0.25"
updated: 2026-03-31
standalone: true
tags: [quickbooks, finance, setup, accounting]
---

# Setting up QuickBooks

Reference guide for connecting QuickBooks Online to this application. QuickBooks is used for one-way transaction sync — completed payments and refunds are pushed to QuickBooks as Sales Receipts and Refund Receipts.

---

## Prerequisites

You need two things before connecting:

1. A **QuickBooks Online** account (any tier — Simple Start, Essentials, Plus, or Advanced).
2. A **developer app** registered at the [Intuit Developer Portal](https://developer.intuit.com/).

---

## Step 1 — Create a developer app

1. Sign in at [developer.intuit.com](https://developer.intuit.com/).
2. From the dashboard, create a new app and select **QuickBooks Online Accounting** as the platform.
3. Under **Keys & credentials**, you will see two sets of keys: **Development** (sandbox) and **Production**. Use the set that matches the environment you are connecting to.
4. Add your callback URL to the **Redirect URIs** list:

```
https://yourdomain.com/admin/quickbooks/callback
```

Replace `yourdomain.com` with your actual domain.

---

## Step 2 — Choose your environment

In **Settings > Finance**, the **QuickBooks — Environment** section shows whether the application is pointing at the sandbox or production QuickBooks API.

- **Sandbox** — connects to Intuit's testing environment. Use the **Development** Client ID and Client Secret from the developer portal.
- **Production** — connects to live QuickBooks data. Use the **Production** Client ID and Client Secret.

Make sure the environment setting and the credential set match. Mixing sandbox credentials with the production environment (or vice versa) will result in authorization errors.

Click the toggle button to switch environments. After switching, you will need to re-enter credentials and reconnect.

---

## Step 3 — Enter credentials

On the Finance Settings page, enter:

- **QuickBooks — Client ID** — the OAuth Client ID from the developer portal.
- **QuickBooks — Client Secret** — the OAuth Client Secret from the developer portal.

Both values are encrypted at rest and cannot be viewed after saving. Record them in a password manager before proceeding.

---

## Step 4 — Connect

Once both the Client ID and Client Secret are saved, a **Connect to QuickBooks** button appears. Click it to be redirected to Intuit's authorization page.

During authorization, QuickBooks will ask you to **select which company** to connect. Choose the company whose books you want transactions synced to. If you are using the sandbox environment, select your sandbox company.

After authorization, you are returned to Finance Settings with a green **Connected** badge showing the Company ID (Realm) and token expiry.

---

## Step 5 — Select a deposit account

Once connected, a **QuickBooks — Transaction Sync** section appears. Select the QuickBooks account where synced transactions should be deposited.

The dropdown lists accounts from your QuickBooks Chart of Accounts. If you do not see the account you expect, click **Refresh Accounts** to reload the list from QuickBooks.

Sync is disabled until an account is selected.

---

## How sync works

- When a payment completes (donation, product purchase, or recurring invoice), a **Sales Receipt** is created in QuickBooks.
- When a refund is processed through Stripe, a **Refund Receipt** is created in QuickBooks.
- Each transaction is synced exactly once. A transaction that has already been synced is never sent again.
- If a transaction's contact has an email address, the system automatically matches or creates a **QuickBooks Customer** record and links it to the receipt. This enables per-person reporting in QuickBooks. Contacts without an email are synced without customer attribution.

---

## Sandbox testing

Intuit provides sandbox companies for testing during development. Sandbox companies look and act like real QuickBooks companies but contain sample data and are free to use.

To manage sandbox companies:

1. Sign in at [developer.intuit.com](https://developer.intuit.com/).
2. Go to **My Hub > Sandboxes** to create, view, or reset sandbox companies.

When connecting this application in sandbox mode, use the **Development** keys from your app and select your sandbox company during the OAuth authorization step.

For more details, see Intuit's [sandbox documentation](https://developer.intuit.com/app/developer/qbo/docs/develop/sandboxes).

---

## Disconnecting

Click **Disconnect** on the Finance Settings page and confirm. This removes all stored QuickBooks tokens. You will need to re-authorize to reconnect. Disconnecting does not affect data already synced to QuickBooks.

---

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| **Connect button not visible** | Client ID and/or Client Secret have not been saved yet. |
| **Authorization fails or 403 errors** | Environment mismatch — check that the Client ID/Secret match the selected environment (Development keys for sandbox, Production keys for production). |
| **No accounts in the dropdown** | The OAuth token may have expired or lost scope. Disconnect and reconnect, then click Refresh Accounts. |
| **Sync shows "Error" on a transaction** | Hover over the error badge for details. Common causes: the selected deposit account was deleted in QuickBooks, or the QB connection was lost. |
| **Transactions sync but no customer name in QuickBooks** | The contact has no email address, or the transaction was synced before customer matching was enabled. Only new syncs for contacts with email addresses create QB Customer records. |
