<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // notes: superseded by the morphMany Note relationship (see NotesRelationManager).
            // email_secondary / phone_secondary: removed from the edit form; not reinstated.
            $table->dropColumn(['notes', 'email_secondary', 'phone_secondary']);
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('postal_code');
            $table->string('email_secondary')->nullable()->after('email');
            $table->string('phone_secondary')->nullable()->after('phone');
        });
    }
};
