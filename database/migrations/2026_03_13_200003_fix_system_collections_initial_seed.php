<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Donations do not belong in the collections system — they are private financial
     * records with no meaningful public data shape. Remove the seeded system collection.
     *
     * Events are genuinely public. Flip is_public to true so they surface correctly
     * to the widget system via Collection::scopePublic().
     */
    public function up(): void
    {
        DB::table('collections')
            ->where('slug', 'donations')
            ->where('source_type', 'donations')
            ->delete();

        DB::table('collections')
            ->where('slug', 'events')
            ->where('source_type', 'events')
            ->update(['is_public' => true]);
    }

    public function down(): void
    {
        // Re-insert the donations collection if rolling back.
        DB::table('collections')->insert([
            'id'          => \Illuminate\Support\Str::uuid()->toString(),
            'name'        => 'Donations',
            'slug'        => 'donations',
            'description' => 'System collection — CMS-facing display config only. All financial records remain in the CRM.',
            'source_type' => 'donations',
            'fields'      => json_encode([
                ['key' => 'campaign_name', 'label' => 'Campaign Name',   'type' => 'text',   'required' => true,  'helpText' => '',                               'options' => []],
                ['key' => 'fund_name',     'label' => 'Fund Name',       'type' => 'text',   'required' => false, 'helpText' => '',                               'options' => []],
                ['key' => 'goal_amount',   'label' => 'Goal Amount',     'type' => 'number', 'required' => false, 'helpText' => 'Used for progress display only.', 'options' => []],
                ['key' => 'is_active',     'label' => 'Campaign Active', 'type' => 'toggle', 'required' => false, 'helpText' => '',                               'options' => []],
            ]),
            'is_public'   => false,
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::table('collections')
            ->where('slug', 'events')
            ->where('source_type', 'events')
            ->update(['is_public' => false]);
    }
};
