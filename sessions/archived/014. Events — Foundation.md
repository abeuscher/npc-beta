# Session 014 Prompt — Events: Foundation

## Context

Sessions 001–013 delivered the full CRM taxonomy (Contacts, Households, Organizations), Finance
models (Donations, Funds, Campaigns, Transactions), CMS (Posts, Pages, Collections, Tags,
Navigation, Widgets), user roles and permissions (Spatie-backed, RoleResource UI, 90 permissions
across 18 resources), and a system collection `source_type = 'events'` seeded but left as a
placeholder in `WidgetDataResolver`. This session builds the Event system end-to-end: data model,
admin UI, public landing pages, and a public registration form (free events only).

---

## Design Decisions (locked before implementation)

| Question | Decision |
|---|---|
| Event date model | `events` table holds canonical metadata; `event_dates` table holds each occurrence. Single-date events have one `event_date` row. |
| Recurrence | Manual date list OR rule-based (both supported). Rule stored as JSON on `events`. Filament "Generate Dates" action populates `event_dates` from the rule. |
| Series vs. occurrence edits | Each `event_date` is independently editable. Changing a single occurrence (location, time, etc.) never affects siblings or the parent event. The parent event holds the template; occurrences inherit but can override. |
| Location model | Fields directly on `events` table. Supports physical, virtual, or hybrid. Each event has `map_url` + `map_label` (no map picker this session). Location override fields on `event_dates` (nullable JSON) allow a single occurrence to have a different location. |
| Speakers/Presenters | Deferred entirely. No model, no fields. |
| Virtual events | `is_virtual` (bool) + `meeting_url` (nullable string) on `events`. `event_dates` can override `meeting_url`. Hybrid (physical + virtual) is valid — `is_in_person` and `is_virtual` are independent flags. |
| Public URL strategy | Same pattern as blog: `events_prefix` from `config('site.events_prefix', 'events')`. Add `events_prefix` to `config/site.php`. |
| Public registration form | Yes — simple Blade form for free events, this session. No payment. Model shaped to attach Stripe payment later. |
| Cancelled event public page | Renders the event page with a visible "This event has been cancelled" notice — never 404. |
| Capacity | Integer field on `events` (nullable = unlimited). Enforced on registration: form shows "sold out" and submission blocked when capacity is reached. Waitlist deferred. |
| Honeypot spam protection | Manual honeypot implementation — no external package. Hidden field + timing check + rate limiting. |
| Member pre-fill | `contact_id` (nullable FK) on `event_registrations` — designed for future member lookup but not implemented this session. |
| Mailchimp | Deferred. Not in scope. |
| Stripe readiness | `event_registrations` includes a nullable `stripe_payment_intent_id` column so ticketing (future session) has a clear attach point. |

---

## Data Model

### Migration 1 — `create_events_table`

```
events
  id               uuid, primary key
  title            string(255), not null
  slug             string(255), unique, not null
  description      text, nullable
  status           enum('draft','published','cancelled'), default 'draft'
  is_in_person     boolean, default true
  address_line_1   string(255), nullable
  address_line_2   string(255), nullable
  city             string(100), nullable
  state            string(100), nullable
  zip              string(20), nullable
  map_url          string(2048), nullable
  map_label        string(255), nullable
  is_virtual       boolean, default false
  meeting_url      string(2048), nullable
  is_free          boolean, default true
  capacity         unsignedInteger, nullable
  registration_open boolean, default true
  is_recurring     boolean, default false
  recurrence_type  enum('manual','rule'), nullable
  recurrence_rule  json, nullable
  timestamps
```

`recurrence_rule` JSON schema (for type = 'rule'):
```json
{
  "freq": "daily|business_days|weekly|monthly_day|monthly_date",
  "interval": 1,
  "days_of_week": ["monday","wednesday"],
  "nth": 1,
  "weekday": "monday",
  "day_of_month": 15,
  "start_time": "09:00",
  "end_time": "10:00",
  "until": "2026-12-31",
  "count": null
}
```
- `daily`: every `interval` days
- `business_days`: every `interval` business days (Mon–Fri only)
- `weekly`: every `interval` weeks on `days_of_week`
- `monthly_day`: the `nth` `weekday` of each month (e.g. first Monday)
- `monthly_date`: the `day_of_month` of each month

