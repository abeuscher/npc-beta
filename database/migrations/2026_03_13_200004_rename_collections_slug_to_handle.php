<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Collections are not routable — they are data sources accessed by handle in the
     * widget system. Renaming 'slug' to 'handle' removes the URL implication.
     */
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->renameColumn('slug', 'handle');
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->renameColumn('handle', 'slug');
        });
    }
};
