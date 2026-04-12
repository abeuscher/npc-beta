<?php

use App\Filament\Resources\TemplateResource;
use App\Filament\Resources\TemplateResource\Pages\EditContentTemplate;
use App\Filament\Resources\TemplateResource\Pages\EditPageTemplate;
use App\Filament\Resources\TemplateResource\Pages\ListTemplates;
use App\Livewire\PageBuilder;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\Template;
use App\Models\User;
use App\Models\WidgetType;
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
});

// ── Template list page ────────────────────────────────────────────────────

it('loads the template list page', function () {
    Template::factory()->create(['type' => 'page', 'is_default' => true, 'name' => 'Default']);
    Template::factory()->create(['type' => 'content', 'name' => 'About Page']);

    $this->get(TemplateResource::getUrl('index'))
        ->assertOk()
        ->assertSee('About Page');
});

it('lists content templates on the content tab', function () {
    Template::factory()->create(['type' => 'page', 'is_default' => true]);
    Template::factory()->create([
        'type'       => 'content',
        'name'       => 'About Page',
        'definition' => [['handle' => 'text_block', 'config' => [], 'sort_order' => 0]],
    ]);

    Livewire::test(ListTemplates::class)
        ->assertSee('About Page');
});

it('lists page templates on the page tab', function () {
    Template::factory()->create(['type' => 'page', 'is_default' => true, 'name' => 'Default']);
    Template::factory()->create(['type' => 'page', 'is_default' => false, 'name' => 'Landing']);

    Livewire::test(ListTemplates::class)
        ->set('activeTab', 'page')
        ->assertSee('Landing');
});

// ── Edit content template ─────────────────────────────────────────────────

