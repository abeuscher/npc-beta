<?php

use App\Filament\Pages\Settings\GeneralSettingsPage;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('super_admin');
    $this->actingAs($user);

    SiteSetting::firstOrCreate(['key' => 'base_url'],      ['value' => 'http://localhost', 'group' => 'general', 'type' => 'string']);
    SiteSetting::firstOrCreate(['key' => 'blog_prefix'],   ['value' => 'news',             'group' => 'general', 'type' => 'string']);
    SiteSetting::firstOrCreate(['key' => 'events_prefix'], ['value' => 'events',           'group' => 'general', 'type' => 'string']);
    SiteSetting::firstOrCreate(['key' => 'portal_prefix'], ['value' => 'members',          'group' => 'general', 'type' => 'string']);
});

it('persists the notes_edit_only_by_creator toggle on via the per-section save', function () {
    expect(SiteSetting::get('notes_edit_only_by_creator', 'false'))->toBe('false');

    Livewire::test(GeneralSettingsPage::class)
        ->set('data.notes_edit_only_by_creator', true)
        ->call('saveSection', 'notes', 'Notes')
        ->assertHasNoErrors();

    expect(SiteSetting::get('notes_edit_only_by_creator', 'false'))->toBe('true');
});

it('persists the notes_edit_only_by_creator toggle off via the per-section save', function () {
    SiteSetting::set('notes_edit_only_by_creator', 'true');

    Livewire::test(GeneralSettingsPage::class)
        ->set('data.notes_edit_only_by_creator', false)
        ->call('saveSection', 'notes', 'Notes')
        ->assertHasNoErrors();

    expect(SiteSetting::get('notes_edit_only_by_creator', 'false'))->toBe('false');
});
