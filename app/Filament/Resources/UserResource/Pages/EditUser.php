<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Mail\AdminInvitation;
use App\Models\InvitationToken;
use App\Models\Role;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('invite')
                ->label('Send Invitation')
                ->icon('heroicon-o-envelope')
                ->color('warning')
                ->hidden(fn () => DB::table('sessions')->where('user_id', $this->record->id)->exists())
                ->form([
                    Forms\Components\Select::make('roles')
                        ->label('Role')
                        ->multiple()
                        ->options(fn () => Role::all()->mapWithKeys(fn ($r) => [$r->name => $r->display_label]))
                        ->default(fn () => $this->record->getRoleNames()->toArray())
                        ->preload(),
                ])
                ->action(function (array $data) {
                    $this->record->update(['is_active' => false]);
                    $this->record->syncRoles($data['roles'] ?? []);

                    [$plain] = InvitationToken::createForUser($this->record);

                    Mail::to($this->record->email)->send(new AdminInvitation($this->record, $plain));

                    Notification::make()
                        ->success()
                        ->title('Invitation sent')
                        ->body("An invitation email has been sent to {$this->record->email}.")
                        ->send();
                }),

            Actions\Action::make('resend')
                ->label('Resend Invitation')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => (bool) $this->record->pendingInvitationToken())
                ->requiresConfirmation()
                ->modalHeading('Resend invitation?')
                ->modalDescription('This will generate a new invitation link and send it to ' . $this->record->email . '. The previous link will be invalidated.')
                ->action(function () {
                    [$plain] = InvitationToken::createForUser($this->record);

                    Mail::to($this->record->email)->send(new AdminInvitation($this->record, $plain));

                    Notification::make()
                        ->success()
                        ->title('Invitation resent')
                        ->send();
                }),

            Actions\Action::make('revoke')
                ->label('Revoke Invitation')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => (bool) $this->record->pendingInvitationToken())
                ->requiresConfirmation()
                ->modalHeading('Revoke invitation?')
                ->modalDescription('The pending invitation link will be deleted. The user account will not be affected.')
                ->action(function () {
                    InvitationToken::where('user_id', $this->record->id)
                        ->whereNull('accepted_at')
                        ->delete();

                    Notification::make()
                        ->success()
                        ->title('Invitation revoked')
                        ->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->record->getRoleNames()->toArray();
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['roles']);
        return $data;
    }

    protected function afterSave(): void
    {
        $roles = $this->form->getRawState()['roles'] ?? [];
        $this->record->syncRoles($roles);
    }
}
