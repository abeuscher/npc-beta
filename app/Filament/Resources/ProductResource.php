<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'CMS';

    protected static ?string $navigationLabel = 'Products';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_product') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if ($user && ! $user->can('delete_product')) {
            return false;
        }

        return $record->purchases()->doesntExist();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Group::make([

                // ── Left column ───────────────────────────────────────────
                Forms\Components\Group::make([
                    Forms\Components\Section::make('Main Info')->schema([
                        Forms\Components\TextInput::make('name')
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
                            ->unique(Product::class, 'slug', ignoreRecord: true)
                            ->regex('/^[a-z0-9\-]+$/')
                            ->helperText('URL-safe identifier. Auto-generated from name on create.')
                            ->hiddenOn('create'),

                        Forms\Components\Textarea::make('description')
                            ->nullable()
                            ->rows(4)
                            ->columnSpanFull(),
                    ])->columns(2),
                ])->columnSpan(2),

                // ── Right column ──────────────────────────────────────────
                Forms\Components\Group::make([
                    Forms\Components\Section::make('Settings')->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft'     => 'Draft',
                                'published' => 'Published',
                            ])
                            ->default('draft')
                            ->required(),

                        Forms\Components\TextInput::make('capacity')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->helperText('Total number of units available.'),

                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                    ]),
                ])->columnSpan(1),

            ])->columns(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record): string => static::getUrl('edit', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'draft',
                        'success' => 'published',
                    ]),

                Tables\Columns\TextColumn::make('capacity')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'     => 'Draft',
                        'published' => 'Published',
                    ]),
                Tables\Filters\TernaryFilter::make('is_archived')
                    ->label('Archived')
                    ->placeholder('Not archived')
                    ->trueLabel('Archived only')
                    ->falseLabel('All'),
            ])
            ->defaultSort('sort_order', 'asc')
            ->modifyQueryUsing(fn ($query) => $query->withoutArchived())
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_archive')
                    ->label(fn (Product $record): string => $record->is_archived ? 'Unarchive' : 'Archive')
                    ->icon(fn (Product $record): string => $record->is_archived ? 'heroicon-o-arrow-up-tray' : 'heroicon-o-archive-box')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->hidden(fn () => ! auth()->user()?->can('update_product'))
                    ->action(function (Product $record) {
                        abort_unless(auth()->user()?->can('update_product'), 403);
                        $record->update(['is_archived' => ! $record->is_archived]);
                        Notification::make()
                            ->title($record->is_archived ? 'Product archived' : 'Product unarchived')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Product $record): bool => $record->purchases()->exists()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (Product $record) {
                                if ($record->purchases()->doesntExist()) {
                                    $record->delete();
                                }
                            });
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ProductPricesRelationManager::class,
            RelationManagers\WaitlistEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
