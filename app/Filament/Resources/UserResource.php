<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Mail\AdminInvitation;
use App\Models\InvitationToken;
use App\Models\Role;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Users';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_user') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(User::class, 'email', ignoreRecord: true),

                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->label(fn (string $operation) => $operation === 'create' ? 'Password' : 'New Password (leave blank to keep current)'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                Forms\Components\Select::make('roles')
                    ->label('Roles')
                    ->multiple()
                    ->options(fn () => Role::all()->mapWithKeys(fn ($r) => [$r->name => $r->display_label]))
                    ->preload(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->copyable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(','),

                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\TextColumn::make('created_at')->date()->sortable(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\Action::make('invite')
                    ->label('Invite')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->hidden(fn (User $record) => DB::table('sessions')->where('user_id', $record->id)->exists())
                    ->form([
                        Forms\Components\Select::make('roles')
                            ->label('Role')
                            ->multiple()
                            ->options(fn () => Role::all()->mapWithKeys(fn ($r) => [$r->name => $r->display_label]))
                            ->default(fn (User $record) => $record->getRoleNames()->toArray())
                            ->preload(),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->update(['is_active' => false]);
                        $record->syncRoles($data['roles'] ?? []);

                        [$plain] = InvitationToken::createForUser($record);

                        Mail::to($record->email)->send(new AdminInvitation($record, $plain));

                        Notification::make()
                            ->success()
                            ->title('Invitation sent')
                            ->body("An invitation email has been sent to {$record->email}.")
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->modalHeading(fn (User $record) => "Delete {$record->name} ({$record->email})?")
                    ->modalDescription('This will permanently remove this user account. This cannot be undone.')
                    ->hidden(fn (User $record) => $record->isProtected()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $protected = $records->filter(fn (User $u) => $u->isProtected());
                            $records->filter(fn (User $u) => ! $u->isProtected())->each->delete();

                            if ($protected->isNotEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Primary administrator account skipped')
                                    ->body('The original administrator account is protected and cannot be deleted.')
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
