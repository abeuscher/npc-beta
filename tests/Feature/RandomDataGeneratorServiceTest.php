<?php

use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\Donation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RandomDataGenerator;
use App\WidgetPrimitive\Source;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
});

function asSuperAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    test()->actingAs($admin);

    return $admin;
}

it('throws AuthorizationException when invoked without a super-admin user', function () {
    $service = app(RandomDataGenerator::class);

    expect(fn () => $service->generate(['contacts' => 1]))
        ->toThrow(AuthorizationException::class);
});

it('throws AuthorizationException for an authenticated non-super-admin user', function () {
    $user = User::factory()->create();
    test()->actingAs($user);

    $service = app(RandomDataGenerator::class);

    expect(fn () => $service->generate(['contacts' => 1]))
        ->toThrow(AuthorizationException::class);
});

it('zero counts produce a no-op summary with no rows created', function () {
    asSuperAdmin();
    $contactsBefore = Contact::count();

    $summary = app(RandomDataGenerator::class)->generate([]);

    expect($summary)->toBe([
        'contacts'      => 0,
        'events'        => 0,
        'registrations' => 0,
        'donations'     => 0,
        'memberships'   => 0,
        'transactions'  => 0,
        'posts'         => 0,
        'products'      => 0,
    ])->and(Contact::count())->toBe($contactsBefore);
});

