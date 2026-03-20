<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TagResource\Pages;
use App\Models\Tag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Tags';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Label')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('slug')
                ->label('Handle')
                ->required()
                ->maxLength(255)
                ->unique(Tag::class, 'slug', ignoreRecord: true)
                ->hiddenOn('create'),

            Forms\Components\Select::make('type')
                ->options([
                    'contact'    => 'Contact',
                    'page'       => 'Page',
                    'post'       => 'Post',
                    'event'      => 'Event',
                    'collection' => 'Collection',
                ])
                ->required()
                ->default('contact')
                ->hiddenOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'gray'    => 'contact',
                        'info'    => 'page',
                        'warning' => 'post',
                        'success' => 'event',
                        'primary' => 'collection',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('attached')
                    ->label('Attached')
                    ->getStateUsing(fn (Tag $record): int => (int) DB::table('taggables')->where('tag_id', $record->id)->count())
                    ->sortable(false),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'contact'    => 'Contact',
                        'page'       => 'Page',
                        'post'       => 'Post',
                        'event'      => 'Event',
                        'collection' => 'Collection',
                    ]),
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

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            'edit'   => Pages\EditTag::route('/{record}/edit'),
        ];
    }
}
