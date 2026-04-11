# Session 017 Prompt — Event Notifications & Registrant Export

## Context

Sessions 014–016 built the full events foundation: the `Event` and `EventDate` models,
`EventRegistration`, the widget-based public landing page system (pages of type `event` with
slug `events/{event-slug}`), and the registration form via the `event_registration` widget.

What's missing is any communication back to the registrant after they sign up, and any way
for admins to pull the list of who is coming.

---

## Goals for This Session

1. **Registration confirmation email** — every new registrant gets an email immediately
2. **Event cancellation email** — all registered attendees are notified when an event is cancelled
3. **Event reminder email** — scheduled command sends reminders N days before event dates
4. **Registrant CSV export** — Filament action on the event admin page
5. **Manual "Send reminder" action** — one-off trigger from the EditEvent header

Mailchimp sync is in scope **only if** a `MAILCHIMP_API_KEY` is present in `.env`. If it is
not, skip that section entirely and leave a clear TODO comment — do not attempt to configure
or troubleshoot the credential mid-session.

---

## Pre-Session Checks

Before writing any code, verify:

1. **Mail configuration**: Read `.env` for `MAIL_MAILER`, `MAIL_HOST`, `MAIL_FROM_ADDRESS`.
   If the driver is `log`, that is fine for development — all sent mail will appear in
   `storage/logs/laravel.log`. Do not change mail credentials; just document what production
   will need.
2. **Queue driver**: Read `.env` for `QUEUE_CONNECTION`. `sync` is expected in development
   and is fine — emails send inline. Note in code comments where a production deploy should
   switch to `redis` or `database`.
3. **Scheduler**: Check whether `routes/console.php` or `app/Console/Kernel.php` is where
   scheduled commands are registered in this Laravel version.
4. **Mailchimp**: Check `.env` for `MAILCHIMP_API_KEY`. Report what you find and confirm
   with me before starting any Mailchimp work.

---

## Implementation Plan

### 1. Mailables

Create three Markdown mailables (use `php artisan make:mail --markdown`):

**`App\Mail\RegistrationConfirmation`**
- Constructor: `EventRegistration $registration`
- Eager-load `registration->event` if not already loaded
- Content: event title, registrant name, event dates (from `event->eventDates()->upcoming()`),
  location summary (in-person address or "Online"), a note that this is a free event
- Subject: `"You're registered: {event title}"`

**`App\Mail\EventCancellation`**
- Constructor: `EventRegistration $registration`
- Content: event title, apology message, registrant name
- Subject: `"Event cancelled: {event title}"`

**`App\Mail\EventReminder`**
- Constructor: `EventRegistration $registration`, `EventDate $upcomingDate`
- Content: event title, registrant name, the specific date/time being reminded about,
  location for that date, link to the event landing page
- Subject: `"Reminder: {event title} is coming up"`

Store Markdown templates in `resources/views/mail/`. Keep them minimal — no heavy HTML.

---

### 2. Triggering Emails

**Registration confirmation — Model Observer**

Create `App\Observers\EventRegistrationObserver`. Register it in a service provider or
via the `#[ObservedBy]` attribute on `EventRegistration`.

```
created(EventRegistration $registration):
    if $registration->email is not empty:
        Mail::to($registration->email)->send(new RegistrationConfirmation($registration))
```

**Event cancellation — Model Observer**

Add to the existing `EventRegistrationObserver` or create `App\Observers\EventObserver`.
Register on the `Event` model.

```
updated(Event $event):
    if status changed to 'cancelled':
        foreach $event->registrations()->where('status', 'registered')->get() as $reg:
            if $reg->email:
                Mail::to($reg->email)->send(new EventCancellation($reg))
```

Use `$event->wasChanged('status')` and `$event->status === 'cancelled'` to guard the condition.

---

### 3. Scheduled Reminder Command

Create `App\Console\Commands\SendEventReminders`.

**Signature:** `events:send-reminders {--days=1 : Days before the event date to send}`

**Logic:**
- Query `EventDate::with('event.registrations')` where `starts_at` is between `now()` and
  `now()->addDays($days)->endOfDay()` and `status` is published (or inherited from a
  published event)
