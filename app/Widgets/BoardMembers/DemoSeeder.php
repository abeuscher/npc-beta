<?php

namespace App\Widgets\BoardMembers;

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
            ['handle' => 'board-members-demo'],
            [
                'name'        => 'Board Members Demo',
                'description' => 'Sample board members for testing the board members widget.',
                'source_type' => 'custom',
                'fields'      => [
                    ['key' => 'name',          'label' => 'Name',          'type' => 'text',     'required' => true,  'helpText' => '', 'options' => []],
                    ['key' => 'photo',         'label' => 'Photo',         'type' => 'image',    'required' => false, 'helpText' => '', 'options' => []],
                    ['key' => 'job_title',     'label' => 'Job Title',     'type' => 'text',     'required' => false, 'helpText' => '', 'options' => []],
                    ['key' => 'department',    'label' => 'Department',    'type' => 'text',     'required' => false, 'helpText' => '', 'options' => []],
                    ['key' => 'bio',           'label' => 'Bio',           'type' => 'textarea', 'required' => false, 'helpText' => '', 'options' => []],
                    ['key' => 'linkedin',      'label' => 'LinkedIn',      'type' => 'text',     'required' => false, 'helpText' => '', 'options' => []],
                    ['key' => 'github',        'label' => 'GitHub',        'type' => 'text',     'required' => false, 'helpText' => '', 'options' => []],
                    ['key' => 'website_url',   'label' => 'Website URL',   'type' => 'text',     'required' => false, 'helpText' => '', 'options' => []],
                    ['key' => 'website_label', 'label' => 'Website Label', 'type' => 'text',     'required' => false, 'helpText' => '', 'options' => []],
                ],
                'is_public'        => true,
                'is_active'        => true,
                'accepted_sources' => [Source::HUMAN, Source::DEMO],
            ]
        );

        $titles      = ['Board Chair', 'Vice Chair', 'Treasurer', 'Secretary', 'Director', 'Director'];
        $departments = ['Executive Committee', 'Executive Committee', 'Finance', 'Governance', 'Programs', 'Development'];

        $this->call(SampleImageLibrarySeeder::class);
        $portraits = app(SampleImageLibrary::class)->random(SampleImage::CATEGORY_PORTRAITS, count($titles));

        foreach ($titles as $i => $jobTitle) {
            $name = fake()->name();

            $data = [
                'name'       => $name,
                'job_title'  => $jobTitle,
                'department' => $departments[$i],
                'bio'        => '<p>' . fake()->paragraph() . '</p>',
            ];

            if ($i === 0 || $i === 4) {
                $data['linkedin'] = 'https://linkedin.com/in/' . fake()->slug(2);
            }
            if ($i === 1 || $i === 4) {
                $data['github'] = 'https://github.com/' . fake()->slug(1);
            }
            if ($i === 2) {
                $data['website_url']   = 'https://example.com';
                $data['website_label'] = 'Website';
            }

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

            $source = $portraits->get($i);
            if ($source) {
                try {
                    $item->clearMediaCollection('photo');
                    $item->addMedia($source->getPath())
                        ->preservingOriginal()
                        ->toMediaCollection('photo');
                } catch (\Throwable $e) {
                    $this->command?->warn("Could not attach portrait: {$e->getMessage()}");
                }
            }
        }
    }
}
