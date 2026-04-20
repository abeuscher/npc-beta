<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Actions\EmailPreviewWizardAction;
use App\Filament\Resources\UserResource;
use App\Mail\AdminInvitation;
use App\Models\EmailTemplate;
use App\Models\InvitationToken;
use App\Models\Role;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class EditUser extends ReadOnlyAwareEditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EmailPreviewWizardAction::make(
                name: 'invite',
                emailTypeName: 'Admin Invitation',
                recipientSummary: fn () => 'An invitation email will be sent to <strong>' . e($this->record->email) . '</strong>.',
                previewHtmlResolver: fn () => $this->invitationPreviewHtml(),
                sendCallable: function (array $data) {
                    abort_unless(auth()->user()?->can('update_user'), 403);
                    $this->record->update(['is_active' => false]);
                    $this->record->syncRoles($data['roles'] ?? []);

                    [$plain] = InvitationToken::createForUser($this->record);

                    Mail::to($this->record->email)->send(new AdminInvitation($this->record, $plain));

                    Notification::make()
                        ->success()
                        ->title('Invitation sent')
                        ->body("An invitation email has been sent to {$this->record->email}.")
                        ->send();
                },
                step1ExtraSchema: [
                    Forms\Components\Select::make('roles')
                        ->label('Role')
                        ->multiple()
                        ->options(fn () => Role::all()->mapWithKeys(fn ($r) => [$r->name => $r->display_label]))
                        ->default(fn () => $this->record->getRoleNames()->toArray())
                        ->preload(),
                ],
            )
                ->label('Send Invitation')
                ->icon('heroicon-o-envelope')
                ->color('warning')
                ->hidden(fn () => ! auth()->user()?->can('update_user') || DB::table('sessions')->where('user_id', $this->record->id)->exists()),

            EmailPreviewWizardAction::make(
                name: 'resend',
                emailTypeName: 'Admin Invitation',
                recipientSummary: fn () => 'A new invitation link will be sent to <strong>' . e($this->record->email) . '</strong>. The previous link will be invalidated.',
                previewHtmlResolver: fn () => $this->invitationPreviewHtml(),
                sendCallable: function (array $data) {
                    abort_unless(auth()->user()?->can('update_user'), 403);
                    [$plain] = InvitationToken::createForUser($this->record);

                    Mail::to($this->record->email)->send(new AdminInvitation($this->record, $plain));

                    Notification::make()
                        ->success()
                        ->title('Invitation resent')
                        ->send();
                },
            )
                ->label('Resend Invitation')
                ->icon('heroicon-o-arrow-path')
                ->color('secondary')
                ->visible(fn () => auth()->user()?->can('update_user') && (bool) $this->record->pendingInvitationToken()),

            Actions\Action::make('revoke')
                ->label('Revoke Invitation')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => auth()->user()?->can('update_user') && (bool) $this->record->pendingInvitationToken())
                ->requiresConfirmation()
                ->modalHeading('Revoke invitation?')
                ->modalDescription('The pending invitation link will be deleted. The user account will not be affected.')
                ->action(function () {
                    abort_unless(auth()->user()?->can('update_user'), 403);
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

    private function invitationPreviewHtml(): string
    {
        $template = EmailTemplate::forHandle('admin_invitation');
        $tokens   = [
            'name'           => $this->record->name,
            'org_name'       => config('app.name'),
            'invitation_url' => '#',
        ];

        return $template->resolveWrapper($template->render($tokens));
    }
}
