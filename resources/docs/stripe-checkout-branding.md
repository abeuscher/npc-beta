---
title: Stripe Checkout Branding
description: Make the Stripe Checkout pages your buyers see look like your organization — logo, brand color, business details, copy strings, and statement descriptors. Walks the Stripe Dashboard half + the in-app half.
version: "0.1"
updated: 2026-05-13
tags: [stripe, payments, branding, checkout, setup]
category: finance
standalone: true
---

# Stripe Checkout Branding

When someone donates, registers for an event, buys a product, or subscribes to a membership through your public site, this app sends them to a payment page hosted by Stripe. That page can either look generic or look like your organization. This guide walks you through both halves of making it look like you.

There are two halves because Stripe splits the work:

- **Half 1 — Stripe Dashboard** — logo, brand color, business name, support email, Terms of Service URL, Privacy Policy URL, and the statement descriptor that subscriptions use. You set these once, in your Stripe Dashboard, and they apply to every Checkout page you ever create. **This app cannot set these for you** — Stripe only exposes them through their Dashboard.
- **Half 2 — This app's CMS Settings** — the helper text shown on the Checkout page, the statement descriptor for one-off charges, and the default thumbnail images for each flow. These live in CMS Settings → *Stripe Checkout — Branding*.

Do Half 1 first. Half 2 only matters once the Dashboard half is in place.

---

## Half 1 — Stripe Dashboard

You will need a Stripe account with at least one user who can edit Settings. If you have not yet created the account or added the API keys to this app, see the [Finance Settings](settings-finance) doc first.

### Step 1 — Branding (logo + color)

1. Open the Stripe Dashboard at <https://dashboard.stripe.com/settings/branding>.
2. Upload your **icon** — Stripe shows this in the Checkout header. Square. PNG or JPG. At least 128×128 px. Stripe recommends a transparent background.
3. Upload your **logo** — appears beside the business name in some Checkout layouts and on receipts. Wider rectangle. PNG or JPG.
4. Set your **brand color** — used for the buttons and links on the Checkout page. Pick a color that matches your site's primary color so the visual jump from your site to Stripe feels seamless.
5. Set the **accent color** if Stripe asks for one — a secondary color used sparingly.
6. Click **Save**.

### Step 2 — Public Details (business name + support contact)

1. In the Dashboard, go to **Settings → Public Details**.
2. Set your **public business name** — what shows up as "Pay [name]" on the Checkout page. Use your organization's full legal or doing-business-as name, whichever your buyers will recognize.
3. Set the **support email** — Stripe shows this on the Checkout page and on receipts so buyers know where to ask questions.
4. Set the **support phone** if you want one shown.
5. Set the **website URL** — your organization's main public site.
6. Click **Save**.

### Step 3 — Terms of Service and Privacy Policy URLs

1. Still in **Settings → Public Details**, find the Terms of Service URL and Privacy Policy URL fields.
2. Paste your Terms of Service URL — the public URL where your terms are published.
3. Paste your Privacy Policy URL — same shape.
4. Click **Save**.

If you do not yet have published Terms of Service or Privacy Policy pages on your site, this app's roadmap includes default starter pages for both as part of the upcoming public-website work; check with your developer if you need them now.

### Step 4 — Subscription statement descriptor

The statement descriptor is what shows up on a buyer's bank statement next to a charge. For one-off charges this app sends the descriptor on every Checkout session (see Half 2). For **subscriptions** — recurring donations and paid memberships — Stripe requires the descriptor to be configured at the account level, not per session.

1. In the Dashboard, go to **Settings → Public Details → Statement descriptors**.
2. Set the **statement descriptor** to a short version of your organization's name. **5 to 22 characters. Letters, numbers, and spaces only — no punctuation.** Examples: `ACME FOUNDATION`, `RIVERTOWN CHURCH`, `HOPE CLINIC`.
3. Click **Save**.

This descriptor will appear on bank statements for every recurring donation and paid membership. Donors and members who do not recognize the line item are likely to dispute the charge with their bank, so prioritize a descriptor your supporters will read and immediately understand as you.

