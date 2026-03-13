<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug($state));
                        }
                    }),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(Page::class, 'slug', ignoreRecord: true)
                    ->rules(['alpha_dash'])
                    ->notIn(['admin', 'horizon', 'up', 'login', 'logout', 'register'])
                    ->helperText('URL-safe identifier. Auto-generated from title on create.'),

                Forms\Components\RichEditor::make('content')
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Publication')
                ->schema([
                    Forms\Components\Toggle::make('is_published')
                        ->label('Published')
                        ->live(),

                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Publish Date')
                        ->visible(fn (Forms\Get $get) => $get('is_published')),
                ])->columns(2),

            Forms\Components\Section::make('SEO')
                ->schema([
                    Forms\Components\TextInput::make('meta_title')
                        ->maxLength(255)
                        ->helperText('Defaults to page title if blank.'),

                    Forms\Components\Textarea::make('meta_description')
                        ->rows(3)
                        ->maxLength(160),
                ])
                ->columns(1)
                ->collapsible()
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published At')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published')
                    ->trueLabel('Published only')
                    ->falseLabel('Unpublished only'),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit'   => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
