<?php

namespace App\Widgets\LogoGarden;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\SampleImage;
use App\Services\SampleImageLibrary;
use Database\Seeders\SampleImageLibrarySeeder;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
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

        $this->call(SampleImageLibrarySeeder::class);
        $logos = app(SampleImageLibrary::class)->random(SampleImage::CATEGORY_LOGOS, 9);

        CollectionItem::where('collection_id', $collection->id)->delete();

        foreach ($logos as $i => $source) {
            $item = CollectionItem::create([
                'collection_id' => $collection->id,
                'sort_order'    => $i,
                'data'          => ['name' => $this->labelFromFilename($source->file_name)],
                'is_published'  => true,
            ]);

            try {
                $item->addMedia($source->getPath())
                    ->preservingOriginal()
                    ->toMediaCollection('logo');
            } catch (\Throwable $e) {
                $this->command?->warn("Could not attach logo {$source->file_name}: {$e->getMessage()}");
            }
        }
    }

    private function labelFromFilename(string $fileName): string
    {
        $base = preg_replace('/\.(svg\.png|svg|png|jpe?g|webp|gif)$/i', '', $fileName);
        $base = preg_replace('/[_\-\s]logo([_\-\s].*)?$/i', '', $base);
        $base = preg_replace('/^logo[_\-\s]of[_\-\s]/i', '', $base);
        $base = str_replace(['_', '-'], ' ', $base);
        $base = preg_replace('/\s+/', ' ', $base);

        return trim($base) ?: $fileName;
    }
}