### Migration 2 — `create_event_dates_table`

```
event_dates
  id                   uuid, primary key
  event_id             uuid, FK events.id, onDelete cascade
  starts_at            datetime, not null
  ends_at              datetime, nullable
  status               enum('inherited','draft','published','cancelled'), default 'inherited'
  location_override    json, nullable   (same shape as events location fields, any key may be absent)
  meeting_url_override string(2048), nullable
  notes                text, nullable
  timestamps
```

`location_override` is a partial JSON object. Only keys that are present override the parent.
Example: `{"city":"Chicago"}` overrides only city; other location fields still inherit from the event.

### Migration 3 — `create_event_registrations_table`

```
event_registrations
  id                       uuid, primary key
  event_date_id            uuid, FK event_dates.id, onDelete cascade
  contact_id               uuid, FK contacts.id, nullable, onDelete set null
  name                     string(255), not null
  email                    string(255), not null
  phone                    string(50), nullable
  company                  string(255), nullable
  address_line_1           string(255), nullable
  address_line_2           string(255), nullable
  city                     string(100), nullable
  state                    string(100), nullable
  zip                      string(20), nullable
  status                   enum('registered','waitlisted','cancelled','attended'), default 'registered'
  registered_at            timestamp, not null (use useCurrent())
  stripe_payment_intent_id string(255), nullable
  notes                    text, nullable
  timestamps
```

---

## Models

### `app/Models/Event.php`

- `$fillable`: all columns above except id/timestamps
- `$casts`: `recurrence_rule` → `array`, `is_in_person`/`is_virtual`/`is_free`/`is_recurring`/`registration_open` → `boolean`, `capacity` → `integer`
- Relationships:
  - `eventDates()` → `hasMany(EventDate::class)`
  - `registrations()` → `hasManyThrough(EventRegistration::class, EventDate::class)`
- Scopes:
  - `scopePublished($q)` → `where('status', 'published')`
  - `scopeUpcoming($q)` → `whereHas('eventDates', fn($q) => $q->where('starts_at', '>=', now()))`
  - `scopeOpenForRegistration($q)` → `published()->upcoming()->where('registration_open', true)`
- Methods:
  - `nextDate(): ?EventDate` — first upcoming published event_date for this event
  - `isAtCapacity(): bool` — if capacity is null, return false; else count registrations with status != 'cancelled' across all dates and compare
  - `generateDatesFromRule(int $maxOccurrences = 52): Collection` — interprets `recurrence_rule` and returns a Collection of `['starts_at', 'ends_at']` arrays. Does NOT persist; called from the Filament action.

### `app/Models/EventDate.php`

- `$fillable`: all columns except id/timestamps
- `$casts`: `starts_at`/`ends_at` → `datetime`, `location_override` → `array`
- Relationships:
  - `event()` → `belongsTo(Event::class)`
  - `registrations()` → `hasMany(EventRegistration::class)`
- Scopes:
  - `scopeUpcoming($q)` → `where('starts_at', '>=', now())`
  - `scopePublished($q)` → `where(fn($q) => $q->where('status', 'published')->orWhere(fn($q) => $q->where('status', 'inherited')->whereHas('event', fn($q) => $q->where('status', 'published'))))`
- Methods:
  - `effectiveStatus(): string` — returns own status unless 'inherited', then returns parent event status
  - `effectiveLocation(): array` — merges event location fields with location_override (override wins)
  - `effectiveMeetingUrl(): ?string` — returns `meeting_url_override ?? event->meeting_url`
  - `registrationCount(): int` — count registrations with status != 'cancelled'
  - `isAtCapacity(): bool` — event->capacity is not null AND registrationCount() >= event->capacity

### `app/Models/EventRegistration.php`

- `$fillable`: all columns except id/timestamps
- `$casts`: `registered_at` → `datetime`
- Relationships:
  - `eventDate()` → `belongsTo(EventDate::class)`
  - `contact()` → `belongsTo(Contact::class)`

---

## Public Routes and Controller

### `config/site.php`

Create `config/site.php` with:
```php
<?php
return [
    'name'          => env('SITE_NAME', 'Nonprofit CRM'),
    'blog_prefix'   => env('BLOG_PREFIX', 'news'),
    'events_prefix' => env('EVENTS_PREFIX', 'events'),
];
```

