<?php

namespace App\Widgets\RecordDetailPlaceholder;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Source;

class RecordDetailPlaceholderDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'record_detail_placeholder';
    }

    public function label(): string
    {
        return 'Record Detail Placeholder';
    }

    public function description(): string
    {
        return 'Placeholder widget that proves the record-detail sidebar pipeline is wired end-to-end (Phase 5b).';
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
        return [Source::HUMAN];
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
            source: DataContract::SOURCE_RECORD_CONTEXT,
        );
    }
}
