<?php

namespace App\Widgets\RecentNotes;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Source;

class RecentNotesDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'recent_notes';
    }

    public function label(): string
    {
        return 'Recent Notes';
    }

    public function description(): string
    {
        return 'Lists notes attached to the current contact, ordered by occurred_at descending.';
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
        return [
            ['key' => 'limit', 'type' => 'number', 'label' => 'Notes to show', 'default' => 5, 'group' => 'content'],
        ];
    }

    public function defaults(): array
    {
        return [
            'limit' => 5,
        ];
    }

    public function dataContract(array $config): ?DataContract
    {
        $limit = (int) ($config['limit'] ?? 5);
        if ($limit < 1) {
            $limit = 5;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SYSTEM_MODEL,
            fields: ['note_id', 'note_subject', 'note_body_excerpt', 'note_type', 'note_occurred_at', 'note_author_name'],
            filters: [
                'limit'     => $limit,
                'order_by'  => 'occurred_at',
                'direction' => 'desc',
            ],
            model: 'note',
        );
    }
}
