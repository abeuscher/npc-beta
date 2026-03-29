<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\CollectionItem;
use Illuminate\Database\Seeder;

class CarouselDemoSeeder extends Seeder
{
    public function run(): void
    {
        $collection = Collection::updateOrCreate(
            ['handle' => 'carousel-demo'],
            [
                'name'        => 'Carousel Demo',
                'description' => 'Sample slides for testing the carousel widget.',
                'source_type' => 'custom',
                'fields'      => [
                    ['key' => 'title',       'label' => 'Title',       'type' => 'text',     'required' => true,  'helpText' => '', 'options' => []],
                    ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false, 'helpText' => '', 'options' => []],
                    ['key' => 'image',       'label' => 'Image',       'type' => 'image',    'required' => false, 'helpText' => '', 'options' => []],
                ],
                'is_public' => true,
                'is_active' => true,
            ]
        );

        $slides = [
            [
                'title'       => 'Welcome to Our Organization',
                'description' => 'Serving the community since 2020.',
            ],
            [
                'title'       => 'Annual Fundraiser',
                'description' => 'Join us for our biggest event of the year.',
            ],
            [
                'title'       => 'Volunteer Spotlight',
                'description' => 'Meet the people who make it all possible.',
            ],
            [
                'title'       => 'Get Involved',
                'description' => 'There are many ways to support our mission.',
            ],
        ];

        foreach ($slides as $i => $data) {
            CollectionItem::updateOrCreate(
                [
                    'collection_id' => $collection->id,
                    'sort_order'    => $i,
                ],
                [
                    'data'         => $data,
                    'is_published' => true,
                ]
            );
        }
    }
}
