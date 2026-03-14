<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            // Organizations (foundations, corporations) can be donors independent
            // of any individual contact. See ADR 014.
            // contact_id and organization_id are both nullable — exactly one must
            // be set. Enforced at the application layer.
            $table->foreignUuid('organization_id')
                ->nullable()
                ->after('contact_id')
                ->constrained('organizations')
                ->nullOnDelete();

            $table->uuid('contact_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
            $table->uuid('contact_id')->nullable(false)->change();
        });
    }
};
