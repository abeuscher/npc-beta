<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('name', 'cms_editor')
            ->update(['name' => 'staff']);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'staff')
            ->update(['name' => 'cms_editor']);
    }
};
