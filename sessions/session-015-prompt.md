# Session 015 Prompt — Events: Registration Model, Landing Pages, and Event Widgets

## Context

Session 014 built the Events foundation. During post-build review two problems were identified:

1. `event_registrations.event_date_id` is the wrong anchor. Registration belongs to the *event*
   (the series), not a specific occurrence. A recurring board meeting has one registrant list.
2. The public URL `/events/{slug}/{dateId}` exposes a UUID and treats a date as the primary
   public entity. The correct URL is `/events/{slug}` — one page per event.

This session corrects both problems, adds the landing page system, and builds three event-aware
widget types. All design decisions are locked. Begin coding immediately.

---

## Step 0 — Wipe the database

Before writing any code, run:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

This drops all tables and re-runs all migrations and seeders from scratch. There is no
production data. This is intentional.

---

## Step 1 — New migrations

### 1a. `add_landing_page_id_to_events`

```php
Schema::table('events', function (Blueprint $table) {
    $table->foreignUuid('landing_page_id')
        ->nullable()
        ->after('is_recurring')
        ->constrained('pages')
        ->nullOnDelete();
});
```

### 1b. `alter_event_registrations_swap_fk`

Drop the `event_date_id` foreign key and column. Add `event_id` in its place.

```php
// up()
Schema::table('event_registrations', function (Blueprint $table) {
    $table->dropForeign(['event_date_id']);
    $table->dropColumn('event_date_id');
    $table->foreignUuid('event_id')
        ->after('id')
        ->constrained('events')
        ->cascadeOnDelete();
});

// down()
Schema::table('event_registrations', function (Blueprint $table) {
    $table->dropForeign(['event_id']);
    $table->dropColumn('event_id');
    $table->foreignUuid('event_date_id')
        ->after('id')
        ->constrained('event_dates')
        ->cascadeOnDelete();
});
```

Run: `docker compose exec app php artisan migrate`

---

## Step 2 — Update models

### `app/Models/Event.php`

- Add `landing_page_id` to `$fillable`
- Add relationship: `public function landingPage(): BelongsTo` → `belongsTo(Page::class)`
- Change `registrations()` from `hasManyThrough` to `hasMany(EventRegistration::class)`
- Simplify `isAtCapacity()` — no longer needs table-qualified column (no join ambiguity):
  ```php
  $registered = $this->registrations()
      ->whereIn('status', ['registered', 'waitlisted', 'attended'])
      ->count();
  ```

### `app/Models/EventRegistration.php`

- Remove `event_date_id` from `$fillable`, add `event_id`
- Remove `eventDate()` relationship, add: `public function event(): BelongsTo` → `belongsTo(Event::class)`

---

## Step 3 — Update routes

`routes/web.php` — remove `/{dateId}` from all event routes:

```php
$eventsPrefix = config('site.events_prefix', 'events');
Route::get("/{$eventsPrefix}", [EventController::class, 'index'])->name('events.index');
Route::get("/{$eventsPrefix}/{slug}", [EventController::class, 'show'])->name('events.show');
Route::post("/{$eventsPrefix}/{slug}/register", [EventController::class, 'register'])
    ->name('events.register')
    ->middleware('throttle:10,1');
```

---

## Step 4 — Update EventController

### `index()`
No changes needed except the view now links to `route('events.show', $date->event->slug)` —
but that is a template change, not a controller change.

### `show(string $slug)`

```php
public function show(string $slug): View
{
    $event = Event::where('slug', $slug)->firstOrFail();

    $dates = $event->eventDates()
        ->upcoming()
        ->orderBy('starts_at')
        ->get();

    $isCancelled      = $event->status === 'cancelled';
    $isAtCapacity     = $event->isAtCapacity();
    $registrationOpen = $event->registration_open
        && ! $isCancelled
        && ! $isAtCapacity
        && $event->is_free;

    return view('events.show', compact('event', 'dates', 'isCancelled', 'isAtCapacity', 'registrationOpen'));
}
```

Note: cancelled events still render (never 404). The view shows a cancellation notice.

