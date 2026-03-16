<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_defs', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');            // 'contact', 'event', 'page'
            $table->string('handle');                // machine key, e.g. wild_apricot_id
            $table->string('label');                 // display name
            $table->string('field_type')->default('text'); // text, number, date, boolean, select
            $table->jsonb('options')->nullable();    // for select type: [{value, label}, ...]
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['model_type', 'handle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_defs');
    }
};
