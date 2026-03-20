<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomFieldDefResource\Pages;
use App\Models\CustomFieldDef;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CustomFieldDefResource extends Resource
{
    protected static ?string $model = CustomFieldDef::class;

    protected static ?string $navigationIcon = 'heroicon-o-variable';

    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Custom Fields';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('model_type')
                    ->label('Applies To')
                    ->required()
                    ->options([
                        'contact' => 'Contacts',
                        'event'   => 'Events',
                        'page'    => 'Pages',
                    ])
                    ->default('contact'),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0),

                Forms\Components\TextInput::make('label')
                    ->label('Label')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                        if ($operation === 'create') {
                            $set('handle', Str::slug($state, '_'));
                        }
                    }),

                Forms\Components\TextInput::make('handle')
                    ->label('Handle')
                    ->required()
                    ->maxLength(255)
                    ->rules(['alpha_dash'])
                    ->helperText('Machine identifier. Auto-generated from label on create. Cannot be changed after creation.')
                    ->disabled(fn (string $operation) => $operation === 'edit')
                    ->dehydrated(),

                Forms\Components\Select::make('field_type')
                    ->label('Field Type')
                    ->required()
                    ->options([
                        'text'    => 'Text',
                        'number'  => 'Number',
                        'date'    => 'Date',
                        'boolean' => 'Boolean (Yes/No)',
                        'select'  => 'Select (dropdown)',
                    ])
                    ->default('text')
                    ->live(),

                Forms\Components\Toggle::make('is_filterable')
                    ->label('Filterable')
                    ->helperText('Creates a database index to speed up mailing list filters on this field. Contact fields only.')
                    ->visible(fn (Forms\Get $get) => $get('model_type') === 'contact')
                    ->default(false),
            ])->columns(2),

            Forms\Components\Section::make('Select Options')
                ->description('Define the choices available in the dropdown.')
                ->visible(fn (Forms\Get $get) => $get('field_type') === 'select')
                ->schema([
                    Forms\Components\Repeater::make('options')
                        ->schema([
                            Forms\Components\TextInput::make('value')
                                ->required()
                                ->helperText('Stored value (machine key)'),

                            Forms\Components\TextInput::make('label')
                                ->required()
                                ->helperText('Displayed to users'),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->reorderable()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('model_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'contact' => 'Contact',
                        'event'   => 'Event',
                        'page'    => 'Page',
                        default   => ucfirst($state),
                    })
                    ->colors([
                        'primary' => 'contact',
                        'success' => 'event',
                        'warning' => 'page',
                    ]),

                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('handle')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('field_type')
                    ->label('Field Type')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'text'    => 'Text',
                        'number'  => 'Number',
                        'date'    => 'Date',
                        'boolean' => 'Boolean',
                        'select'  => 'Select',
                        default   => ucfirst($state),
                    })
                    ->colors(['gray' => fn () => true]),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->defaultSort('model_type')
            ->filters([
                Tables\Filters\SelectFilter::make('model_type')
                    ->label('Applies To')
                    ->options([
                        'contact' => 'Contacts',
                        'event'   => 'Events',
                        'page'    => 'Pages',
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
            'index'  => Pages\ListCustomFieldDefs::route('/'),
            'create' => Pages\CreateCustomFieldDef::route('/create'),
            'edit'   => Pages\EditCustomFieldDef::route('/{record}/edit'),
        ];
    }
}
