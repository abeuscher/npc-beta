# Session 018 Prompt — Event Model Enhancements & Contact Auto-Creation

## Context

Sessions 014–017 built events, public landing pages, the registration form, and email
notifications. This session extends the Event model with several fields that emerged from
planning, consolidates the Contact data model, and wires up event registrations to
automatically create or match Contact records.

---

## Pre-Session Checks

Before writing any code, read:

- `app/Models/Event.php` — confirm current fields, particularly `registration_open` (boolean)
  which will be replaced in this session
- `app/Models/EventRegistration.php` — fields and observer registration
- `app/Models/Contact.php` — current fillable fields, relationships, and any unique constraints
  on email
- `app/Filament/Resources/EventResource.php` — current form structure, to understand where
  new fields will be inserted
- `app/Http/Controllers/EventController.php` — `register()` method, to understand where
  contact creation logic will be added

---

## Goals for This Session

1. Add `external_registration_url` field to Event — disables registration-related form fields
   in admin when set
2. Replace `registration_open` boolean with `registration_mode` enum
3. Add `auto_create_contacts` and `mailing_list_opt_in_enabled` toggles to Event
4. Add `mailing_list_opt_in` field to `EventRegistration`
5. Auto-create or upsert a Contact record when a registration is submitted
6. Show opt-in checkbox on public registration form when `mailing_list_opt_in_enabled` is true

---

## Implementation Plan

### 1. Migration: Event Changes

Create a migration that:

- Adds `external_registration_url` (nullable string) to `events`
- Adds `registration_mode` (string, default `'open'`) to `events`
- Drops `registration_open` boolean column from `events`
- Adds `auto_create_contacts` (boolean, default `true`) to `events`
- Adds `mailing_list_opt_in_enabled` (boolean, default `false`) to `events`

Update `Event::$fillable` and `Event::$casts` accordingly.

Update the `registration_open` boolean cast to `registration_mode` (no cast needed — it's
a plain string).

Update the `scopeOpenForRegistration` scope:
```php
// Before:
$query->published()->upcoming()->where('registration_open', true);

// After:
$query->published()->upcoming()->where('registration_mode', 'open');
```

Update `EventFactory`: replace `'registration_open' => true` with `'registration_mode' => 'open'`.
Add factory states `closedFull()` and `walkIn()` for the new modes.

---

### 2. Migration: EventRegistration Change

Create a separate migration that adds `mailing_list_opt_in` (boolean, default `false`) to
`event_registrations`.

Update `EventRegistration::$fillable` and `$casts`.

---

### 3. EventResource Form Updates

**`external_registration_url` field** — insert below `status`:

```php
TextInput::make('external_registration_url')
    ->label('External registration URL')
    ->url()
    ->nullable()
    ->helperText('If set, registration is handled externally. Registration fields below will be disabled.')
    ->columnSpan('full'),
```

**Disable registration-related fields when external URL is set.** The fields to disable:

- `price`
- `capacity`
- `registration_mode` (the new select, see below)
- `auto_create_contacts`
- `mailing_list_opt_in_enabled`

Use `->disabled(fn (Get $get) => filled($get('external_registration_url')) && filter_var($get('external_registration_url'), FILTER_VALIDATE_URL) !== false)` on each of those fields. Do NOT use `->hidden()` — the fields must remain visible but inactive.

**`registration_mode` select** — replace the `registration_open` toggle with:

```php
Select::make('registration_mode')
    ->options([
        'open'   => 'Open — accepting registrations',
        'closed' => 'Closed — at capacity or paused',
        'none'   => 'No registration required (walk-in / public event)',
    ])
    ->default('open')
    ->required(),
```

**New toggles** — add in the registration settings section:

```php
Toggle::make('auto_create_contacts')
    ->label('Automatically add registrants to Contacts')
    ->default(true)
    ->helperText('Creates or updates a Contact record for each new registrant.')
    ->disabled(/* same external URL condition */),

Toggle::make('mailing_list_opt_in_enabled')
    ->label('Show mailing list opt-in checkbox on registration form')
    ->default(false)
    ->helperText('Adds an opt-in checkbox to the public registration form. Stores the answer on the registration record.')
    ->disabled(/* same external URL condition */),
```

---

### 4. Public Registration Form

In `resources/views/widgets/event-registration.blade.php` (or wherever the registration form
lives — read the file before editing):

