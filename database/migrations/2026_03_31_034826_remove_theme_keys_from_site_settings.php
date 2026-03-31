<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $keys = [
        'public_primary_color',
        'public_heading_font',
        'public_body_font',
        'header_bg_color',
        'footer_bg_color',
        'nav_link_color',
        'nav_hover_color',
        'nav_active_color',
        'theme_scss',
    ];

    public function up(): void
    {
        DB::table('site_settings')->whereIn('key', $this->keys)->delete();
    }

    public function down(): void
    {
        // Values have been migrated to the templates table — no reversal needed.
    }
};