### Step 5 — Verify

1. From your live site, run a real test transaction. The cleanest test is a low-amount one-off donation (you can refund yourself afterwards).
2. Confirm the Checkout page shows: your icon at the top, your business name in the "Pay [name]" header, your brand color on the button, your support email near the bottom.
3. Confirm the receipt email has your icon and business name.
4. Confirm — once the charge settles to your bank, usually 1–3 business days — the line item on your bank statement reads as expected.

---

## Half 2 — In this app

CMS Settings → *Stripe Checkout — Branding*. Permission required: `manage_cms_settings`.

This section adds the per-Checkout-session pieces that complement what you set in the Dashboard.

### Mark the Dashboard half complete

Toggle **I have configured branding in my Stripe Dashboard** on after you finish Step 1–4 above. This clears the matching item from the Onboarding Checklist on the dashboard. It does not change any Checkout behavior — it is purely an acknowledgement so this app stops nagging you about it.

### Submit-button helper text

Optional copy that appears above the **Pay**, **Donate**, or **Subscribe** button on every Checkout session. Use it to reassure buyers about what happens next, where their money goes, or how long their commitment is. Plain text or limited Markdown — `**bold**`, `*italic*`, `[link text](https://url)` — up to 1200 characters.

Example for a donation-heavy site: *"Your contribution directly funds our programs. Tax-deductible — receipts emailed within 24 hours."*

### Post-submit helper text

Optional copy shown briefly after the buyer clicks submit, before Stripe redirects back to your site. Use it to set expectations about what arrives in their inbox. Same format and length rules as above.

Example: *"Thank you. Look for a confirmation email within a few minutes."*

### Terms of Service acceptance

Two pieces:

1. **The toggle** — *"I have configured Terms of Service and Privacy Policy URLs in Stripe Dashboard."* Flip this on **only after** you completed Step 3 in Half 1. If you flip it on without those URLs in place, Stripe will reject every Checkout session and your public site's payment flows will break.
2. **The text** — optional helper copy that appears beside the ToS checkbox on the Checkout page. Same format and length rules as the other copy fields.

### Statement descriptor for one-off charges

Optional. Sent on every one-off donation, event ticket purchase, and product purchase as the line on the buyer's bank statement. **5 to 22 characters. Letters, numbers, and spaces only — no punctuation.**

Leave blank to inherit from your Stripe Account default (the one you set in Half 1, Step 4). Set explicitly to override per Checkout session.

### Statement descriptor suffix

Optional. Appended to your Stripe Account's default descriptor when set. Use this if you want the bank-statement line to read like `[ORG NAME] * EVENT` — your account default is the prefix, your suffix is what comes after the asterisk.

### Default Checkout images

Stripe shows a small thumbnail (~80×80) beside each line item on the Checkout page. This app sends an image in the following order:

| Flow | Source |
|---|---|
| Donation | Default donation image (set here) |
| Membership | Default membership image (set here) |
| Event | The event's thumbnail if one is uploaded; otherwise the default event image set here |
| Product | The product's image if one is uploaded; otherwise the default product image set here |

Upload one image per flow. JPG, PNG, or WEBP. Square works best — Stripe crops non-square images. ~400×400 is a sensible target size. Skip uploading any default and the line item will render without a thumbnail (text only).

---

## What this app cannot configure

Some pieces are exposed by Stripe only through their Dashboard or only through paid Stripe features:

- **Custom domain** (`checkout.yourorg.com` instead of `checkout.stripe.com`) — Stripe Custom Domains, paid feature.
- **Page layout, fonts, custom CSS on Checkout pages** — not available at any tier.
- **Per-fund or per-event override** of the helper text strings — the strings here apply to every Checkout session site-wide.
- **Per-tier branding** — all tiers in one membership Checkout session share the same branding.

If any of these become forcing functions for your organization, talk with your developer; some shapes are achievable by replacing Stripe Checkout with a fully custom payment surface, which is a much larger lift.
