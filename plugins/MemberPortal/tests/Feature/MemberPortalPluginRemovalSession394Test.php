<?php

use App\Models\Contact;
use App\Models\Page;
use App\Models\PortalAccount;
use App\Models\User;
use App\Plugins\CapabilityRegistry;
use App\Services\ActivityLogger;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\MemberPortalRemovedTestCase;

uses(MemberPortalRemovedTestCase::class, RefreshDatabase::class);

// Session 394 (Plugin Architecture arc D5): the remove-the-line mirror for
// the Member Portal vertical. The application boots with the MemberPortal
// line stripped from config('plugins'); the whole portal surface must vanish
// — routes, guard config, middleware alias, widgets, the capability — while
// portal DATA is kept (the two portal tables are core-dump tables until the
// package phase) and every gated core read degrades instead of touching the
// undefined guard. The Events portal registration route must vanish with the
// capability rather than 500 on the unresolvable alias (crm-plugin--events
// v0.1.2 — the Events-without-Portal matrix cell).

it('does not load the MemberPortal provider', function () {
    expect(config('plugins'))->not->toContain(\Plugins\MemberPortal\MemberPortalServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\MemberPortal\MemberPortalServiceProvider::class))->toBeFalse();
});

it('withdraws the member-portal capability', function () {
    expect(app(CapabilityRegistry::class)->present('member-portal'))->toBeFalse();
});

it('leaves no portal auth config — guard, provider, and broker are gone', function () {
    expect(config('auth.guards.portal'))->toBeNull()
        ->and(config('auth.providers.portal_accounts'))->toBeNull()
        ->and(config('auth.passwords.portal_accounts'))->toBeNull();
});

it('leaves no portal.auth middleware alias', function () {
    expect(app('router')->getMiddleware())->not->toHaveKey('portal.auth');
});

it('drops every portal route', function () {
    foreach ([
        'portal.signup', 'portal.signup.post',
        'portal.login', 'portal.login.post', 'portal.logout',
        'portal.password.request', 'portal.password.sent', 'portal.password.email',
        'portal.password.reset', 'portal.password.update',
        'portal.verification.notice', 'portal.verification.verify',
        'portal.account',
        'portal.account.update-address', 'portal.account.update-password',
        'portal.account.request-email-change', 'portal.account.confirm-email',
    ] as $name) {
        expect(Route::has($name))->toBeFalse("route {$name} should be gone");
    }
});

it('answers 405 on the portal POST paths — the page catch-all owns GET on every path', function () {
    $this->post('/login', ['email' => 'x@example.com', 'password' => 'x'])->assertStatus(405);
    $this->post('/signup', [])->assertStatus(405);
});

it('drops the Events portal registration route instead of 500ing on the unresolvable alias', function () {
    // The Events plugin is still loaded — only its member-portal-gated route
    // vanishes (crm-plugin--events v0.1.2; contract surface 13).
    expect(app()->providerIsLoaded(\Plugins\Events\EventsServiceProvider::class))->toBeTrue()
        ->and(Route::has('portal.events.register'))->toBeFalse()
        ->and(Route::has('events.register'))->toBeTrue();

    $this->post('/account/events/some-event/register')->assertStatus(405);
});

it('drops all six portal widget rows on the next widgets:sync', function () {
    $this->artisan('widgets:sync')->assertSuccessful();

    $handles = \App\Models\WidgetType::pluck('handle');

    foreach ([
        'portal_signup', 'portal_login', 'portal_contact_edit',
        'portal_change_password', 'portal_forgot_password', 'portal_account_dashboard',
    ] as $handle) {
        expect($handles)->not->toContain($handle);
    }
});

it('keeps portal account data queryable — disabling is never destructive', function () {
    $contact = Contact::factory()->create();
    PortalAccount::factory()->create(['contact_id' => $contact->id]);

    expect(PortalAccount::count())->toBe(1);
});

// ── The gated core reads (session 394) — degrade, never touch the guard ──────

it('renders a public page — PageContext resolves a null portal user without the guard', function () {
    User::factory()->create();
    $page = Page::factory()->create([
        'title'  => 'Plain Public Page',
        'slug'   => 'plain-public-page',
        'type'   => 'default',
        'status' => 'published',
    ]);

    $this->get('/plain-public-page')->assertOk();
});

it('answers 404 on a member page — unreachable rather than erroring', function () {
    User::factory()->create();
    Page::factory()->create([
        'title'  => 'Members Home',
        'slug'   => 'members',
        'type'   => 'member',
        'status' => 'published',
    ]);

    $this->get('/members')->assertNotFound();
});

it('resolves the portal_member arm to a null item without touching the guard', function () {
    $dto = app(ContractResolver::class)->resolve([
        new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SYSTEM_MODEL,
            fields: ['id'],
            model: 'portal_member',
            cardinality: DataContract::CARDINALITY_ONE,
        ),
    ], new SlotContext(new PageAmbientContext()))[0];

    expect($dto['item'])->toBeNull();
});

it('still writes activity log rows with a system actor', function () {
    $contact = Contact::factory()->create();

    ActivityLogger::log($contact, 'test_event', 'portal-absent logging still works');

    $log = \App\Models\ActivityLog::where('event', 'test_event')->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_type)->toBe('system');
});

it('accepts form submissions with a colliding portal email — the collision check self-skips', function () {
    $contact = Contact::factory()->create(['email' => 'collide@example.com']);
    PortalAccount::factory()->create(['contact_id' => $contact->id, 'email' => 'collide@example.com', 'is_active' => true]);

    $form = \App\Models\Form::create([
        'title'    => 'Contact Form',
        'handle'   => 'contact-form',
        'fields'   => [
            ['type' => 'email', 'label' => 'Email', 'handle' => 'email', 'required' => false, 'validation' => 'email'],
        ],
        'settings' => ['honeypot' => false, 'form_type' => 'general', 'success_message' => 'Thanks.'],
        'is_active' => true,
    ]);

    \Illuminate\Support\Facades\Mail::fake();

    $this->post(route('forms.submit', $form->handle), ['email' => 'collide@example.com'])
        ->assertRedirect();

    // The submission lands as a plain submission; no collision mail is sent
    // and the portal guard is never resolved.
    expect(\App\Models\FormSubmission::count())->toBe(1);
    \Illuminate\Support\Facades\Mail::assertNothingSent();
});

it('leaves the other plugins running', function () {
    expect(app()->providerIsLoaded(\Plugins\LogoGarden\LogoGardenServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\Payments\PaymentsServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\Events\EventsServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\Donations\DonationsServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\Memberships\MembershipsServiceProvider::class))->toBeTrue();
});
