<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Resources\TemplateResource;
use App\Jobs\ExportBundleJob;
use App\Services\ImportExport\ContentExporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditContentTemplate extends ReadOnlyAwareEditRecord
{
    protected static string $resource = TemplateResource::class;

    protected static string $view = 'filament.resources.template-resource.pages.edit-content-template';

    public function getTitle(): string
    {
        return 'Edit Content Template';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),

            Forms\Components\Textarea::make('description')
                ->rows(3)
                ->maxLength(1000),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportTemplate')
                ->label('Export Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                ->action(function (): StreamedResponse {
                    abort_unless(auth()->user()?->can('update_page'), 403);

                    $bundle   = app(ContentExporter::class)->exportTemplates([$this->record->id]);
                    $nameSlug = Str::slug($this->record->name);
                    $filename = now()->format('Ymd-His') . '-template-' . $nameSlug . '.json';

                    return response()->streamDownload(
                        fn () => print(json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                        $filename,
                        ['Content-Type' => 'application/json'],
                    );
                }),

            Actions\Action::make('exportTemplateWithMedia')
                ->label('Export Template with media (zip)')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                ->action(function (): void {
                    abort_unless(auth()->user()?->can('update_page'), 403);

                    ExportBundleJob::dispatch(
                        'templates',
                        [$this->record->id],
                        (int) auth()->id(),
                        'template-' . Str::slug($this->record->name),
                    );

                    Notification::make()
                        ->title('Export queued')
                        ->body('Your bundle is being built in the background. You will be notified when it is ready to download.')
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make()
                ->hidden(fn () => $this->record->is_default),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            TemplateResource::getUrl() => 'Templates',
            'Edit Content Template',
        ];
    }
}
