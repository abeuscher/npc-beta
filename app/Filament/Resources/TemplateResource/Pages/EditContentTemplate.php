<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Resources\TemplateResource;
use App\Services\ImportExport\ContentExporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditContentTemplate extends ReadOnlyAwareEditRecord
{
    protected static string $resource = TemplateResource::class;

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
