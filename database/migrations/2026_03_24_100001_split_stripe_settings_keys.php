<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename stripe_api_key → stripe_secret_key and mark it encrypted
        DB::table('site_settings')
            ->where('key', 'stripe_api_key')
            ->update(['key' => 'stripe_secret_key', 'type' => 'encrypted']);

        // Add stripe_publishable_key (plaintext)
        if (! DB::table('site_settings')->where('key', 'stripe_publishable_key')->exists()) {
            DB::table('site_settings')->insert([
                'key'        => 'stripe_publishable_key',
                'value'      => '',
                'group'      => 'finance',
                'type'       => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Add stripe_webhook_secret (encrypted)
        if (! DB::table('site_settings')->where('key', 'stripe_webhook_secret')->exists()) {
            DB::table('site_settings')->insert([
                'key'        => 'stripe_webhook_secret',
                'value'      => '',
                'group'      => 'finance',
                'type'       => 'encrypted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Ensure quickbooks_api_key is marked encrypted
        DB::table('site_settings')
            ->where('key', 'quickbooks_api_key')
            ->update(['type' => 'encrypted', 'group' => 'finance']);
    }

    public function down(): void
    {
        DB::table('site_settings')
            ->where('key', 'stripe_secret_key')
            ->update(['key' => 'stripe_api_key', 'type' => 'string']);

        DB::table('site_settings')->where('key', 'stripe_publishable_key')->delete();
        DB::table('site_settings')->where('key', 'stripe_webhook_secret')->delete();

        DB::table('site_settings')
            ->where('key', 'quickbooks_api_key')
            ->update(['type' => 'string', 'group' => 'general']);
    }
};
