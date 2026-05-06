<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contact_id')
                ->constrained('contacts')
                ->cascadeOnDelete();
            $table->foreignUuid('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->text('role')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('contact_id');
            $table->index('organization_id');
        });

        DB::statement(
            'CREATE UNIQUE INDEX affiliations_one_primary_per_contact '
            . 'ON affiliations (contact_id) WHERE is_primary = true'
        );

        Schema::table('organizations', function (Blueprint $table) {
            $table->text('industry')->nullable()->after('type');
            $table->text('ein')->nullable()->after('industry');
        });

        DB::statement(
            'INSERT INTO affiliations '
            . '(id, contact_id, organization_id, role, is_primary, created_at, updated_at) '
            . 'SELECT gen_random_uuid(), id, organization_id, NULL, true, NOW(), NOW() '
            . 'FROM contacts WHERE organization_id IS NOT NULL'
        );

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['industry', 'ein']);
        });

        DB::statement('DROP INDEX IF EXISTS affiliations_one_primary_per_contact');

        Schema::dropIfExists('affiliations');
    }
};
