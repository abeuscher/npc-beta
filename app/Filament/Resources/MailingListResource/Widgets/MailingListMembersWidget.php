<?php

namespace App\Filament\Resources\MailingListResource\Widgets;

use App\Models\MailingList;
use App\Services\MailingListQueryBuilder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MailingListMembersWidget extends BaseWidget
{
    public ?MailingList $record = null;

    protected static ?string $heading = 'Members';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => MailingListQueryBuilder::build($this->record))
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->label('First name')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_name')
                    ->label('Last name')
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->sortable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('City'),

                Tables\Columns\TextColumn::make('state')
                    ->label('State'),
            ])
            ->defaultSort('last_name')
            ->paginated([25, 50, 100]);
    }
}
