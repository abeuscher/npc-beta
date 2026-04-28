<?php

namespace App\Widgets\RecentDonations;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Source;

class RecentDonationsDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'recent_donations';
    }

    public function label(): string
    {
        return 'Recent Donations';
    }

    public function description(): string
    {
        return 'Lists the contact\'s recent donations (active, cancelled, past_due) ordered by start date descending.';
    }

    public function category(): array
    {
        return ['admin'];
    }

    public function allowedSlots(): array
    {
        return ['record_detail_sidebar'];
    }

    public function acceptedSources(): array
    {
        return [Source::HUMAN, Source::IMPORT];
    }

    public function schema(): array
    {
        return [];
    }

    public function defaults(): array
    {
        return [];
    }

    public function dataContract(array $config): ?DataContract
    {
        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SYSTEM_MODEL,
            fields: [
                'donation_id',
                'donation_amount',
                'donation_date',
                'donation_fund_name',
                'donation_type',
                'donation_status',
                'donation_origin',
            ],
            filters: [],
            model: 'donation',
            requiredPermission: 'view_donation',
        );
    }
}