Update `routes/web.php` to replace `config('site.blog_prefix', 'news')` with `config('site.blog_prefix')` and add event routes:

```php
$eventsPrefix = config('site.events_prefix');
Route::get("/{$eventsPrefix}", [EventController::class, 'index'])->name('events.index');
Route::get("/{$eventsPrefix}/{slug}/{dateId}", [EventController::class, 'show'])->name('events.show');
Route::post("/{$eventsPrefix}/{slug}/{dateId}/register", [EventController::class, 'register'])
    ->name('events.register')
    ->middleware('throttle:10,1');
```

### `app/Http/Controllers/EventController.php`

**`index()`**
- Query: `EventDate::with('event')->published()->upcoming()->orderBy('starts_at')->paginate(15)`
- Pass to view: `$dates`, `$title = 'Events'`

**`show(string $slug, string $dateId)`**
- Load event: `Event::where('slug', $slug)->firstOrFail()`
- Load date: `EventDate::where('id', $dateId)->where('event_id', $event->id)->firstOrFail()`
- Pass to view: `$event`, `$date`, `$isCancelled` (effectiveStatus === 'cancelled'), `$isAtCapacity` ($date->isAtCapacity()), `$registrationOpen` (event->registration_open && !$isCancelled && !$isAtCapacity && event->is_free)
- Note: even cancelled event dates render — never 404

**`register(Request $request, string $slug, string $dateId)`**
Honeypot + timing checks:
```php
// Reject bots silently — redirect back as if successful
if ($request->filled('_hp_name')) {
    return redirect()->route('events.show', [$slug, $dateId])
        ->with('registration_success', true);
}
$formStart = (int) $request->input('_form_start', 0);
if ($formStart > 0 && (time() - $formStart) < 3) {
    return redirect()->route('events.show', [$slug, $dateId])
        ->with('registration_success', true);
}
```
Validation:
```php
$validated = $request->validate([
    'name'           => ['required', 'string', 'max:255'],
    'email'          => ['required', 'email', 'max:255'],
    'phone'          => ['nullable', 'string', 'max:50'],
    'company'        => ['nullable', 'string', 'max:255'],
    'address_line_1' => ['nullable', 'string', 'max:255'],
    'address_line_2' => ['nullable', 'string', 'max:255'],
    'city'           => ['nullable', 'string', 'max:100'],
    'state'          => ['nullable', 'string', 'max:100'],
    'zip'            => ['nullable', 'string', 'max:20'],
]);
```
Business logic checks (after validation — load event and date):
- If `$date->isAtCapacity()` → back with error
- If `!$event->registration_open` → back with error
- If effectiveStatus === 'cancelled' → back with error
- If `!$event->is_free` → back with error (payment path not built yet)

Create registration:
```php
EventRegistration::create([
    ...$validated,
    'event_date_id' => $date->id,
    'contact_id'    => null,
    'registered_at' => now(),
    'status'        => 'registered',
]);
```
Redirect: `redirect()->route('events.show', [$slug, $dateId])->with('registration_success', true)`

---

## Blade Templates

### `resources/views/events/index.blade.php`

Extend `layouts.public`. Show a list of upcoming event dates. Each item shows:
- Event title (linked to `route('events.show', [$date->event->slug, $date->id])`)
- Date and time (`$date->starts_at->format('D, F j, Y \a\t g:i A')`)
- Location summary: city/state if in-person, "Virtual" if only virtual, "In-person + Virtual" if hybrid
- "Free" badge if `$date->event->is_free`
- "Registration closed" note if `!$date->event->registration_open`
- "Sold out" note if `$date->isAtCapacity()`

Empty state: "No upcoming events. Check back soon."

### `resources/views/events/show.blade.php`

Extend `layouts.public`. Structure:

**Cancelled notice** (shown when `$isCancelled`):
```html
<div role="alert">
    <strong>This event has been cancelled.</strong>
</div>
```

