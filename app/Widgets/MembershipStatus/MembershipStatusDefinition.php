<?php

namespace App\Widgets\MembershipStatus;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Source;

class MembershipStatusDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'membership_status';
    }

    public function label(): string
    {
        return 'Membership Status';
    }

    public function description(): string
    {
        return 'Displays the contact\'s active membership tier, status, and key dates.';
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
                'membership_id',
                'membership_tier_name',
                'membership_billing_interval',
                'membership_status',
                'membership_starts_on',
                'membership_expires_on',
                'membership_amount_paid',
            ],
            filters: [],
            model: 'membership',
            cardinality: DataContract::CARDINALITY_ONE,
            requiredPermission: 'view_membership',
        );
    }
}
