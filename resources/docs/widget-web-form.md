---
title: Web Form Widget
description: Embedding a form built in the Form Manager on a CMS page — selecting the form, submission handling, and the after-submit experience.
tags: [widget, page-builder, web-form, forms, cms]
category: cms
standalone: true
---

# Web Form Widget

The Web Form widget embeds a form that was built in the **Form Manager** (CMS → Forms) onto a CMS page. The widget itself has almost no configuration — all of the form's behaviour (which fields it has, where submissions go, what the success message says) is configured in the Form Manager, and the widget simply renders the form by handle.

Think of it as a bridge: Form Manager owns *what the form is*; the widget owns *where the form appears*.

## When to use this widget

Use Web Form for contact forms, newsletter sign-ups, volunteer interest forms, RSVPs, surveys — anything where you've defined the form in the Form Manager and want it to appear on a public page.

For donations, use the **Donation Form** widget instead — it has its own dedicated pipeline through Stripe. For event registration, use the **Event Registration Form** widget. The generic Web Form widget is for everything else.

## Inspector — Content tab

- **Form** — pick the form to embed. The dropdown lists every form in the Form Manager. Required — until a form is picked, the widget shows a setup notice in the editor.

That's it for the Content tab. The form's fields, validation, submission handler, and success messaging are all set in the Form Manager — not on the widget.

## Inspector — Appearance tab

The widget has no widget-specific appearance fields. Standard widget appearance (background, padding, full-width) applies as usual. The form's *internal* styling (field layout, button colour) is governed by site-wide form styles and the Design System.

## Submission flow

When a visitor submits the form:

1. The submission POSTs to `/form-submissions` and is dispatched through Laravel's form pipeline.
2. The submission's `FormSubmission` record is created and associated with the form.
3. The form's configured submission actions fire — for example, "create a contact", "subscribe to a mailing list", "send an admin notification email". These actions are configured in the Form Manager per-form, not per-widget.
4. The visitor sees the form's configured success message (or success page, depending on the form's setting).

## Common patterns

- **Contact form on the contact page.** Create a "Contact Us" form in Form Manager with fields `name`, `email`, `message`. Configure the submission action to email the admin and to create a Contact record. Drop the widget on `/contact` and pick the form.
- **Newsletter sign-up on the homepage footer.** Create a "Newsletter" form with just an `email` field. Configure the submission action to subscribe the email to a Mailing List. Drop the widget where you want the sign-up.
- **Volunteer interest form.** Create a form with fields for availability and skill area. Configure the submission action to create a Contact tagged `volunteer-prospect`. Drop the widget on `/volunteer`.

## Gotchas

- **The form must exist before you can pick it.** Build the form in Form Manager first, then add the widget to the page. If the Form dropdown is empty in the inspector, no forms have been defined yet.
- **Renaming a form's handle breaks the embed.** The widget references the form by handle, not by ID. If you change a form's handle in Form Manager, the widget instance still references the old handle and will render an error. Re-pick the form to refresh the binding.
- **Multiple instances of the same form on different pages are fine.** Each page renders the same form independently; submissions are tracked per submission, not per page.
- **Submission honeypot.** Forms include a built-in honeypot field that catches simple bot submissions. No configuration needed; it's transparent to legitimate visitors.
- **No file uploads in form submissions today.** Forms support text, email, textarea, select, checkbox, and similar text-shaped fields. Uploads are not on the widget yet.