**Event header**: title, date/time, location details.
Location rendering:
- If in-person: show full address, and if `map_url` is set, render `<a href="{{ $date->effectiveLocation()['map_url'] }}">{{ $date->effectiveLocation()['map_label'] ?? 'View map' }}</a>`
- If virtual: show "Online event" and meeting URL link (only show full link after registration — for now, show it if event has no registration or after success)
- If hybrid: show both sections

**Description** block.

**Registration form** — shown only when `$registrationOpen` is true and not `$isCancelled`.

If `session('registration_success')`:
```html
<p>You're registered! A confirmation will be sent to your email.</p>
```

Form structure:
```html
<form method="POST" action="{{ route('events.register', [$event->slug, $date->id]) }}">
    @csrf
    {{-- Honeypot --}}
    <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;opacity:0;">
        <label for="_hp_name">Leave this empty</label>
        <input type="text" id="_hp_name" name="_hp_name" tabindex="-1" autocomplete="off">
    </div>
    <input type="hidden" name="_form_start" value="{{ time() }}">

    {{-- Required fields --}}
    <div>
        <label for="name">Full Name *</label>
        <input type="text" id="name" name="name" required value="{{ old('name') }}">
        @error('name')<span>{{ $message }}</span>@enderror
    </div>

    <div>
        <label for="email">Email Address *</label>
        <input type="email" id="email" name="email" required value="{{ old('email') }}">
        @error('email')<span>{{ $message }}</span>@enderror
    </div>

    {{-- Optional fields --}}
    <div>
        <label for="phone">Phone <span>(optional)</span></label>
        <input type="tel" id="phone" name="phone" value="{{ old('phone') }}">
    </div>

    <div>
        <label for="company">Organization <span>(optional)</span></label>
        <input type="text" id="company" name="company" value="{{ old('company') }}">
    </div>

    {{-- Address fields — shown only for in-person events via Alpine --}}
    @if ($event->is_in_person)
    <div>
        <label for="address_line_1">Address <span>(optional)</span></label>
        <input type="text" id="address_line_1" name="address_line_1" value="{{ old('address_line_1') }}">
    </div>
    <div>
        <label for="address_line_2">Address Line 2 <span>(optional)</span></label>
        <input type="text" id="address_line_2" name="address_line_2" value="{{ old('address_line_2') }}">
    </div>
    <div>
        <label for="city">City <span>(optional)</span></label>
        <input type="text" id="city" name="city" value="{{ old('city') }}">
    </div>
    <div>
        <label for="state">State <span>(optional)</span></label>
        <input type="text" id="state" name="state" value="{{ old('state') }}">
    </div>
    <div>
        <label for="zip">Zip Code <span>(optional)</span></label>
        <input type="text" id="zip" name="zip" value="{{ old('zip') }}">
    </div>
    @endif

    <button type="submit">Register</button>
</form>
```

**"Sold out" message** when `$isAtCapacity`:
```html
<p>This event is at capacity. Registration is closed.</p>
```

---

## Filament Admin UI

### `app/Filament/Resources/EventResource.php`

**Navigation**: group `Content`, sort `5`, icon `heroicon-o-calendar-days`, label `Events`

**Form** — use `Tabs` to organise the form into three tabs:

**Tab: Details**
- `TextInput::make('title')` — required, max 255, live(onBlur) → auto-populate slug
- `TextInput::make('slug')` — required, unique (ignore self on edit), regex `^[a-z0-9\-]+$`
- `Select::make('status')` — options: `['draft'=>'Draft','published'=>'Published','cancelled'=>'Cancelled']`
- `RichEditor::make('description')` — nullable
- `Toggle::make('is_free')` — label "Free event", default true
- `TextInput::make('capacity')` — numeric, nullable, min 1, hint "Leave blank for unlimited"
- `Toggle::make('registration_open')` — label "Registration open", default true

**Tab: Location**
- `Toggle::make('is_in_person')` — label "In-person attendance", default true
- `TextInput::make('address_line_1')` — nullable, `->hidden(fn($get) => !$get('is_in_person'))`
- `TextInput::make('address_line_2')` — nullable, hidden when not in-person
- `TextInput::make('city')` — nullable, hidden when not in-person
- `TextInput::make('state')` — nullable, hidden when not in-person
- `TextInput::make('zip')` — nullable, hidden when not in-person
- `TextInput::make('map_url')` — nullable, url validation, hidden when not in-person, label "Map URL"
- `TextInput::make('map_label')` — nullable, hidden when not in-person, label "Map Link Label", hint "e.g. 'View on Google Maps'"
- `Toggle::make('is_virtual')` — label "Virtual / Online attendance"
- `TextInput::make('meeting_url')` — nullable, url validation, `->hidden(fn($get) => !$get('is_virtual'))`, label "Meeting URL"

