<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->foreignUuid('organization_id')
                ->nullable()
                ->after('contact_id')
                ->constrained('organizations')
                ->nullOnDelete();
            $table->index('organization_id');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->foreignUuid('organization_id')
                ->nullable()
                ->after('contact_id')
                ->constrained('organizations')
                ->nullOnDelete();
            $table->index('organization_id');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreignUuid('organization_id')
                ->nullable()
                ->after('contact_id')
                ->constrained('organizations')
                ->nullOnDelete();
            $table->index('organization_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignUuid('sponsor_organization_id')
                ->nullable()
                ->after('author_id')
                ->constrained('organizations')
                ->nullOnDelete();
            $table->index('sponsor_organization_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUuid('organization_id')
                ->nullable()
                ->after('contact_id')
                ->constrained('organizations')
                ->nullOnDelete();
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['sponsor_organization_id']);
            $table->dropIndex(['sponsor_organization_id']);
            $table->dropColumn('sponsor_organization_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
