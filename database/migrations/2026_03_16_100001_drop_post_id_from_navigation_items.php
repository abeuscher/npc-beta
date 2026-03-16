<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->dropColumn('post_id');
        });
    }

    public function down(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->uuid('post_id')->nullable()->after('page_id');
            $table->foreign('post_id')->references('id')->on('posts')->nullOnDelete();
        });
    }
};
