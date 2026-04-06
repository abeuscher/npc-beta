<?php

use App\Models\WidgetType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Group + subtype assignments per widget handle.
     * Each key maps to [group, subtype (or null)].
     */
    private function fieldMap(): array
    {
        return [
            // ── bar_chart ──
            'bar_chart' => [
                'heading'           => ['content', 'title'],
                'collection_handle' => ['content', null],
                'x_field'           => ['content', null],
                'y_field'           => ['content', null],
                'x_label'           => ['content', null],
                'y_label'           => ['content', null],
                'bar_color'         => ['appearance', null],
            ],

            // ── blog_listing ──
            'blog_listing' => [
                'heading'          => ['content', 'title'],
                'content_template' => ['content', null],
                'columns'          => ['appearance', null],
                'items_per_page'   => ['content', null],
                'show_search'      => ['appearance', null],
                'sort_default'     => ['content', null],
                'effect'           => ['appearance', null],
                'background_color' => ['appearance', null],
                'text_color'       => ['appearance', null],
            ],

            // ── board_members ──
            'board_members' => [
                'heading'              => ['content', 'title'],
                'collection_handle'    => ['content', null],
                'image_field'          => ['content', null],
                'name_field'           => ['content', null],
                'title_field'          => ['content', null],
                'department_field'     => ['content', null],
                'description_field'    => ['content', null],
                'linkedin_field'       => ['content', null],
                'github_field'         => ['content', null],
                'extra_url_field'      => ['content', null],
                'extra_url_label_field'=> ['content', null],
                'image_shape'          => ['appearance', null],
                'background_color'     => ['appearance', null],
                'pane_color'           => ['appearance', null],
                'border_color'         => ['appearance', null],
                'items_per_row'        => ['appearance', null],
                'row_alignment'        => ['appearance', null],
                'image_aspect_ratio'   => ['appearance', null],
                'border_radius'        => ['appearance', null],
            ],

            // ── carousel ──
            'carousel' => [
                'collection_handle' => ['content', null],
                'image_field'       => ['content', null],
                'caption_template'  => ['content', null],
                'object_fit'        => ['appearance', null],
                'autoplay'          => ['appearance', null],
                'interval'          => ['appearance', null],
                'loop'              => ['appearance', null],
                'pagination'        => ['appearance', null],
                'navigation'        => ['appearance', null],
                'slides_per_view'   => ['appearance', null],
                'effect'            => ['appearance', null],
                'speed'             => ['appearance', null],
                'link_color'        => ['appearance', null],
                'text_color'        => ['appearance', null],
            ],

            // ── column_widget ──
            'column_widget' => [
                'num_columns'           => ['content', null],
                'grid_template_columns' => ['appearance', null],
            ],

            // ── donation_form ──
            'donation_form' => [
                'heading'      => ['content', 'title'],
                'amounts'      => ['content', null],
                'show_monthly' => ['appearance', null],
                'show_annual'  => ['appearance', null],
                'success_page' => ['content', 'url'],
            ],

            // ── event_calendar ──
            'event_calendar' => [
                'heading'      => ['content', 'title'],
                'default_view' => ['appearance', null],
            ],

            // ── event_description ──
            'event_description' => [
                'event_slug' => ['content', null],
            ],

            // ── event_registration ──
            'event_registration' => [
                'event_slug' => ['content', null],
            ],

            // ── events_listing ──
            'events_listing' => [
                'heading'          => ['content', 'title'],
                'content_template' => ['content', null],
                'columns'          => ['appearance', null],
                'items_per_page'   => ['content', null],
                'show_search'      => ['appearance', null],
                'sort_default'     => ['content', null],
                'effect'           => ['appearance', null],
                'background_color' => ['appearance', null],
                'text_color'       => ['appearance', null],
            ],

            // ── hero ──
            'hero' => [
                'content'          => ['content', null],
                'background_color' => ['appearance', null],
                'text_color'       => ['appearance', null],
                'background_image' => ['content', null],
                'background_video' => ['content', null],
                'text_position'    => ['appearance', null],
                'ctas'             => ['content', null],
                'fullscreen'       => ['appearance', null],
                'scroll_indicator' => ['appearance', null],
                'full_width'       => ['appearance', null],
                'overlap_nav'      => ['appearance', null],
                'overlay_opacity'  => ['appearance', null],
                'nav_link_color'   => ['appearance', null],
                'nav_hover_color'  => ['appearance', null],
                'min_height'       => ['appearance', null],
            ],

            // ── image ──
            'image' => [
                'image'      => ['content', null],
                'alt_text'   => ['content', null],
                'object_fit' => ['appearance', null],
                'link_url'   => ['content', null],
            ],

            // ── logo_garden ──
            'logo_garden' => [
                'collection_handle' => ['content', null],
                'image_field'       => ['content', null],
                'display_mode'      => ['appearance', null],
                'show_name'         => ['appearance', null],
                'name_field'        => ['content', null],
                'background_color'  => ['appearance', null],
                'logos_per_row'     => ['appearance', null],
                'logo_max_height'   => ['appearance', null],
                'carousel_duration' => ['appearance', null],
                'flip_duration'     => ['appearance', null],
            ],

            // ── map_embed ──
            'map_embed' => [
                'heading'      => ['content', 'title'],
                'map_input'    => ['content', null],
                'aspect_ratio' => ['appearance', null],
                'min_height'   => ['appearance', null],
                'max_height'   => ['appearance', null],
                'full_width'   => ['appearance', null],
            ],

            // ── product_carousel ──
            'product_carousel' => [
                'heading'          => ['content', 'title'],
                'limit'            => ['content', null],
                'navigation'       => ['appearance', null],
                'pagination'       => ['appearance', null],
                'autoplay'         => ['appearance', null],
                'interval'         => ['appearance', null],
                'background_color' => ['appearance', null],
                'text_color'       => ['appearance', null],
                'success_page'     => ['content', null],
                'full_width'       => ['appearance', null],
            ],

            // ── product_display ──
            'product_display' => [
                'product_slug' => ['content', null],
            ],

            // ── site_footer ──
            'site_footer' => [
                'nav_handle'        => ['content', null],
                'show_theme_toggle' => ['appearance', null],
                'copyright_text'    => ['content', null],
            ],

            // ── site_header ──
            'site_header' => [
                'logo'           => ['content', null],
                'nav_handle'     => ['content', null],
                'header_content' => ['content', null],
            ],

            // ── social_sharing ──
            'social_sharing' => [
                'heading'           => ['content', 'title'],
                'platforms'         => ['content', null],
                'alignment'         => ['appearance', null],
                'icon_size'         => ['appearance', null],
                'background_color'  => ['appearance', null],
                'text_color'        => ['appearance', null],
                'full_width'        => ['appearance', null],
                'mastodon_instance' => ['content', 'url'],
            ],

            // ── text_block ──
            'text_block' => [
                'content' => ['content', null],
            ],

            // ── three_buckets ──
            'three_buckets' => [
                'heading_1'         => ['content', 'title'],
                'body_1'            => ['content', null],
                'ctas_1'            => ['content', null],
                'heading_2'         => ['content', 'title'],
                'body_2'            => ['content', null],
                'ctas_2'            => ['content', null],
                'heading_3'         => ['content', 'title'],
                'body_3'            => ['content', null],
                'ctas_3'            => ['content', null],
                'heading_alignment' => ['appearance', null],
                'body_alignment'    => ['appearance', null],
                'button_alignment'  => ['appearance', null],
                'gap'               => ['appearance', null],
            ],

            // ── video_embed ──
            'video_embed' => [
                'video_url'       => ['content', 'url'],
                'show_related'    => ['appearance', null],
                'modest_branding' => ['appearance', null],
                'show_controls'   => ['appearance', null],
            ],

            // ── web_form ──
            'web_form' => [
                'form_handle' => ['content', null],
            ],
        ];
    }

    public function up(): void
    {
        $map = $this->fieldMap();

        foreach (WidgetType::all() as $widgetType) {
            $handle = $widgetType->handle;
            $fieldAssignments = $map[$handle] ?? null;

            $schema = $widgetType->config_schema ?? [];
            $changed = false;

            foreach ($schema as $i => $field) {
                $key = $field['key'] ?? null;
                if (! $key) {
                    continue;
                }

                // Assign group
                if ($fieldAssignments && isset($fieldAssignments[$key])) {
                    [$group, $subtype] = $fieldAssignments[$key];
                    $schema[$i]['group'] = $group;
                    if ($subtype !== null) {
                        $schema[$i]['subtype'] = $subtype;
                    } else {
                        unset($schema[$i]['subtype']);
                    }
                    $changed = true;
                } else {
                    // Default: content for data fields, appearance otherwise
                    if (! isset($schema[$i]['group']) || ! in_array($schema[$i]['group'], ['content', 'appearance'])) {
                        $schema[$i]['group'] = 'content';
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                $widgetType->config_schema = array_values($schema);
                $widgetType->save();
            }
        }
    }

    public function down(): void
    {
        foreach (WidgetType::all() as $widgetType) {
            $schema = $widgetType->config_schema ?? [];
            $changed = false;

            foreach ($schema as $i => $field) {
                if (isset($schema[$i]['group'])) {
                    unset($schema[$i]['group']);
                    $changed = true;
                }
                if (isset($schema[$i]['subtype'])) {
                    unset($schema[$i]['subtype']);
                    $changed = true;
                }
            }

            if ($changed) {
                $widgetType->config_schema = array_values($schema);
                $widgetType->save();
            }
        }
    }
};
