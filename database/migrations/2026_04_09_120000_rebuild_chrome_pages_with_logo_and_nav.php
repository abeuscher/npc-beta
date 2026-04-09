<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // 1. Find _header and _footer system pages
        $headerPage = DB::table('pages')->where('slug', '_header')->first();
        $footerPage = DB::table('pages')->where('slug', '_footer')->first();

        // 2 + 3. Wipe layouts (cascades to layout-child widgets) and root widgets
        foreach ([$headerPage, $footerPage] as $page) {
            if (! $page) {
                continue;
            }
            DB::table('page_layouts')->where('page_id', $page->id)->delete();
            DB::table('page_widgets')->where('page_id', $page->id)->delete();
        }

        // 4. Delete site_header and site_footer widget types
        foreach (['site_header', 'site_footer'] as $oldHandle) {
            $oldType = DB::table('widget_types')->where('handle', $oldHandle)->first();
            if ($oldType) {
                DB::table('page_widgets')->where('widget_type_id', $oldType->id)->delete();
                DB::table('widget_types')->where('id', $oldType->id)->delete();
            }
        }

        // Look up new widget types — these are seeded by WidgetTypeSeeder, but a
        // migration cannot rely on the seeder having run. Insert them inline if
        // missing so the migration is self-sufficient.
        $logoType      = $this->ensureWidgetType('logo', $now);
        $navType       = $this->ensureWidgetType('nav', $now);
        $textBlockType = DB::table('widget_types')->where('handle', 'text_block')->first();

        // 5. Re-seed _header
        if ($headerPage && $logoType && $navType) {
            $layoutId = (string) Str::uuid();
            DB::table('page_layouts')->insert([
                'id'            => $layoutId,
                'page_id'       => $headerPage->id,
                'label'         => null,
                'display'       => 'grid',
                'columns'       => 2,
                'layout_config' => json_encode([
                    'grid_template_columns' => 'auto 1fr',
                    'gap'                   => '1rem',
                    'align_items'           => 'center',
                ]),
                'sort_order'    => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            DB::table('page_widgets')->insert([
                'id'             => (string) Str::uuid(),
                'page_id'        => $headerPage->id,
                'layout_id'      => $layoutId,
                'column_index'   => 0,
                'widget_type_id' => $logoType->id,
                'label'          => null,
                'config'         => json_encode(['link_url' => '/', 'text' => '']),
                'query_config'   => json_encode([]),
                'style_config'   => json_encode([]),
                'sort_order'     => 0,
                'is_active'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);

            DB::table('page_widgets')->insert([
                'id'             => (string) Str::uuid(),
                'page_id'        => $headerPage->id,
                'layout_id'      => $layoutId,
                'column_index'   => 1,
                'widget_type_id' => $navType->id,
                'label'          => null,
                'config'         => json_encode(['nav_handle' => 'primary']),
                'query_config'   => json_encode([]),
                'style_config'   => json_encode([]),
                'sort_order'     => 0,
                'is_active'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }

        // 6. Re-seed _footer
        if ($footerPage && $textBlockType && $navType) {
            $layoutId = (string) Str::uuid();
            DB::table('page_layouts')->insert([
                'id'            => $layoutId,
                'page_id'       => $footerPage->id,
                'label'         => null,
                'display'       => 'grid',
                'columns'       => 2,
                'layout_config' => json_encode([
                    'grid_template_columns' => '1fr auto',
                    'gap'                   => '1rem',
                    'align_items'           => 'center',
                ]),
                'sort_order'    => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            DB::table('page_widgets')->insert([
                'id'             => (string) Str::uuid(),
                'page_id'        => $footerPage->id,
                'layout_id'      => $layoutId,
                'column_index'   => 0,
                'widget_type_id' => $textBlockType->id,
                'label'          => null,
                'config'         => json_encode([
                    'content' => '<p>&copy; 2026 Your Organization. All rights reserved.</p>',
                ]),
                'query_config'   => json_encode([]),
                'style_config'   => json_encode([]),
                'sort_order'     => 0,
                'is_active'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);

            DB::table('page_widgets')->insert([
                'id'             => (string) Str::uuid(),
                'page_id'        => $footerPage->id,
                'layout_id'      => $layoutId,
                'column_index'   => 1,
                'widget_type_id' => $navType->id,
                'label'          => null,
                'config'         => json_encode(['nav_handle' => 'footer']),
                'query_config'   => json_encode([]),
                'style_config'   => json_encode([]),
                'sort_order'     => 0,
                'is_active'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }
    }

    public function down(): void
    {
        // One-way migration — beta system, no rollback.
    }

    private function ensureWidgetType(string $handle, $now): ?object
    {
        $existing = DB::table('widget_types')->where('handle', $handle)->first();
        if ($existing) {
            return $existing;
        }

        $defaults = [
            'logo' => [
                'label'              => 'Logo',
                'description'        => 'Site logo image with optional text and link target.',
                'category'           => json_encode(['layout']),
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => json_encode([]),
                'assets'             => json_encode(['scss' => ['resources/scss/widgets/_logo.scss']]),
                'default_open'       => false,
                'full_width'         => false,
                'config_schema'      => json_encode([
                    ['key' => 'logo',     'type' => 'image', 'label' => 'Logo image',       'group' => 'content'],
                    ['key' => 'text',     'type' => 'text',  'label' => 'Text beside logo', 'group' => 'content'],
                    ['key' => 'link_url', 'type' => 'text',  'label' => 'Link URL',          'default' => '/', 'group' => 'content', 'subtype' => 'url'],
                ]),
                'template'           => "@include('widgets.logo')",
            ],
            'nav' => [
                'label'              => 'Navigation',
                'description'        => 'Navigation menu rendered from a NavigationMenu by handle.',
                'category'           => json_encode(['layout']),
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => json_encode([]),
                'assets'             => json_encode(['libs' => []]),
                'default_open'       => false,
                'full_width'         => false,
                'config_schema'      => json_encode([
                    ['key' => 'nav_handle', 'type' => 'text', 'label' => 'Navigation menu handle', 'default' => 'primary', 'group' => 'content'],
                ]),
                'template'           => "@include('widgets.nav')",
                'required_config'    => json_encode(['keys' => ['nav_handle'], 'message' => 'Enter a navigation menu handle (e.g. primary).']),
            ],
        ];

        $row = array_merge([
            'id'         => (string) Str::uuid(),
            'handle'     => $handle,
            'created_at' => $now,
            'updated_at' => $now,
        ], $defaults[$handle]);

        DB::table('widget_types')->insert($row);

        return DB::table('widget_types')->where('handle', $handle)->first();
    }
};
