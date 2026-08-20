<?php

use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Plugins\CapabilityRegistry;
use App\Plugins\EmailTemplateRegistry;
use App\Services\WidgetRegistry;
use App\Widgets\QuickActions\QuickActionsDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 397 (Plugin Architecture arc D7): the enabled twin for the carved
// Forms vertical. The full composition must be behavior-identical to the
// pre-carve state: same route, same widget, same admin surface, same
// notification emails. The plugin declares NO capability — nothing consumes
// forms presence; the presence signal, were one ever needed, is route
// presence (Route::has('forms.submit')).

it('loads the Forms provider as the eighth plugin', function () {
    expect(config('plugins'))->toContain(\Plugins\Forms\FormsServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\Forms\FormsServiceProvider::class))->toBeTrue();
});

it('declares no forms capability — nothing consumes forms presence', function () {
    expect(app(CapabilityRegistry::class)->present('forms'))->toBeFalse();
});

it('registers forms.submit with its pre-carve URI and middleware', function () {
    expect(Route::has('forms.submit'))->toBeTrue();

    $route = Route::getRoutes()->getByName('forms.submit');

    expect($route->uri())->toBe('forms/{handle}')
        ->and($route->methods())->toContain('POST')
        ->and($route->gatherMiddleware())->toContain('throttle:10,1')
        ->and($route->gatherMiddleware())->toContain('web')
        ->and($route->getActionName())
            ->toBe(\Plugins\Forms\Http\Controllers\FormSubmissionController::class . '@store');
});

it('registers the three FormResource admin routes through the admin socket', function () {
    foreach ([
        'filament.admin.resources.forms.index',
        'filament.admin.resources.forms.create',
        'filament.admin.resources.forms.edit',
    ] as $name) {
        expect(Route::has($name))->toBeTrue("route {$name} should exist");
    }
});

it('registers the WebForm widget from the plugin provider', function () {
    $handles = collect(app(WidgetRegistry::class)->all())->map(fn ($d) => $d->handle());

    expect($handles)->toContain('web_form');
});

it('renders the plugin-owned x-public-form component through the anonymous component path', function () {
    // A missing handle short-circuits the component body — resolution alone
    // is the assertion (an unregistered component throws instead).
    expect(Blade::render('<x-public-form handle="no-such-form" />'))->toBe('');
});

it('renders the widget template through the plugin view namespace', function () {
    expect(view()->exists('plugin-forms-widgets::WebForm.template'))->toBeTrue();
});

it('resolves the New Form quick action by route name', function () {
    $url = (QuickActionsDefinition::actionRegistry()['new_form']['url'])();

    expect($url)->toBe(route('filament.admin.resources.forms.create'));
});

it('resolves both form email handles through the plugin registry contribution', function () {
    $registry = app(EmailTemplateRegistry::class);

    expect($registry->defaultsFor('form_submission')['subject'])->toBe('New submission: {{form_title}}')
        ->and($registry->defaultsFor('portal_form_collision')['subject'])->toBe('Someone submitted a form using your email address')
        ->and(EmailTemplate::forHandle('form_submission')->subject)->toBe('New submission: {{form_title}}')
        ->and(EmailTemplate::forHandle('portal_form_collision')->subject)->toBe('Someone submitted a form using your email address');
});

it('registers both form policies via Gate::policy', function () {
    expect(Gate::getPolicyFor(Form::class))->toBeInstanceOf(\Plugins\Forms\Policies\FormPolicy::class)
        ->and(Gate::getPolicyFor(FormSubmission::class))->toBeInstanceOf(\Plugins\Forms\Policies\FormSubmissionPolicy::class);
});