### `register(string $slug)`

Same honeypot + timing + validation logic as before. Change the business logic and record
creation:

```php
$event = Event::where('slug', $slug)->firstOrFail();

// Guards
if ($event->status === 'cancelled') {
    return back()->withErrors(['register' => 'This event has been cancelled.']);
}
if (! $event->registration_open) {
    return back()->withErrors(['register' => 'Registration is not open for this event.']);
}
if (! $event->is_free) {
    return back()->withErrors(['register' => 'Paid registration is not yet available.']);
}
if ($event->isAtCapacity()) {
    return back()->withErrors(['register' => 'This event is at capacity.']);
}

EventRegistration::create([
    ...$validated,
    'event_id'      => $event->id,
    'contact_id'    => null,
    'registered_at' => now(),
    'status'        => 'registered',
]);

return redirect()->route('events.show', $slug)
    ->with('registration_success', true);
```

---

## Step 5 — Update Blade templates

### `resources/views/events/index.blade.php`

Change the event link and route call:
```blade
<a href="{{ route('events.show', $date->event->slug) }}">
```
(Remove the `$date->id` argument — route now takes only slug.)

Also update the footer link:
```blade
<a href="{{ route('events.show', $date->event->slug) }}">View event &rarr;</a>
```

### `resources/views/events/show.blade.php`

This view now receives `$event`, `$dates` (collection of EventDate), `$isCancelled`,
`$isAtCapacity`, `$registrationOpen`.

Structural changes:
1. Remove all references to `$date` (the old single EventDate variable)
2. Replace the single date/time block with a dates list — see spec below
3. Location display: use `$event` fields directly (not `$date->effectiveLocation()`)
4. Registration form action: `route('events.register', $event->slug)` (no date ID)

**Dates list section:**
```blade
@if ($dates->isNotEmpty())
    <ul>
        @foreach ($dates as $d)
            <li>
                <time datetime="{{ $d->starts_at->toIso8601String() }}">
                    {{ $d->starts_at->format('F j, Y') }}
                </time>
                @php $loc = $d->effectiveLocation(); @endphp
                @if ($loc['is_in_person'] && $loc['is_virtual'])
                    &mdash; In-person + Online
                    @if ($loc['city']) ({{ $loc['city'] }}@if($loc['state']), {{ $loc['state'] }}@endif) @endif
                @elseif ($loc['is_in_person'])
                    @if ($loc['city']) &mdash; {{ $loc['city'] }}@if($loc['state']), {{ $loc['state'] }}@endif @endif
                @elseif ($loc['is_virtual'])
                    &mdash; Online
                @endif
            </li>
        @endforeach
    </ul>
@endif
```

Keep all other sections (location details, virtual meeting URL, description, registration form,
success message, capacity message) — just update them to use `$event` instead of `$date` where
appropriate. The meeting URL section should use `$event->meeting_url` directly.

---

## Step 6 — Event widget types (seeder)

Add three new entries to `WidgetTypeSeeder::run()`. Follow the exact `updateOrCreate` pattern
already used for `text_block`.

### `event_description`
```php
WidgetType::updateOrCreate(
    ['handle' => 'event_description'],
    [
        'label'         => 'Event Description',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [
            ['key' => 'event_id', 'type' => 'text', 'label' => 'Event ID (UUID)'],
        ],
        'template'      => "@include('widgets.event-description')",
    ]
);
```

### `event_dates`
```php
WidgetType::updateOrCreate(
    ['handle' => 'event_dates'],
    [
        'label'         => 'Event Dates List',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [
            ['key' => 'event_id', 'type' => 'text', 'label' => 'Event ID (UUID)'],
        ],
        'template'      => "@include('widgets.event-dates')",
    ]
);
```

### `event_registration`
```php
WidgetType::updateOrCreate(
    ['handle' => 'event_registration'],
    [
        'label'         => 'Event Registration Form',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [
            ['key' => 'event_id', 'type' => 'text', 'label' => 'Event ID (UUID)'],
        ],
        'template'      => "@include('widgets.event-registration')",
    ]
);
```

