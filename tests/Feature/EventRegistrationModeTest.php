<?php

use App\Models\Event;
use App\Models\EventDate;
use App\Models\EventRegistration;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['site.events_prefix' => 'events']);
});

it('registration is accessible when registration_mode is open', function () {
    $event = Event::factory()->create([
        'status'            => 'published',
        'registration_mode' => 'open',
    ]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane Doe',
        'email'       => 'jane@example.com',
        '_form_start' => time() - 10,
        '_hp_name'    => '',
    ])->assertRedirect()->assertSessionDoesntHaveErrors('register');

    expect(EventRegistration::where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('registration is blocked with closed message when registration_mode is closed', function () {
    $event = Event::factory()->closedFull()->create(['status' => 'published']);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane Doe',
        'email'       => 'jane@example.com',
        '_form_start' => time() - 10,
        '_hp_name'    => '',
    ])->assertRedirect()->assertSessionHasErrors('register');

    expect(session('errors')->first('register'))
        ->toBe('Registration for this event is currently closed.');

    expect(EventRegistration::count())->toBe(0);
});

it('registration is blocked with no-registration message when registration_mode is none', function () {
    $event = Event::factory()->walkIn()->create(['status' => 'published']);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane Doe',
        'email'       => 'jane@example.com',
        '_form_start' => time() - 10,
        '_hp_name'    => '',
    ])->assertRedirect()->assertSessionHasErrors('register');

    expect(session('errors')->first('register'))
        ->toBe('This event does not require registration.');

    expect(EventRegistration::count())->toBe(0);
});

it('scopeOpenForRegistration returns only events with registration_mode open', function () {
    $open = Event::factory()->create(['status' => 'published', 'registration_mode' => 'open']);
    EventDate::factory()->upcoming()->create(['event_id' => $open->id]);

    $closed = Event::factory()->closedFull()->create(['status' => 'published']);
    EventDate::factory()->upcoming()->create(['event_id' => $closed->id]);

    $walkIn = Event::factory()->walkIn()->create(['status' => 'published']);
    EventDate::factory()->upcoming()->create(['event_id' => $walkIn->id]);

    $results = Event::openForRegistration()->pluck('id');

    expect($results)->toContain($open->id)
        ->not->toContain($closed->id)
        ->not->toContain($walkIn->id);
});

it('widget shows closed message when registration_mode is closed', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $event = Event::factory()->closedFull()->create(['status' => 'published']);

    $widgetType = WidgetType::where('handle', 'event_registration')->first();
    $page = Page::factory()->create(['is_published' => true]);
    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'label'          => 'Event Registration',
        'config'         => ['event_id' => $event->id],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    $this->get('/' . $page->slug)
        ->assertOk()
        ->assertSee('Registration for this event is currently closed.');
});

it('registration is blocked with external message when registration_mode is external', function () {
    $event = Event::factory()->create([
        'status'            => 'published',
        'registration_mode' => 'external',
    ]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane Doe',
        'email'       => 'jane@example.com',
        '_form_start' => time() - 10,
        '_hp_name'    => '',
    ])->assertRedirect()->assertSessionHasErrors('register');

    expect(session('errors')->first('register'))
        ->toBe('Registration for this event is handled externally.');

    expect(EventRegistration::count())->toBe(0);
});

it('widget shows external registration link when registration_mode is external and url is set', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $event = Event::factory()->create([
        'status'                    => 'published',
        'registration_mode'         => 'external',
        'external_registration_url' => 'https://external.example.com/register',
    ]);

    $widgetType = WidgetType::where('handle', 'event_registration')->first();
    $page = Page::factory()->create(['is_published' => true]);
    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'label'          => 'Event Registration',
        'config'         => ['event_id' => $event->id],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    $this->get('/' . $page->slug)
        ->assertOk()
        ->assertSee('https://external.example.com/register', false)
        ->assertSee('Register for this event');
});

it('widget shows walk-in message when registration_mode is none', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $event = Event::factory()->walkIn()->create(['status' => 'published']);

    $widgetType = WidgetType::where('handle', 'event_registration')->first();
    $page = Page::factory()->create(['is_published' => true]);
    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'label'          => 'Event Registration',
        'config'         => ['event_id' => $event->id],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    $this->get('/' . $page->slug)
        ->assertOk()
        ->assertSee('No registration required');
});
