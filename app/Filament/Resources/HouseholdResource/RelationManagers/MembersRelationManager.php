<?php

namespace App\Filament\Resources\HouseholdResource\RelationManagers;

use App\Models\Contact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Members';

    public function form(Form $form): Form
    {
        // Not used — members are added via the custom action below, not created here.
        return $form->schema([]);
    }

    public function table(Table $table): Table
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
                    }),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('add_member')
                    ->label('Add Member')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Forms\Components\Select::make('contact_id')
                            ->label('Contact')
                            ->options(
                                Contact::whereNull('household_id')
                                    ->orderBy('last_name')
                                    ->orderBy('first_name')
                                    ->get()
                                    ->mapWithKeys(fn ($c) => [$c->id => $c->display_name])
                            )
                            ->searchable()
                            ->required()
                            ->helperText('Only contacts not currently in a household are shown.'),

                        Forms\Components\Toggle::make('sync_address')
                            ->label('Apply household address to this contact')
                            ->helperText('Overwrites the contact\'s current address with the household mailing address.')
                            ->default(true),
                    ])
                    ->action(function (array $data): void {
                        $household = $this->getOwnerRecord();
                        $contact = Contact::findOrFail($data['contact_id']);

                        $update = ['household_id' => $household->id];

                        if ($data['sync_address']) {
                            $update = array_merge($update, [
                                'address_line_1' => $household->address_line_1,
                                'address_line_2' => $household->address_line_2,
                                'city'           => $household->city,
                                'state'          => $household->state,
                                'postal_code'    => $household->postal_code,
                                'country'        => $household->country,
                            ]);
                        }

                        $contact->update($update);

                        Notification::make()
                            ->title("{$contact->display_name} added to household")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('sync_address_all')
                    ->label('Sync Address to All Members')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Sync household address to all members?')
                    ->modalDescription('This will overwrite the address on every member contact with the household mailing address.')
                    ->action(function (): void {
                        $household = $this->getOwnerRecord();

                        $household->members()->update([
                            'address_line_1' => $household->address_line_1,
                            'address_line_2' => $household->address_line_2,
                            'city'           => $household->city,
                            'state'          => $household->state,
                            'postal_code'    => $household->postal_code,
                            'country'        => $household->country,
                        ]);

                        Notification::make()
                            ->title('Household address synced to all members')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove from household?')
                    ->modalDescription('The contact will keep their current address. They will no longer be part of this household.')
                    ->action(fn (Contact $record) => $record->update(['household_id' => null])),
            ]);
    }
}
