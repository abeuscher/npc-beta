<?php

namespace App\Widgets\Carousel;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\SampleImage;
use App\Services\SampleImageLibrary;
use App\WidgetPrimitive\Source;
use Database\Seeders\SampleImageLibrarySeeder;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
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
                'is_public'        => true,
                'is_active'        => true,
                'accepted_sources' => [Source::HUMAN, Source::DEMO],
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

        $this->call(SampleImageLibrarySeeder::class);
        $photos = app(SampleImageLibrary::class)->random(SampleImage::CATEGORY_STILL_PHOTOS, count($slides));

        foreach ($slides as $i => $data) {
            $item = CollectionItem::updateOrCreate(
                [
                    'collection_id' => $collection->id,
                    'sort_order'    => $i,
                ],
                [
                    'data'         => $data,
                    'is_published' => true,
                ]
            );

            $source = $photos->get($i);
            if ($source && $item->getFirstMedia('image') === null) {
                try {
                    $item->addMedia($source->getPath())
                        ->preservingOriginal()
                        ->toMediaCollection('image');
                } catch (\Throwable $e) {
                    $this->command?->warn("Could not attach carousel image: {$e->getMessage()}");
                }
            }
        }
    }
}
