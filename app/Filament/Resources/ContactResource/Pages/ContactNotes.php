<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Resources\ContactResource;
use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Note;
use App\Models\User;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ContactNotes extends Page implements HasActions
{
    use InteractsWithActions;

    protected static string $resource = ContactResource::class;

    protected static string $view = 'filament.pages.contact-notes';

    public Contact $record;

    public string $filter = 'all';

    public function mount(Contact|int|string $record): void
    {
        $this->record = $record instanceof Contact ? $record : Contact::findOrFail($record);
    }

    public function getTitle(): string
    {
        return $this->record->display_name . ' — Timeline';
    }

    public function getBreadcrumbs(): array
    {
        return [
            ContactResource::getUrl('index') => 'Contacts',
            ContactResource::getUrl('edit', ['record' => $this->record]) => 'Edit',
            'Timeline',
        ];
    }

    public function getTimeline(): \Illuminate\Support\Collection
    {
        $notes = $this->filter !== 'activity'
            ? Note::query()
                ->with(['author', 'importSource'])
                ->where('notable_type', Contact::class)
                ->where('notable_id', $this->record->id)
                ->get()
            : collect();

        $logs = $this->filter !== 'notes'
            ? ActivityLog::where('subject_type', Contact::class)
                ->where('subject_id', $this->record->id)
                ->latest()
                ->get()
            : collect();

        $adminIds = $logs->where('actor_type', 'admin')->pluck('actor_id')->filter()->unique();
        $adminUsers = $adminIds->isNotEmpty()
            ? User::whereIn('id', $adminIds)->get()->keyBy('id')
            : collect();

        $noteItems = $notes->map(fn ($n) => (object) [
            '_type'              => 'note',
            'id'                 => $n->id,
            'body'               => $n->body,
            'author_name'        => $n->author?->name ?? 'Unknown',
            'occurred_at'        => $n->occurred_at,
            'created_at'         => $n->created_at,
            'import_source_name' => $n->importSource?->name,
            'import_source_url'  => $n->importSource
                ? \App\Filament\Pages\ImportHistoryPage::getUrl(['source' => $n->importSource->id])
                : null,
        ]);

        $logItems = $logs->map(fn ($l) => (object) [
            '_type'        => 'activity',
            'id'           => $l->id,
            'event'        => $l->event,
            'description'  => $l->description,
            'meta'         => $l->meta ?? [],
            'actor_label'  => match ($l->actor_type) {
                'admin'  => $adminUsers->has($l->actor_id) ? 'by ' . $adminUsers[$l->actor_id]->name : 'by admin',
                'portal' => 'by portal member',
                default  => 'by system',
            },
            'created_at'   => $l->created_at,
        ]);

        $merged = match ($this->filter) {
            'notes'    => $noteItems,
            'activity' => $logItems,
            default    => $noteItems->concat($logItems),
        };

        return $merged->sortByDesc('created_at')->take(200)->values();
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
                ->hidden(fn () => ! auth()->user()?->can('create_note'))
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
                    abort_unless(auth()->user()?->can('create_note'), 403);

                    $this->record->notes()->create($data);

                    Notification::make()
                        ->success()
                        ->title('Note created.')
                        ->send();
                }),

            Actions\ActionGroup::make([
                Actions\Action::make('filter_all')
                    ->label('Show all')
                    ->icon(fn () => $this->filter === 'all' ? 'heroicon-m-check' : null)
                    ->action(fn () => $this->filter = 'all'),

                Actions\Action::make('filter_notes')
                    ->label('Contact notes')
                    ->icon(fn () => $this->filter === 'notes' ? 'heroicon-m-check' : null)
                    ->action(fn () => $this->filter = 'notes'),

                Actions\Action::make('filter_activity')
                    ->label('Activity log')
                    ->icon(fn () => $this->filter === 'activity' ? 'heroicon-m-check' : null)
                    ->action(fn () => $this->filter = 'activity'),
            ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray'),
        ];
    }

    public function editNoteAction(): Action
    {
        return Action::make('editNote')
            ->hidden(fn () => ! auth()->user()?->can('update_note'))
            ->modalHeading('Edit Note')
            ->modalWidth('lg')
            ->fillForm(function (array $arguments): array {
                $note = Note::where('id', $arguments['note'])
                    ->where('notable_type', Contact::class)
                    ->where('notable_id', $this->record->id)
                    ->firstOrFail();

                return [
                    'body'        => $note->body,
                    'occurred_at' => $note->occurred_at,
                ];
            })
            ->form([
                Forms\Components\Textarea::make('body')
                    ->label('Note')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),

                Forms\Components\DateTimePicker::make('occurred_at')
                    ->label('Occurred At'),
            ])
            ->action(function (array $data, array $arguments): void {
                abort_unless(auth()->user()?->can('update_note'), 403);

                Note::where('id', $arguments['note'])
                    ->where('notable_type', Contact::class)
                    ->where('notable_id', $this->record->id)
                    ->firstOrFail()
                    ->update($data);

                Notification::make()
                    ->success()
                    ->title('Note updated.')
                    ->send();
            });
    }

    public function deleteNoteAction(): Action
    {
        return Action::make('deleteNote')
            ->hidden(fn () => ! auth()->user()?->can('delete_note'))
            ->requiresConfirmation()
            ->modalHeading('Delete Note')
            ->modalDescription('Are you sure you want to delete this note? This cannot be undone.')
            ->color('danger')
            ->action(function (array $arguments): void {
                abort_unless(auth()->user()?->can('delete_note'), 403);

                Note::where('id', $arguments['note'])
                    ->where('notable_type', Contact::class)
                    ->where('notable_id', $this->record->id)
                    ->firstOrFail()
                    ->delete();

                Notification::make()
                    ->success()
                    ->title('Note deleted.')
                    ->send();
            });
    }
}
