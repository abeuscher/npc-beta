<?php

use App\Models\Page;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Factory creates widgets with category and allowed_page_types ────────────

it('factory creates widget type with category array', function () {
    $wt = WidgetType::factory()->create();

    expect($wt->category)->toBe(['content'])
        ->and($wt->allowed_page_types)->toBeNull();
});

it('factory can create widget type with custom category and allowed_page_types', function () {
    $wt = WidgetType::factory()->create([
        'category'           => ['portal', 'forms'],
        'allowed_page_types' => ['member'],
    ]);

    expect($wt->category)->toBe(['portal', 'forms'])
        ->and($wt->allowed_page_types)->toBe(['member']);
});

// ── Seeder assigns categories to all widgets ────────────────────────────────

it('seeder assigns category to all existing widgets', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $widgets = WidgetType::all();
    expect($widgets)->toHaveCount(25);

    foreach ($widgets as $wt) {
        expect($wt->category)
            ->toBeArray("Widget '{$wt->handle}' has no category assigned")
            ->not->toBeEmpty("Widget '{$wt->handle}' has empty category array");
    }
});

it('seeder assigns portal widgets to member page type only', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $portalHandles = [
        'portal_forgot_password',
        'portal_account_dashboard', 'portal_contact_edit',
        'portal_change_password', 'portal_event_registrations',
    ];

    foreach ($portalHandles as $handle) {
        $wt = WidgetType::where('handle', $handle)->first();
        expect($wt->allowed_page_types)->toBe(['member'], "Widget '{$handle}' should be restricted to member pages");
    }

    // Login and signup are available on all page types (public-facing forms)
    foreach (['portal_login', 'portal_signup'] as $handle) {
        $wt = WidgetType::where('handle', $handle)->first();
        expect($wt->allowed_page_types)->toBeNull("Widget '{$handle}' should be available on all page types");
    }
});

it('seeder assigns blog_pager to post page type only', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $wt = WidgetType::where('handle', 'blog_pager')->first();
    expect($wt->allowed_page_types)->toBe(['post']);
});

// ── Widgets with null allowed_page_types appear on all page types ───────────

it('widgets with null allowed_page_types are not filtered out for any page type', function () {
    $universal = WidgetType::factory()->create([
        'handle'             => 'universal_widget',
        'allowed_page_types' => null,
    ]);

    $restricted = WidgetType::factory()->create([
        'handle'             => 'member_only_widget',
        'allowed_page_types' => ['member'],
    ]);

    foreach (['default', 'post', 'member', 'system'] as $pageType) {
        $filtered = WidgetType::all()
            ->filter(fn ($wt) => $wt->allowed_page_types === null || in_array($pageType, $wt->allowed_page_types, true));

        expect($filtered->pluck('handle'))->toContain('universal_widget');
    }
});

// ── Page-type filtering logic ───────────────────────────────────────────────

it('member page excludes blog_pager widget', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $pageType = 'member';
    $filtered = WidgetType::all()
        ->filter(fn ($wt) => $wt->allowed_page_types === null || in_array($pageType, $wt->allowed_page_types, true));

    expect($filtered->pluck('handle'))->not->toContain('blog_pager');
});

it('default page excludes portal widgets', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $pageType = 'default';
    $filtered = WidgetType::all()
        ->filter(fn ($wt) => $wt->allowed_page_types === null || in_array($pageType, $wt->allowed_page_types, true));

    $portalHandles = [
        'portal_forgot_password',
        'portal_account_dashboard', 'portal_contact_edit',
        'portal_change_password', 'portal_event_registrations',
    ];

    foreach ($portalHandles as $handle) {
        expect($filtered->pluck('handle'))->not->toContain($handle);
    }

    // Login and signup should appear on default pages
    expect($filtered->pluck('handle'))->toContain('portal_login')
        ->toContain('portal_signup');
});

it('post page includes blog_pager and universal widgets but excludes portal widgets', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $pageType = 'post';
    $filtered = WidgetType::all()
        ->filter(fn ($wt) => $wt->allowed_page_types === null || in_array($pageType, $wt->allowed_page_types, true));

    $handles = $filtered->pluck('handle');

    expect($handles)->toContain('blog_pager')
        ->toContain('text_block')
        ->toContain('portal_login')
        ->not->toContain('portal_account_dashboard');
});

// ── Multi-category assignment ───────────────────────────────────────────────

it('widgets can belong to multiple categories', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $donationForm = WidgetType::where('handle', 'donation_form')->first();
    expect($donationForm->category)->toContain('giving_and_sales')
        ->toContain('forms');

    $image = WidgetType::where('handle', 'image')->first();
    expect($image->category)->toContain('content')
        ->toContain('media');
});

// ── Description field ──────────────────────────────────────────────────────

it('seeder assigns description to all existing widgets', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $widgets = WidgetType::all();

    foreach ($widgets as $wt) {
        expect($wt->description)
            ->not->toBeNull("Widget '{$wt->handle}' has no description")
            ->not->toBeEmpty("Widget '{$wt->handle}' has empty description");
    }
});

// ── Media collections ──────────────────────────────────────────────────────

it('widget type registers thumbnail and thumbnail_hover media collections', function () {
    $wt = WidgetType::factory()->create();

    $collections = $wt->getRegisteredMediaCollections();
    $names = collect($collections)->pluck('name')->all();

    expect($names)->toContain('thumbnail')
        ->toContain('thumbnail_hover');
});