**Tab: Dates** — contains a placeholder message: "Add and edit dates in the Dates tab below." (The actual dates are managed via the `EventDatesRelationManager`.)

Also add to the Details tab, below status:
- `Toggle::make('is_recurring')` — label "Recurring event"
- `Select::make('recurrence_type')` — options `['manual'=>'Manual (pick dates individually)', 'rule'=>'Rule-based (generate from pattern)']`, nullable, `->hidden(fn($get) => !$get('is_recurring'))`

**Table columns**:
- `TextColumn::make('title')` — sortable, searchable
- `BadgeColumn::make('status')` — colors: `draft`→gray, `published`→green, `cancelled`→red
- `TextColumn::make('eventDates_count')` — label "Dates", via `->counts('eventDates')`
- `IconColumn::make('is_free')` — boolean, label "Free"
- `TextColumn::make('registrations_count')` — label "Registrations", custom using `$record->registrations()->count()`

**Table actions**: standard Edit, Delete (with confirmation)

### `app/Filament/Resources/EventResource/Pages/`

Standard pages: `ListEvents`, `CreateEvent`, `EditEvent`.

`EditEvent` has two relation managers (tabs below the form): `EventDatesRelationManager`, `EventRegistrationsRelationManager`.

### `app/Filament/Resources/EventResource/RelationManagers/EventDatesRelationManager.php`

**Table columns**:
- `TextColumn::make('starts_at')` — sortable, format `'D, M j, Y g:i A'`
- `TextColumn::make('ends_at')` — nullable, format `'g:i A'`
- `BadgeColumn::make('status')` — `inherited`→gray, `published`→green, `cancelled`→red, `draft`→gray
- `TextColumn::make('registrations_count')` — label "Registrations", `->counts('registrations')`

**Table header actions**:
- `CreateAction` (standard)
- `Action::make('generateDates')` — label "Generate from Rule", icon `heroicon-o-sparkles`, hidden when `!$this->getOwnerRecord()->is_recurring || $this->getOwnerRecord()->recurrence_type !== 'rule'`

  The `generateDates` action opens a modal form with:
  - `Select::make('freq')` — options mapping to recurrence types
  - `TextInput::make('interval')` — integer, min 1, default 1
  - `CheckboxList::make('days_of_week')` — shown for weekly freq
  - `Select::make('nth')` and `Select::make('weekday')` — shown for monthly_day
  - `TextInput::make('day_of_month')` — shown for monthly_date
  - `TimePicker::make('start_time')` — required
  - `TimePicker::make('end_time')` — nullable
  - `DatePicker::make('until')` — "Repeat until" date
  - `TextInput::make('count')` — "Max occurrences" (nullable)

  On submit, call `$event->generateDatesFromRule($formData)` which returns an array of `[starts_at, ends_at]` pairs. Bulk-insert into `event_dates`. Show a success notification: "X dates generated."

**Form** (create/edit a single event date):
- `DateTimePicker::make('starts_at')` — required
- `DateTimePicker::make('ends_at')` — nullable
- `Select::make('status')` — options `['inherited'=>'Inherit from event','draft'=>'Draft','published'=>'Published','cancelled'=>'Cancelled']`, default 'inherited'
- `Textarea::make('notes')` — nullable
- Collapsible section "Location Override" with address fields + map fields + meeting_url_override (all nullable, with hint "Leave blank to use the event's location")

### `app/Filament/Resources/EventResource/RelationManagers/EventRegistrationsRelationManager.php`

Read-only in this session (no create/edit — registrations come from the public form).

**Table columns**:
- `TextColumn::make('name')` — searchable
- `TextColumn::make('email')` — searchable
- `TextColumn::make('eventDate.starts_at')` — label "Date", format `'M j, Y g:i A'`
- `BadgeColumn::make('status')` — `registered`→green, `waitlisted`→yellow, `cancelled`→red, `attended`→blue
- `TextColumn::make('registered_at')` — sortable, format `'M j, Y'`