Run the seeder: `docker compose exec app php artisan db:seed --class=WidgetTypeSeeder`

---

## Step 7 — Extend PageController to inject event data

In `PageController::renderPage()`, before `Blade::render()` is called for a server widget,
check if the widget config contains an `event_id` and inject the event and its upcoming dates
into the template variables.

Add this block inside the `foreach ($pageWidgets as $pw)` loop, after `$config` and
`$queryConfig` are set and before `Blade::render()`:

```php
// Inject event data for event-aware widget types
$eventData = [];
if (isset($config['event_id'])) {
    $resolvedEvent = \App\Models\Event::find($config['event_id']);
    if ($resolvedEvent) {
        $eventData = [
            'event' => $resolvedEvent,
            'dates' => $resolvedEvent->eventDates()->upcoming()->orderBy('starts_at')->get(),
        ];
    }
}
```

Then merge `$eventData` into the `Blade::render()` call:

```php
$html = $widgetType->template
    ? Blade::render(
        $widgetType->template,
        array_merge($collectionData, $eventData, ['config' => $config])
    )
    : '';
```

---

## Step 8 — Widget partial views

Create three files. These receive `$event`, `$dates`, and `$config` from the render context.

### `resources/views/widgets/event-description.blade.php`
```blade
@isset($event)
    @if ($event->description)
        <div class="event-description">
            {!! $event->description !!}
        </div>
    @endif
@endisset
```

### `resources/views/widgets/event-dates.blade.php`
```blade
@isset($event)
    @if ($dates->isNotEmpty())
        <ul class="event-dates-list">
            @foreach ($dates as $d)
                <li>
                    <time datetime="{{ $d->starts_at->toIso8601String() }}">
                        {{ $d->starts_at->format('F j, Y') }}
                    </time>
                    @php $loc = $d->effectiveLocation(); @endphp
                    @if ($loc['is_in_person'] && $loc['is_virtual'])
                        &mdash; In-person + Online
                        @if ($loc['city']) ({{ $loc['city'] }}@if($loc['state']), {{ $loc['state'] }}@endif) @endif
                    @elseif ($loc['is_in_person'])
                        @if ($loc['city']) &mdash; {{ $loc['city'] }}@if($loc['state']), {{ $loc['state'] }}@endif @endif
                    @elseif ($loc['is_virtual'])
                        &mdash; Online
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <p>No upcoming dates scheduled.</p>
    @endif
@endisset
```

