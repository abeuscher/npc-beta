<?php

use App\Livewire\PageBuilder;
use App\Models\Page;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->assignRole('super_admin');
    $this->actingAs($this->user);
});

it('saveChanges sends a success notification (cosmetic save affordance)', function () {
    $page = Page::factory()->create(['type' => 'default']);

    Livewire::actingAs($this->user)
        ->test(PageBuilder::class, ['pageId' => $page->id])
        ->call('saveChanges');

    Notification::assertNotified('Saved');
});
