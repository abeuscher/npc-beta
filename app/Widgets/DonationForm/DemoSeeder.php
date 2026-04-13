<?php

namespace App\Widgets\DonationForm;

use App\Models\Campaign;
use App\Models\Fund;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        Fund::updateOrCreate(
            ['code' => 'demo-fund'],
            [
                'name'             => 'General Operating Fund',
                'description'      => 'Unrestricted support for day-to-day programs.',
                'is_active'        => true,
                'restriction_type' => 'unrestricted',
                'is_archived'      => false,
            ]
        );

        Campaign::updateOrCreate(
            ['name' => 'Spring Annual Appeal'],
            [
                'description' => 'Demo campaign for widget previews.',
                'goal_amount' => 25000,
                'starts_on'   => now()->startOfMonth(),
                'ends_on'     => now()->addMonths(2)->endOfMonth(),
                'is_active'   => true,
            ]
        );
    }
}