### `resources/views/widgets/event-registration.blade.php`
```blade
@isset($event)
    @php
        $isCancelled  = $event->status === 'cancelled';
        $isAtCapacity = $event->isAtCapacity();
        $regOpen      = $event->registration_open && ! $isCancelled && ! $isAtCapacity && $event->is_free;
    @endphp

    @if (session('registration_success'))
        <div role="status">
            <strong>You're registered!</strong>
            <p>We look forward to seeing you.</p>
        </div>

    @elseif ($isCancelled)
        <div role="alert">
            <strong>This event has been cancelled.</strong>
        </div>

    @elseif ($isAtCapacity)
        <p>This event is at capacity. Registration is closed.</p>

    @elseif (! $event->registration_open)
        <p>Registration is not open for this event.</p>

    @elseif ($regOpen)
        <h2>Register</h2>

        @if ($errors->has('register'))
            <div role="alert">{{ $errors->first('register') }}</div>
        @endif

        <form method="POST" action="{{ route('events.register', $event->slug) }}">
            @csrf

            {{-- Honeypot --}}
            <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;opacity:0;pointer-events:none;">
                <label for="_hp_name">Leave this empty</label>
                <input type="text" id="_hp_name" name="_hp_name" tabindex="-1" autocomplete="off">
            </div>
            <input type="hidden" name="_form_start" value="{{ time() }}">

            <div>
                <label for="reg_name">Full Name <span aria-hidden="true">*</span></label>
                <input type="text" id="reg_name" name="name" required
                       value="{{ old('name') }}" autocomplete="name">
                @error('name')<span role="alert">{{ $message }}</span>@enderror
            </div>

            <div>
                <label for="reg_email">Email Address <span aria-hidden="true">*</span></label>
                <input type="email" id="reg_email" name="email" required
                       value="{{ old('email') }}" autocomplete="email">
                @error('email')<span role="alert">{{ $message }}</span>@enderror
            </div>

            <div>
                <label for="reg_phone">Phone <span>(optional)</span></label>
                <input type="tel" id="reg_phone" name="phone"
                       value="{{ old('phone') }}" autocomplete="tel">
            </div>

            <div>
                <label for="reg_company">Organization <span>(optional)</span></label>
                <input type="text" id="reg_company" name="company"
                       value="{{ old('company') }}" autocomplete="organization">
            </div>

            @if ($event->is_in_person)
                <fieldset>
                    <legend>Mailing Address <span>(optional)</span></legend>
                    <div>
                        <label for="reg_addr1">Address</label>
                        <input type="text" id="reg_addr1" name="address_line_1"
                               value="{{ old('address_line_1') }}" autocomplete="address-line1">
                    </div>
                    <div>
                        <label for="reg_addr2">Address Line 2</label>
                        <input type="text" id="reg_addr2" name="address_line_2"
                               value="{{ old('address_line_2') }}" autocomplete="address-line2">
                    </div>
                    <div>
                        <label for="reg_city">City</label>
                        <input type="text" id="reg_city" name="city"
                               value="{{ old('city') }}" autocomplete="address-level2">
                    </div>
                    <div>
                        <label for="reg_state">State</label>
                        <input type="text" id="reg_state" name="state"
                               value="{{ old('state') }}" autocomplete="address-level1">
                    </div>
                    <div>
                        <label for="reg_zip">Zip Code</label>
                        <input type="text" id="reg_zip" name="zip"
                               value="{{ old('zip') }}" autocomplete="postal-code">
                    </div>
                </fieldset>
            @endif

            <button type="submit">Register for this event</button>
        </form>
    @endif
@endisset
```

---

## Step 9 — "Create basic landing page" action

Update `app/Filament/Resources/EventResource/Pages/EditEvent.php`:

```php
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

protected function getHeaderActions(): array
{
    return [
        Actions\Action::make('viewEventPage')
            ->label('View event page')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->color('gray')
            ->url(fn () => $this->getRecord()->landing_page_id
                ? route('pages.show-by-slug', optional($this->getRecord()->landingPage)->slug)
                : route('events.show', $this->getRecord()->slug))
            ->openUrlInNewTab()
            // Use the page's public URL if LP exists, else the auto event page
            ->url(function () {
                $record = $this->getRecord();
                if ($record->landing_page_id && $record->landingPage) {
                    return url('/' . $record->landingPage->slug);
                }
                return route('events.show', $record->slug);
            }),

        Actions\Action::make('createLandingPage')
            ->label('Create basic landing page')
            ->icon('heroicon-o-document-plus')
            ->color('primary')
            ->visible(fn () => $this->getRecord()->landing_page_id === null)
            ->requiresConfirmation()
            ->modalHeading('Create landing page')
            ->modalDescription('This will create a new draft page with event widgets pre-configured. You can edit it fully after creation.')
            ->action(function () {
                $event = $this->getRecord();

                $page = Page::create([
                    'title'        => $event->title,
                    'slug'         => $event->slug,
                    'is_published' => false,
                ]);

                $widgetHandles = ['event_description', 'event_dates', 'event_registration'];
                $sort = 1;

                foreach ($widgetHandles as $handle) {
                    $widgetType = WidgetType::where('handle', $handle)->first();

                    if (! $widgetType) {
                        continue;
                    }

                    PageWidget::create([
                        'page_id'        => $page->id,
                        'widget_type_id' => $widgetType->id,
                        'label'          => $widgetType->label,
                        'config'         => ['event_id' => $event->id],
                        'sort_order'     => $sort++,
                        'is_active'      => true,
                    ]);
                }

                $event->update(['landing_page_id' => $page->id]);

                \Filament\Notifications\Notification::make()
                    ->title('Landing page created')
                    ->body('The page is saved as a draft. Edit it to customise before publishing.')
                    ->success()
                    ->send();

                $this->redirect(
                    \App\Filament\Resources\PageResource::getUrl('edit', ['record' => $page])
                );
            }),

        Actions\Action::make('editLandingPage')
            ->label('Edit landing page')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn () => $this->getRecord()->landing_page_id !== null)
            ->url(fn () => \App\Filament\Resources\PageResource::getUrl(
                'edit', ['record' => $this->getRecord()->landing_page_id]
            )),

        Actions\DeleteAction::make(),
    ];
}
```

