<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FundResource\Pages;
use App\Models\Fund;
use App\Services\QuickBooks\QuickBooksAuth;
use App\Services\QuickBooks\QuickBooksClient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

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
        $user = auth()->user();
        if ($user && ! $user->can('delete_fund')) {
            return false;
        }

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

            ...static::quickBooksSection(),
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
            ->filters([
                Tables\Filters\TernaryFilter::make('is_archived')
                    ->label('Archived')
                    ->placeholder('Not archived')
                    ->trueLabel('Archived only')
                    ->falseLabel('All'),
            ])
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->withoutArchived())
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_archive')
                    ->label(fn (Fund $record): string => $record->is_archived ? 'Unarchive' : 'Archive')
                    ->icon(fn (Fund $record): string => $record->is_archived ? 'heroicon-o-arrow-up-tray' : 'heroicon-o-archive-box')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->hidden(fn () => ! auth()->user()?->can('update_fund'))
                    ->action(function (Fund $record) {
                        abort_unless(auth()->user()?->can('update_fund'), 403);
                        $record->update(['is_archived' => ! $record->is_archived]);
                        Notification::make()
                            ->title($record->is_archived ? 'Fund archived' : 'Fund unarchived')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Fund $record): bool => $record->donations()->exists()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (Fund $record) {
                                if ($record->donations()->doesntExist()) {
                                    $record->delete();
                                }
                            });
                        }),
                ]),
            ]);
    }

    private static function quickBooksSection(): array
    {
        if (! app(QuickBooksAuth::class)->isConnected()) {
            return [];
        }

        $accounts = Cache::remember('qb_deposit_accounts', 3600, function () {
            try {
                return app(QuickBooksClient::class)->getDepositAccounts();
            } catch (\Throwable) {
                return [];
            }
        });

        return [
            Forms\Components\Section::make('QuickBooks')
                ->schema([
                    Forms\Components\Select::make('quickbooks_account_id')
                        ->label('Deposit Account Override')
                        ->options($accounts)
                        ->placeholder('Use default (from Finance Settings)')
                        ->helperText('Override the deposit account for donations allocated to this fund. Leave blank to use the default donation account.'),
                ]),
        ];
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
