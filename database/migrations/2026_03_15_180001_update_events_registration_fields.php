<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('external_registration_url')->nullable()->after('status');
            $table->string('registration_mode')->default('open')->after('registration_open');
            $table->boolean('auto_create_contacts')->default(true)->after('registration_mode');
            $table->boolean('mailing_list_opt_in_enabled')->default(false)->after('auto_create_contacts');
            $table->dropColumn('registration_open');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('registration_open')->default(true);
            $table->dropColumn([
                'external_registration_url',
                'registration_mode',
                'auto_create_contacts',
                'mailing_list_opt_in_enabled',
            ]);
        });
    }
};
