# Session 015 Outline — Events: Notifications & List Integrations

> **Session Preparation**: This is a planning outline, not a complete implementation prompt.
> At the start of this session, review the EventRegistration model, the Transaction model,
> the deployment environment (session 009 — is a mail server configured?), and any Mailchimp
> API credentials available. Email delivery is environment-dependent.

---

## Goal

Send the right emails at the right times for events, and make event registrant lists available to Mailchimp as pre-built audience segments.

---

## Key Decisions to Make at Session Start

- **Mail driver**: What is the mail driver on staging/production? Mailgun, SES, Postmark, SMTP? Decide before building any mailable.
- **Queue**: Are emails queued (background job) or sent synchronously? Queued is correct for production — is the worker running?
- **Email templates**: Plain Laravel Blade mailables, or a Markdown mailable? Decide a consistent approach for all transactional email.
- **Mailchimp integration approach**: Mailchimp API v3 directly, or via a Laravel package (e.g. `spatie/laravel-newsletter`)? Assess package options at session start.
- **Mailchimp list strategy**: One audience per event, one organisation-wide audience with tags/segments, or a hybrid? Tags + segments within one audience is Mailchimp best practice.
- **Bifurcations for Mailchimp sync**: Which registrant statuses sync to which tags? (e.g. `registered` → "registered", `attended` → "attended", `waitlisted` → "waitlist")
- **Reminder timing**: How many reminders, how far in advance? Is this admin-configurable per event, or a system default?

---

## Scope (draft — refine at session start)

**In:**
- Registration confirmation email (sent on EventRegistration creation)
- Payment receipt email for paid registrations (sent on Stripe webhook confirmation)
- Event reminder emails (1 week out, 1 day out — system default, schedulable via Laravel scheduler)
- Event cancellation email (triggered when event status changes to cancelled)
- Mailchimp sync: push event registrants to Mailchimp audience with appropriate tags
- Admin UI: per-event "sync to Mailchimp" action, registrant list export (CSV at minimum)
- Laravel scheduler configured for reminder dispatch

**Out:**
- Custom email templates designed by end user (future — WYSIWYG email editor)
- SMS notifications
- Mailchimp campaign creation (we sync lists, not create campaigns)
- Automated post-event follow-up sequences

---

## Rough Build List

- Mailables: RegistrationConfirmation, PaymentReceipt, EventReminder, EventCancellation
- Jobs: SendRegistrationConfirmation, SendEventReminder, SyncEventToMailchimp
- Scheduler: event reminder dispatch command
- Mailchimp API integration: contact/tag management
- Admin UI: manual Mailchimp sync action on EventResource, CSV export of registrant list
- Tests: mailable content, scheduler registration, Mailchimp sync (mocked)

---

## Open Questions at Planning Time

- Does the organisation have a Mailchimp account and API key?
- Should registrant opt-in be explicit (checkbox on registration form) before syncing to Mailchimp?
- Are there other list integrations beyond Mailchimp that should be planned for (Constant Contact, etc.)?

---

## What This Unlocks

- Events feature is complete end-to-end
- Email infrastructure can be reused for donation receipts, membership renewals, etc.
- Mailchimp integration pattern can be extended to other contact segments
