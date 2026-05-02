<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganizationResource\Pages;
use App\Filament\Resources\OrganizationResource\RelationManagers\EventsSponsoredRelationManager;
use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(12)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(6),

                    Forms\Components\Select::make('type')
                        ->options([
                            'nonprofit'  => 'Nonprofit',
                            'for_profit' => 'For profit',
                            'government' => 'Government',
                            'other'      => 'Other',
                        ])
                        ->nullable()
                        ->columnSpan(6),

                    Forms\Components\TextInput::make('website')
                        ->url()
                        ->maxLength(255)
                        ->columnSpan(4),

                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->maxLength(50)
                        ->columnSpan(4),

                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->maxLength(255)
                        ->columnSpan(4),

                    Forms\Components\TextInput::make('address_line_1')
                        ->label('Address Line 1')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('address_line_2')
                        ->label('Address Line 2')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('city')->columnSpan(3),
                    Forms\Components\TextInput::make('state')->columnSpan(3),
                    Forms\Components\TextInput::make('postal_code')->columnSpan(3),
                    Forms\Components\TextInput::make('country')->default('US')->columnSpan(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'nonprofit'  => 'Nonprofit',
                        'for_profit' => 'For profit',
                        'government' => 'Government',
                        'other'      => 'Other',
                        default      => $state ?? '',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'nonprofit'  => 'info',
                        'for_profit' => 'warning',
                        'government' => 'success',
                        default      => 'gray',
                    }),

                Tables\Columns\TextColumn::make('city')
                    ->sortable(),

                Tables\Columns\TextColumn::make('contacts_count')
                    ->label('Contacts')
                    ->counts('contacts')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'nonprofit'  => 'Nonprofit',
                        'for_profit' => 'For profit',
                        'government' => 'Government',
                        'other'      => 'Other',
                    ]),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Organization $record) {
                        if (! self::guardDeletion($record)) {
                            $action->cancel();
                        }
                    }),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, \Illuminate\Database\Eloquent\Collection $records) {
                            $blocked = $records->filter(fn (Organization $org) => self::countRelatedRecords($org) > 0);

                            if ($blocked->isNotEmpty()) {
                                Notification::make()
                                    ->title('Cannot delete')
                                    ->body($blocked->count() . ' organization(s) have linked members, donations, memberships, sponsored events, or invoices. Reassign or remove those records, or use Force Delete.')
                                    ->danger()
                                    ->send();

                                $action->cancel();
                            }
                        }),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function countRelatedRecords(Organization $organization): int
    {
        return $organization->contacts()->count()
            + $organization->donations()->count()
            + $organization->memberships()->count()
            + $organization->eventsSponsored()->count()
            + $organization->transactions()->count();
    }

    public static function guardDeletion(Organization $organization): bool
    {
        $counts = [
            'affiliated contacts' => $organization->contacts()->count(),
            'donations'           => $organization->donations()->count(),
            'memberships'         => $organization->memberships()->count(),
            'sponsored events'    => $organization->eventsSponsored()->count(),
            'invoices'            => $organization->transactions()->count(),
        ];

        $nonZero = array_filter($counts, fn ($n) => $n > 0);

        if (empty($nonZero)) {
            return true;
        }

        $parts = [];
        foreach ($nonZero as $label => $count) {
            $parts[] = "{$count} {$label}";
        }

        Notification::make()
            ->title('Cannot delete')
            ->body('This organization has ' . implode(', ', $parts) . '. Reassign or remove these before deleting, or use Force Delete to keep the records and null their organization link.')
            ->danger()
            ->send();

        return false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [
            EventsSponsoredRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'edit'   => Pages\EditOrganization::route('/{record}/edit'),
            'notes'  => Pages\OrganizationNotes::route('/{record}/notes'),
        ];
    }
}
