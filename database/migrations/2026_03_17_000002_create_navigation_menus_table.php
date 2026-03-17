<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create navigation_menus table
        Schema::create('navigation_menus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label');
            $table->string('handle')->unique();
            $table->timestamps();
        });

        // 2. Insert one row per distinct menu_handle in navigation_items
        $handles = DB::table('navigation_items')
            ->select('menu_handle')
            ->distinct()
            ->whereNotNull('menu_handle')
            ->pluck('menu_handle');

        $now = now();

        foreach ($handles as $handle) {
            DB::table('navigation_menus')->insert([
                'id'         => (string) Str::uuid(),
                'label'      => $handle,
                'handle'     => $handle,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 3. Add navigation_menu_id (nullable) to navigation_items
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->uuid('navigation_menu_id')->nullable()->after('menu_handle');
        });

        // 4. Populate navigation_menu_id by joining on menu_handle
        DB::statement('
            UPDATE navigation_items ni
            SET navigation_menu_id = (
                SELECT nm.id FROM navigation_menus nm WHERE nm.handle = ni.menu_handle LIMIT 1
            )
        ');

        // 5. Make navigation_menu_id non-nullable and add FK (cascade delete)
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->uuid('navigation_menu_id')->nullable(false)->change();
            $table->foreign('navigation_menu_id')
                ->references('id')
                ->on('navigation_menus')
                ->cascadeOnDelete();
        });

        // 6. Drop menu_handle from navigation_items
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->dropColumn('menu_handle');
        });
    }

    public function down(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->dropForeign(['navigation_menu_id']);
            $table->dropColumn('navigation_menu_id');
            $table->string('menu_handle')->nullable()->default('primary');
        });

        Schema::dropIfExists('navigation_menus');
    }
};
