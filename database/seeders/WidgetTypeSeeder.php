<?php

namespace Database\Seeders;

use App\Models\WidgetType;
use Illuminate\Database\Seeder;

class WidgetTypeSeeder extends Seeder
{
    public function run(): void
    {
        WidgetType::firstOrCreate(
            ['handle' => 'text_block'],
            [
                'label'       => 'Text Block',
                'render_mode' => 'server',
                'collections' => [],
                'template'    => '{!! $content ?? \'\' !!}',
            ]
        );
    }
}
