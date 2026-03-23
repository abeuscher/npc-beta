<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Resources\ContactResource;
use App\Models\Membership;
use App\Models\MembershipTier;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditContact extends EditRecord
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
            ]),
        ];
    }
}
