<?php

namespace App\Filament\Resources\ContentCollectionResource\Pages;

use App\Filament\Resources\ContentCollectionResource;
use App\Models\CmsTag;
use App\Models\Collection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;

class ManageContentCollectionItems extends ManageRelatedRecords
{
    protected static string $resource = ContentCollectionResource::class;

    protected static string $relationship = 'collectionItems';

    protected static ?string $navigationLabel = null;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->getOwnerRecord()->name;
    }

    public function getBreadcrumb(): string
    {
        return $this->getOwnerRecord()->name;
    }

    public function form(Form $form): Form
    {
        /** @var Collection $collection */
        $collection = $this->getOwnerRecord();

        return $form->schema([
            Forms\Components\Toggle::make('is_published')
                ->label('Published')
                ->default(false),

            Forms\Components\TextInput::make('sort_order')
                ->label('Sort Order')
                ->numeric()
                ->default(0),

            Forms\Components\Section::make('Content')
                ->schema($collection->getFormSchema())
                ->columnSpanFull(),

            Forms\Components\Select::make('cmsTags')
                ->label('Tags')
                ->multiple()
                ->relationship('cmsTags', 'name')
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->unique(CmsTag::class, 'name'),
                ])
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var Collection $collection */
        $collection = $this->getOwnerRecord();

        $firstTextField = collect($collection->fields ?? [])
            ->first(fn ($f) => in_array($f['type'] ?? '', ['text', 'textarea'], true));

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
                    ->getStateUsing(function ($record) use ($firstTextField): string {
                        if ($firstTextField) {
                            $value = $record->data[$firstTextField['key']] ?? null;
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
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
