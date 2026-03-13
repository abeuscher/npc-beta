<?php

namespace App\Filament\Resources\PageResource\RelationManagers;

use App\Widgets\WidgetRegistry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PageWidgetsRelationManager extends RelationManager
{
    protected static string $relationship = 'pageWidgets';

    protected static ?string $title = 'Widgets';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('widget_type')
                ->label('Widget Type')
                ->options(fn () => WidgetRegistry::options())
                ->required()
                ->live(),

            Forms\Components\Section::make('Configuration')
                ->schema(fn (Get $get) => WidgetRegistry::get($get('widget_type'))?->configSchema() ?? [])
                ->visible(fn (Get $get) => filled($get('widget_type'))),

            Forms\Components\TextInput::make('label')
                ->label('Admin Label')
                ->maxLength(255)
                ->helperText('Optional admin label for identifying this widget.'),

            Forms\Components\TextInput::make('sort_order')
                ->label('Sort Order')
                ->numeric()
                ->default(0),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Widget')
                    ->getStateUsing(function ($record): string {
                        if ($record->label) {
                            return $record->label;
                        }

                        $class = WidgetRegistry::getClass($record->widget_type);

                        return $class ? $class::label() : $record->widget_type;
                    }),

                Tables\Columns\TextColumn::make('widget_type')
                    ->label('Type')
                    ->getStateUsing(function ($record): string {
                        $class = WidgetRegistry::getClass($record->widget_type);

                        return $class ? $class::label() : $record->widget_type;
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
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
