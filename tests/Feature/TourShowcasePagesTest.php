<?php

use App\Filament\Pages\ImporterPage;
use App\Filament\Pages\TourImportShowcasePage;
use App\Filament\Pages\TourRolesShowcasePage;
use App\Filament\Resources\RoleResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * The guided product tour (session 338) shows the demo prospect Roles and Import
 * — two features the public `demo` role is deliberately walled off from
 * (DemoRoleLockdownTest). It does so through demo-safe *showcase* pages: the real
 * permission matrix fed sample data, and a captured screenshot of the importer.
 * No real data, no secrets.
 *
 * These assertions pin that the showcases are reachable by the demo role while
 * the real locked pages stay shut — so the tour never has to weaken the lockdown.
 */
beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();

    $this->demo = User::factory()->create(['is_active' => true]);
    $this->demo->assignRole('demo');
});

it('lets the demo role reach the tour showcase pages', function () {
    $this->actingAs($this->demo);

    expect(TourRolesShowcasePage::canAccess())->toBeTrue();
    expect(TourImportShowcasePage::canAccess())->toBeTrue();
});

it('keeps the real role + import pages locked for the demo role (the reason the showcases exist)', function () {
    $this->actingAs($this->demo);

    expect(RoleResource::canViewAny())->toBeFalse();
    expect(ImporterPage::canAccess())->toBeFalse();
});

it('renders the roles showcase as a live sample, never loading real role data', function () {
    $this->actingAs($this->demo);

    $this->get(TourRolesShowcasePage::getUrl())
        ->assertOk()
        ->assertSee('Roles & Permissions')
        ->assertSee('nothing here is saved')   // the live-sample disclaimer
        ->assertDontSee('super_admin');         // real role vocabulary is never queried
});

it('renders the import showcase for the demo role', function () {
    $this->actingAs($this->demo);

    $this->get(TourImportShowcasePage::getUrl())
        ->assertOk()
        ->assertSee('guided importers');
});

it('keeps the showcase pages out of the navigation (tour destinations, not features)', function () {
    expect(TourRolesShowcasePage::shouldRegisterNavigation())->toBeFalse();
    expect(TourImportShowcasePage::shouldRegisterNavigation())->toBeFalse();
});
