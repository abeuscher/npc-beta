<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MembershipResource\Pages;
use App\Models\Membership;
use App\Models\MembershipTier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MembershipResource extends Resource
{
    protected static ?string $model = Membership::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_membership') ?? false;
    }

    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_membership') ?? false;
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_membership') ?? false;
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

                Forms\Components\Select::make('tier_id')
                    ->label('Tier')
                    ->options(
                        MembershipTier::where('is_active', true)
                            ->where('is_archived', false)
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
                    ->default('pending')
                    ->required(),

                Forms\Components\TextInput::make('amount_paid')
                    ->numeric()
                    ->prefix('$'),
            ])->columns(2),

            Forms\Components\Section::make('Dates')->schema([
                Forms\Components\DatePicker::make('starts_on')->label('Start Date'),
                Forms\Components\DatePicker::make('expires_on')->label('Expiry Date'),
            ])->columns(2),

            Forms\Components\Section::make('Notes')->schema([
                Forms\Components\Textarea::make('notes')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contact.display_name')
                    ->label('Contact')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('contact', fn ($q) => $q
                            ->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                        );
                    })
                    ->sortable()
                    ->url(fn ($record) => $record->contact_id
                        ? \App\Filament\Resources\ContactResource::getUrl('edit', ['record' => $record->contact_id])
                        : null),

                Tables\Columns\TextColumn::make('tier.name')
                    ->label('Tier')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'    => 'success',
                        'expired'   => 'warning',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('expires_on')
                    ->label('Expires')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'active'    => 'Active',
                        'expired'   => 'Expired',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->defaultSort('created_at', 'desc')
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

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMemberships::route('/'),
            'create' => Pages\CreateMembership::route('/create'),
            'edit'   => Pages\EditMembership::route('/{record}/edit'),
        ];
    }
}
