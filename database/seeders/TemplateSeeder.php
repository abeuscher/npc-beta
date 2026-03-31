<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Template;
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
                'heading_font'     => null,
                'body_font'        => null,
                'header_bg_color'  => '#ffffff',
                'footer_bg_color'  => '#ffffff',
                'nav_link_color'   => '#373c44',
                'nav_hover_color'  => '#0172ad',
                'nav_active_color' => '#0172ad',
                'custom_scss'      => $this->loadCustomScss(),
                'header_page_id'   => Page::where('slug', '_header')->value('id'),
                'footer_page_id'   => Page::where('slug', '_footer')->value('id'),
            ]
        );

        // Ensure is_default is true even if the record already existed.
        if (! $default->is_default) {
            $default->update(['is_default' => true]);
        }

        // ── Content templates ────────────────────────────────────────────────

        Template::firstOrCreate(
            ['name' => 'Contact Page', 'type' => 'content'],
            [
                'description' => 'Contact page with a heading and web form.',
                'definition'  => [
                    ['handle' => 'text_block', 'config' => ['heading' => 'Contact Us'], 'sort_order' => 1],
                    ['handle' => 'web_form',   'config' => [],                          'sort_order' => 2],
                ],
            ]
        );

        Template::firstOrCreate(
            ['name' => 'About Page', 'type' => 'content'],
            [
                'description' => 'About page with heading, image, and body text.',
                'definition'  => [
                    ['handle' => 'text_block', 'config' => ['heading' => 'About Us'], 'sort_order' => 1],
                    ['handle' => 'image',      'config' => [],                         'sort_order' => 2],
                    ['handle' => 'text_block', 'config' => [],                         'sort_order' => 3],
                ],
            ]
        );

        Template::firstOrCreate(
            ['name' => 'Event Landing Page', 'type' => 'content'],
            [
                'description' => 'Standard event landing page with description and registration widgets.',
                'definition'  => [
                    ['handle' => 'event_description',  'config' => [], 'sort_order' => 1],
                    ['handle' => 'event_registration', 'config' => [], 'sort_order' => 2],
                ],
            ]
        );

        Template::firstOrCreate(
            ['name' => 'Blog Post', 'type' => 'content'],
            [
                'description' => 'Blog post with a single text block.',
                'definition'  => [
                    ['handle' => 'text_block', 'config' => [], 'sort_order' => 1],
                ],
            ]
        );

        Template::firstOrCreate(
            ['name' => 'Blank', 'type' => 'content'],
            [
                'description' => 'Empty page — no widgets.',
                'definition'  => [],
            ]
        );
    }

    private function loadCustomScss(): ?string
    {
        $path = resource_path('scss/_custom.scss');

        if (! file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content !== '' ? $content : null;
    }
}
