<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NavigationItemResource\Pages;
use App\Models\NavigationItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NavigationItemResource extends Resource
{
    protected static ?string $model = NavigationItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Navigation';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('label')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('target')
                    ->options([
                        '_self'   => 'Same window',
                        '_blank'  => 'New window',
                    ])
                    ->default('_self'),

                Forms\Components\Select::make('link_type')
                    ->label('Link Type')
                    ->options([
                        'page'     => 'Internal Page',
                        'post'     => 'Post',
                        'external' => 'External URL',
                    ])
                    ->default('page')
                    ->live()
                    ->dehydrated(false),

                Forms\Components\Select::make('page_id')
                    ->label('Page')
                    ->relationship('page', 'title')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->visible(fn (Forms\Get $get) => $get('link_type') === 'page'),

                Forms\Components\Select::make('post_id')
                    ->label('Post')
                    ->relationship('post', 'title')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->visible(fn (Forms\Get $get) => $get('link_type') === 'post'),

                Forms\Components\TextInput::make('url')
                    ->label('URL')
                    ->url()
                    ->nullable()
                    ->visible(fn (Forms\Get $get) => $get('link_type') === 'external'),

                Forms\Components\Select::make('parent_id')
                    ->label('Parent Item')
                    ->relationship('parent', 'label')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),

                Forms\Components\Toggle::make('is_visible')
                    ->label('Visible')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.label')
                    ->label('Parent')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('page.title')
                    ->label('Page')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->placeholder('—')
                    ->limit(40),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNavigationItems::route('/'),
            'create' => Pages\CreateNavigationItem::route('/create'),
            'edit'   => Pages\EditNavigationItem::route('/{record}/edit'),
        ];
    }
}