it('can edit a content template name and description', function () {
    $ct = Template::factory()->create([
        'type'        => 'content',
        'name'        => 'Old Name',
        'description' => 'Old desc',
    ]);

    Livewire::test(EditContentTemplate::class, ['record' => $ct->id])
        ->fillForm([
            'name'        => 'New Name',
            'description' => 'New desc',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $ct->refresh();
    expect($ct->name)->toBe('New Name');
    expect($ct->description)->toBe('New desc');
});

it('can delete a non-default content template', function () {
    $ct = Template::factory()->create(['type' => 'content', 'is_default' => false]);

    Livewire::test(ListTemplates::class)
        ->callTableAction('delete', $ct);

    expect(Template::find($ct->id))->toBeNull();
});

// ── Edit page template ────────────────────────────────────────────────────

it('can create a new page template', function () {
    Template::factory()->create(['type' => 'page', 'is_default' => true]);

    Livewire::test(TemplateResource\Pages\CreateTemplate::class)
        ->fillForm([
            'name'        => 'Minimal',
            'description' => 'A clean template',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Template::where('name', 'Minimal')->where('type', 'page')->exists())->toBeTrue();
});

it('can delete a non-default page template', function () {
    Template::factory()->create(['type' => 'page', 'is_default' => true]);
    $pt = Template::factory()->create(['type' => 'page', 'is_default' => false, 'name' => 'Deletable']);

    Livewire::test(ListTemplates::class)
        ->set('activeTab', 'page')
        ->callTableAction('delete', $pt);

    expect(Template::find($pt->id))->toBeNull();
});

it('cannot delete the default page template', function () {
    $default = Template::factory()->create(['type' => 'page', 'is_default' => true]);

    // Delete action is hidden for default templates, so verify it can't be called
    Livewire::test(ListTemplates::class)
        ->assertTableActionHidden('delete', $default);
});

it('can set a page template as default', function () {
    $old = Template::factory()->create(['type' => 'page', 'is_default' => true, 'name' => 'Old Default']);
    $new = Template::factory()->create(['type' => 'page', 'is_default' => false, 'name' => 'New Default']);

    Livewire::test(ListTemplates::class)
        ->set('activeTab', 'page')
        ->callTableAction('setDefault', $new);

    expect($old->fresh()->is_default)->toBeFalse();
    expect($new->fresh()->is_default)->toBeTrue();
});

it('saves page template appearance via edit form', function () {
    $pt = Template::factory()->create([
        'type'          => 'page',
        'is_default'    => true,
        'primary_color' => '#000000',
    ]);

    Livewire::test(EditPageTemplate::class, ['record' => $pt->id])
        ->fillForm(['primary_color' => '#ff0000'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($pt->fresh()->primary_color)->toBe('#ff0000');
});

it('clears appearance to inherit from default', function () {
    Template::factory()->create(['type' => 'page', 'is_default' => true, 'primary_color' => '#111111']);
    $child = Template::factory()->create([
        'type'          => 'page',
        'is_default'    => false,
        'primary_color' => '#222222',
        'heading_font'  => 'Inter',
    ]);

    Livewire::test(EditPageTemplate::class, ['record' => $child->id])
        ->call('clearAppearance');

    $child->refresh();
    expect($child->primary_color)->toBeNull();
    expect($child->heading_font)->toBeNull();
});

// ── Page creation with templates ──────────────────────────────────────────

it('page creation sets template_id from page template selection', function () {
    $pt = Template::factory()->create(['type' => 'page', 'is_default' => true]);

    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    Livewire::test(\App\Filament\Resources\PageResource\Pages\CreatePage::class)
        ->fillForm([
            'title'       => 'Test Page With Template',
            'type'        => 'default',
            'template_id' => $pt->id,
        ])
        ->call('create');

    $page = Page::where('title', 'Test Page With Template')->first();
    expect($page)->not->toBeNull();
    expect($page->template_id)->toBe($pt->id);
});

it('page creation with content template prepopulates widgets', function () {
    $pt = Template::factory()->create(['type' => 'page', 'is_default' => true]);
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $ct = Template::factory()->create([
        'type'       => 'content',
        'name'       => 'Test Content',
        'definition' => [
            ['handle' => 'text_block', 'config' => ['content' => 'Hello'], 'sort_order' => 0],
        ],
    ]);

    Livewire::test(\App\Filament\Resources\PageResource\Pages\CreatePage::class)
        ->fillForm([
            'title'               => 'Hydrated Page',
            'type'                => 'default',
            'template_id'         => $pt->id,
            'content_template_id' => $ct->id,
        ])
        ->call('create');

    $page = Page::where('title', 'Hydrated Page')->first();
    expect($page)->not->toBeNull();

    $widgets = PageWidget::where('page_id', $page->id)->get();
    expect($widgets)->toHaveCount(1);
    expect($widgets->first()->config)->toBe(['content' => 'Hello']);
});

// ── Page edit — template change ───────────────────────────────────────────

it('changing page template on edit updates template_id', function () {
    $pt1 = Template::factory()->create(['type' => 'page', 'is_default' => true, 'name' => 'Default']);
    $pt2 = Template::factory()->create(['type' => 'page', 'is_default' => false, 'name' => 'Alt']);

    $page = Page::factory()->create([
        'template_id' => $pt1->id,
        'type'        => 'default',
        'status'      => 'published',
    ]);

    Livewire::test(\App\Filament\Resources\PageResource\Pages\EditPageDetails::class, [
        'record' => $page->id,
    ])
        ->fillForm(['template_id' => $pt2->id])
        ->call('save');

    expect($page->fresh()->template_id)->toBe($pt2->id);
});

// ── Save as Content Template ──────────────────────────────────────────────

it('save as template creates content template from page widget stack', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);
    $textWidget = WidgetType::where('handle', 'text_block')->first();

    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);

    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $textWidget->id,
        'label'          => 'Hero',
        'config'         => ['content' => 'Welcome'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    Livewire::test(PageBuilder::class, ['pageId' => $page->id])
        ->call('openSaveTemplateModal')
        ->set('saveTemplateName', 'My Template')
        ->set('saveTemplateDescription', 'A test template')
        ->call('saveAsTemplate');

    $template = Template::where('name', 'My Template')->where('type', 'content')->first();
    expect($template)->not->toBeNull();
    expect($template->definition)->toHaveCount(1);
    expect($template->definition[0]['handle'])->toBe('text_block');
    expect($template->definition[0]['config'])->toBe(['content' => 'Welcome']);
});

// ── Non-default page template inheritance ─────────────────────────────────

it('non-default page template inherits values from default when fields are null', function () {
    $default = Template::factory()->create([
        'type'          => 'page',
        'is_default'    => true,
        'primary_color' => '#aaaaaa',
        'heading_font'  => 'Inter',
    ]);

    $child = Template::factory()->create([
        'type'          => 'page',
        'is_default'    => false,
        'primary_color' => null,
        'heading_font'  => null,
    ]);

    expect($child->resolved('primary_color'))->toBe('#aaaaaa');
    expect($child->resolved('heading_font'))->toBe('Inter');
});

// ── Custom header/footer for non-default templates ────────────────────────

it('enableCustomHeader creates a system page and sets header_page_id', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $headerPage = Page::factory()->create(['slug' => '_header', 'type' => 'system', 'status' => 'published']);
    $default = Template::factory()->create([
        'type'           => 'page',
        'is_default'     => true,
        'header_page_id' => $headerPage->id,
    ]);

    $child = Template::factory()->create([
        'type'           => 'page',
        'is_default'     => false,
        'header_page_id' => null,
    ]);

    Livewire::test(EditPageTemplate::class, ['record' => $child->id])
        ->call('enableCustomHeader');

    $child->refresh();
    expect($child->header_page_id)->not->toBeNull();
    expect($child->header_page_id)->not->toBe($headerPage->id);

    $customHeader = Page::find($child->header_page_id);
    expect($customHeader->type)->toBe('system');
});

it('inheritHeader resets header_page_id to null', function () {
    $default = Template::factory()->create(['type' => 'page', 'is_default' => true]);

    $customHeaderPage = Page::factory()->create(['slug' => '_header_test', 'type' => 'system']);
    $child = Template::factory()->create([
        'type'           => 'page',
        'is_default'     => false,
        'header_page_id' => $customHeaderPage->id,
    ]);

    Livewire::test(EditPageTemplate::class, ['record' => $child->id])
        ->call('inheritHeader');

    expect($child->fresh()->header_page_id)->toBeNull();
});

// ── Save block layout as content template from EditPage ───────────────────

it('save block layout as content template from edit page details', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);
    $textWidget = WidgetType::where('handle', 'text_block')->first();

    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);
    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $textWidget->id,
        'label'          => 'Test Block',
        'config'         => ['heading' => 'Hello'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    Livewire::test(\App\Filament\Resources\PageResource\Pages\EditPageDetails::class, [
        'record' => $page->id,
    ])
        ->callAction('saveAsContentTemplate', [
            'template_name'        => 'From Edit Page',
            'template_description' => 'Created from the edit page menu',
        ]);

    $template = Template::where('name', 'From Edit Page')->where('type', 'content')->first();
    expect($template)->not->toBeNull();
    expect($template->definition)->toHaveCount(1);
    expect($template->definition[0]['handle'])->toBe('text_block');
});

// ── Site Theme page retired ───────────────────────────────────────────────

it('site theme page route is not registered', function () {
    $response = $this->get('/admin/site-theme');
    $response->assertStatus(404);
});
