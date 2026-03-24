<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Resources\ContactResource;
use App\Models\Contact;
use App\Models\Note;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ContactNotes extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ContactResource::class;

    protected static string $view = 'filament.pages.contact-notes';

    public Contact $record;

    public function mount(Contact|int|string $record): void
    {
        $this->record = $record instanceof Contact ? $record : Contact::findOrFail($record);
    }

    public function getTitle(): string
    {
        return $this->record->display_name . ' — Notes';
    }

    public function getBreadcrumbs(): array
    {
        return [
            ContactResource::getUrl('edit', ['record' => $this->record]) => $this->record->display_name,
            'Notes',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_contact')
                ->label('← Back to contact')
                ->color('gray')
                ->url(ContactResource::getUrl('edit', ['record' => $this->record])),

            Actions\Action::make('create_note')
                ->label('Create note')
                ->icon('heroicon-o-plus')
                ->modalHeading('Create Note')
                ->modalWidth('lg')
                ->form([
                    Forms\Components\Textarea::make('body')
                        ->label('Note')
                        ->required()
                        ->rows(4)
                        ->columnSpanFull(),

                    Forms\Components\DateTimePicker::make('occurred_at')
                        ->label('Occurred At')
                        ->default(now()),

                    Forms\Components\Hidden::make('author_id')
                        ->default(fn () => Auth::id()),
                ])
                ->action(function (array $data) {
                    $this->record->notes()->create($data);

                    Notification::make()
                        ->success()
                        ->title('Note created.')
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Note::query()
                    ->where('notable_type', Contact::class)
                    ->where('notable_id', $this->record->id)
            )
            ->columns([
                Tables\Columns\TextColumn::make('body')
                    ->label('Note')
                    ->limit(100),

                Tables\Columns\TextColumn::make('author.name')
                    ->label('Author'),

                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Occurred At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\Textarea::make('body')
                            ->label('Note')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\DateTimePicker::make('occurred_at')
                            ->label('Occurred At'),
                    ]),

                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
