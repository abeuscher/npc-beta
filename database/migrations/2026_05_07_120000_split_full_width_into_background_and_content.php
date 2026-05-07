<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('widget_types', function (Blueprint $table) {
            $table->boolean('background_full_width')->default(true)->after('full_width');
            $table->boolean('content_full_width')->default(false)->after('background_full_width');
        });

        DB::table('widget_types')->update([
            'background_full_width' => true,
            'content_full_width'    => false,
        ]);

        Schema::table('widget_types', function (Blueprint $table) {
            $table->dropColumn('full_width');
        });

        $this->rewriteJsonLayoutKey('page_widgets', 'appearance_config', ['layout']);
        $this->rewriteJsonLayoutKey('page_layouts', 'layout_config', []);
        $this->rewriteJsonLayoutKey('widget_presets', 'appearance_config', ['layout']);
    }

    public function down(): void
    {
        Schema::table('widget_types', function (Blueprint $table) {
            $table->boolean('full_width')->default(false)->after('default_open');
        });

        DB::statement(
            'UPDATE widget_types SET full_width = content_full_width'
        );

        Schema::table('widget_types', function (Blueprint $table) {
            $table->dropColumn(['background_full_width', 'content_full_width']);
        });

        $this->collapseJsonLayoutKey('page_widgets', 'appearance_config', ['layout']);
        $this->collapseJsonLayoutKey('page_layouts', 'layout_config', []);
        $this->collapseJsonLayoutKey('widget_presets', 'appearance_config', ['layout']);
    }

    private function rewriteJsonLayoutKey(string $table, string $column, array $nestPath): void
    {
        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $column, $nestPath) {
            foreach ($rows as $row) {
                $decoded = is_string($row->$column) ? json_decode($row->$column, true) : $row->$column;
                if (! is_array($decoded)) {
                    $decoded = [];
                }

                $bag =& $this->descend($decoded, $nestPath);
                unset($bag['full_width']);
                $bag['background_full_width'] = true;
                $bag['content_full_width']    = false;

                DB::table($table)
                    ->where('id', $row->id)
                    ->update([$column => json_encode($decoded)]);
            }
        });
    }

    private function collapseJsonLayoutKey(string $table, string $column, array $nestPath): void
    {
        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $column, $nestPath) {
            foreach ($rows as $row) {
                $decoded = is_string($row->$column) ? json_decode($row->$column, true) : $row->$column;
                if (! is_array($decoded)) {
                    continue;
                }

                $bag =& $this->descend($decoded, $nestPath);
                $hasContent = array_key_exists('content_full_width', $bag);
                $content = (bool) ($bag['content_full_width'] ?? false);
                unset($bag['background_full_width'], $bag['content_full_width']);
                if ($hasContent) {
                    $bag['full_width'] = $content;
                }

                DB::table($table)
                    ->where('id', $row->id)
                    ->update([$column => json_encode($decoded)]);
            }
        });
    }

    private function &descend(array &$decoded, array $nestPath): array
    {
        $cursor =& $decoded;
        foreach ($nestPath as $segment) {
            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor =& $cursor[$segment];
        }
        return $cursor;
    }
};
