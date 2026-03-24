---
title: Mail Settings
description: Outgoing mail driver, sender address, Resend API key, and MailChimp integration credentials.
version: "0.68"
updated: 2026-03-23
tags: [settings, admin, email]
routes:
  - filament.admin.pages.mail-settings-page
---

# Mail Settings

Mail Settings controls how the system sends transactional email and how it connects to MailChimp for mailing list sync. Access is restricted to super-admin users.

## Sending

- **Mail driver** — choose how outgoing emails are delivered.
  - **Local — log only** — emails are written to the Laravel application log and never delivered. Use this during development or when no sending provider is configured.
  - **Resend** — emails are sent via the Resend API. Requires a valid API key in the Resend section below.
- **From name** — the sender name that appears in the recipient's email client. Typically your organisation's name.
- **From address** — the sender email address. Must be a domain you have verified with your sending provider. Replies to transactional emails will go to this address.

## Resend

This section is only visible when **Resend** is selected as the mail driver.

- **API Key** — your Resend API key. Starts with `re_`. Found in the Resend dashboard under API Keys. Store this securely — it authorises sending on behalf of your verified domain.

## MailChimp

MailChimp credentials for syncing mailing list members. These fields are independent of the mail driver — MailChimp is used for broadcast campaigns, not transactional email.

- **API Key** — your MailChimp API key. Found in your MailChimp account under Profile → Extras → API keys.
- **Server prefix** — the data centre suffix from your API key, e.g. `us14`. It is the part after the last hyphen in the API key string.
- **Audience ID** — the ID of the MailChimp audience (list) to sync with. Found under Audience → Settings → Audience name and defaults.
- **Webhook path** — the URL path segment after `/webhooks/` that MailChimp will call when list membership changes. Use a random string in production for security.
- **Webhook secret** — a secret value appended as `?secret=…` to the webhook URL you register in MailChimp. Used to verify that incoming webhook requests are genuine.

## Testing

Use the **Send test email** button in the page header to verify your sending configuration. Enter any email address — a test message will be dispatched immediately. If the driver is set to **Local**, the button will warn you that no email will be delivered.
