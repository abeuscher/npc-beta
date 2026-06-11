<?php

use App\Filament\Pages\ImporterPage;
use App\Filament\Pages\MediaFinderPage;
use App\Filament\Pages\Settings\CmsSettingsPage;
use App\Filament\Pages\Settings\FinanceSettingsPage;
use App\Filament\Pages\Settings\GeneralSettingsPage;
use App\Filament\Pages\Settings\MailSettingsPage;
use App\Filament\Pages\SiteImportExportPage;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\UserResource;
use App\Http\Middleware\BlockDemoUploads;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Standing guard for the public, internet-facing /demo/enter auto-login.
 *
 * The `demo` role is an allow-list with a binding deny-list (PermissionSeeder).
 * This file asserts the wall holds in practice — at the real Filament
 * canAccess() gates and the wipe route, not just on the permission vocabulary —
 * so a future permission change cannot silently widen the public demo. If a
 * surface here starts passing, the demo just gained an exfiltration / config /
 * destruction capability and this guard must be re-reviewed deliberately.
 */
beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();

    $this->demo = User::factory()->create(['is_active' => true]);
    $this->demo->assignRole('demo');
});

it('walls the demo role off from every settings page (mail, finance, routing, CMS, API keys live inside these)', function () {
    $this->actingAs($this->demo);

    // API keys for Stripe / QuickBooks / Mailchimp / Resend live inside these
    // settings pages — denying the page denies the keys.
    expect(MailSettingsPage::canAccess())->toBeFalse();     // mail provider + Resend key
    expect(FinanceSettingsPage::canAccess())->toBeFalse();  // Stripe / QuickBooks credentials
    expect(GeneralSettingsPage::canAccess())->toBeFalse();  // routing prefixes / instance config
    expect(CmsSettingsPage::canAccess())->toBeFalse();      // CMS settings + Mailchimp
});

it('walls the demo role off from user and role management', function () {
    $this->actingAs($this->demo);

    expect(UserResource::canAccess())->toBeFalse();
    expect(RoleResource::canAccess())->toBeFalse();
});

it('walls the demo role off from the data import tools', function () {
    $this->actingAs($this->demo);

    expect(ImporterPage::canAccess())->toBeFalse();
});

it('walls the demo role off from site export / Media Finder (bulk real-data exfiltration + media delete)', function () {
    $this->actingAs($this->demo);

    expect(SiteImportExportPage::canAccess())->toBeFalse(); // Export Site = bulk content+media download
    expect(MediaFinderPage::canAccess())->toBeFalse();      // duplicate/unused finder + delete
});

it('walls the demo role off from the scrub-data wipe and data-generator routes (403, super_admin-only)', function () {
    $this->actingAs($this->demo);

    $this->post(route('filament.admin.dev-tools.random-data.wipe'))->assertStatus(403);
    $this->post(route('filament.admin.dev-tools.random-data.store'))->assertStatus(403);
    $this->post(route('filament.admin.dev-tools.random-data.seed-collections'))->assertStatus(403);
});

it('denies the binding deny-list permissions and is never the policy-bypassing super_admin', function () {
    foreach ([
        'view_any_user', 'create_user', 'update_user', 'delete_user',
        'manage_mail_settings', 'manage_financial_settings', 'manage_cms_settings',
        'manage_email_templates', 'manage_custom_fields', 'manage_membership_tiers',
        'edit_theme_scss', 'manage_routing_prefixes', 'manage_donations',
        'import_data', 'review_imports',
    ] as $denied) {
        expect($this->demo->can($denied))->toBeFalse("demo must not have '{$denied}'");
    }

    expect($this->demo->hasRole('super_admin'))->toBeFalse();
    expect($this->demo->isSuperAdmin())->toBeFalse();
});

it('keeps the demo role view-only on navigation menus and without the locked-page edit permission (session 328)', function () {
    // The header/footer link structure lives in NavigationMenu/NavigationItem —
    // a model the page `locked` flag cannot reach — so the demo nav is view-only.
    expect($this->demo->can('view_any_navigation_menu'))->toBeTrue();
    expect($this->demo->can('view_navigation_menu'))->toBeTrue();
    foreach (['create_navigation_menu', 'update_navigation_menu', 'delete_navigation_menu'] as $denied) {
        expect($this->demo->can($denied))->toBeFalse("demo must not have '{$denied}'");
    }

    // …and it must never hold the permission that bypasses the page edit lock.
    expect($this->demo->can('edit_locked_pages'))->toBeFalse();
});

it('grants the intended product-feel width — full CRUD on events and donations (tuned at session 321)', function () {
    // The egress firewall (Stripe/email fail closed) + daily demo:reset baseline
    // backstop these write flows. Widened deliberately; still an allow-list.
    foreach (['view_any_event', 'view_event', 'create_event', 'update_event', 'delete_event'] as $granted) {
        expect($this->demo->can($granted))->toBeTrue("demo should have '{$granted}'");
    }
    foreach (['view_any_donation', 'view_donation', 'create_donation', 'update_donation', 'delete_donation'] as $granted) {
        expect($this->demo->can($granted))->toBeTrue("demo should have '{$granted}'");
    }

    // …but the financial ledger and fund config stay view-only.
    expect($this->demo->can('view_any_transaction'))->toBeTrue();
    expect($this->demo->can('create_transaction'))->toBeFalse();
    expect($this->demo->can('create_fund'))->toBeFalse();
    expect($this->demo->can('create_campaign'))->toBeFalse();
});

