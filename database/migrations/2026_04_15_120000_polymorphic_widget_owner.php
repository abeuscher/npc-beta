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
        // 1. Add polymorphic owner columns (nullable during backfill).
        Schema::table('page_widgets', function (Blueprint $table) {
            $table->string('owner_type')->nullable();
            $table->uuid('owner_id')->nullable();
            $table->index(['owner_type', 'owner_id']);
        });

        Schema::table('page_layouts', function (Blueprint $table) {
            $table->string('owner_type')->nullable();
            $table->uuid('owner_id')->nullable();
            $table->index(['owner_type', 'owner_id']);
        });

        // 2. Relax page_id NOT NULL so template-owned inserts (which have no page_id) can proceed.
        DB::statement('ALTER TABLE page_widgets ALTER COLUMN page_id DROP NOT NULL');
        DB::statement('ALTER TABLE page_layouts ALTER COLUMN page_id DROP NOT NULL');

        // 3. Backfill page-owned rows.
        DB::statement("UPDATE page_widgets SET owner_type = 'page', owner_id = page_id WHERE page_id IS NOT NULL");
        DB::statement("UPDATE page_layouts SET owner_type = 'page', owner_id = page_id WHERE page_id IS NOT NULL");

        // 4. Hydrate content-template definitions into owned rows, then clear.
        $templates = DB::table('templates')
            ->where('type', 'content')
            ->whereNotNull('definition')
            ->get(['id', 'definition']);

        foreach ($templates as $template) {
            $definition = is_string($template->definition)
                ? json_decode($template->definition, true)
                : $template->definition;

            if (! is_array($definition) || empty($definition)) {
                continue;
            }

            $this->hydrateDefinitionToOwner($definition, 'template', $template->id);
        }

        // 5. Drop FK + page_id column.
        Schema::table('page_widgets', function (Blueprint $table) {
            $table->dropForeign(['page_id']);
            $table->dropColumn('page_id');
        });

        Schema::table('page_layouts', function (Blueprint $table) {
            $table->dropForeign(['page_id']);
            $table->dropColumn('page_id');
        });

        // 6. Make owner columns NOT NULL now that every row is backfilled.
        DB::statement('ALTER TABLE page_widgets ALTER COLUMN owner_type SET NOT NULL');
        DB::statement('ALTER TABLE page_widgets ALTER COLUMN owner_id SET NOT NULL');
        DB::statement('ALTER TABLE page_layouts ALTER COLUMN owner_type SET NOT NULL');
        DB::statement('ALTER TABLE page_layouts ALTER COLUMN owner_id SET NOT NULL');

        // 7. Drop templates.definition — no longer the source of truth.
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('definition');
        });
    }

    public function down(): void
    {
        // One-way structural migration; pre-beta. No reverse path.
        throw new \RuntimeException('Irreversible migration: polymorphic_widget_owner.');
    }

    /**
     * Create page_widgets + page_layouts rows owned by the given owner, from a
     * serialised-stack definition array (same shape as PageWidget::serializeStack()).
     */
    private function hydrateDefinitionToOwner(array $definition, string $ownerType, string $ownerId): void
    {
        $widgetTypesByHandle = DB::table('widget_types')->pluck('id', 'handle');
        $now = now();

        foreach ($definition as $index => $entry) {
            $sort = $entry['sort_order'] ?? $index;
            $type = $entry['type'] ?? 'widget';

            if ($type === 'layout') {
                $layoutId = (string) Str::uuid();
                DB::table('page_layouts')->insert([
                    'id'            => $layoutId,
                    'owner_type'    => $ownerType,
                    'owner_id'      => $ownerId,
                    'label'         => $entry['label'] ?? null,
                    'display'       => $entry['display'] ?? 'grid',
                    'columns'       => $entry['columns'] ?? 2,
                    'layout_config' => json_encode($entry['layout_config'] ?? []),
                    'sort_order'    => $sort,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);

                foreach ($entry['slots'] ?? [] as $columnIndex => $slotWidgets) {
                    foreach ($slotWidgets as $slotIndex => $child) {
                        $widgetTypeId = $widgetTypesByHandle[$child['handle'] ?? ''] ?? null;
                        if (! $widgetTypeId) {
                            continue;
                        }

                        DB::table('page_widgets')->insert([
                            'id'                => (string) Str::uuid(),
                            'owner_type'        => $ownerType,
                            'owner_id'          => $ownerId,
                            'layout_id'         => $layoutId,
                            'column_index'      => (int) $columnIndex,
                            'widget_type_id'    => $widgetTypeId,
                            'label'             => $child['label'] ?? null,
                            'config'            => json_encode($child['config'] ?? []),
                            'query_config'      => json_encode($child['query_config'] ?? []),
                            'appearance_config' => json_encode($child['appearance_config'] ?? []),
                            'sort_order'        => $child['sort_order'] ?? $slotIndex,
                            'is_active'         => $child['is_active'] ?? true,
                            'created_at'        => $now,
                            'updated_at'        => $now,
                        ]);
                    }
                }

                continue;
            }

            // Root widget.
            $widgetTypeId = $widgetTypesByHandle[$entry['handle'] ?? ''] ?? null;
            if (! $widgetTypeId) {
                continue;
            }

            DB::table('page_widgets')->insert([
                'id'                => (string) Str::uuid(),
                'owner_type'        => $ownerType,
                'owner_id'          => $ownerId,
                'layout_id'         => null,
                'column_index'      => null,
                'widget_type_id'    => $widgetTypeId,
                'label'             => $entry['label'] ?? null,
                'config'            => json_encode($entry['config'] ?? []),
                'query_config'      => json_encode($entry['query_config'] ?? []),
                'appearance_config' => json_encode($entry['appearance_config'] ?? []),
                'sort_order'        => $sort,
                'is_active'         => $entry['is_active'] ?? true,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }
    }
};
