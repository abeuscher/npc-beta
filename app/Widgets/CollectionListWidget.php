<?php

namespace App\Widgets;

use App\Models\Collection;
use App\Services\WidgetDataResolver;
use Filament\Forms;

class CollectionListWidget extends Widget
{
    public static function handle(): string
    {
        return 'collection_list';
    }

    public static function label(): string
    {
        return 'Collection List';
    }

    public static function configSchema(): array
    {
        return [
            Forms\Components\Select::make('config.collection_handle')
                ->label('Collection')
                ->options(fn () => Collection::public()->pluck('name', 'handle'))
                ->required(),

            Forms\Components\TextInput::make('config.heading')
                ->label('Heading')
                ->maxLength(255),

            Forms\Components\TextInput::make('config.limit')
                ->label('Limit')
                ->numeric()
                ->helperText('Leave blank to show all items.'),
        ];
    }

    public function resolveData(array $config): array
    {
        $handle = $config['collection_handle'] ?? null;

        if (! $handle) {
            return [];
        }

        $limit = isset($config['limit']) && $config['limit'] !== '' && $config['limit'] !== null
            ? (int) $config['limit']
            : null;

        return WidgetDataResolver::resolve($handle, limit: $limit);
    }

    public function view(): string
    {
        return 'widgets.collection-list';
    }
}
