<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\Template;
use App\Models\User;
use App\Services\ImportExport\ContentExporter;
use App\Services\ImportExport\ContentImporter;
use App\Services\ImportExport\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    User::factory()->create();

    if (! Template::page()->where('is_default', true)->exists()) {
        Template::create(['name' => 'Default', 'type' => 'page', 'is_default' => true]);
    }
});

function importBundleWithContent(string $slug, string $content): void
{
    $bundle = [
        'format_version' => ContentExporter::FORMAT_VERSION,
        'payload'        => [
            'pages' => [
                [
                    'slug'       => $slug,
                    'title'      => 'Imported',
                    'status'     => 'published',
                    'type'       => 'default',
                    'meta_title' => null,
                    'widgets'    => [
                        [
                            'type'              => 'widget',
                            'handle'            => 'text_block',
                            'label'             => 'Block',
                            'config'            => [
                                'content'        => $content,
                                'vertical_align' => 'middle',
                            ],
                            'query_config'      => [],
                            'appearance_config' => [],
                            'sort_order'        => 0,
                            'is_active'         => true,
                            'media'             => [],
                        ],
                    ],
                ],
            ],
        ],
    ];

    app(ContentImporter::class)->import($bundle, new ImportLog());
}

it('sanitises rich-text widget config values during import', function () {
    importBundleWithContent(
        'imported-malicious',
        '<p>visible</p><script>alert(1)</script><p onclick="x()">y</p>',
    );

    $widget = PageWidget::forOwner(Page::where('slug', 'imported-malicious')->firstOrFail())->firstOrFail();
    expect($widget->config['content'])->toBe('<p>visible</p><p>y</p>');
});

it('strips javascript: hrefs and iframe at the import boundary', function () {
    importBundleWithContent(
        'defence-in-depth',
        '<a href="javascript:alert(1)">x</a><iframe src="https://evil.com"></iframe>',
    );

    $widget = PageWidget::forOwner(Page::where('slug', 'defence-in-depth')->firstOrFail())->firstOrFail();
    expect($widget->config['content'])->toBe('<a>x</a>');
});
