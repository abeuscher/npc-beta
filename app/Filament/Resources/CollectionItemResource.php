<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CollectionItemResource\Pages;
use App\Models\Collection;
use App\Models\CollectionItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CollectionItemResource extends Resource
{
    protected static ?string $model = CollectionItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Collections';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('sort_order')
                ->label('Sort Order')
                ->numeric()
                ->default(0),

            Forms\Components\Toggle::make('is_published')
                ->label('Published')
                ->default(false),

            Forms\Components\Section::make('Content')
                ->schema(fn (Forms\Form $form): array => static::getDynamicSchema($form))
                ->columnSpanFull(),
        ]);
    }

    /**
     * Resolves the parent Collection and delegates to Collection::getFormSchema()
     * to build the dynamic field list for this item's edit form.
     */
    protected static function getDynamicSchema(Forms\Form $form): array
    {
        // In the context of a relation manager, $form->getLivewire() is the relation manager.
        $livewire = $form->getLivewire();

        $collection = null;

        // When editing an existing item, load via the record relationship.
        if (method_exists($livewire, 'getOwnerRecord')) {
            $collection = $livewire->getOwnerRecord();
        } elseif (isset($livewire->record) && $livewire->record instanceof CollectionItem) {
            $collection = $livewire->record->collection;
        }

        if ($collection === null) {
            return [];
        }

        return $collection->getFormSchema();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('collection.name')
                    ->label('Collection')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('summary')
                    ->label('Summary')
                    ->getStateUsing(function (CollectionItem $record): string {
                        $collection = $record->collection;

                        if (! $collection) {
                            return substr($record->id, 0, 8);
                        }

                        // Find the first text or textarea field in the schema.
                        $firstField = collect($collection->fields ?? [])
                            ->first(fn ($f) => in_array($f['type'] ?? '', ['text', 'textarea'], true));

                        if ($firstField) {
                            $key   = $firstField['key'];
                            $value = $record->data[$key] ?? null;

                            return $value ? (string) $value : '(empty)';
                        }

                        return substr($record->id, 0, 8);
                    }),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('collection_id')
                    ->label('Collection')
                    ->options(fn () => Collection::orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCollectionItems::route('/'),
            'create' => Pages\CreateCollectionItem::route('/create'),
            'edit'   => Pages\EditCollectionItem::route('/{record}/edit'),
        ];
    }
}
