<?php

namespace App\Filament\Resources\CollectionResource\RelationManagers;

use App\Forms\Components\TagSelect;
use App\Models\Collection;
use App\Models\CollectionItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CollectionItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'collectionItems';

    protected static ?string $title = 'Items';

    public function isReadOnly(): bool
    {
        // System collections do not use collection_items; hide the manager for them.
        return $this->getOwnerRecord()->isSystemCollection();
    }

    public function form(Form $form): Form
    {
        /** @var Collection $collection */
        $collection = $this->getOwnerRecord();

        return $form->schema([
            Forms\Components\TextInput::make('sort_order')
                ->label('Sort Order')
                ->numeric()
                ->default(0),

            Forms\Components\Toggle::make('is_published')
                ->label('Published')
                ->default(false),

            Forms\Components\Section::make('Content')
                ->schema($collection->getFormSchema())
                ->columnSpanFull(),

            TagSelect::make('collection')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('summary')
                    ->label('Summary')
                    ->getStateUsing(function (CollectionItem $record): string {
                        $collection = $this->getOwnerRecord();

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
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->withoutGlobalScopes([SoftDeletingScope::class]))
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