- When `$event->mailing_list_opt_in_enabled` is true, show a checkbox:
  `<label><input type="checkbox" name="mailing_list_opt_in" value="1"> Keep me informed about future events and updates</label>`
- The checkbox should be unchecked by default
- Validate in `EventController@register`: `'mailing_list_opt_in' => 'nullable|boolean'`
- Save to `EventRegistration::$mailing_list_opt_in`

---

### 5. Contact Auto-Creation (Observer)

Update `App\Observers\EventRegistrationObserver::created()`:

After sending the confirmation email, if `$registration->event->auto_create_contacts` is true:

```
$contact = Contact::firstOrCreate(
    ['email' => $registration->email],          // match key
    [                                           // defaults for new record
        'first_name' => (split name on space)[0],
        'last_name'  => (split name on space)[1..] joined,
        'phone'      => $registration->phone,
        'company'    => $registration->company,
        'address_line_1' => $registration->address_line_1,
        'city'       => $registration->city,
        'state'      => $registration->state,
        'zip'        => $registration->zip,
    ]
);

// Link the contact to the registration if not already set
if (! $registration->contact_id) {
    $registration->update(['contact_id' => $contact->id]);
}
```

Read `app/Models/Contact.php` carefully before implementing — match field names exactly.

If `$registration->email` is empty, skip Contact creation entirely (already no confirmation
email is sent, so the guard is consistent).

**Note:** This only fires when `auto_create_contacts` is true on the event. The observer
must load the event relationship: `$registration->loadMissing('event')`.

---

### 6. EventController: registration_open → registration_mode

In `EventController@register()`, find where `registration_open` is checked and update to
use `registration_mode`:

```php
// Before (conceptual):
if (! $event->registration_open) { ... }

// After:
if ($event->registration_mode !== 'open') {
    // Return appropriate message based on mode
    $message = $event->registration_mode === 'none'
        ? 'This event does not require registration.'
        : 'Registration for this event is currently closed.';
    // redirect with message
}
```

Read the controller before editing to match existing redirect/response patterns.

---

### 7. Public-Facing Registration Mode Messaging

In the `event_registration` widget template, update the "registration closed" state to
show mode-specific messages:

- `closed`: "Registration for this event is currently closed."
- `none`: "No registration required — just show up!"
- `open` with external URL: render a link button to `$event->external_registration_url`
  labeled "Register for this event →" instead of the form

Read the widget template before editing.

---

## Tests to Write

- **`EventRegistrationModeTest`**:
  - `registration_mode = 'open'` → form is accessible
  - `registration_mode = 'closed'` → registration blocked, "closed" message shown
  - `registration_mode = 'none'` → registration blocked, "no registration required" message shown
  - `scopeOpenForRegistration` returns only `mode = 'open'` events

- **`ContactAutoCreationTest`**:
  - Registration with email and `auto_create_contacts = true` creates a Contact record
  - Registration with email and `auto_create_contacts = false` does NOT create Contact
  - Registration with matching email updates `contact_id` on the registration (does not create duplicate Contact)
  - Registration without email skips Contact creation

- **`MailingListOptInTest`**:
  - `mailing_list_opt_in_enabled = false` → opt-in checkbox not rendered on form
  - `mailing_list_opt_in_enabled = true` → opt-in checkbox is rendered
  - Submitting with checkbox checked → `mailing_list_opt_in = true` on registration record
  - Submitting without checkbox → `mailing_list_opt_in = false` on registration record

---

## Acceptance Criteria

- [ ] `external_registration_url` field appears below status in EventResource form
- [ ] Registration-related fields are disabled (not hidden) when external URL is valid
- [ ] External registration URL renders a link button on the public event page instead of the form
- [ ] `registration_open` boolean is gone; `registration_mode` select is in its place
- [ ] `closed` and `none` modes each produce distinct messages on the public registration widget
- [ ] `auto_create_contacts` toggle present in admin, defaults to true
- [ ] `mailing_list_opt_in_enabled` toggle present in admin, defaults to false
- [ ] Opt-in checkbox appears on public form only when toggle is enabled
- [ ] `mailing_list_opt_in` stored correctly on `EventRegistration`
- [ ] Contact created/matched when `auto_create_contacts` is true and email is present
- [ ] `contact_id` populated on `EventRegistration` after Contact creation
- [ ] `php artisan test` passes with 0 failures
