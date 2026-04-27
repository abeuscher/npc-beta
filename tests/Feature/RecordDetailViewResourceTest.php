<?php

use App\Filament\Resources\RecordDetailViewResource;
use App\Filament\Resources\RecordDetailViewResource\Pages\CreateRecordDetailView;
use App\Filament\Resources\RecordDetailViewResource\Pages\EditRecordDetailView;
use App\Filament\Resources\RecordDetailViewResource\Pages\ListRecordDetailViews;
use App\Models\Contact;
use App\Models\Template;
use App\Models\User;
use App\WidgetPrimitive\Views\RecordDetailView;
use Database\Seeders\RecordDetailViewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    (new \Database\Seeders\PermissionSeeder())->run();
    (new RecordDetailViewSeeder())->run();
});

it('lists Views to a super_admin and excludes chrome Views', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(ListRecordDetailViews::class)
        ->assertCanSeeTableRecords(
            RecordDetailView::query()
                ->where('record_type', Contact::class)
                ->where('handle', 'contact_overview')
                ->get()
        )
        ->assertCanNotSeeTableRecords(
            RecordDetailView::query()
                ->where('record_type', Template::class)
                ->whereIn('handle', ['page_template_header', 'page_template_footer'])
                ->get()
        );
});

it('denies cms_editor access to the resource', function () {
    $user = User::factory()->create();
    $user->assignRole('cms_editor');

    $response = $this->actingAs($user)->get(RecordDetailViewResource::getUrl('index'));

    expect($response->getStatusCode())->toBeIn([403, 404]);
});

it('creates a View via the create page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(CreateRecordDetailView::class)
        ->fillForm([
            'record_type' => Contact::class,
            'handle'      => 'contact_recent_activity',
            'label'       => 'Recent Activity',
            'sort_order'  => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(RecordDetailView::where('handle', 'contact_recent_activity')->exists())->toBeTrue();
});

it('rejects duplicate (record_type, handle) pairs on create', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(CreateRecordDetailView::class)
        ->fillForm([
            'record_type' => Contact::class,
            'handle'      => 'contact_overview',
            'label'       => 'Duplicate',
            'sort_order'  => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['handle']);
});

it('flags the seeded contact_overview View as primary and prevents deletion', function () {
    $primary = RecordDetailView::query()
        ->where('record_type', Contact::class)
        ->where('handle', 'contact_overview')
        ->firstOrFail();

    expect(RecordDetailViewResource::isPrimary($primary))->toBeTrue()
        ->and(RecordDetailViewResource::canDelete($primary))->toBeFalse();
});

it('does not flag user-created Views as primary and allows their deletion', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $custom = RecordDetailView::create([
        'record_type' => Contact::class,
        'handle'      => 'contact_followups',
        'label'       => 'Follow-Ups',
        'sort_order'  => 1,
    ]);

    expect(RecordDetailViewResource::isPrimary($custom))->toBeFalse()
        ->and(RecordDetailViewResource::canDelete($custom))->toBeTrue();
});

it('mounts the EditRecordDetailView page and exposes the record', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $view = RecordDetailView::query()
        ->where('record_type', Contact::class)
        ->where('handle', 'contact_overview')
        ->firstOrFail();

    Livewire::actingAs($admin)
        ->test(EditRecordDetailView::class, ['record' => $view->id])
        ->assertSuccessful()
        ->assertFormSet([
            'record_type' => Contact::class,
            'handle'      => 'contact_overview',
            'label'       => 'Overview',
        ]);
});