it('generates contacts tagged with source = scrub_data', function () {
    asSuperAdmin();

    $summary = app(RandomDataGenerator::class)->generate(['contacts' => 5]);

    expect($summary['contacts'])->toBe(5)
        ->and(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(5);
});

it('respects custom-field types when seeding contact custom_fields', function () {
    asSuperAdmin();

    CustomFieldDef::create(['model_type' => 'contact', 'handle' => 'cf_text',    'label' => 'Text',    'field_type' => 'text']);
    CustomFieldDef::create(['model_type' => 'contact', 'handle' => 'cf_number',  'label' => 'Number',  'field_type' => 'number']);
    CustomFieldDef::create(['model_type' => 'contact', 'handle' => 'cf_date',    'label' => 'Date',    'field_type' => 'date']);
    CustomFieldDef::create(['model_type' => 'contact', 'handle' => 'cf_boolean', 'label' => 'Boolean', 'field_type' => 'boolean']);
    CustomFieldDef::create([
        'model_type' => 'contact',
        'handle'     => 'cf_select',
        'label'      => 'Select',
        'field_type' => 'select',
        'options'    => [
            ['value' => 'opt_a', 'label' => 'Option A'],
            ['value' => 'opt_b', 'label' => 'Option B'],
        ],
    ]);

    app(RandomDataGenerator::class)->generate(['contacts' => 3]);

    $contacts = Contact::where('source', Source::SCRUB_DATA)->get();
    expect($contacts)->toHaveCount(3);

    foreach ($contacts as $contact) {
        $cf = $contact->custom_fields;

        expect($cf['cf_text'])->toBeString()
            ->and($cf['cf_number'])->toBeInt()
            ->and($cf['cf_date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/')
            ->and($cf['cf_boolean'])->toBeBool()
            ->and($cf['cf_select'])->toBeIn(['opt_a', 'opt_b']);
    }
});

it('generates events with the super-admin user as author and source = scrub_data', function () {
    $admin = asSuperAdmin();

    $summary = app(RandomDataGenerator::class)->generate(['events' => 3]);

    expect($summary['events'])->toBe(3)
        ->and(Event::where('author_id', $admin->id)->count())->toBe(3)
        ->and(Event::where('source', Source::SCRUB_DATA)->count())->toBe(3);
});

it('generates registrations referencing existing events and contacts', function () {
    asSuperAdmin();

    $summary = app(RandomDataGenerator::class)->generate([
        'contacts'      => 5,
        'events'        => 2,
        'registrations' => 10,
    ]);

    expect($summary['registrations'])->toBe(10);

    $eventIds = Event::pluck('id')->all();
    foreach (EventRegistration::where('source', Source::SCRUB_DATA)->get() as $reg) {
        expect($reg->event_id)->toBeIn($eventIds);
        if ($reg->contact_id !== null) {
            expect(Contact::where('id', $reg->contact_id)->exists())->toBeTrue();
        }
    }
});

it('refuses to generate registrations when no events exist', function () {
    asSuperAdmin();

    expect(fn () => app(RandomDataGenerator::class)->generate(['registrations' => 5]))
        ->toThrow(\RuntimeException::class);
});

it('generates donations and writes Transactions for active ones (per session 233 ledger discipline)', function () {
    asSuperAdmin();

    $summary = app(RandomDataGenerator::class)->generate([
        'contacts'  => 5,
        'donations' => 20,
    ]);

    expect($summary['donations'])->toBe(20);

    $activeDonations  = Donation::where('source', Source::SCRUB_DATA)->where('status', 'active')->count();
    $pendingDonations = Donation::where('source', Source::SCRUB_DATA)->where('status', 'pending')->count();
    $donationTransactions = Transaction::where('subject_type', Donation::class)
        ->where('source', Source::SCRUB_DATA)
        ->count();

    expect($activeDonations + $pendingDonations)->toBe(20)
        ->and($donationTransactions)->toBe($activeDonations);
});

it('refuses to generate donations when no contacts exist', function () {
    asSuperAdmin();

    expect(fn () => app(RandomDataGenerator::class)->generate(['donations' => 5]))
        ->toThrow(\RuntimeException::class);
});

it('generates memberships and writes Transactions for active paid ones', function () {
    asSuperAdmin();

    $summary = app(RandomDataGenerator::class)->generate([
        'contacts'    => 5,
        'memberships' => 20,
    ]);

    expect($summary['memberships'])->toBe(20);

    $activeMemberships = Membership::where('source', Source::SCRUB_DATA)->where('status', 'active')->count();
    $membershipTxs = Transaction::where('subject_type', Membership::class)
        ->where('source', Source::SCRUB_DATA)
        ->count();

    expect($membershipTxs)->toBeLessThanOrEqual($activeMemberships)
        ->and($membershipTxs)->toBeGreaterThan(0);
});

it('combined counts produce a unified summary', function () {
    asSuperAdmin();

    $summary = app(RandomDataGenerator::class)->generate([
        'contacts'      => 4,
        'events'        => 2,
        'registrations' => 6,
        'donations'     => 8,
        'memberships'   => 3,
    ]);

    expect($summary['contacts'])->toBe(4)
        ->and($summary['events'])->toBe(2)
        ->and($summary['registrations'])->toBe(6)
        ->and($summary['donations'])->toBe(8)
        ->and($summary['memberships'])->toBe(3)
        ->and($summary['transactions'])->toBeGreaterThan(0);
});

it('a mid-run failure rolls back the entire transaction (no orphan rows)', function () {
    asSuperAdmin();

    $contactsBefore = Contact::count();
    $eventsBefore   = Event::count();

    expect(fn () => app(RandomDataGenerator::class)->generate([
        'contacts'      => 5,
        'events'        => 0,
        'registrations' => 5,
    ]))->toThrow(\RuntimeException::class);

    expect(Contact::count())->toBe($contactsBefore)
        ->and(Event::count())->toBe($eventsBefore)
        ->and(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(0);
});

it('wipe — throws AuthorizationException for non-super-admin', function () {
    $user = User::factory()->create();
    test()->actingAs($user);

    expect(fn () => app(RandomDataGenerator::class)->wipe())
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('wipe — clean install with no scrub data is a no-op (returns zeros)', function () {
    asSuperAdmin();

    $deleted = app(RandomDataGenerator::class)->wipe();

    expect($deleted)->toBe([
        'transactions'  => 0,
        'registrations' => 0,
        'donations'     => 0,
        'memberships'   => 0,
        'events'        => 0,
        'posts'         => 0,
        'products'      => 0,
        'contacts'      => 0,
    ]);
});

it('wipe — generate-then-wipe round-trip leaves real data intact', function () {
    asSuperAdmin();

    $realContact = Contact::factory()->create();
    $realEvent   = Event::factory()->create();
    $contactsBefore = Contact::count();
    $eventsBefore   = Event::count();

    $service = app(RandomDataGenerator::class);

    $service->generate([
        'contacts'      => 5,
        'events'        => 2,
        'registrations' => 4,
        'donations'     => 6,
        'memberships'   => 3,
    ]);

    expect(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(5)
        ->and(Event::where('source', Source::SCRUB_DATA)->count())->toBe(2);

    $deleted = $service->wipe();

    expect($deleted['contacts'])->toBe(5)
        ->and($deleted['events'])->toBe(2)
        ->and(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(0)
        ->and(Event::where('source', Source::SCRUB_DATA)->count())->toBe(0)
        ->and(Donation::where('source', Source::SCRUB_DATA)->count())->toBe(0)
        ->and(Membership::where('source', Source::SCRUB_DATA)->count())->toBe(0)
        ->and(EventRegistration::where('source', Source::SCRUB_DATA)->count())->toBe(0)
        ->and(Transaction::where('source', Source::SCRUB_DATA)->count())->toBe(0)
        ->and(Contact::where('id', $realContact->id)->exists())->toBeTrue()
        ->and(Event::where('id', $realEvent->id)->exists())->toBeTrue()
        ->and(Contact::count())->toBe($contactsBefore)
        ->and(Event::count())->toBe($eventsBefore);
});

it('wipe — inheritance + wipe interaction (downstream rehearsal flow)', function () {
    asSuperAdmin();

    $service = app(RandomDataGenerator::class);

    $scrubContact = Contact::factory()->create(['source' => Source::SCRUB_DATA]);

    $rehearsalDonation = Donation::create([
        'contact_id' => $scrubContact->id,
        'type'       => 'one_off',
        'amount'     => 50.00,
        'currency'   => 'usd',
        'status'     => 'active',
        'source'     => Source::IMPORT,
    ]);
    expect($rehearsalDonation->source)->toBe(Source::SCRUB_DATA);

    $rehearsalTransaction = Transaction::create([
        'subject_type' => Donation::class,
        'subject_id'   => $rehearsalDonation->id,
        'contact_id'   => $scrubContact->id,
        'type'         => 'payment',
        'amount'       => 50.00,
        'direction'    => 'in',
        'status'       => 'completed',
        'source'       => Source::STRIPE_WEBHOOK,
        'occurred_at'  => now(),
    ]);
    expect($rehearsalTransaction->source)->toBe(Source::SCRUB_DATA);

    $service->wipe();

    expect(Contact::where('id', $scrubContact->id)->exists())->toBeFalse()
        ->and(Donation::where('id', $rehearsalDonation->id)->exists())->toBeFalse()
        ->and(Transaction::where('id', $rehearsalTransaction->id)->exists())->toBeFalse();
});

it('scrubCounts — returns accurate per-table counts', function () {
    asSuperAdmin();

    $service = app(RandomDataGenerator::class);

    $service->generate([
        'contacts'      => 4,
        'events'        => 2,
        'registrations' => 3,
        'donations'     => 5,
        'memberships'   => 2,
    ]);

    $counts = $service->scrubCounts();

    expect($counts['contacts'])->toBe(4)
        ->and($counts['registrations'])->toBe(3)
        ->and($counts['donations'])->toBe(5)
        ->and($counts['memberships'])->toBe(2)
        ->and($counts['transactions'])->toBeGreaterThanOrEqual(0);
});

it('wipe — soft-deleted scrub Contact rows are also hard-deleted', function () {
    asSuperAdmin();

    $service = app(RandomDataGenerator::class);
    $service->generate(['contacts' => 3]);

    $contacts = Contact::where('source', Source::SCRUB_DATA)->get();
    $contacts->first()->delete();

    expect(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(2)
        ->and(Contact::where('source', Source::SCRUB_DATA)->withTrashed()->count())->toBe(3);

    $deleted = $service->wipe();

    expect($deleted['contacts'])->toBe(3)
        ->and(Contact::where('source', Source::SCRUB_DATA)->withTrashed()->count())->toBe(0);
});

it('generates blog posts as published Pages tagged scrub_data with text_block + blog_pager widgets', function () {
    asSuperAdmin();
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $summary = app(RandomDataGenerator::class)->generate(['posts' => 3]);

    expect($summary['posts'])->toBe(3);

    $posts = Page::where('source', Source::SCRUB_DATA)->where('type', 'post')->get();

    expect($posts)->toHaveCount(3);

    foreach ($posts as $post) {
        expect($post->status)->toBe('published')
            ->and($post->author_id)->toBe(auth()->id())
            ->and($post->slug)->toStartWith(config('site.blog_prefix', 'news') . '/')
            ->and($post->widgets()->count())->toBe(2);

        $handles = $post->widgets()->with('widgetType')->get()->pluck('widgetType.handle')->all();
        expect($handles)->toContain('text_block')
            ->and($handles)->toContain('blog_pager');
    }
});

it('wipe — also removes scrub-tagged blog posts and their widgets', function () {
    asSuperAdmin();
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $service = app(RandomDataGenerator::class);
    $service->generate(['posts' => 2]);

    $postIds = Page::where('source', Source::SCRUB_DATA)->pluck('id')->all();
    expect($postIds)->toHaveCount(2);

    $deleted = $service->wipe();

    expect($deleted['posts'])->toBe(2)
        ->and(Page::where('source', Source::SCRUB_DATA)->withTrashed()->count())->toBe(0)
        ->and(\App\Models\PageWidget::whereIn('owner_id', $postIds)->where('owner_type', Page::class)->count())->toBe(0);
});

it('seedWidgetCollections — runs each widget definition that declares a demoSeeder', function () {
    asSuperAdmin();
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $labels = app(RandomDataGenerator::class)->seedWidgetCollections();

    expect($labels)->not->toBeEmpty()
        ->and(\App\Models\Collection::where('handle', 'logo-garden-demo')->exists())->toBeTrue();
});

it('seedWidgetCollections — throws AuthorizationException for non-super-admin', function () {
    $user = User::factory()->create();
    test()->actingAs($user);

    expect(fn () => app(RandomDataGenerator::class)->seedWidgetCollections())
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('generates products tagged scrub_data with 1–3 ProductPrice rows each', function () {
    asSuperAdmin();

    $summary = app(RandomDataGenerator::class)->generate(['products' => 4]);

    expect($summary['products'])->toBe(4)
        ->and(Product::where('source', Source::SCRUB_DATA)->count())->toBe(4);

    $products = Product::where('source', Source::SCRUB_DATA)->withCount('prices')->get();
    foreach ($products as $product) {
        expect($product->prices_count)->toBeGreaterThanOrEqual(1)
            ->and($product->prices_count)->toBeLessThanOrEqual(3);
    }
});

it('wipe — also removes scrub-tagged products and cascades their ProductPrices', function () {
    asSuperAdmin();

    $service = app(RandomDataGenerator::class);
    $service->generate(['products' => 3]);

    $productIds = Product::where('source', Source::SCRUB_DATA)->pluck('id')->all();
    expect($productIds)->toHaveCount(3)
        ->and(ProductPrice::whereIn('product_id', $productIds)->count())->toBeGreaterThan(0);

    $deleted = $service->wipe();

    expect($deleted['products'])->toBe(3)
        ->and(Product::where('source', Source::SCRUB_DATA)->count())->toBe(0)
        ->and(ProductPrice::whereIn('product_id', $productIds)->count())->toBe(0);
});