// ── Session 329 — demo upload lockdown ────────────────────────────────────────
// New-file uploads are blocked for the demo role at every chokepoint; existing-
// media reuse (no new file) stays open. BlockDemoUploads is the server-side
// boundary (the FileUpload field-disable in AppServiceProvider is only UX).

/**
 * A published, unlocked page + one widget the demo role may otherwise edit
 * (demo holds full page CRUD), so a 403 isolates the upload gate rather than a
 * missing permission or the page lock.
 */
function demoUploadWidget(): PageWidget
{
    $page = Page::factory()->create([
        'slug'   => 'demo-upload-' . uniqid(),
        'status' => 'published',
    ]);

    $widgetType = WidgetType::create([
        'handle'        => 'demo_upload_widget_' . uniqid(),
        'label'         => 'Demo Upload Widget',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [],
    ]);

    return $page->widgets()->create([
        'widget_type_id'    => $widgetType->id,
        'label'             => 'Test',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => ['background' => ['color' => '#ffffff']],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);
}

it('blocks the demo role from uploading a new page-builder image (config + appearance chokepoints)', function () {
    Storage::fake('public');
    $widget = demoUploadWidget();

    $this->actingAs($this->demo)
        ->post("/admin/api/page-builder/widgets/{$widget->id}/image", [
            'key'  => 'image',
            'file' => UploadedFile::fake()->image('new.jpg', 400, 300),
        ])
        ->assertForbidden();

    $this->actingAs($this->demo)
        ->post("/admin/api/page-builder/widgets/{$widget->id}/appearance-image", [
            'file' => UploadedFile::fake()->image('bg.jpg', 400, 300),
        ])
        ->assertForbidden();
});

it('blocks the demo role from the rich-text inline-image upload', function () {
    Storage::fake('public');
    $widget = demoUploadWidget();

    $this->actingAs($this->demo)
        ->post('/admin/inline-image-upload', [
            'model_type' => 'page_widget',
            'model_id'   => $widget->id,
            'file'       => UploadedFile::fake()->image('inline.jpg', 400, 300),
        ])
        ->assertForbidden();
});

it('gates the Livewire temporary-upload endpoint (covers every Filament FileUpload field)', function () {
    // Every Filament FileUpload field funnels through this one endpoint, so
    // confirming the gate is attached here proves the whole admin-form surface
    // is covered. gatherMiddleware() includes the controller-applied middleware
    // that Livewire reads from config('livewire.temporary_file_upload.middleware').
    $route = app('router')->getRoutes()->getByName('livewire.upload-file');

    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain(BlockDemoUploads::class);
});

it('still lets the demo role set an image from existing media (no new file)', function () {
    Storage::fake('public');
    $widget = demoUploadWidget();

    // Seed a stored media row at the model level (not through a gated endpoint),
    // then reuse it — the reuse path introduces no new file and stays open.
    $source = $widget->addMedia(UploadedFile::fake()->image('seed.jpg', 400, 300))
        ->toMediaCollection('config_image', 'public');

    $this->actingAs($this->demo)
        ->post("/admin/api/page-builder/widgets/{$widget->id}/use-existing-image", [
            'key'      => 'image',
            'media_id' => $source->id,
        ])
        ->assertOk();
});

it('still lets the demo role browse the media picker list (read-only, no new file)', function () {
    // The media-browser list endpoint (session 356) is the read surface the
    // reuse-by-id action above is selected from — it must stay open to demo, as
    // browsing introduces no new file (only the upload affordance is gated).
    $this->actingAs($this->demo)
        ->getJson('/admin/api/page-builder/media')
        ->assertOk()
        ->assertJsonStructure(['data', 'has_more']);
});

it('still lets a non-demo user upload (the gate is scoped to the demo role, not demo mode)', function () {
    Storage::fake('public');
    $widget = demoUploadWidget();

    $admin = User::factory()->create();
    $admin->givePermissionTo(['view_page', 'update_page']);

    $this->actingAs($admin)
        ->post("/admin/api/page-builder/widgets/{$widget->id}/appearance-image", [
            'file' => UploadedFile::fake()->image('bg.jpg', 400, 300),
        ])
        ->assertOk();
});

// ── Session 345 — Flag 344-E: close the BlockDemoUploads chokepoint gap ───────
// The dashboard-builder and record-detail-view-builder appearance-image upload
// routes lacked BlockDemoUploads, breaking the single-chokepoint invariant (not
// exploitable today — the demo role lacks manage_dashboard_config /
// manage_record_detail_views — but the gate belongs on every new-file path).
// The demo-role permission deny-list means a request-level 403 cannot isolate
// the middleware from the permission gate, so assert the middleware is attached.

it('attaches BlockDemoUploads to the dashboard + record-detail appearance-image upload routes', function () {
    $find = function (string $uriNeedle) {
        foreach (app('router')->getRoutes() as $route) {
            if (in_array('POST', $route->methods(), true) && str_contains($route->uri(), $uriNeedle)) {
                return $route;
            }
        }

        return null;
    };

    $dashboard = $find('dashboard-builder/configs/{dashboardConfig}/widgets/{widget}/appearance-image');
    $recordDetail = $find('record-detail-view-builder/views/{view}/widgets/{widget}/appearance-image');

    expect($dashboard)->not->toBeNull();
    expect($recordDetail)->not->toBeNull();
    expect($dashboard->gatherMiddleware())->toContain(BlockDemoUploads::class);
    expect($recordDetail->gatherMiddleware())->toContain(BlockDemoUploads::class);
});
