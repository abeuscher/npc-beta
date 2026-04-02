<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProductPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Price Tiers';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('label')
                ->required()
                ->maxLength(255)
                ->columnSpan(5),

            Forms\Components\TextInput::make('amount')
                ->numeric()
                ->prefix('$')
                ->required()
                ->minValue(0)
                ->step(0.01)
                ->helperText('Set to 0 for a free tier.')
                ->columnSpan(5),

            Forms\Components\TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->columnSpan(2),
        ])->columns(12);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->hidden(fn () => ! auth()->user()?->can('update_product')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->hidden(fn () => ! auth()->user()?->can('update_product')),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn () => ! auth()->user()?->can('update_product'))
                    ->disabled(fn ($record) => \App\Models\Purchase::where('product_price_id', $record->id)->exists()),
            ]);
    }
}
