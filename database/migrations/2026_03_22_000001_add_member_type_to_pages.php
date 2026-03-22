<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_type_check");
        DB::statement("ALTER TABLE pages ADD CONSTRAINT pages_type_check CHECK (type IN ('default', 'post', 'event', 'member'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_type_check");
        DB::statement("ALTER TABLE pages ADD CONSTRAINT pages_type_check CHECK (type IN ('default', 'post', 'event'))");
    }
};
