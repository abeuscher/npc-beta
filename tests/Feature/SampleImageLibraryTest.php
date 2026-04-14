<?php

use App\Models\SampleImage;
use App\Services\DemoDataService;
use App\Services\SampleImageLibrary;
use Database\Seeders\SampleImageLibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Seeder ──────────────────────────────────────────────────────────────────

it('ingests files from every category folder', function () {
    $this->artisan('db:seed', ['--class' => SampleImageLibrarySeeder::class]);

    foreach (SampleImage::CATEGORIES as $category) {
        $host = SampleImage::where('category', $category)->first();
        expect($host)->not->toBeNull();

        $onDisk  = collect(glob(resource_path("sample-images/{$category}/*")))
            ->filter(fn ($p) => is_file($p) && ! str_starts_with(basename($p), '.'))
            ->count();
        $inPool = $host->getMedia($category)->count();

        expect($inPool)->toBe($onDisk, "pool for {$category} should match disk ({$onDisk})");
    }
});

it('seeder is idempotent — re-running does not duplicate media', function () {
    $this->artisan('db:seed', ['--class' => SampleImageLibrarySeeder::class]);
    $first = SampleImage::where('category', 'logos')->first()->getMedia('logos')->count();

    $this->artisan('db:seed', ['--class' => SampleImageLibrarySeeder::class]);
    $second = SampleImage::where('category', 'logos')->first()->getMedia('logos')->count();

    expect($second)->toBe($first);
});

it('seeder removes media rows whose files are no longer on disk', function () {
    $dir = resource_path('sample-images/still-photos');
    $tempFile = $dir . '/test-sample.png';
    $img = imagecreatetruecolor(10, 10);
    imagepng($img, $tempFile);
    imagedestroy($img);

    try {
        $this->artisan('db:seed', ['--class' => SampleImageLibrarySeeder::class]);
        $host = SampleImage::where('category', 'still-photos')->first();
        expect($host->getMedia('still-photos')->pluck('file_name')->all())->toContain('test-sample.png');

        unlink($tempFile);
        $this->artisan('db:seed', ['--class' => SampleImageLibrarySeeder::class]);
        expect($host->refresh()->getMedia('still-photos')->pluck('file_name')->all())->not->toContain('test-sample.png');
    } finally {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
});

// ── Service ─────────────────────────────────────────────────────────────────

it('random returns the requested count (or less) from the pool', function () {
    $this->artisan('db:seed', ['--class' => SampleImageLibrarySeeder::class]);
    $lib = app(SampleImageLibrary::class);

    $result = $lib->random(SampleImage::CATEGORY_LOGOS, 3);

    expect($result)->toHaveCount(3);
});

it('random returns empty collection when pool is empty', function () {
    SampleImage::forCategory(SampleImage::CATEGORY_STILL_PHOTOS); // row but no media
    $lib = app(SampleImageLibrary::class);

    expect($lib->random(SampleImage::CATEGORY_STILL_PHOTOS, 3))->toHaveCount(0);
});

it('randomUrl returns null when pool is empty', function () {
    $lib = app(SampleImageLibrary::class);
    expect($lib->randomUrl(SampleImage::CATEGORY_PRODUCT_PHOTOS))->toBeNull();
});

it('urlOrPlaceholder falls back to placeholder when empty', function () {
    $lib = app(SampleImageLibrary::class);
    expect($lib->urlOrPlaceholder(SampleImage::CATEGORY_PRODUCT_PHOTOS))
        ->toBe(SampleImageLibrary::PLACEHOLDER_URL);
});

// ── DemoDataService integration ─────────────────────────────────────────────

it('DemoDataService generateFieldValue maps image keys to pool URLs or placeholder', function () {
    $svc = app(DemoDataService::class);

    $url = $svc->generateFieldValue('image', null, ['key' => 'product_photo']);
    expect($url)->toBe(SampleImageLibrary::PLACEHOLDER_URL);

    $this->artisan('db:seed', ['--class' => SampleImageLibrarySeeder::class]);

    $logoUrl = $svc->generateFieldValue('image', null, ['key' => 'company_logo']);
    expect($logoUrl)
        ->not->toBe(SampleImageLibrary::PLACEHOLDER_URL)
        ->and($logoUrl)->toContain('/storage/');

    $portraitUrl = $svc->generateFieldValue('image', null, ['key' => 'member_photo']);
    expect($portraitUrl)
        ->not->toBe(SampleImageLibrary::PLACEHOLDER_URL);
});
