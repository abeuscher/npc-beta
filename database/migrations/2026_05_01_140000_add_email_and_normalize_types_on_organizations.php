<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
        });

        DB::statement("UPDATE organizations SET type = 'nonprofit' WHERE type = 'foundation'");
        DB::statement("UPDATE organizations SET type = 'for_profit' WHERE type = 'corporate'");
    }

    public function down(): void
    {
        DB::statement("UPDATE organizations SET type = 'foundation' WHERE type = 'nonprofit'");
        DB::statement("UPDATE organizations SET type = 'corporate' WHERE type = 'for_profit'");

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
