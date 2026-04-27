<?php

use App\Filament\Resources\TemplateResource;
use App\Filament\Resources\TemplateResource\Pages\EditContentTemplate;
use App\Filament\Resources\TemplateResource\Pages\EditPageTemplate;
use App\Filament\Resources\TemplateResource\Pages\EditPageTemplateChrome;
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

it('saves page template appearance via the entry page (Label and Colors)', function () {
    $pt = Template::factory()->create([
        'type'          => 'page',
        'is_default'    => true,
        'name'          => 'Default',
        'primary_color' => '#000000',
    ]);

    Livewire::test(EditPageTemplate::class, ['record' => $pt->id])
        ->fillForm(['primary_color' => '#ff0000'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($pt->fresh()->primary_color)->toBe('#ff0000');
});

it('clears appearance to inherit from default via the entry page', function () {
    Template::factory()->create(['type' => 'page', 'is_default' => true, 'primary_color' => '#111111']);
    $child = Template::factory()->create([
        'type'            => 'page',
        'is_default'      => false,
        'primary_color'   => '#222222',
        'header_bg_color' => '#333333',
    ]);

    Livewire::test(EditPageTemplate::class, ['record' => $child->id])
        ->call('clearAppearance');

    $child->refresh();
    expect($child->primary_color)->toBeNull();
    expect($child->header_bg_color)->toBeNull();
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
        'type' => 'content',
        'name' => 'Test Content',
    ]);

    $textWidget = WidgetType::where('handle', 'text_block')->firstOrFail();
    $ct->widgets()->create([
        'widget_type_id' => $textWidget->id,
        'config'         => ['content' => 'Hello'],
        'sort_order'     => 0,
        'is_active'      => true,
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

    $widgets = PageWidget::forOwner($page)->get();
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

    $page->widgets()->create([
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

    $templateWidgets = PageWidget::forOwner($template)->with('widgetType')->get();
    expect($templateWidgets)->toHaveCount(1);
    expect($templateWidgets->first()->widgetType->handle)->toBe('text_block');
    expect($templateWidgets->first()->config)->toBe(['content' => 'Welcome']);
});

// ── Non-default page template inheritance ─────────────────────────────────

it('non-default page template inherits values from default when fields are null', function () {
    $default = Template::factory()->create([
        'type'            => 'page',
        'is_default'      => true,
        'primary_color'   => '#aaaaaa',
        'header_bg_color' => '#bbbbbb',
    ]);

    $child = Template::factory()->create([
        'type'            => 'page',
        'is_default'      => false,
        'primary_color'   => null,
        'header_bg_color' => null,
    ]);

    expect($child->resolved('primary_color'))->toBe('#aaaaaa');
    expect($child->resolved('header_bg_color'))->toBe('#bbbbbb');
});

// ── Custom header/footer for non-default templates ────────────────────────

it('enableCustomChrome (header) creates a system page and sets header_page_id', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);
    $this->artisan('db:seed', ['--class' => 'RecordDetailViewSeeder']);

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

    Livewire::test(EditPageTemplateChrome::class, ['record' => $child->id, 'view' => 'page_template_header'])
        ->call('enableCustomChrome');

    $child->refresh();
    expect($child->header_page_id)->not->toBeNull();
    expect($child->header_page_id)->not->toBe($headerPage->id);

    $customHeader = Page::find($child->header_page_id);
    expect($customHeader->type)->toBe('system');
});

it('inheritChrome (header) resets header_page_id to null', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);
    $this->artisan('db:seed', ['--class' => 'RecordDetailViewSeeder']);

    $default = Template::factory()->create(['type' => 'page', 'is_default' => true]);

    $customHeaderPage = Page::factory()->create(['slug' => '_header_test', 'type' => 'system']);
    $child = Template::factory()->create([
        'type'           => 'page',
        'is_default'     => false,
        'header_page_id' => $customHeaderPage->id,
    ]);

    Livewire::test(EditPageTemplateChrome::class, ['record' => $child->id, 'view' => 'page_template_header'])
        ->call('inheritChrome');

    expect($child->fresh()->header_page_id)->toBeNull();
});

// ── Save block layout as content template from EditPage ───────────────────

it('save block layout as content template from edit page details', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);
    $textWidget = WidgetType::where('handle', 'text_block')->first();

    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);
    $page->widgets()->create([
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

    $templateWidgets = PageWidget::forOwner($template)->with('widgetType')->get();
    expect($templateWidgets)->toHaveCount(1);
    expect($templateWidgets->first()->widgetType->handle)->toBe('text_block');
});

// ── Site Theme page retired ───────────────────────────────────────────────

it('site theme page route is not registered', function () {
    $response = $this->get('/admin/site-theme');
    $response->assertStatus(404);
});
