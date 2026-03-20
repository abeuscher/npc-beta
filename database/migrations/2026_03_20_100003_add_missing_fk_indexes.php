<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add explicit indexes to all foreign-key columns that lacked them.
 *
 * PostgreSQL does not auto-create an index for FK constraints; this migration
 * closes that gap so JOIN and lookup queries on these columns are efficient.
 *
 * Excluded (already covered):
 *   - taggables: composite PK covers tag_id; uuidMorphs covers taggable_id/type
 *   - notes.notable_id/type: uuidMorphs() creates a composite index automatically
 *   - import_id_maps.import_source_id: covered by the unique constraint on (import_source_id, model_type, source_id)
 *   - contacts.email: already indexed in the create_contacts_table migration
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->index('organization_id');
            $table->index('household_id');
            $table->index('import_session_id');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->index('contact_id');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->index('author_id');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->index('author_id');
        });

        Schema::table('navigation_items', function (Blueprint $table) {
            $table->index('navigation_menu_id');
            $table->index('page_id');
            $table->index('parent_id');
        });

        Schema::table('donations', function (Blueprint $table) {
            $table->index('contact_id');
            $table->index('campaign_id');
            $table->index('fund_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index('donation_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index('landing_page_id');
        });

        Schema::table('mailing_list_filters', function (Blueprint $table) {
            $table->index('mailing_list_id');
        });

        Schema::table('import_sessions', function (Blueprint $table) {
            $table->index('import_source_id');
            $table->index('imported_by');
            $table->index('approved_by');
        });

        Schema::table('help_article_routes', function (Blueprint $table) {
            $table->index('help_article_id');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['household_id']);
            $table->dropIndex(['import_session_id']);
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex(['contact_id']);
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex(['author_id']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['author_id']);
        });

        Schema::table('navigation_items', function (Blueprint $table) {
            $table->dropIndex(['navigation_menu_id']);
            $table->dropIndex(['page_id']);
            $table->dropIndex(['parent_id']);
        });

        Schema::table('donations', function (Blueprint $table) {
            $table->dropIndex(['contact_id']);
            $table->dropIndex(['campaign_id']);
            $table->dropIndex(['fund_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['donation_id']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['landing_page_id']);
        });

        Schema::table('mailing_list_filters', function (Blueprint $table) {
            $table->dropIndex(['mailing_list_id']);
        });

        Schema::table('import_sessions', function (Blueprint $table) {
            $table->dropIndex(['import_source_id']);
            $table->dropIndex(['imported_by']);
            $table->dropIndex(['approved_by']);
        });

        Schema::table('help_article_routes', function (Blueprint $table) {
            $table->dropIndex(['help_article_id']);
        });
    }
};
