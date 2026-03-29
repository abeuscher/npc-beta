<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE media ALTER COLUMN model_id TYPE varchar(36)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE media ALTER COLUMN model_id TYPE bigint USING model_id::bigint');
    }
};
