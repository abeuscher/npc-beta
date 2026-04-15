<?php

use App\Filament\Widgets\DashboardDebugGeneratorWidget;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Page;
use App\Models\Product;
use App\Models\SampleImage;
use Database\Seeders\SampleImageLibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->group('slow');

it('attaches pool images to generated products when the pool has images', function () {
    $this->artisan('db:seed', ['--class' => SampleImageLibrarySeeder::class]);

    $widget = new DashboardDebugGeneratorWidget();
    $widget->type = 'products';
    $widget->quantity = 3;
    $widget->generate();

    $products = Product::all();
    expect($products)->toHaveCount(3);

    $products->each(function (Product $p) {
        expect($p->getMedia('product_image'))->toHaveCount(1);
    });
});

it('attaches pool images to generated events for thumbnail and header collections', function () {
    $this->artisan('db:seed', ['--class' => SampleImageLibrarySeeder::class]);

    $widget = new DashboardDebugGeneratorWidget();
    $widget->type = 'events';
    $widget->quantity = 3;
    $widget->generate();

    $events = Event::all();
    expect($events)->toHaveCount(3);

    $events->each(function (Event $e) {
        expect($e->getMedia('event_thumbnail'))->toHaveCount(1);
        expect($e->getMedia('event_header'))->toHaveCount(1);
    });
});

it('attaches pool images to generated blog posts for thumbnail and header collections', function () {
    $this->artisan('db:seed', ['--class' => SampleImageLibrarySeeder::class]);

    \App\Models\User::factory()->create();

    $widget = new DashboardDebugGeneratorWidget();
    $widget->type = 'blog_posts';
    $widget->quantity = 2;
    $widget->generate();

    $posts = Page::where('type', 'post')->get();
    expect($posts)->toHaveCount(2);

    $posts->each(function (Page $page) {
        expect($page->getMedia('post_thumbnail'))->toHaveCount(1);
        expect($page->getMedia('post_header'))->toHaveCount(1);
    });
});

it('no-ops gracefully when the product-photos pool is empty', function () {
    // Ensure the host row exists but has no media attached
    SampleImage::forCategory(SampleImage::CATEGORY_PRODUCT_PHOTOS);

    $widget = new DashboardDebugGeneratorWidget();
    $widget->type = 'products';
    $widget->quantity = 2;
    $widget->generate();

    expect(Product::count())->toBe(2);
    Product::all()->each(function (Product $p) {
        expect($p->getMedia('product_image'))->toHaveCount(0);
    });
});
