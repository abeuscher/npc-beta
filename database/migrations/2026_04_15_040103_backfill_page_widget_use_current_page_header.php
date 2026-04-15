<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('page_widgets')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $config = json_decode($row->appearance_config ?? 'null', true);
                if (! is_array($config)) {
                    $config = [];
                }

                $background = $config['background'] ?? [];
                if (! is_array($background)) {
                    $background = [];
                }

                if (array_key_exists('use_current_page_header', $background)) {
                    continue;
                }

                $background['use_current_page_header'] = false;
                $config['background'] = $background;

                DB::table('page_widgets')
                    ->where('id', $row->id)
                    ->update(['appearance_config' => json_encode($config)]);
            }
        });
    }

    public function down(): void
    {
        DB::table('page_widgets')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $config = json_decode($row->appearance_config ?? 'null', true);
                if (! is_array($config) || ! isset($config['background']['use_current_page_header'])) {
                    continue;
                }

                unset($config['background']['use_current_page_header']);

                DB::table('page_widgets')
                    ->where('id', $row->id)
                    ->update(['appearance_config' => json_encode($config)]);
            }
        });
    }
};