Note: There is a duplicate `->url()` call in the `viewEventPage` action above — remove the
first `->url()` call and keep only the closure version. Clean it up when implementing.

---

## Step 10 — EventResource form: Split layout

Replace the `Tabs` layout in `EventResource::form()` with a `Split` layout. The structure:

**Left column (main, wider):**
- `Section` labelled "Description": `RichEditor::make('description')`
- `Section` labelled "Location": all location fields (is_in_person, address fields, map_url, map_label, is_virtual, meeting_url) — use `->hidden()` conditionals as before

**Right sidebar (narrow):**
- `Section` labelled "Settings":
  - `TextInput::make('title')` with slug auto-population
  - `TextInput::make('slug')`
  - `Select::make('status')`
  - `Toggle::make('is_free')`
  - `TextInput::make('capacity')`
  - `Toggle::make('registration_open')`
- `Section` labelled "Recurrence":
  - `Toggle::make('is_recurring')`
  - `Select::make('recurrence_type')` (hidden when not recurring)

Remove the "Dates" tab placeholder — the relation manager handles dates below the form.

Filament Split usage:
```php
Forms\Components\Split::make([
    Forms\Components\Group::make([/* left sections */])->columnSpan(2),
    Forms\Components\Group::make([/* right sections */])->columnSpan(1),
])->from('md')->columnSpanFull(),
```

---

## Step 11 — Update tests and factories

### `database/factories/EventRegistrationFactory.php`

Change `event_date_id` to `event_id`:
```php
'event_id' => Event::factory(),
```
Add `use App\Models\Event;` import.

### `tests/Feature/EventTest.php`

Update every test:
- Replace `route('events.show', [$event->slug, $date->id])` → `route('events.show', $event->slug)`
- Replace `route('events.register', [$event->slug, $date->id])` → `route('events.register', $event->slug)`
- Registration creation tests: use `event_id` not `event_date_id`
- The `show` test no longer needs a date to be created for the route to work (just the event)
- Keep date creation tests for the dates list rendering (assert dates appear on the show page)
- Capacity test: `$event->isAtCapacity()` now counts against the event directly

Add new tests:
```
it('creates a landing page with three widgets when action is triggered')
it('view event page button uses landing page URL when landing_page_id is set')
it('event_description widget renders event description on a page')
it('event_dates widget renders upcoming dates on a page')
it('event_registration widget renders the registration form on a page')
```

---

## Step 12 — Run tests

```bash
docker compose exec app php artisan test
```

Fix any failures before proceeding.

---

## Acceptance Criteria

- [ ] `migrate:fresh` runs cleanly with all migrations
- [ ] `event_registrations` has `event_id` FK, no `event_date_id`
- [ ] `events` has `landing_page_id` nullable FK to pages
- [ ] Registration creates a record with `event_id` set
- [ ] Public URL is `/events/{slug}` — no date ID anywhere
- [ ] Event show page lists all upcoming dates with location summary
- [ ] Cancelled event renders with notice, not 404
- [ ] Capacity enforced at event level
- [ ] Three event widget types seeded in `widget_types`
- [ ] Widget partials render correctly when event_id is in config
- [ ] "Create basic landing page" action creates page + 3 widgets + sets landing_page_id
- [ ] "Edit landing page" link appears after LP is created
- [ ] EventResource form uses Split layout (no tabs)
- [ ] All tests pass
