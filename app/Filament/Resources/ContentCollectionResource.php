<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContentCollectionResource\Pages;
use App\Models\Collection;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContentCollectionResource extends Resource
{
    protected static ?string $model = Collection::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationGroup = 'CMS';

    protected static ?string $navigationLabel = 'Collections';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'browse-collections';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('source_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'custom'     => 'Custom',
                        'blog_posts' => 'System',
                        'events'     => 'System',
                        default      => 'System',
                    })
                    ->colors([
                        'gray'    => fn ($state) => $state !== 'custom',
                        'success' => 'custom',
                    ]),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->getStateUsing(function (Collection $record): string {
                        return $record->isSystemCollection() ? '—' : (string) $record->collectionItems()->count();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('manage')
                    ->label('Manage Items')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Collection $record): string => static::getUrl('items', ['record' => $record]))
                    ->visible(fn (Collection $record): bool => ! $record->isSystemCollection()),
            ])
            ->bulkActions([])
            ->defaultSort('name')
            ->recordUrl(null);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContentCollections::route('/'),
            'items' => Pages\ManageContentCollectionItems::route('/{record}/items'),
        ];
    }
}
