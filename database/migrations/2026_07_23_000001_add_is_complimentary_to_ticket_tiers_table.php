<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_tiers', function (Blueprint $table) {
            // Display-only "Complimentary" label for the admin repeater + public
            // picker (session 374 / C3c). Routing stays price-driven: a tier is
            // free because price = 0, never because this flag is set.
            $table->boolean('is_complimentary')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_tiers', function (Blueprint $table) {
            $table->dropColumn('is_complimentary');
        });
    }
};
