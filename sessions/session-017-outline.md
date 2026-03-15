# Session 017 Outline — Event Notifications & Registrant Export

> **Session Preparation**: This is a planning outline, not a complete implementation prompt.
> At the start of this session, review the `EventRegistration` model (session 014),
> the `Event` model with `status` enum and `landing_page_id` (sessions 014–016),
> and the current `.env` mail configuration. Email delivery decisions depend on what
> driver is available. Mailchimp integration is conditional on an API key being available —
> pause and ask before starting that section if none is configured.

---

## Goal

Send the right emails at the right times for events, and give admins the ability to export
registrant lists. This completes the core events feature loop: a visitor registers, receives
a confirmation, gets a reminder, and the admin can pull the list.

---

## Key Decisions to Make at Session Start

- **Mail driver**: What is the mail driver in `.env`? (`MAIL_MAILER`, `MAIL_HOST`, etc.)
  If no external driver is configured, use `log` for development and document what production
  needs. Do not configure a real mail driver mid-session — establish the pattern, let the
  operator configure credentials.
- **Queued vs synchronous**: For development, synchronous (`QUEUE_CONNECTION=sync`) is fine.
  Note in the code where a production deploy should switch to a real queue driver.
- **Mailable format**: Blade mailables or Markdown mailables? Markdown is faster to build
  and renders acceptably in most clients. Prefer Markdown for v1.
- **Reminder scheduling**: Is the Laravel scheduler (`php artisan schedule:run`) set up in
  the Docker environment? If not, build the scheduled command and document the cron entry —
  don't block the session on the scheduler running.
- **Mailchimp**: Is a Mailchimp API key available? If yes, include the sync feature.
  If not, skip it and note it as a future addition — do not attempt to troubleshoot missing
  credentials mid-session.
- **CSV export**: Simple on-demand download (no stored file) is fine for v1.

---

## Scope (draft — refine at session start)

**In:**
- Registration confirmation email — sent when an `EventRegistration` record is created
- Event cancellation email — sent to all registered attendees when event status → `cancelled`
- Event reminder email — sent N days before the event date via a scheduled command
- CSV export of registrant list — Filament action on `EventResource` / registrations relation
- Admin UI: manual "Send reminder" action on EditEvent page (one-off trigger)
- Mailchimp sync (if API key available): push registrants to audience with status-based tags

**Out:**
- Payment receipt email (no Stripe integration yet)
- Custom email template editor (future)
- SMS notifications
- Mailchimp campaign creation
- Real-time delivery status tracking

---

## Rough Build List

- Mailables: `RegistrationConfirmation`, `EventCancellation`, `EventReminder`
- Observer or model event: fire `RegistrationConfirmation` on `EventRegistration::created`
- Observer or model event: fire `EventCancellation` batch on `Event` status → `cancelled`
- `SendEventReminders` artisan command: queries upcoming dates within N days, dispatches reminders
- Scheduler registration in `Console/Kernel.php` (or `routes/console.php` in Laravel 11)
- Filament action: "Export registrants" → CSV download on EventResource
- Filament action: "Send reminder now" → manual trigger on EditEvent header
- Mailchimp service class (if in scope): upsert contact, apply tags by registration status
- Tests: mailable content assertions, cancellation fires to all registrants, CSV row count

---

## Open Questions at Planning Time

- Should the reminder be per-event (admin sets N days) or a system default?
- Should the cancellation email include any refund/next-steps message, or just the notice?
- Is there an unsubscribe/opt-out requirement before adding registrants to Mailchimp?

---

## What This Unlocks

- Events feature is complete end-to-end for free events
- Email infrastructure (Mailables, Observer pattern) is reusable for donation receipts,
  membership renewals, and any future transactional email
- Mailchimp integration pattern can be extended to contact segments and newsletter signups
