<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\WidgetPrimitive\Source;
use Illuminate\Database\Seeder;

class MemosCollectionSeeder extends Seeder
{
    public function run(): void
    {
        Collection::firstOrCreate(
            ['handle' => 'memos'],
            [
                'name'             => 'Memos',
                'description'      => 'Admin-only notices and announcements surfaced on the dashboard.',
                'source_type'      => 'custom',
                'is_public'        => false,
                'is_active'        => true,
                'accepted_sources' => [Source::HUMAN],
                'fields'           => [
                    ['key' => 'title',     'label' => 'Title',        'type' => 'text',      'required' => true,  'helpText' => '', 'options' => []],
                    ['key' => 'body',      'label' => 'Body',         'type' => 'rich_text', 'required' => true,  'helpText' => '', 'options' => []],
                    ['key' => 'posted_at', 'label' => 'Posted Date',  'type' => 'date',      'required' => false, 'helpText' => 'Defaults to today if left blank when surfaced.', 'options' => []],
                ],
            ]
        );
    }
}
