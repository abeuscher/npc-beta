<?php

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['site.events_prefix' => 'events']);
    Mail::fake();
});

it('opt-in checkbox is not rendered when mailing_list_opt_in_enabled is false', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $event = Event::factory()->create([
        'status'                    => 'published',
        'registration_mode'         => 'open',
        'mailing_list_opt_in_enabled' => false,
    ]);

    $widgetType = WidgetType::where('handle', 'event_registration')->first();
    $page = Page::factory()->create(['is_published' => true]);
    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'label'          => 'Event Registration',
        'config'         => ['event_slug' => $event->slug],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    $this->get('/' . $page->slug)
        ->assertOk()
        ->assertDontSee('mailing_list_opt_in');
});

it('opt-in checkbox is rendered when mailing_list_opt_in_enabled is true', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $event = Event::factory()->create([
        'status'                    => 'published',
        'registration_mode'         => 'open',
        'mailing_list_opt_in_enabled' => true,
    ]);

    $widgetType = WidgetType::where('handle', 'event_registration')->first();
    $page = Page::factory()->create(['is_published' => true]);
    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'label'          => 'Event Registration',
        'config'         => ['event_slug' => $event->slug],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    $this->get('/' . $page->slug)
        ->assertOk()
        ->assertSee('mailing_list_opt_in', false)
        ->assertSee('Keep me informed about future events');
});

it('stores mailing_list_opt_in as true when checkbox is submitted', function () {
    $event = Event::factory()->create([
        'status'                    => 'published',
        'registration_mode'         => 'open',
        'mailing_list_opt_in_enabled' => true,
        'auto_create_contacts'      => false,
    ]);

    $this->post(route('events.register', $event->slug), [
        'name'                => 'Jane Doe',
        'email'               => 'jane@example.com',
        'mailing_list_opt_in' => '1',
        '_form_start'         => time() - 10,
        '_hp_name'            => '',
    ])->assertRedirect();

    $registration = EventRegistration::where('email', 'jane@example.com')->first();
    expect($registration->mailing_list_opt_in)->toBeTrue();
});

it('stores mailing_list_opt_in as false when checkbox is not submitted', function () {
    $event = Event::factory()->create([
        'status'                    => 'published',
        'registration_mode'         => 'open',
        'mailing_list_opt_in_enabled' => true,
        'auto_create_contacts'      => false,
    ]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'John Smith',
        'email'       => 'john@example.com',
        '_form_start' => time() - 10,
        '_hp_name'    => '',
    ])->assertRedirect();

    $registration = EventRegistration::where('email', 'john@example.com')->first();
    expect($registration->mailing_list_opt_in)->toBeFalse();
});
