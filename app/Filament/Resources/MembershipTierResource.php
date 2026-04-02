<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MembershipTierResource\Pages;
use App\Models\MembershipTier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MembershipTierResource extends Resource
{
    protected static ?string $model = MembershipTier::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Membership Tiers';

    protected static ?int $navigationSort = 7;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if ($user && ! $user->isSuperAdmin()) {
            return false;
        }

        return $record->memberships()->where('status', 'active')->doesntExist();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),

                Forms\Components\Select::make('billing_interval')
                    ->options([
                        'monthly'  => 'Monthly',
                        'annual'   => 'Annual',
                        'one_time' => 'One-time',
                        'lifetime' => 'Lifetime',
                    ])
                    ->default('lifetime')
                    ->required(),

                Forms\Components\TextInput::make('default_price')
                    ->numeric()
                    ->prefix('$')
                    ->placeholder('Leave blank for complimentary'),
            ])->columns(2),

            Forms\Components\Section::make('Advanced')->schema([
                Forms\Components\Textarea::make('description')
                    ->nullable()
                    ->rows(3),

                Forms\Components\TextInput::make('renewal_notice_days')
                    ->numeric()
                    ->default(30)
                    ->suffix('days')
                    ->helperText('How many days before expiry to send a renewal reminder'),

                Forms\Components\Toggle::make('is_active')
                    ->default(true),

                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ])->columns(2)->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('billing_interval')
                    ->label('Interval')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'monthly'  => 'Monthly',
                        'annual'   => 'Annual',
                        'one_time' => 'One-time',
                        'lifetime' => 'Lifetime',
                        default    => $state,
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('default_price')
                    ->label('Default Price')
                    ->formatStateUsing(fn ($state): string => $state === null ? 'Complimentary' : '$' . number_format($state, 2))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
                Tables\Filters\TernaryFilter::make('is_archived')
                    ->label('Archived')
                    ->placeholder('Not archived')
                    ->trueLabel('Archived only')
                    ->falseLabel('All'),
            ])
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->withoutArchived())
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_archive')
                    ->label(fn (MembershipTier $record): string => $record->is_archived ? 'Unarchive' : 'Archive')
                    ->icon(fn (MembershipTier $record): string => $record->is_archived ? 'heroicon-o-arrow-up-tray' : 'heroicon-o-archive-box')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->hidden(fn () => ! auth()->user()?->isSuperAdmin())
                    ->action(function (MembershipTier $record) {
                        abort_unless(auth()->user()?->isSuperAdmin(), 403);
                        $record->update(['is_archived' => ! $record->is_archived]);
                        Notification::make()
                            ->title($record->is_archived ? 'Tier archived' : 'Tier unarchived')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (MembershipTier $record): bool => $record->memberships()->where('status', 'active')->exists()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (MembershipTier $record) {
                                if ($record->memberships()->where('status', 'active')->doesntExist()) {
                                    $record->delete();
                                }
                            });
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMembershipTiers::route('/'),
            'create' => Pages\CreateMembershipTier::route('/create'),
            'edit'   => Pages\EditMembershipTier::route('/{record}/edit'),
        ];
    }
}
