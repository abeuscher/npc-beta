<?php

namespace App\Filament\Resources\ContactResource\RelationManagers;

use App\Models\MembershipTier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tier_id')
                ->label('Tier')
                ->options(
                    MembershipTier::where('is_active', true)
                        ->orderBy('sort_order')
                        ->get()
                        ->pluck('name', 'id')
                )
                ->required(),

            Forms\Components\Select::make('status')
                ->options([
                    'pending'   => 'Pending',
                    'active'    => 'Active',
                    'expired'   => 'Expired',
                    'cancelled' => 'Cancelled',
                ])
                ->default('active')
                ->required(),

            Forms\Components\DatePicker::make('starts_on')->label('Member since'),
            Forms\Components\DatePicker::make('expires_on')->label('Expires on'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tier.name')
                    ->label('Tier'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'    => 'success',
                        'expired'   => 'warning',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('starts_on')
                    ->label('Member since')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_on')
                    ->label('Expires on')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('starts_on', 'desc')
            ->headerActions([])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
