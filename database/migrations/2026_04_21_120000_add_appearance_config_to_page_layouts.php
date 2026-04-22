<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_layouts', function (Blueprint $table) {
            $table->jsonb('appearance_config')->default('{}')->after('layout_config');
        });

        DB::table('page_layouts')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $layoutConfig = json_decode($row->layout_config ?? 'null', true);
                if (! is_array($layoutConfig)) {
                    $layoutConfig = [];
                }

                $appearance = [];

                if (array_key_exists('background_color', $layoutConfig)) {
                    $bgColor = $layoutConfig['background_color'];
                    unset($layoutConfig['background_color']);
                    if ($bgColor !== null && $bgColor !== '') {
                        $appearance['background']['color'] = $bgColor;
                    }
                }

                $padding = [];
                foreach (['top', 'right', 'bottom', 'left'] as $side) {
                    $key = 'padding_' . $side;
                    if (array_key_exists($key, $layoutConfig)) {
                        $val = $layoutConfig[$key];
                        unset($layoutConfig[$key]);
                        if ($val !== null && $val !== '') {
                            $padding[$side] = $val;
                        }
                    }
                }
                if (! empty($padding)) {
                    $appearance['layout']['padding'] = $padding;
                }

                $margin = [];
                foreach (['top', 'right', 'bottom', 'left'] as $side) {
                    $key = 'margin_' . $side;
                    if (array_key_exists($key, $layoutConfig)) {
                        $val = $layoutConfig[$key];
                        unset($layoutConfig[$key]);
                        if ($val !== null && $val !== '') {
                            $margin[$side] = $val;
                        }
                    }
                }
                if (! empty($margin)) {
                    $appearance['layout']['margin'] = $margin;
                }

                DB::table('page_layouts')
                    ->where('id', $row->id)
                    ->update([
                        'layout_config'     => json_encode($layoutConfig),
                        'appearance_config' => json_encode((object) $appearance),
                    ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('page_layouts')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $layoutConfig = json_decode($row->layout_config ?? 'null', true);
                if (! is_array($layoutConfig)) {
                    $layoutConfig = [];
                }

                $appearance = json_decode($row->appearance_config ?? 'null', true);
                if (! is_array($appearance)) {
                    $appearance = [];
                }

                $bgColor = $appearance['background']['color'] ?? null;
                if ($bgColor !== null && $bgColor !== '') {
                    $layoutConfig['background_color'] = $bgColor;
                }

                foreach (['top', 'right', 'bottom', 'left'] as $side) {
                    $pv = $appearance['layout']['padding'][$side] ?? null;
                    if ($pv !== null && $pv !== '') {
                        $layoutConfig['padding_' . $side] = $pv;
                    }
                    $mv = $appearance['layout']['margin'][$side] ?? null;
                    if ($mv !== null && $mv !== '') {
                        $layoutConfig['margin_' . $side] = $mv;
                    }
                }

                DB::table('page_layouts')
                    ->where('id', $row->id)
                    ->update(['layout_config' => json_encode($layoutConfig)]);
            }
        });

        Schema::table('page_layouts', function (Blueprint $table) {
            $table->dropColumn('appearance_config');
        });
    }
};
