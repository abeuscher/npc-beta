<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Resources\ContactResource;
use App\Filament\Resources\TransactionResource;
use App\Mail\PortalEmailVerification;
use App\Models\Contact;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\PortalAccount;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EditContact extends EditRecord
{
    protected static string $resource = ContactResource::class;

    public ?string $initialHouseholdId = null;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->initialHouseholdId = $data['household_id'] ?? null;

        // Show null in the Select when the contact is their own head (solo)
        if (isset($data['household_id']) && $data['household_id'] === $this->record->id) {
            $data['household_id'] = null;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Null selection means solo — assign the contact as their own head
        if (empty($data['household_id'])) {
            $data['household_id'] = $this->record->id;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $contact = $this->record;

        // Sync head's address when newly assigned to a different household
        if ($contact->household_id &&
            $contact->household_id !== $contact->id &&
            $contact->household_id !== $this->initialHouseholdId) {

            $head = Contact::find($contact->household_id);

            if ($head) {
                $contact->updateQuietly([
                    'address_line_1' => $head->address_line_1,
                    'address_line_2' => $head->address_line_2,
                    'city'           => $head->city,
                    'state'          => $head->state,
                    'postal_code'    => $head->postal_code,
                    'country'        => $head->country,
                ]);
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_transactions')
                ->label('View transactions →')
                ->color('gray')
                ->url(fn () => TransactionResource::getUrl('index')
                    . '?tableFilters[contact_id][value]=' . $this->record->getKey()),

            Actions\Action::make('notes')
                ->label('Timeline')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(fn () => ContactResource::getUrl('notes', ['record' => $this->record->getKey()])),

            Actions\DeleteAction::make(),

            Actions\ActionGroup::make([
                Actions\Action::make('promote_to_member')
                    ->label('Promote to Member')
                    ->icon('heroicon-o-identification')
                    ->hidden(fn () => $this->record->memberships()->where('status', 'active')->exists())
                    ->modalHeading('Promote to Member')
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\Select::make('tier_id')
                            ->label('Tier')
                            ->options(function () {
                                return MembershipTier::where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->get()
                                    ->mapWithKeys(fn ($tier) => [
                                        $tier->id => $tier->name . ' (' . match ($tier->billing_interval) {
                                            'monthly'  => 'Monthly',
                                            'annual'   => 'Annual',
                                            'one_time' => 'One-time',
                                            'lifetime' => 'Lifetime',
                                        } . ')',
                                    ]);
                            })
                            ->required()
                            ->reactive(),

                        Forms\Components\DatePicker::make('starts_on')
                            ->label('Member since')
                            ->required()
                            ->default(today()),

                        Forms\Components\DatePicker::make('expires_on')
                            ->label('Expires on')
                            ->nullable()
                            ->hidden(function (Forms\Get $get) {
                                $tierId = $get('tier_id');
                                if (! $tierId) {
                                    return false;
                                }
                                $tier = MembershipTier::find($tierId);
                                return $tier && $tier->billing_interval === 'lifetime';
                            }),

                        Forms\Components\TextInput::make('amount_paid')
                            ->label('Amount paid')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->helperText('Leave as 0 for complimentary'),
                    ])
                    ->action(function (array $data) {
                        $tierId = $data['tier_id'];
                        $tier   = MembershipTier::find($tierId);

                        $expiresOn = ($tier && $tier->billing_interval === 'lifetime')
                            ? null
                            : ($data['expires_on'] ?? null);

                        Membership::create([
                            'contact_id'  => $this->record->id,
                            'tier_id'     => $tierId,
                            'status'      => 'active',
                            'starts_on'   => $data['starts_on'],
                            'expires_on'  => $expiresOn,
                            'amount_paid' => $data['amount_paid'] ?? 0,
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Membership created.')
                            ->send();
                    }),

                Actions\Action::make('grant_portal_access')
                    ->label('Grant Portal Access')
                    ->icon('heroicon-o-key')
                    ->hidden(fn () => $this->record->portalAccount !== null)
                    ->modalHeading('Grant Portal Access')
                    ->modalWidth('md')
                    ->form([
                        Forms\Components\TextInput::make('portal_email')
                            ->label('Portal email')
                            ->email()
                            ->required()
                            ->default(fn () => $this->record->email),

                        Forms\Components\Toggle::make('send_invite')
                            ->label('Send invite email')
                            ->default(true),
                    ])
                    ->action(function (array $data) {
                        $sendInvite = $data['send_invite'];

                        $portal = PortalAccount::create([
                            'contact_id'        => $this->record->id,
                            'email'             => $data['portal_email'],
                            'password'          => Hash::make(Str::random(32)),
                            'is_active'         => true,
                            'email_verified_at' => $sendInvite ? null : now(),
                        ]);

                        if ($sendInvite) {
                            Mail::to($portal->email)->send(new PortalEmailVerification($portal));
                        }

                        Notification::make()
                            ->success()
                            ->title($sendInvite ? 'Portal access granted — invite sent.' : 'Portal access granted.')
                            ->send();
                    }),

                Actions\Action::make('suspend_portal_access')
                    ->label('Suspend portal access')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->hidden(fn () => $this->record->portalAccount === null || ! $this->record->portalAccount->is_active)
                    ->requiresConfirmation()
                    ->action(function () {
                        $this->record->portalAccount->update(['is_active' => false]);

                        Notification::make()
                            ->success()
                            ->title('Portal access suspended.')
                            ->send();
                    }),

                Actions\Action::make('restore_portal_access')
                    ->label('Restore portal access')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->hidden(fn () => $this->record->portalAccount === null || $this->record->portalAccount->is_active)
                    ->requiresConfirmation()
                    ->action(function () {
                        $this->record->portalAccount->update(['is_active' => true]);

                        Notification::make()
                            ->success()
                            ->title('Portal access restored.')
                            ->send();
                    }),

                Actions\Action::make('mark_email_verified')
                    ->label('Mark email verified')
                    ->icon('heroicon-o-check-badge')
                    ->hidden(fn () => $this->record->portalAccount === null || $this->record->portalAccount->email_verified_at !== null)
                    ->requiresConfirmation()
                    ->action(function () {
                        $this->record->portalAccount->update(['email_verified_at' => now()]);

                        Notification::make()
                            ->success()
                            ->title('Email marked as verified.')
                            ->send();
                    }),
            ]),
        ];
    }
}
