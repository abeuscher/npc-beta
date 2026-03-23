<?php

namespace Database\Seeders;

use App\Models\MembershipTier;
use Illuminate\Database\Seeder;

class MembershipTierSeeder extends Seeder
{
    public function run(): void
    {
        if (MembershipTier::exists()) {
            return;
        }

        MembershipTier::create([
            'name'                => 'Standard',
            'billing_interval'    => 'annual',
            'default_price'       => null,
            'renewal_notice_days' => 30,
            'is_active'           => true,
            'sort_order'          => 0,
        ]);
    }
}
