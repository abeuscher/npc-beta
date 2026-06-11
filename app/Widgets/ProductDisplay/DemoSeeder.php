<?php

namespace App\Widgets\ProductDisplay;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\SampleImage;
use App\Services\SampleImageLibrary;
use Database\Seeders\SampleImageLibrarySeeder;
use Illuminate\Database\Seeder;

/**
 * Demo data for the ProductDisplay widget: a single published product with a
 * couple of price tiers and a product image, so the dev thumbnail renders a
 * real product card instead of rendering blank against an empty products table.
 * Idempotent.
 */
class DemoSeeder extends Seeder
{
    public const PRODUCT_SLUG = 'demo-workshop-ticket';

    public function run(): void
    {
        $product = Product::updateOrCreate(
            ['slug' => self::PRODUCT_SLUG],
            [
                'name'        => 'Spring Workshop Ticket',
                'description' => 'A hands-on afternoon workshop with our program team. Includes materials and refreshments.',
                'status'      => 'published',
                'capacity'    => 40,
                'sort_order'  => 0,
            ]
        );

        if ($product->prices()->count() === 0) {
            foreach ([['label' => 'General Admission', 'amount' => 35.00, 'sort_order' => 0],
                      ['label' => 'Member Rate', 'amount' => 25.00, 'sort_order' => 1]] as $price) {
                ProductPrice::create(['product_id' => $product->id] + $price);
            }
        }

        if ($product->getFirstMedia('product_image') === null) {
            $this->call(SampleImageLibrarySeeder::class);
            $photo = app(SampleImageLibrary::class)->random(SampleImage::CATEGORY_PRODUCT_PHOTOS, 1)->first();

            if ($photo) {
                try {
                    $product->addMedia($photo->getPath())
                        ->preservingOriginal()
                        ->toMediaCollection('product_image');
                } catch (\Throwable $e) {
                    $this->command?->warn("Could not attach demo product image: {$e->getMessage()}");
                }
            }
        }
    }
}
