<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('donation_id')
                    ->label('Donation')
                    ->relationship('donation', 'id')
                    ->searchable()
                    ->nullable(),

                Forms\Components\Select::make('type')
                    ->options([
                        'donation'   => 'Donation',
                        'refund'     => 'Refund',
                        'fee'        => 'Fee',
                        'adjustment' => 'Adjustment',
                    ])
                    ->default('donation')
                    ->required(),

                Forms\Components\TextInput::make('amount')->numeric()->prefix('$')->required(),

                Forms\Components\Select::make('direction')
                    ->options(['in' => 'In', 'out' => 'Out'])
                    ->default('in')
                    ->required(),

                Forms\Components\Select::make('status')
                    ->options([
                        'pending'  => 'Pending',
                        'cleared'  => 'Cleared',
                        'failed'   => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->default('pending')
                    ->required(),

                Forms\Components\TextInput::make('stripe_id')->nullable(),
                Forms\Components\TextInput::make('quickbooks_id')->nullable(),
                Forms\Components\DateTimePicker::make('occurred_at')->default(now()),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('amount')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('direction')->badge()
                    ->color(fn ($state) => $state === 'in' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn ($state) => match ($state) {
                        'cleared'  => 'success',
                        'failed'   => 'danger',
                        'refunded' => 'warning',
                        default    => 'gray',
                    }),
                Tables\Columns\TextColumn::make('occurred_at')->dateTime()->sortable(),
            ])
            ->defaultSort('occurred_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit'   => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}
