<?php

namespace App\Filament\Concerns;

use App\Filament\Resources\NoteResource;
use App\Models\ActivityLog;
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
use Illuminate\Support\Str;

abstract class RecordTimelinePage extends Page implements HasActions
{
    use InteractsWithActions;

    protected static string $view = 'filament.pages.record-timeline';

    public string $filter = 'all';

    public string $typeFilter = 'all';

    public string $viewMode = 'collapsed';

    abstract protected function notableType(): string;

    abstract protected function recordResourceClass(): string;

    abstract public function getRecordTitle(): string;

    public function getTitle(): string
    {
        return $this->getRecordTitle() . ' — Timeline';
    }

    public function getBreadcrumbs(): array
    {
        $resource = $this->recordResourceClass();

        return [
            $resource::getUrl('index') => $resource::getPluralModelLabel(),
            $resource::getUrl('edit', ['record' => $this->record]) => 'Edit',
            'Timeline',
        ];
    }

    public function getTimeline(): \Illuminate\Support\Collection
    {
        $notableType = $this->notableType();

        $notes = $this->filter !== 'activity'
            ? Note::query()
                ->with(['author', 'importSource'])
                ->where('notable_type', $notableType)
                ->where('notable_id', $this->record->id)
                ->when($this->typeFilter !== 'all', fn ($q) => $q->where('type', $this->typeFilter))
                ->get()
            : collect();

        $logs = $this->filter !== 'notes'
            ? ActivityLog::where('subject_type', $notableType)
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
            'type'               => $n->type,
            'subject'            => $n->subject,
            'status'             => $n->status,
            'body'               => $n->body,
            'outcome'            => $n->outcome,
            'duration_minutes'   => $n->duration_minutes,
            'follow_up_at'       => $n->follow_up_at,
            'meta'               => is_array($n->meta) ? $n->meta : [],
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

    public function getNonCanonicalTypes(): array
    {
        $canonical = array_keys(NoteResource::TYPE_OPTIONS);

        return Note::query()
            ->where('notable_type', $this->notableType())
            ->where('notable_id', $this->record->id)
            ->whereNotIn('type', $canonical)
            ->distinct()
            ->pluck('type')
            ->filter()
            ->values()
            ->all();
    }

    public function toggleViewMode(): void
    {
        $this->viewMode = $this->viewMode === 'collapsed' ? 'expanded' : 'collapsed';
    }

    protected function getHeaderActions(): array
    {
        $resource = $this->recordResourceClass();

        return [
            Actions\Action::make('back_to_record')
                ->label('← Back to ' . Str::lower($resource::getModelLabel()))
                ->color('secondary')
                ->url($resource::getUrl('edit', ['record' => $this->record])),

            Actions\Action::make('toggle_view_mode')
                ->label(fn () => $this->viewMode === 'collapsed' ? 'Expand all' : 'Collapse all')
                ->icon(fn () => $this->viewMode === 'collapsed'
                    ? 'heroicon-o-arrows-pointing-out'
                    : 'heroicon-o-arrows-pointing-in')
                ->color('gray')
                ->action(fn () => $this->toggleViewMode()),

            Actions\Action::make('create_note')
                ->label('Create note')
                ->icon('heroicon-o-plus')
                ->hidden(fn () => ! auth()->user()?->can('create_note'))
                ->modalHeading('Create Note')
                ->modalWidth('2xl')
                ->form([
                    ...NoteResource::coreFormSchema(),

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
                    ->label('Notes only')
                    ->icon(fn () => $this->filter === 'notes' ? 'heroicon-m-check' : null)
                    ->action(fn () => $this->filter = 'notes'),

                Actions\Action::make('filter_activity')
                    ->label('Activity log')
                    ->icon(fn () => $this->filter === 'activity' ? 'heroicon-m-check' : null)
                    ->action(fn () => $this->filter = 'activity'),
            ])
                ->label('Source')
                ->icon('heroicon-m-funnel')
                ->color('gray')
                ->button(),

            Actions\ActionGroup::make($this->typeFilterActions())
                ->label(fn () => 'Type: ' . $this->typeFilterLabel())
                ->icon('heroicon-m-tag')
                ->color('gray')
                ->button()
                ->hidden(fn () => $this->filter === 'activity'),
        ];
    }

    protected function typeFilterActions(): array
    {
        $actions = [
            Actions\Action::make('type_filter_all')
                ->label('All types')
                ->icon(fn () => $this->typeFilter === 'all' ? 'heroicon-m-check' : null)
                ->action(fn () => $this->typeFilter = 'all'),
        ];

        foreach (NoteResource::TYPE_OPTIONS as $value => $label) {
            $actions[] = Actions\Action::make('type_filter_' . $value)
                ->label($label)
                ->icon(fn () => $this->typeFilter === $value ? 'heroicon-m-check' : null)
                ->action(fn () => $this->typeFilter = $value);
        }

        foreach ($this->getNonCanonicalTypes() as $value) {
            $actions[] = Actions\Action::make('type_filter_' . Str::slug($value, '_'))
                ->label($value)
                ->icon(fn () => $this->typeFilter === $value ? 'heroicon-m-check' : null)
                ->action(fn () => $this->typeFilter = $value);
        }

        return $actions;
    }

    protected function typeFilterLabel(): string
    {
        if ($this->typeFilter === 'all') {
            return 'All';
        }

        return NoteResource::TYPE_OPTIONS[$this->typeFilter] ?? $this->typeFilter;
    }

    public function editNoteAction(): Action
    {
        $notableType = $this->notableType();

        return Action::make('editNote')
            ->hidden(fn () => ! auth()->user()?->can('update_note'))
            ->modalHeading('Edit Note')
            ->modalWidth('2xl')
            ->fillForm(function (array $arguments) use ($notableType): array {
                $note = Note::where('id', $arguments['note'])
                    ->where('notable_type', $notableType)
                    ->where('notable_id', $this->record->id)
                    ->firstOrFail();

                return [
                    'type'             => $note->type,
                    'subject'          => $note->subject,
                    'status'           => $note->status,
                    'body'             => $note->body,
                    'occurred_at'      => $note->occurred_at,
                    'follow_up_at'     => $note->follow_up_at,
                    'outcome'          => $note->outcome,
                    'duration_minutes' => $note->duration_minutes,
                ];
            })
            ->form(NoteResource::coreFormSchema())
            ->action(function (array $data, array $arguments) use ($notableType): void {
                abort_unless(auth()->user()?->can('update_note'), 403);

                Note::where('id', $arguments['note'])
                    ->where('notable_type', $notableType)
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
        $notableType = $this->notableType();

        return Action::make('deleteNote')
            ->hidden(fn () => ! auth()->user()?->can('delete_note'))
            ->requiresConfirmation()
            ->modalHeading('Delete Note')
            ->modalDescription('Are you sure you want to delete this note? This cannot be undone.')
            ->color('danger')
            ->action(function (array $arguments) use ($notableType): void {
                abort_unless(auth()->user()?->can('delete_note'), 403);

                Note::where('id', $arguments['note'])
                    ->where('notable_type', $notableType)
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
