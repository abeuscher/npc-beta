<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\Page;
use App\Models\Product;
use App\WidgetPrimitive\Source;
use Database\Seeders\DemoBaselineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

function scrubBaselineCounts(): array
{
    return [
        'contacts'      => Contact::where('source', Source::SCRUB_DATA)->count(),
        'events'        => Event::where('source', Source::SCRUB_DATA)->count(),
        'registrations' => EventRegistration::where('source', Source::SCRUB_DATA)->count(),
        'donations'     => Donation::where('source', Source::SCRUB_DATA)->count(),
        'memberships'   => Membership::where('source', Source::SCRUB_DATA)->count(),
        'posts'         => Page::where('source', Source::SCRUB_DATA)->where('type', 'post')->count(),
        'products'      => Product::where('source', Source::SCRUB_DATA)->count(),
    ];
}

it('refuses to run when the install is not in demo mode, wiping nothing', function () {
    expect(isDemoMode())->toBeFalse();

    // A pre-existing scrub contact must survive — the guard returns before any wipe.
    Contact::factory()->create(['source' => Source::SCRUB_DATA]);

    $this->artisan('demo:reset')->assertExitCode(1);

    expect(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(1);
});

it('soft reset seeds the curated baseline counts', function () {
    app()->instance('env', 'demo');
    expect(isDemoMode())->toBeTrue();

    $this->artisan('demo:reset', ['--soft' => true])->assertExitCode(0);

    expect(scrubBaselineCounts())->toMatchArray([
        'contacts'      => DemoBaselineSeeder::BASELINE['contacts'],
        'events'        => DemoBaselineSeeder::BASELINE['events'],
        'registrations' => DemoBaselineSeeder::BASELINE['registrations'],
        'donations'     => DemoBaselineSeeder::BASELINE['donations'],
        'memberships'   => DemoBaselineSeeder::BASELINE['memberships'],
        'posts'         => DemoBaselineSeeder::BASELINE['posts'],
        'products'      => DemoBaselineSeeder::BASELINE['products'],
    ]);
});

it('soft reset is idempotent — re-running returns the same baseline, not a doubled one', function () {
    app()->instance('env', 'demo');

    $this->artisan('demo:reset', ['--soft' => true])->assertExitCode(0);
    $first = scrubBaselineCounts();

    $this->artisan('demo:reset', ['--soft' => true])->assertExitCode(0);
    $second = scrubBaselineCounts();

    expect($second)->toBe($first);
    expect($second['contacts'])->toBe(DemoBaselineSeeder::BASELINE['contacts']);
});

it('locks every page on the demo node, idempotently across resets', function () {
    app()->instance('env', 'demo');

    // A pre-existing unlocked page must end up locked after the reset.
    $page = Page::factory()->create(['locked' => false]);

    $this->artisan('demo:reset', ['--soft' => true])->assertExitCode(0);

    expect(Page::count())->toBeGreaterThan(0);
    expect(Page::where('locked', false)->count())->toBe(0);
    expect($page->fresh()->locked)->toBeTrue();

    // Re-running keeps every page locked — no page slips back to unlocked.
    $this->artisan('demo:reset', ['--soft' => true])->assertExitCode(0);
    expect(Page::where('locked', false)->count())->toBe(0);
});
