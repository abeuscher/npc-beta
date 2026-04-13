<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function writeHeroThumbnail(string $file, ?string $bytes = null): string
{
    $dir = base_path('app/Widgets/Hero/thumbnails');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = $dir . '/' . $file;
    file_put_contents($path, $bytes ?? pngBytes());
    return $path;
}

function pngBytes(): string
{
    // 1x1 transparent PNG
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
    );
}

it('serves a PNG with the correct content-type and cache headers', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);
    $path = writeHeroThumbnail('preset-test-thumb.png');

    try {
        $response = $this->get('/widget-thumbnails/hero/preset-test-thumb.png');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        expect($response->headers->get('Cache-Control'))->toContain('max-age=3600')->toContain('public');
    } finally {
        @unlink($path);
    }
});

it('rejects path traversal and non-PNG filenames with 404', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $this->get('/widget-thumbnails/hero/..%2Fetc%2Fpasswd')->assertNotFound();
    $this->get('/widget-thumbnails/hero/evil.txt')->assertNotFound();
    $this->get('/widget-thumbnails/hero/preset-foo.jpg')->assertNotFound();
    $this->get('/widget-thumbnails/hero/random.png')->assertNotFound();
});

it('returns 404 for an unknown widget handle', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $this->get('/widget-thumbnails/not-a-widget/static.png')->assertNotFound();
});

it('returns 404 when the widget exists but the file is not on disk', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $path = base_path('app/Widgets/Hero/thumbnails/preset-does-not-exist.png');
    @unlink($path);

    $this->get('/widget-thumbnails/hero/preset-does-not-exist.png')->assertNotFound();
});
