<?php

use App\Livewire\RecordDetailViewBuilder;
use App\Models\Contact;
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

    $this->view = RecordDetailView::query()
        ->where('record_type', Contact::class)
        ->where('handle', 'contact_overview')
        ->firstOrFail();
});

it('aborts 403 for users without manage_record_detail_views', function () {
    $user = User::factory()->create();
    $user->assignRole('cms_editor');

    $this->actingAs($user);

    Livewire::test(RecordDetailViewBuilder::class, ['viewId' => $this->view->id])
        ->assertStatus(403);
});

it('bootstrap data is record_detail-mode and scoped to the record-detail-view-builder API', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $component = Livewire::test(RecordDetailViewBuilder::class, ['viewId' => $this->view->id]);
    $data = $component->instance()->getBootstrapData();

    expect($data['mode'])->toBe('record_detail')
        ->and($data['owner_id'])->toBe($this->view->id)
        ->and($data['owner_type'])->toBe('record_detail_view')
        ->and($data['api_base_url'])->toContain('/api/record-detail-view-builder/views/' . $this->view->id)
        ->and($data['api_lookup_url'])->toBe($data['api_base_url'])
        ->and($data['allowed_appearance_fields'])->toBe(['background', 'text'])
        ->and($data['allowed_widget_handles'])->toEqualCanonicalizing(['record_detail_placeholder', 'recent_notes', 'membership_status', 'recent_donations'])
        ->and($data['view_label'])->toBe('Overview')
        ->and($data['record_type_label'])->toBe('Contact');
});