**Table actions**: none (read-only). No header CreateAction.

**Note**: Add a `ViewAction` if you want to see full registration details including address.

---

## WidgetDataResolver Update

Replace the `'events' => []` placeholder in `WidgetDataResolver::resolve()`:

```php
'events' => static::resolveEvents($queryConfig),
```

Add the private method:
```php
private static function resolveEvents(array $queryConfig): array
{
    $limit = isset($queryConfig['limit']) ? (int) $queryConfig['limit'] : null;

    $eventsPrefix = config('site.events_prefix', 'events');

    $query = EventDate::with('event')
        ->published()
        ->upcoming()
        ->orderBy('starts_at', 'asc');

    if ($limit) {
        $query->limit($limit);
    }

    return $query->get()->map(fn (EventDate $date) => [
        'id'        => $date->id,
        'title'     => $date->event->title,
        'slug'      => $date->event->slug,
        'starts_at' => $date->starts_at->toIso8601String(),
        'ends_at'   => $date->ends_at?->toIso8601String(),
        'is_virtual'  => $date->event->is_virtual,
        'is_free'     => $date->event->is_free,
        'url'         => route('events.show', [$date->event->slug, $date->id]),
    ])->all();
}
```

Add `use App\Models\EventDate;` to the imports.

---

## ADR — `docs/adr/014-event-data-model.md`

Document the following decisions:
- Why `event_dates` is a separate table (recurring/multi-date support, independent override per occurrence)
- Why occurrence changes do not cascade to siblings (each occurrence independently editable; use the event record as a template, not a live parent)
- Why `contact_id` is nullable now (member pre-fill deferred; the field exists so future work doesn't require a migration)
- Why `stripe_payment_intent_id` is on registrations (clean attach point for ticketing session; no payment logic yet)
- Why honeypot + rate limiting instead of CAPTCHA (privacy-first; no external dependency; Google reCAPTCHA reserved for when payment is live and liability transfer matters)
- Why location is on `events` not a separate `locations` table (sufficient for MVP; location reuse and a locations admin are session 015+ concerns)

---

## Tests — `tests/Feature/EventTest.php`

Write Pest feature tests:

```
it('published upcoming event dates appear on the events index page')
it('draft events do not appear on the events index page')
it('show page renders for a published event date')
it('cancelled event date renders with a cancellation notice not a 404')
it('registration form creates an EventRegistration record')
it('registration is blocked when capacity is reached')
it('registration is blocked for cancelled event dates')
it('honeypot field triggers silent success without creating a registration')
it('timing check blocks submissions under 3 seconds without creating a registration')
it('event upcoming scope returns only events with future dates')
it('event published scope returns only published events')
```

Test helpers:
- Use `Event::factory()` and `EventDate::factory()` with `EventRegistration::factory()`
- Create factories for all three models
- Use `$this->get(route('events.index'))` etc. for HTTP tests
- For capacity test: create `n` registrations equal to `event->capacity`, then attempt to register one more

---

## Acceptance Criteria

- [ ] All three migrations run cleanly via Docker (`docker compose exec app php artisan migrate`)
- [ ] `Event`, `EventDate`, `EventRegistration` models exist with correct relationships and scopes
- [ ] `EventResource` appears in Content navigation (sort 5), admin can create/edit events with all fields
- [ ] `EventDatesRelationManager` works: add, edit, delete individual dates
- [ ] "Generate from Rule" action generates `event_date` rows from the recurrence rule
- [ ] `EventRegistrationsRelationManager` shows registrations (read-only)
- [ ] Public `/events` page lists upcoming published dates
- [ ] Public `/events/{slug}/{dateId}` renders event details
- [ ] Cancelled event date shows notice, not 404
- [ ] Registration form submits and creates a record; success message displayed
- [ ] Registration blocked when capacity is reached
- [ ] Honeypot field silently discards bot submissions
- [ ] `WidgetDataResolver` returns real event data for `source_type = 'events'`
- [ ] `config/site.php` exists with `events_prefix`
- [ ] ADR written in `docs/adr/014-event-data-model.md`
- [ ] `php artisan test` passes with no failures
