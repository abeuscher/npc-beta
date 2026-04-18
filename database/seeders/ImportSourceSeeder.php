<?php

namespace Database\Seeders;

use App\Models\ImportSource;
use App\Services\Import\FieldMapper;
use Illuminate\Database\Seeder;

class ImportSourceSeeder extends Seeder
{
    public function run(): void
    {
        $builtIns = [
            'generic'      => 'Generic CSV',
            'wild_apricot' => 'Wild Apricot',
            'bloomerang'   => 'Bloomerang',
        ];

        foreach ($builtIns as $preset => $name) {
            ImportSource::firstOrCreate(
                ['name' => $name],
                [
                    'notes'                     => "Built-in preset: {$preset}. Maps common {$name} export columns.",
                    'contacts_field_map'        => FieldMapper::presetMap($preset),
                    'contacts_custom_field_map' => [],
                    'contacts_match_key'        => 'email',
                    'contacts_match_key_column' => 'email',
                ]
            );
        }
    }
}
