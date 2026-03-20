<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DonationResource\Pages;
use App\Models\Donation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DonationResource extends Resource
{
    protected static ?string $model = Donation::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_donation') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('contact_id')
                    ->label('Contact')
                    ->relationship('contact', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('campaign_id')
                    ->label('Campaign')
                    ->relationship('campaign', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Forms\Components\Select::make('fund_id')
                    ->label('Fund')
                    ->relationship('fund', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->prefix('$')
                    ->required(),

                Forms\Components\DatePicker::make('donated_on')
                    ->required()
                    ->default(today()),

                Forms\Components\Select::make('method')
                    ->options([
                        'cash'  => 'Cash',
                        'check' => 'Check',
                        'card'  => 'Card',
                        'ach'   => 'ACH',
                        'other' => 'Other',
                    ])
                    ->default('other')
                    ->required(),

                Forms\Components\TextInput::make('reference')
                    ->label('Reference (check #, etc.)')
                    ->nullable(),

                Forms\Components\Toggle::make('is_anonymous')->default(false),

                Forms\Components\Textarea::make('notes')->rows(3)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contact.display_name')
                    ->label('Contact')
                    ->searchable(query: fn ($query, $search) => $query->whereHas('contact', fn ($q) => $q
                        ->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                    ))
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')->money('USD')->sortable(),

                Tables\Columns\TextColumn::make('method')->badge(),

                Tables\Columns\TextColumn::make('campaign.name')->label('Campaign')->placeholder('—'),

                Tables\Columns\TextColumn::make('fund.name')->label('Fund')->placeholder('—'),

                Tables\Columns\TextColumn::make('donated_on')->date()->sortable(),
            ])
            ->defaultSort('donated_on', 'desc')
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
            'index'  => Pages\ListDonations::route('/'),
            'create' => Pages\CreateDonation::route('/create'),
            'edit'   => Pages\EditDonation::route('/{record}/edit'),
        ];
    }
}
