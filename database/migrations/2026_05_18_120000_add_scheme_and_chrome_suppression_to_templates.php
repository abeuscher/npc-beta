<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Theme/Template Re-Taxonomy arc, session 301. Per-template *structural*
     * deviation returns the coherent way: the template SELECTS a vetted
     * content scheme (never edits tokens) and may suppress chrome.
     *
     * Concrete-values rule: every column carries a concrete non-null default,
     * so existing rows and a fresh, unconfigured install are sane with no
     * backfill — `scheme='default'` is the empty delta (byte-identical to the
     * 297 token defaults), and chrome is suppressed only when explicitly
     * checked. A null header_page_id/footer_page_id keeps meaning "inherit
     * the theme header/footer"; no_header/no_footer is the distinct
     * "suppress entirely" state (wins even when a page is set).
     */
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('scheme')->default('default')->after('custom_scss');
            $table->boolean('no_header')->default(false)->after('header_page_id');
            $table->boolean('no_footer')->default(false)->after('footer_page_id');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['scheme', 'no_header', 'no_footer']);
        });
    }
};
