<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Page;
use App\Models\PortalAccount;
use App\Models\Template;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    User::factory()->create();

    if (! Template::page()->where('is_default', true)->exists()) {
        Template::create(['name' => 'Default', 'type' => 'page', 'is_default' => true]);
    }
});

function memberPageWithWidget(string $slug, string $widgetHandle): Page
{
    $page = Page::factory()->create([
        'title'  => 'Member Area',
        'slug'   => $slug,
        'type'   => 'member',
        'status' => 'published',
    ]);

    $wt = WidgetType::where('handle', $widgetHandle)->firstOrFail();

    $page->widgets()->create([
        'widget_type_id'    => $wt->id,
        'label'             => $wt->label,
        'config'            => $wt->getDefaultConfig(),
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    return $page;
}

function verifiedMember(Contact $contact): PortalAccount
{
    return PortalAccount::factory()->create([
        'contact_id'        => $contact->id,
        'is_active'         => true,
        'email_verified_at' => now(),
    ]);
}

it('scopes a member-page data widget to the viewing member, never another contact', function () {
    $alphaGala = Event::factory()->create(['title' => 'Alpha Gala']);
    $betaBash  = Event::factory()->create(['title' => 'Beta Bash']);

    $bob = Contact::factory()->create(['first_name' => 'Bob']);
    EventRegistration::factory()->create([
        'contact_id' => $bob->id,
        'event_id'   => $alphaGala->id,
        'status'     => 'registered',
    ]);

    $alice = Contact::factory()->create(['first_name' => 'Alice']);
    EventRegistration::factory()->create([
        'contact_id' => $alice->id,
        'event_id'   => $betaBash->id,
        'status'     => 'registered',
    ]);

    memberPageWithWidget('members/registrations', 'portal_event_registrations');

    $response = $this->actingAs(verifiedMember($bob), 'portal')->get('/members/registrations');

    $response->assertOk();
    $response->assertSee('Alpha Gala');       // Bob's own registration
    $response->assertDontSee('Beta Bash');    // Alice's — must never leak
});

it('fails an admin record-context widget closed on a member page (no donor data leaks)', function () {
    $bob = Contact::factory()->create(['first_name' => 'Bob']);
    Donation::factory()->create([
        'contact_id' => $bob->id,
        'amount'     => 4242.42,
        'status'     => 'active',
    ]);

    // recent_donations is a record_detail_sidebar/admin widget. Attached to a
    // member page (bypassing the write-time slot guard), it must still resolve
    // to empty — no ambient Contact record + no portal 'view_donation' grant.
    memberPageWithWidget('members/dashboard', 'recent_donations');

    $response = $this->actingAs(verifiedMember($bob), 'portal')->get('/members/dashboard');

    $response->assertOk();
    $response->assertDontSee('4242.42');
});
