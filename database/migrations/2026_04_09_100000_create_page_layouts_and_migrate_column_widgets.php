<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create page_layouts table
        Schema::create('page_layouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_id');
            $table->string('label')->nullable();
            $table->string('display')->default('grid');
            $table->integer('columns')->default(2);
            $table->jsonb('layout_config')->default('{}');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('page_id')
                ->references('id')->on('pages')
                ->cascadeOnDelete();
        });

        // 2. Add layout_id to page_widgets
        Schema::table('page_widgets', function (Blueprint $table) {
            $table->uuid('layout_id')->nullable()->after('page_id');

            $table->foreign('layout_id')
                ->references('id')->on('page_layouts')
                ->cascadeOnDelete();
        });

        // 3. Migrate existing column_widget data
        $columnWidgetType = DB::table('widget_types')
            ->where('handle', 'column_widget')
            ->first();

        if ($columnWidgetType) {
            $columnWidgets = DB::table('page_widgets')
                ->where('widget_type_id', $columnWidgetType->id)
                ->get();

            foreach ($columnWidgets as $cw) {
                $config = json_decode($cw->config, true) ?? [];
                $numColumns = (int) ($config['num_columns'] ?? 2);
                $gridTemplateCols = $config['grid_template_columns'] ?? str_repeat('1fr ', $numColumns);

                $layoutId = Str::uuid()->toString();

                // Create the page_layout
                DB::table('page_layouts')->insert([
                    'id'            => $layoutId,
                    'page_id'       => $cw->page_id,
                    'label'         => $cw->label,
                    'display'       => 'grid',
                    'columns'       => $numColumns,
                    'layout_config' => json_encode([
                        'grid_template_columns' => trim($gridTemplateCols),
                        'gap'                   => '1.5rem',
                    ]),
                    'sort_order'    => $cw->sort_order,
                    'created_at'    => $cw->created_at,
                    'updated_at'    => now(),
                ]);

                // Reparent children: set layout_id, clear parent_widget_id
                DB::table('page_widgets')
                    ->where('parent_widget_id', $cw->id)
                    ->update([
                        'layout_id'        => $layoutId,
                        'parent_widget_id' => null,
                    ]);

                // Delete the column widget row
                DB::table('page_widgets')->where('id', $cw->id)->delete();
            }

            // Delete the column_widget widget type
            DB::table('widget_types')->where('id', $columnWidgetType->id)->delete();
        }

        // 4. Drop parent_widget_id from page_widgets
        Schema::table('page_widgets', function (Blueprint $table) {
            $table->dropForeign(['parent_widget_id']);
            $table->dropColumn('parent_widget_id');
        });
    }

    public function down(): void
    {
        // Re-add parent_widget_id
        Schema::table('page_widgets', function (Blueprint $table) {
            $table->uuid('parent_widget_id')->nullable()->after('page_id');

            $table->foreign('parent_widget_id')
                ->references('id')->on('page_widgets')
                ->nullOnDelete();
        });

        // Drop layout_id
        Schema::table('page_widgets', function (Blueprint $table) {
            $table->dropForeign(['layout_id']);
            $table->dropColumn('layout_id');
        });

        // Drop page_layouts
        Schema::dropIfExists('page_layouts');
    }
};
