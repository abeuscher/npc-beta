<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\CollectionItem;
use Illuminate\Database\Seeder;

class LogoGardenDemoSeeder extends Seeder
{
    public function run(): void
    {
        $collection = Collection::updateOrCreate(
            ['handle' => 'logo-garden-demo'],
            [
                'name'        => 'Logo Garden Demo',
                'description' => 'Sample logos for testing the logo garden widget.',
                'source_type' => 'custom',
                'fields'      => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text',  'required' => true,  'helpText' => '', 'options' => []],
                    ['key' => 'logo', 'label' => 'Logo', 'type' => 'image', 'required' => false, 'helpText' => '', 'options' => []],
                ],
                'is_public' => true,
                'is_active' => true,
            ]
        );

        $logos = [
            ['name' => 'Adidas',    'file' => 'logo-adidas.png'],
            ['name' => 'Amazon',    'file' => 'logo-amazon.png'],
            ['name' => 'Arrow',     'file' => 'logo-arrow.webp'],
            ['name' => 'Google',    'file' => 'logo-google.png'],
            ['name' => 'Instagram', 'file' => 'logo-instagram.png'],
            ['name' => 'Nissan',    'file' => 'logo-nissan.png'],
            ['name' => 'Spotify',   'file' => 'logo-spotify.png'],
            ['name' => 'Wave',      'file' => 'logo-wave.webp'],
            ['name' => 'YouTube',   'file' => 'logo-youtube.png'],
        ];

        $sampleDir = resource_path('sample-images/logos');

        foreach ($logos as $i => $data) {
            $item = CollectionItem::updateOrCreate(
                [
                    'collection_id' => $collection->id,
                    'sort_order'    => $i,
                ],
                [
                    'data'         => ['name' => $data['name']],
                    'is_published' => true,
                ]
            );

            // Attach sample image if available and not already attached
            $filePath = $sampleDir . '/' . $data['file'];
            if (file_exists($filePath) && $item->getFirstMedia('logo') === null) {
                $item->addMedia($filePath)
                    ->preservingOriginal()
                    ->toMediaCollection('logo');
            }
        }
    }
}
