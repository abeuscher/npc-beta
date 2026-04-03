<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop string default, convert values to JSON arrays, change column type, set new default.
        DB::statement("ALTER TABLE widget_types ALTER COLUMN category DROP DEFAULT");
        DB::statement("UPDATE widget_types SET category = jsonb_build_array(category)");
        DB::statement("ALTER TABLE widget_types ALTER COLUMN category TYPE jsonb USING category::jsonb");
        DB::statement("ALTER TABLE widget_types ALTER COLUMN category SET DEFAULT '[\"content\"]'::jsonb");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE widget_types ALTER COLUMN category TYPE varchar(255) USING category->>0");
        DB::statement("ALTER TABLE widget_types ALTER COLUMN category SET DEFAULT 'content'");
    }
};
