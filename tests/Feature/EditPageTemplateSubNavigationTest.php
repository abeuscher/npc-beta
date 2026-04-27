<?php

use App\Filament\Resources\TemplateResource\Pages\EditPageTemplate;
use App\Filament\Resources\TemplateResource\Pages\EditPageTemplateChrome;
use App\Filament\Resources\TemplateResource\Pages\EditPageTemplateScss;
use App\Models\Template;
use App\Models\User;
use Filament\Navigation\NavigationItem;
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

    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);
    $this->artisan('db:seed', ['--class' => 'RecordDetailViewSeeder']);
});

it('renders four sub-nav entries on EditPageTemplate (Label and Colors, Header, Footer, SCSS)', function () {
    $template = Template::factory()->create(['type' => 'page', 'is_default' => true]);

    $page = new EditPageTemplate();
    $page->record = $template;

    $items = $page->getSubNavigation();

    expect($items)->toHaveCount(4);

    $labels = array_map(fn (NavigationItem $i) => $i->getLabel(), $items);
    expect($labels)->toBe(['Label and Colors', 'Header', 'Footer', 'SCSS']);
});

it('mounts the Chrome sub-page for the Header View', function () {
    $template = Template::factory()->create(['type' => 'page', 'is_default' => true]);

    Livewire::test(EditPageTemplateChrome::class, ['record' => $template->id, 'view' => 'page_template_header'])
        ->assertSuccessful();
});

it('mounts the Chrome sub-page for the Footer View', function () {
    $template = Template::factory()->create(['type' => 'page', 'is_default' => true]);

    Livewire::test(EditPageTemplateChrome::class, ['record' => $template->id, 'view' => 'page_template_footer'])
        ->assertSuccessful();
});

it('rejects an unknown View handle on the Chrome sub-page', function () {
    $template = Template::factory()->create(['type' => 'page', 'is_default' => true]);

    expect(fn () => Livewire::test(EditPageTemplateChrome::class, ['record' => $template->id, 'view' => 'nonexistent']))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('mounts the SCSS sub-page', function () {
    $template = Template::factory()->create(['type' => 'page', 'is_default' => true]);

    Livewire::test(EditPageTemplateScss::class, ['record' => $template->id])
        ->assertSuccessful();
});
