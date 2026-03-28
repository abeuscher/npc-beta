<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FundResource\Pages;
use App\Models\Fund;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FundResource extends Resource
{
    protected static ?string $model = Fund::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Funds & Grants';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_fund') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record->donations()->doesntExist();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(Fund::class, 'code', ignoreRecord: true)
                    ->helperText('QuickBooks class code'),
                Forms\Components\Select::make('restriction_type')
                    ->required()
                    ->options([
                        'unrestricted'           => 'Unrestricted',
                        'temporarily_restricted' => 'Temporarily Restricted',
                        'permanently_restricted' => 'Permanently Restricted',
                    ])
                    ->default('unrestricted')
                    ->disabled(fn ($livewire) => $livewire instanceof EditRecord),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable(),
                Tables\Columns\TextColumn::make('restriction_type')
                    ->label('Restriction')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'temporarily_restricted' => 'Temporarily Restricted',
                        'permanently_restricted'  => 'Permanently Restricted',
                        default                   => 'Unrestricted',
                    }),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->defaultSort('name')
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
            'index'  => Pages\ListFunds::route('/'),
            'create' => Pages\CreateFund::route('/create'),
            'edit'   => Pages\EditFund::route('/{record}/edit'),
        ];
    }
}
