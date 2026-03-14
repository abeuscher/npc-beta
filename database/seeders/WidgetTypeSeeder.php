<?php

namespace Database\Seeders;

use App\Models\WidgetType;
use Illuminate\Database\Seeder;

class WidgetTypeSeeder extends Seeder
{
    public function run(): void
    {
        WidgetType::updateOrCreate(
            ['handle' => 'text_block'],
            [
                'label'         => 'Text Block',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [
                    ['key' => 'content', 'type' => 'richtext', 'label' => 'Content'],
                ],
                'template'      => '{!! $config[\'content\'] ?? \'\' !!}',
            ]
        );
    }
}
