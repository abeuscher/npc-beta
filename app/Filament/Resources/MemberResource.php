<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemberResource\Pages;
use App\Models\Contact;
use App\Models\MembershipTier;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MemberResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Members';

    protected static ?string $modelLabel = 'Member';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->isMember();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_member') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_contact') ?? false;
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_contact') ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable(query: function ($query, string $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('first_name', 'ilike', "%{$search}%")
                                ->orWhere('last_name', 'ilike', "%{$search}%");
                        });
                    })
                    ->sortable(query: fn ($query, $direction) => $query->orderByRaw(
                        "COALESCE(last_name, first_name) {$direction}"
                    )),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('membership_tier')
                    ->label('Membership Tier')
                    ->getStateUsing(fn (Contact $record): ?string => $record->memberships->first()?->tier?->name)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('membership_status')
                    ->label('Status')
                    ->getStateUsing(fn (Contact $record): string => ucfirst($record->memberships->first()?->status ?? ''))
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('member_since')
                    ->label('Member Since')
                    ->getStateUsing(fn (Contact $record): ?string => $record->memberships->first()?->starts_on?->format('M j, Y'))
                    ->sortable(false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tier')
                    ->label('Membership Tier')
                    ->options(fn () => MembershipTier::orderBy('name')->pluck('name', 'id')->toArray())
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->whereHas('memberships', fn ($q) => $q->where('status', 'active')->where('tier_id', $data['value']))
                        : $query
                    ),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->hidden(fn () => ! auth()->user()?->can('update_contact'))
                    ->url(fn (Contact $record): string => ContactResource::getUrl('edit', ['record' => $record])),

                Tables\Actions\DeleteAction::make()
                    ->hidden(fn () => ! auth()->user()?->can('delete_contact')),
                Tables\Actions\RestoreAction::make()
                    ->hidden(fn () => ! auth()->user()?->can('delete_contact')),
                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn () => auth()->user()?->hasRole('super_admin') ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn (Contact $record): string => ContactResource::getUrl('edit', ['record' => $record]))
            ->modifyQueryUsing(fn ($query) => $query->with([
                'memberships' => fn ($q) => $q->where('status', 'active')->with('tier'),
            ]));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
        ];
    }
}
