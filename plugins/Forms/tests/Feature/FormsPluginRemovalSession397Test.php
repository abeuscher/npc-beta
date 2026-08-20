<?php

use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Widgets\QuickActions\QuickActionsDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\FormsRemovedTestCase;

uses(FormsRemovedTestCase::class, RefreshDatabase::class);

// Session 397 (Plugin Architecture arc D7): the remove-the-line mirror for
// the Forms vertical. The application boots with the Forms line stripped
// from config('plugins'); the route, the widget, the admin surface, the
// observer, and both email-handle defaults must vanish — while form DATA is
// kept (disabled ≠ uninstalled, contract surface 5: the fixture registers
// the plugin's migration path so the tables exist).

it('does not load the Forms provider', function () {
    expect(config('plugins'))->not->toContain(\Plugins\Forms\FormsServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\Forms\FormsServiceProvider::class))->toBeFalse();
});

it('drops the forms.submit route — a POST answers 405 from the GET catch-all', function () {
    expect(Route::has('forms.submit'))->toBeFalse();

    $this->post('/forms/newsletter-signup')->assertStatus(405);
});

it('drops the three FormResource admin routes', function () {
    foreach ([
        'filament.admin.resources.forms.index',
        'filament.admin.resources.forms.create',
        'filament.admin.resources.forms.edit',
    ] as $name) {
        expect(Route::has($name))->toBeFalse("route {$name} should be gone");
    }
});

it('drops the web_form widget row on the next widgets:sync', function () {
    $this->artisan('widgets:sync')->assertSuccessful();

    expect(\App\Models\WidgetType::pluck('handle'))->not->toContain('web_form');
});

it('yields a null New Form quick-action URL instead of a missing class', function () {
    expect((QuickActionsDefinition::actionRegistry()['new_form']['url'])())->toBeNull();
});

it('keeps form data queryable — disabled is inert, never destructive', function () {
    $form = Form::create([
        'title'     => 'Kept Form',
        'handle'    => 'kept-form',
        'fields'    => [['type' => 'text', 'label' => 'Name', 'handle' => 'name']],
        'settings'  => ['form_type' => 'general'],
        'is_active' => true,
    ]);

    FormSubmission::create([
        'form_id'    => $form->id,
        'data'       => ['name' => 'Kept'],
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    expect(Form::count())->toBe(1)
        ->and(FormSubmission::count())->toBe(1)
        ->and($form->submissions()->count())->toBe(1);
});

it('sends no notification on submission creation — the observer is detached with the plugin', function () {
    Mail::fake();

    $form = Form::create([
        'title'     => 'Notifying Form',
        'handle'    => 'notifying-form',
        'fields'    => [['type' => 'text', 'label' => 'Name', 'handle' => 'name']],
        'settings'  => ['notifications' => [['to' => 'owner@example.com', 'include_submission_data' => true]]],
        'is_active' => true,
    ]);

    FormSubmission::create([
        'form_id'    => $form->id,
        'data'       => ['name' => 'Quiet'],
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    Mail::assertNothingSent();
});

it('resolves neither form email handle — the registry contribution is gone', function () {
    expect(EmailTemplate::forHandle('form_submission')->subject)->toBe('')
        ->and(EmailTemplate::forHandle('portal_form_collision')->subject)->toBe('');
});