- For each date, for each registered (not waitlisted, not cancelled) `EventRegistration`
  on the parent event that has an email address:
  - Send `EventReminder($registration, $eventDate)`
- Log a summary: "Sent N reminders for M event dates"

**Scheduler registration:** Register `$schedule->command('events:send-reminders')->dailyAt('08:00')`
in the appropriate location for this Laravel version. Add a comment: `// Production: ensure
'php artisan schedule:run' is called every minute via cron`.

---

### 4. Registrant CSV Export

Add a `HeaderAction` (or `Action`) to `EventResource` that downloads a CSV of registrants
for the current event.

**Filament action on `EditEvent.php`** (header action, alongside existing ones):
- Label: "Export registrants"
- Icon: `heroicon-o-arrow-down-tray`
- Visible only when `$this->getRecord()->registrations()->exists()`
- On action: build a CSV with columns:
  `name, email, phone, company, address_line_1, city, state, zip, status, registered_at`
- Return a `Response::streamDownload()` with filename `registrants-{event-slug}-{date}.csv`
- No stored file — generate and stream on demand

---

### 5. Manual "Send Reminder Now" Action

Add another header action to `EditEvent.php`:

- Label: "Send reminders now"
- Icon: `heroicon-o-bell`
- `requiresConfirmation()` with a description explaining it will email all current registrants
- Visible only when there are upcoming dates and registered attendees with email addresses
- On action: call the same reminder logic as the scheduled command (extract to a service or
  call the command directly via `Artisan::call('events:send-reminders', ['--days' => 999])`)
- Show success notification with count of emails sent

---

### 6. Mailchimp Sync (only if API key is present)

**Skip this section entirely if `MAILCHIMP_API_KEY` is not in `.env`.**

If the key is present:

- Install `mailchimp/marketing` via Composer
- Create `App\Services\MailchimpService` with:
  - `upsertContact(EventRegistration $reg): void` — add/update member in the configured
    list (`MAILCHIMP_LIST_ID`), applying a tag matching the event title
  - `applyTag(string $email, string $tag): void`
- Call `MailchimpService::upsertContact($registration)` from the observer after sending
  the confirmation email
- Config: add `mailchimp.api_key` and `mailchimp.list_id` to `config/services.php`
- Add a Filament header action "Sync to Mailchimp" on EditEvent that syncs all current
  registrants for the event

---

## Models to Read Before Starting

- `app/Models/EventRegistration.php` — fields: `event_id`, `name`, `email`, `phone`,
  `company`, address fields, `status`, `registered_at`
- `app/Models/Event.php` — `status` enum (draft/published/cancelled), `landingPage()` relationship,
  `registrations()` hasMany, `eventDates()` hasMany
- `app/Models/EventDate.php` — `starts_at`, `ends_at`, `effectiveLocation()`, `effectiveStatus()`

---

## Tests to Write

- `RegistrationConfirmationTest`: creating an `EventRegistration` fires the mailable to
  the registrant's email (use `Mail::fake()`)
- `EventCancellationTest`: changing event status to `cancelled` queues cancellation emails
  to all registered (not waitlisted) attendees; draft or already-cancelled events do not
  trigger re-sends
- `SendEventRemindersTest`: command sends reminders only to registrants of events whose next
  date falls within the `--days` window; past dates are skipped
- `RegistrantExportTest`: the CSV download contains the correct number of rows and expected
  column headers

---

## Acceptance Criteria

- [ ] A new registrant receives a confirmation email (visible in `storage/logs/laravel.log`
  when `MAIL_MAILER=log`)
- [ ] Cancelling an event (changing status to `cancelled` in admin) sends cancellation emails
  to all `registered` attendees
- [ ] `php artisan events:send-reminders --days=7` sends reminders for events with dates
  in the next 7 days; events outside that window are skipped
- [ ] "Export registrants" downloads a valid CSV with one row per registrant
- [ ] "Send reminders now" action triggers emails and shows a success notification with count
- [ ] All mailable subjects and key content verified in tests
- [ ] No emails sent to registrants with a blank email address
- [ ] `php artisan test` passes with 0 failures before session close
