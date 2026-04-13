<?php

use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function withPreservedPresetFile(string $path, callable $test): void
{
    $backup = null;
    if (file_exists($path)) {
        $backup = file_get_contents($path);
    }
    try {
        $test();
    } finally {
        if ($backup !== null) {
            file_put_contents($path, $backup);
        } else {
            @unlink($path);
        }
    }
}

it('resolves thumbnail URLs for presets when PNG files exist on disk', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $file = base_path('app/Widgets/TextBlock/thumbnails/preset-draft-1.png');

    withPreservedPresetFile($file, function () use ($file) {
        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }
        file_put_contents($file, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
        ));

        $picker = WidgetType::forPicker();
        $textBlock = collect($picker)->firstWhere('handle', 'text_block');
        $entry = collect($textBlock['presets'])->firstWhere('handle', 'draft-1');

        expect($entry['thumbnail'])->toBeString();
        expect($entry['thumbnail'])->toContain('/widget-thumbnails/text_block/preset-draft-1.png');
    });
});

it('returns null thumbnail when the preset PNG is not on disk', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $file = base_path('app/Widgets/TextBlock/thumbnails/preset-draft-1.png');

    withPreservedPresetFile($file, function () use ($file) {
        @unlink($file);

        $picker = WidgetType::forPicker();
        $textBlock = collect($picker)->firstWhere('handle', 'text_block');
        $entry = collect($textBlock['presets'])->firstWhere('handle', 'draft-1');

        expect($entry)->toHaveKey('thumbnail');
        expect($entry['thumbnail'])->toBeNull();
    });
});
