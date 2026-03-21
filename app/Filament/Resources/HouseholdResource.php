<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HouseholdResource\Pages;
use App\Filament\Resources\HouseholdResource\RelationManagers;
use App\Models\Household;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HouseholdResource extends Resource
{
    protected static ?string $model = Household::class;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_household') ?? false;
    }

    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_household') ?? false;
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_household') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Household')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Household Name')
                        ->placeholder('e.g. Smith Household')
                        ->required()
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Mailing Address')
                ->description('This address is used for all household mailings and will be applied to members when they are added.')
                ->schema([
                    Forms\Components\TextInput::make('address_line_1')
                        ->label('Address Line 1')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('address_line_2')
                        ->label('Address Line 2')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('city')
                        ->label('City'),

                    Forms\Components\TextInput::make('state')
                        ->label('State'),

                    Forms\Components\TextInput::make('postal_code')
                        ->label('Postal Code'),

                    Forms\Components\TextInput::make('country')
                        ->label('Country')
                        ->default('US'),
                ])
                ->columns(2),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Household Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_location')
                    ->label('Location')
                    ->getStateUsing(fn (Household $record) => collect([$record->city, $record->state])
                        ->filter()
                        ->implode(', ')
                    ),

                Tables\Columns\TextColumn::make('members_count')
                    ->label('Members')
                    ->counts('members')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->defaultSort('name')
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

    public static function getRelations(): array
    {
        return [
            RelationManagers\MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListHouseholds::route('/'),
            'create' => Pages\CreateHousehold::route('/create'),
            'edit'   => Pages\EditHousehold::route('/{record}/edit'),
        ];
    }
}
