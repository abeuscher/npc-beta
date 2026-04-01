<?php

use App\Filament\Resources\CampaignResource;
use App\Filament\Resources\CollectionResource;
use App\Filament\Resources\ContactResource;
use App\Filament\Resources\FormResource;
use App\Filament\Resources\MembershipResource;
use App\Filament\Resources\NoteResource;
use App\Filament\Resources\OrganizationResource;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\PostResource;
use App\Models\Campaign;
use App\Models\Collection;
use App\Models\Contact;
use App\Models\Form;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Helpers ─────────────────────────────────────────────────────────────────

function createSuperAdmin(): User
{
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

function createRegularAdmin(array $permissions = []): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('admin');

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        $user->givePermissionTo($perm);
    }

    return $user;
}

// ── Trashed records visible via getEloquentQuery ────────────────────────────

it('includes trashed contacts in resource query', function () {
    $contact = Contact::factory()->create();
    $contact->delete();

    $query = ContactResource::getEloquentQuery();
    expect($query->where('id', $contact->id)->exists())->toBeTrue();
});

it('includes trashed pages in resource query', function () {
    $page = Page::factory()->create(['type' => 'default']);
    $page->delete();

    $query = PageResource::getEloquentQuery();
    expect($query->where('id', $page->id)->exists())->toBeTrue();
});

it('includes trashed posts in resource query', function () {
    $post = Page::factory()->create(['type' => 'post']);
    $post->delete();

    $query = PostResource::getEloquentQuery();
    expect($query->where('id', $post->id)->exists())->toBeTrue();
});

it('includes trashed organizations in resource query', function () {
    $org = Organization::factory()->create();
    $org->delete();

    $query = OrganizationResource::getEloquentQuery();
    expect($query->where('id', $org->id)->exists())->toBeTrue();
});

it('includes trashed campaigns in resource query', function () {
    $campaign = Campaign::factory()->create();
    $campaign->delete();

    $query = CampaignResource::getEloquentQuery();
    expect($query->where('id', $campaign->id)->exists())->toBeTrue();
});

it('includes trashed notes in resource query', function () {
    $contact = Contact::factory()->create();
    $note = Note::factory()->create(['notable_type' => Contact::class, 'notable_id' => $contact->id]);
    $note->delete();

    $query = NoteResource::getEloquentQuery();
    expect($query->where('id', $note->id)->exists())->toBeTrue();
});

it('includes trashed memberships in resource query', function () {
    $membership = Membership::factory()->create();
    $membership->delete();

    $query = MembershipResource::getEloquentQuery();
    expect($query->where('id', $membership->id)->exists())->toBeTrue();
});

it('includes trashed forms in resource query', function () {
    $form = Form::factory()->create();
    $form->delete();

    $query = FormResource::getEloquentQuery();
    expect($query->where('id', $form->id)->exists())->toBeTrue();
});

it('includes trashed collections in resource query', function () {
    $collection = Collection::factory()->create();
    $collection->delete();

    $query = CollectionResource::getEloquentQuery();
    expect($query->where('id', $collection->id)->exists())->toBeTrue();
});

// ── Restore allowed for users with delete permission ────────────────────────

it('allows restore for users with delete permission', function () {
    $admin = createRegularAdmin(['delete_contact']);

    $contact = Contact::factory()->create();
    $contact->delete();

    test()->actingAs($admin);
    expect(ContactResource::canRestore($contact))->toBeTrue();
});

it('blocks restore for users without delete permission', function () {
    $admin = createRegularAdmin([]);

    $contact = Contact::factory()->create();
    $contact->delete();

    test()->actingAs($admin);
    expect(ContactResource::canRestore($contact))->toBeFalse();
});

// ── Restore action works on soft-deleted record ─────────────────────────────

it('can restore a soft-deleted contact', function () {
    $contact = Contact::factory()->create();
    $contact->delete();

    expect(Contact::find($contact->id))->toBeNull();

    $contact->restore();

    expect(Contact::find($contact->id))->not->toBeNull()
        ->and(Contact::find($contact->id)->deleted_at)->toBeNull();
});

it('can restore a soft-deleted page', function () {
    $page = Page::factory()->create(['type' => 'default']);
    $page->delete();

    $page->restore();

    expect(Page::find($page->id))->not->toBeNull();
});

// ── Force-delete gated to super_admin on all resources ──────────────────────

$resourceTests = [
    ['resource' => ContactResource::class, 'model' => Contact::class, 'permission' => 'delete_contact'],
    ['resource' => PageResource::class, 'model' => Page::class, 'permission' => 'delete_page', 'attrs' => ['type' => 'default']],
    ['resource' => PostResource::class, 'model' => Page::class, 'permission' => 'delete_post', 'attrs' => ['type' => 'post']],
    ['resource' => OrganizationResource::class, 'model' => Organization::class, 'permission' => 'delete_organization'],
    ['resource' => MembershipResource::class, 'model' => Membership::class, 'permission' => 'delete_membership'],
    ['resource' => FormResource::class, 'model' => Form::class, 'permission' => 'delete_form'],
    ['resource' => NoteResource::class, 'model' => Note::class, 'permission' => 'delete_note'],
    ['resource' => CampaignResource::class, 'model' => Campaign::class, 'permission' => 'delete_campaign'],
    ['resource' => CollectionResource::class, 'model' => Collection::class, 'permission' => 'delete_collection'],
];

foreach ($resourceTests as $test) {
    $resourceName = class_basename($test['resource']);

    it("restricts {$resourceName} force-delete to super_admin", function () use ($test) {
        $superAdmin = createSuperAdmin();
        $regularAdmin = createRegularAdmin([$test['permission']]);

        $attrs = $test['attrs'] ?? [];
        $record = $test['model']::factory()->create($attrs);
        $record->delete();

        test()->actingAs($superAdmin);
        expect($test['resource']::canForceDelete($record))->toBeTrue();

        test()->actingAs($regularAdmin);
        expect($test['resource']::canForceDelete($record))->toBeFalse();
    });
}

// ── Force-delete actually removes the record ────────────────────────────────

it('force-deletes a soft-deleted contact permanently', function () {
    $contact = Contact::factory()->create();
    $contact->delete();

    $contact->forceDelete();

    expect(Contact::withTrashed()->find($contact->id))->toBeNull();
});

// ── Policy methods ──────────────────────────────────────────────────────────

it('policy allows restore for users with delete permission', function () {
    $admin = createRegularAdmin(['delete_contact']);
    $contact = Contact::factory()->create();
    $contact->delete();

    expect($admin->can('restore', $contact))->toBeTrue();
});

it('policy blocks force-delete for non-super_admin', function () {
    $admin = createRegularAdmin(['delete_contact']);
    $contact = Contact::factory()->create();
    $contact->delete();

    expect($admin->can('forceDelete', $contact))->toBeFalse();
});

it('policy allows force-delete for super_admin', function () {
    $superAdmin = createSuperAdmin();
    $contact = Contact::factory()->create();
    $contact->delete();

    expect($superAdmin->can('forceDelete', $contact))->toBeTrue();
});
