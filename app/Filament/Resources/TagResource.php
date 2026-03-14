<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TagResource\Pages;
use App\Models\Tag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'CRM Tags';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->unique(Tag::class, 'name', ignoreRecord: true),

            Forms\Components\ColorPicker::make('color')
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('color'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contacts_count')
                    ->label('Contacts')
                    ->counts('contacts')
                    ->sortable(),

                Tables\Columns\TextColumn::make('organizations_count')
                    ->label('Organizations')
                    ->counts('organizations')
                    ->sortable(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            'edit'   => Pages\EditTag::route('/{record}/edit'),
        ];
    }
}
