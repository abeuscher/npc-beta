<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductDemoSeeder extends Seeder
{
    public function run(): void
    {
        $imageDir = base_path('resources/sample-images/products');
        $images = array_merge(
            glob($imageDir . '/*.jpg'),
            glob($imageDir . '/*.png'),
        );
        shuffle($images);

        $products = [
            [
                'name'        => 'Celestial Perfume Collection',
                'description' => 'A limited-edition fragrance inspired by the night sky. Notes of jasmine, sandalwood, and starlight.',
                'capacity'    => 50,
                'prices'      => [
                    ['label' => 'Sample Size (10 ml)', 'amount' => 25.00],
                    ['label' => 'Full Bottle (50 ml)', 'amount' => 85.00],
                ],
            ],
            [
                'name'        => 'Heritage Brick Set',
                'description' => 'Build your own miniature landmark with this 1,200-piece construction kit. Ages 14+.',
                'capacity'    => 100,
                'prices'      => [
                    ['label' => 'Standard Edition', 'amount' => 59.99],
                    ['label' => 'Deluxe Edition (with display case)', 'amount' => 89.99],
                    ['label' => 'Digital Instructions Only', 'amount' => 0.00],
                ],
            ],
            [
                'name'        => 'Artisan Print Series',
                'description' => 'Gallery-quality giclée prints on archival cotton paper. Each print is signed and numbered.',
                'capacity'    => 25,
                'prices'      => [
                    ['label' => 'Small (8×10")', 'amount' => 35.00],
                    ['label' => 'Large (16×20")', 'amount' => 75.00],
                ],
            ],
            [
                'name'        => 'Enchanted Garden Kit',
                'description' => 'Everything you need to grow a pollinator-friendly container garden. Seeds, soil, and hand-painted pot included.',
                'capacity'    => 200,
                'prices'      => [
                    ['label' => 'Single Kit', 'amount' => 42.00],
                ],
            ],
            [
                'name'        => 'Community Cookbook',
                'description' => 'Sixty recipes from our members, beautifully photographed. All proceeds support local food programs.',
                'capacity'    => 500,
                'prices'      => [
                    ['label' => 'Paperback', 'amount' => 22.00],
                    ['label' => 'Hardcover', 'amount' => 38.00],
                    ['label' => 'PDF Download', 'amount' => 0.00],
                ],
            ],
        ];

        foreach ($products as $i => $data) {
            $product = Product::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name'        => $data['name'],
                    'description' => $data['description'],
                    'capacity'    => $data['capacity'],
                    'status'      => 'published',
                    'sort_order'  => $i,
                ]
            );

            foreach ($data['prices'] as $j => $priceData) {
                ProductPrice::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'label'      => $priceData['label'],
                    ],
                    [
                        'amount'     => $priceData['amount'],
                        'sort_order' => $j,
                    ]
                );
            }

            if (isset($images[$i]) && file_exists($images[$i])) {
                try {
                    $product->clearMediaCollection('product_image');
                    $product->addMedia($images[$i])
                        ->preservingOriginal()
                        ->toMediaCollection('product_image');
                } catch (\Throwable $e) {
                    $this->command?->warn("Could not attach image: {$e->getMessage()}");
                }
            }
        }
    }
}
