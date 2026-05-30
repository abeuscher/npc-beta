<?php

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\Event;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['site.events_prefix' => 'events']);
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);
});

function landingPageHandles(Page $page): array
{
    return PageWidget::forOwner($page)
        ->join('widget_types', 'widget_types.id', '=', 'page_widgets.widget_type_id')
        ->orderBy('page_widgets.column_index')
        ->orderBy('page_widgets.sort_order')
        ->pluck('widget_types.handle')
        ->all();
}

// ── Page shape ────────────────────────────────────────────────────────────────

it('createLandingPageForEvent creates a page of type event at the correct slug', function () {
    $event = Event::factory()->create(['slug' => 'my-event', 'title' => 'My Event']);

    EventResource::createLandingPageForEvent($event);

    $page = Page::where('slug', 'events/my-event')->first();
    expect($page)->not->toBeNull();
    expect($page->type)->toBe('event');
    expect($page->status)->toBe('published');
    expect($page->title)->toBe('My Event');
});

it('createLandingPageForEvent sets landing_page_id on the event', function () {
    $event = Event::factory()->create(['slug' => 'my-event']);

    EventResource::createLandingPageForEvent($event);

    $page = Page::where('slug', 'events/my-event')->first();
    expect($event->fresh()->landing_page_id)->toBe($page->id);
});

it('createLandingPageForEvent is a no-op when landing_page_id is already set', function () {
    $existing = Page::factory()->create(['slug' => 'events/other', 'type' => 'event']);
    $event = Event::factory()->create(['slug' => 'my-event', 'landing_page_id' => $existing->id]);

    EventResource::createLandingPageForEvent($event);

    expect(Page::where('slug', 'events/my-event')->exists())->toBeFalse();
    expect($event->fresh()->landing_page_id)->toBe($existing->id);
});

// ── Preset derivation: registration only when the event needs it ───────────────

it('seeds image + description + share and no registration for a free, uncapped event', function () {
    $event = Event::factory()->create(['slug' => 'free-talk']);

    EventResource::createLandingPageForEvent($event);

    $page = Page::where('slug', 'events/free-talk')->firstOrFail();
    expect(landingPageHandles($page))->toBe(['event_image', 'event_description', 'social_sharing']);
});

it('lays the free preset out as two columns: image left, description + share right', function () {
    $event = Event::factory()->create(['slug' => 'two-col-free']);

    EventResource::createLandingPageForEvent($event);

    $page = Page::where('slug', 'events/two-col-free')->firstOrFail();

    $layout = $page->layouts()->first();
    expect($layout)->not->toBeNull()
        ->and($layout->display)->toBe('grid')
        ->and($layout->columns)->toBe(2)
        ->and($layout->layout_config['grid_template_columns'])->toBe('1fr 1fr')
        ->and($layout->appearance_config['layout']['padding']['top'])->toBe(120)
        ->and($layout->appearance_config['layout']['padding']['bottom'])->toBe(300);

    $byColumn = $page->widgets()->with('widgetType')->get()
        ->groupBy('column_index')
        ->map(fn ($group) => $group->sortBy('sort_order')
            ->map(fn ($w) => $w->widgetType->handle)->values()->all());

    expect($byColumn[0])->toBe(['event_image'])
        ->and($byColumn[1])->toBe(['event_description', 'social_sharing']);

    $share = $page->widgets()->with('widgetType')->get()
        ->first(fn ($w) => $w->widgetType->handle === 'social_sharing');
    expect($share->layout_id)->toBe($layout->id)
        ->and($share->config['alignment'])->toBe('left')
        ->and($share->config['heading'])->toBe('')
        ->and($share->appearance_config['layout']['padding']['top'])->toBe(25);
});

it('adds the registration widget when the event has a paid tier', function () {
    $event = Event::factory()->create(['slug' => 'gala']);
    $event->ticketTiers()->create(['name' => 'General', 'price' => 25.00, 'capacity' => null, 'sort_order' => 1]);

    EventResource::createLandingPageForEvent($event);

    $page = Page::where('slug', 'events/gala')->firstOrFail();
    expect(landingPageHandles($page))->toBe(['event_image', 'event_description', 'event_registration', 'social_sharing']);
});

it('adds the registration widget when the event has a free but capacity-capped tier', function () {
    $event = Event::factory()->create(['slug' => 'rsvp-dinner']);
    $event->ticketTiers()->create(['name' => 'RSVP', 'price' => 0, 'capacity' => 40, 'sort_order' => 1]);

    EventResource::createLandingPageForEvent($event);

    $page = Page::where('slug', 'events/rsvp-dinner')->firstOrFail();
    expect(landingPageHandles($page))->toContain('event_registration');
});

it('eventNeedsRegistration is false for a free, uncapped event and true for paid or capped', function () {
    $free = Event::factory()->create();
    expect(EventResource::eventNeedsRegistration($free))->toBeFalse();

    $free->ticketTiers()->create(['name' => 'Free', 'price' => 0, 'capacity' => null, 'sort_order' => 1]);
    expect(EventResource::eventNeedsRegistration($free->fresh()))->toBeFalse();

    $paid = Event::factory()->create();
    $paid->ticketTiers()->create(['name' => 'Paid', 'price' => 10, 'capacity' => null, 'sort_order' => 1]);
    expect(EventResource::eventNeedsRegistration($paid->fresh()))->toBeTrue();

    $capped = Event::factory()->create();
    $capped->ticketTiers()->create(['name' => 'Capped', 'price' => 0, 'capacity' => 50, 'sort_order' => 1]);
    expect(EventResource::eventNeedsRegistration($capped->fresh()))->toBeTrue();
});

// ── Create-form lifecycle: tiers are persisted before the LP is built ──────────

it('the create form seeds a registration widget for a ticketed event', function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    Livewire::test(CreateEvent::class)
        ->fillForm([
            'title'      => 'Benefit Gala',
            'start_date' => now()->addWeek()->format('Y-m-d'),
            'ticketTiers' => [
                ['name' => 'General', 'price' => 50.00, 'capacity' => null],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = Event::where('title', 'Benefit Gala')->firstOrFail();
    expect($event->landing_page_id)->not->toBeNull();
    expect(landingPageHandles($event->landingPage))->toContain('event_registration');
});

// ── Deletion guard: an event's landing page cannot be deleted ──────────────────

it('guardLandingPageDeletion blocks a page that backs an event and allows one that does not', function () {
    $lp = Page::factory()->create(['type' => 'event', 'slug' => 'events/guarded']);
    $event = Event::factory()->create(['landing_page_id' => $lp->id]);

    expect(PageResource::guardLandingPageDeletion($lp->fresh()))->toBeFalse();

    $plain = Page::factory()->create(['slug' => 'about']);
    expect(PageResource::guardLandingPageDeletion($plain))->toBeTrue();
});

it('the delete action leaves an event landing page in place', function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $lp = Page::factory()->create(['type' => 'event', 'slug' => 'events/protected']);
    Event::factory()->create(['landing_page_id' => $lp->id]);

    Livewire::test(ListPages::class)
        ->callTableAction(DeleteAction::class, $lp);

    expect(Page::whereKey($lp->id)->exists())->toBeTrue();
});
