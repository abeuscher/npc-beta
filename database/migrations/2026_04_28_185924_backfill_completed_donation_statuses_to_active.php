<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('donations')
            ->where('status', 'completed')
            ->update(['status' => 'active']);
    }

    public function down(): void
    {
        // No-op: the prior value was non-canonical and cannot be safely restored.
    }
};
