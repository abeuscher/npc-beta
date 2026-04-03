---
title: Setting up Stripe
description: How to configure Stripe for payments, webhooks, and testing — including test card details.
version: "0.25"
updated: 2026-03-25
standalone: true
tags: [stripe, finance, setup, payments]
category: finance
---

# Setting up Stripe

Reference guide for connecting and testing Stripe in this application.

---

## Keys

Stripe credentials are stored encrypted in **Settings → Finance**. Three values are required:

- **Publishable key** — starts with `pk_live_` or `pk_test_`. Safe to store in plaintext.
- **Secret key** — starts with `sk_live_` or `rk_` (restricted). Use a restricted key scoped to the minimum required permissions.
- **Webhook secret** — starts with `whsec_`. Generated in the Stripe dashboard under **Developers → Webhooks**.

Use `pk_test_` / `sk_test_` keys during development and testing. Switch to live keys when moving to production.

---

## Webhook endpoint

Register the following endpoint in the Stripe dashboard under **Developers → Webhooks**:

```
https://yourdomain.org/webhooks/stripe
```

Events to listen for:

- `checkout.session.completed`
- `invoice.payment_succeeded`
- `invoice.payment_failed`
- `payment_intent.payment_failed`
- `refund.created`

---

## Test card details

Use these details when testing checkout flows in Stripe test mode:

| Field | Value |
|---|---|
| Card number | `4242 4242 4242 4242` |
| Expiry | Any future date (e.g. `12/34`) |
| CVC | Any 3 digits (e.g. `123`) |
| ZIP | Any 5 digits (e.g. `12345`) |

This card simulates a successful payment. Stripe provides additional test card numbers for declined cards, 3D Secure flows, etc. — see the Stripe documentation.

---

## Donation prefix

The URL segment for the donation checkout endpoint is configurable under **Settings → General → Routing**. Default: `donate`. This affects the route `POST /donate/checkout` used by the Donation Form widget.
