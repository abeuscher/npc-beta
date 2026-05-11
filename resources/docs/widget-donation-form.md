---
title: Donation Form Widget
description: Configuring the donation form's preset amounts, monthly and annual options, and post-donation redirect behaviour.
tags: [widget, page-builder, donation-form, donations, forms, cms]
category: cms
standalone: true
parent: widgets
---

# Donation Form Widget

The Donation Form widget renders a self-contained donation form on the public site. Visitors enter an amount (either from preset buttons or a free-form input), choose a frequency (one-time, monthly, or annual depending on what's enabled), fill in their contact details, and check out through Stripe Checkout. Successful donations land as `Donation` records in the CRM with the associated contact created or matched.

For configuring the broader donation experience — Stripe keys, default acknowledgement emails, recurring-billing scheduler — see the **Finance Settings** and **Stripe Setup** help articles. This widget is the *form* operators drop onto a page; the donation pipeline behind it is configured elsewhere.

## When to use this widget

Use Donation Form on a dedicated giving page (e.g. `/give`, `/donate`) or embedded on a campaign-detail page where you want a direct ask. Multiple donation forms can exist across the site — each is independent and configurable.

For a more lightweight "Donate" link that jumps to your giving page, use a Button widget instead.

## Inspector — Content tab

- **Heading** — text rendered above the form. Often a short call to action ("Support our work" / "Give today"). Optional.
- **Preset amounts** — a comma-separated list of dollar amounts shown as quick-pick buttons above the free-form amount input (e.g. `25,50,100,250`). Visitors can click a preset or enter a custom amount. Leave blank to show only the free-form input.
- **Success page slug** — the slug of a CMS page (e.g. `thank-you`) to redirect to after a successful donation. Leave blank to stay on the donation page with an in-line success message. Either approach works; pick whichever fits the giving experience.

## Inspector — Appearance tab

- **Show Monthly option** — when enabled, the form shows a "Monthly" toggle alongside "One-time". Monthly donations create a recurring Stripe subscription billed each month.
- **Show Annual option** — when enabled, the form shows an "Annual" toggle. Annual donations create a recurring Stripe subscription billed once a year.

Both can be enabled independently. If neither is enabled, the form is one-time only. If both are enabled, the visitor picks among One-time / Monthly / Annual.

Standard widget appearance fields (background, padding, full-width) apply as usual.

## Common patterns

- **Annual fund page with three tiers.** Preset amounts `100,250,500`, monthly enabled, annual enabled. Success page `thank-you-fund`.
- **Memorial gift page.** No preset amounts, monthly off, annual off, success page redirect to a dedicated "Memorial Gift Confirmation" page.
- **Recurring monthly campaign.** Preset amounts `10,25,50`, monthly enabled, annual off, no success page redirect (in-line thank-you keeps visitors on the page where they can read more).

## Gotchas

- **Stripe must be configured before the form will accept donations.** Check **Finance Settings → Stripe** for the public/secret key pair and webhook secret. The form will render in the page builder regardless, but a publicly-visible form pointing at unconfigured Stripe will fail at the checkout step.
- **Preset amounts are dollars, not cents.** Enter `50` for $50, not `5000`. The widget multiplies by 100 internally before talking to Stripe.
- **Success page redirect happens via Stripe's `success_url`.** This means the visitor leaves your site to Stripe Checkout, completes the donation there, and is then redirected back to the configured success page. The success page renders fully — there is no special success-only mode.
- **Designation pickers and campaign linkage** are not on the widget itself today. Donations created through this form are unassigned at the campaign/fund level. To attribute donations to a campaign, use a campaign-detail page with a campaign-specific donation form (a future enhancement may add per-form designation; today it's flat).
- **Mobile rendering.** The form stacks vertically on narrow viewports. Long preset-amount lists wrap to multiple rows.
