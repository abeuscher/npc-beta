<?php

namespace App\Widgets\Memos;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\ContentType;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\QuerySettings;
use App\WidgetPrimitive\Source;

class MemosDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'memos';
    }

    public function label(): string
    {
        return 'Memos';
    }

    public function description(): string
    {
        return 'Admin-only notices and announcements, posted via Collection Manager → Memos.';
    }

    public function category(): array
    {
        return ['dashboard'];
    }

    public function allowedSlots(): array
    {
        return ['dashboard_grid'];
    }

    public function acceptedSources(): array
    {
        return [Source::HUMAN];
    }

    public function schema(): array
    {
        return [
            ['key' => 'limit', 'type' => 'number', 'label' => 'Memos to show', 'default' => 5, 'group' => 'content'],
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
        $contentType = new ContentType(
            handle: 'memos.entry',
            fields: [
                ['key' => 'title',     'type' => 'text'],
                ['key' => 'body',      'type' => 'rich_text'],
                ['key' => 'posted_at', 'type' => 'date'],
            ],
            accepts: [Source::HUMAN],
        );

        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_WIDGET_CONTENT_TYPE,
            fields: ['title', 'body', 'posted_at'],
            filters: [
                'limit'    => (int) ($config['limit'] ?? 5),
                'order_by' => 'posted_at desc',
            ],
            resourceHandle: 'memos',
            contentType: $contentType,
            querySettings: $this->querySettings($config),
        );
    }

    public function querySettings(array $config): ?QuerySettings
    {
        return new QuerySettings(
            hasPanel: true,
            orderByOptions: QuerySettings::swctOrderByOptions(['title', 'posted_at']),
            supportsTags: true,
        );
    }
}
