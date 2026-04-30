<?php

use App\Models\Event;
use App\Models\Page;
use App\Models\Product;
use App\Models\SampleImage;
use App\Models\User;
use App\Services\RandomDataGenerator;
use Database\Seeders\SampleImageLibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->group('slow');

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    test()->actingAs($admin);
});

it('attaches pool images to generated products when the pool has images', function () {
    $this->artisan('db:seed', ['--class' => SampleImageLibrarySeeder::class]);

    app(RandomDataGenerator::class)->generate(['products' => 3]);

    $products = Product::all();
    expect($products)->toHaveCount(3);

    $products->each(function (Product $p) {
        expect($p->getMedia('product_image'))->toHaveCount(1);
    });
});

it('attaches pool images to generated events for thumbnail and header collections', function () {
    $this->artisan('db:seed', ['--class' => SampleImageLibrarySeeder::class]);

    app(RandomDataGenerator::class)->generate(['events' => 3]);

    $events = Event::all();
    expect($events)->toHaveCount(3);

    $events->each(function (Event $e) {
        expect($e->getMedia('event_thumbnail'))->toHaveCount(1);
        expect($e->getMedia('event_header'))->toHaveCount(1);
    });
});

it('attaches pool images to generated blog posts for thumbnail and header collections', function () {
    $this->artisan('db:seed', ['--class' => SampleImageLibrarySeeder::class]);

    app(RandomDataGenerator::class)->generate(['posts' => 2]);

    $posts = Page::where('type', 'post')->get();
    expect($posts)->toHaveCount(2);

    $posts->each(function (Page $page) {
        expect($page->getMedia('post_thumbnail'))->toHaveCount(1);
        expect($page->getMedia('post_header'))->toHaveCount(1);
    });
});

it('no-ops gracefully when the product-photos pool is empty', function () {
    SampleImage::forCategory(SampleImage::CATEGORY_PRODUCT_PHOTOS);

    app(RandomDataGenerator::class)->generate(['products' => 2]);

    expect(Product::count())->toBe(2);
    Product::all()->each(function (Product $p) {
        expect($p->getMedia('product_image'))->toHaveCount(0);
    });
});
