<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('conjunction')->default('and');
            $table->text('raw_where')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('mailing_list_filters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('mailing_list_id');
            $table->foreign('mailing_list_id')->references('id')->on('mailing_lists')->cascadeOnDelete();
            $table->string('field');
            $table->string('operator');
            $table->string('value')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_list_filters');
        Schema::dropIfExists('mailing_lists');
    }
};
