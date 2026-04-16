<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Template;
use App\Models\WidgetType;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        // ── Default page template ────────────────────────────────────────────
        $default = Template::firstOrCreate(
            ['name' => 'Default', 'type' => 'page'],
            [
                'is_default'       => true,
                'description'      => 'Default site template — colors, fonts, header, and footer.',
                'primary_color'    => '#0172ad',
                'header_bg_color'  => '#ffffff',
                'footer_bg_color'  => '#ffffff',
                'nav_link_color'   => '#373c44',
                'nav_hover_color'  => '#0172ad',
                'nav_active_color' => '#0172ad',
                'custom_scss'      => null,
                'header_page_id'   => Page::where('slug', '_header')->value('id'),
                'footer_page_id'   => Page::where('slug', '_footer')->value('id'),
            ]
        );

        if (! $default->is_default) {
            $default->update(['is_default' => true]);
        }

        // ── Content templates ────────────────────────────────────────────────
        // Each content template is a stack of polymorphic PageWidget rows
        // owned by the template. Seeded only on first creation — existing
        // templates keep whatever stack they have.

        $this->seedContentTemplate('Contact Page', 'Contact page with a heading and web form.', [
            ['handle' => 'text_block', 'config' => ['heading' => 'Contact Us']],
            ['handle' => 'web_form',   'config' => []],
        ]);

        $this->seedContentTemplate('About Page', 'About page with heading, image, and body text.', [
            ['handle' => 'text_block', 'config' => ['heading' => 'About Us']],
            ['handle' => 'image',      'config' => []],
            ['handle' => 'text_block', 'config' => []],
        ]);

        $this->seedContentTemplate('Event Landing Page', 'Standard event landing page with description and registration widgets.', [
            ['handle' => 'event_description',  'config' => []],
            ['handle' => 'event_registration', 'config' => []],
        ]);

        $blogPost = $this->seedContentTemplate('Blog Post', 'Standard blog post layout with content and a prev/next pager.', [
            ['handle' => 'text_block', 'config' => []],
            ['handle' => 'blog_pager', 'config' => []],
        ]);

        $this->seedContentTemplate('Blank', 'Empty page — no widgets.', []);

        // Wire per-type default settings on fresh install. firstOrCreate so we
        // never overwrite an admin's later choice.
        $this->seedDefaultIfUnset('default_content_template_post', $blogPost->id);
    }

    private function seedDefaultIfUnset(string $key, string $value): void
    {
        if (SiteSetting::where('key', $key)->exists()) {
            return;
        }

        SiteSetting::set($key, $value);
    }

    /**
     * @param  array<int, array{handle: string, config?: array<string, mixed>}>  $widgets
     */
    private function seedContentTemplate(string $name, string $description, array $widgets): Template
    {
        $template = Template::firstOrCreate(
            ['name' => $name, 'type' => 'content'],
            ['description' => $description],
        );

        if ($template->wasRecentlyCreated) {
            foreach ($widgets as $index => $spec) {
                $widgetType = WidgetType::where('handle', $spec['handle'])->first();
                if (! $widgetType) {
                    continue;
                }

                $template->widgets()->create([
                    'widget_type_id'    => $widgetType->id,
                    'label'             => null,
                    'config'            => $spec['config'] ?? [],
                    'query_config'      => [],
                    'appearance_config' => \App\Models\PageWidget::resolveAppearance([], $spec['handle']),
                    'sort_order'        => $index + 1,
                    'is_active'         => true,
                ]);
            }
        }

        return $template;
    }
}
