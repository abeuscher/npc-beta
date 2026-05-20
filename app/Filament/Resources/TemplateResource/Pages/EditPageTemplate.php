<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Concerns\HasRecordDetailSubNavigation;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;
use App\Filament\Resources\TemplateResource;
use App\Jobs\ExportBundleJob;
use App\Models\Template;
use App\Services\ImportExport\ContentExporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditPageTemplate extends ReadOnlyAwareEditRecord
{
    use HasRecordDetailSubNavigation;

    protected static string $resource = TemplateResource::class;

    protected static string $view = 'filament.resources.template-resource.pages.edit-page-template';

    protected static ?string $title = 'Label';

    public function getTitle(): string
    {
        return $this->record->name ?? 'Edit Page Template';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Group::make([
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(2)
                    ->maxLength(1000),
            ])->columnSpan(6),

            Forms\Components\Group::make([
                Forms\Components\Select::make('scheme')
                    ->label('Colour scheme')
                    ->options(\App\Services\TemplateAppearanceResolver::SCHEME_LABELS)
                    ->default(\App\Services\TemplateAppearanceResolver::DEFAULT_SCHEME)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->helperText('Recolours this template\'s content region only — it selects a vetted Theme scheme, it never edits individual colours. The standard header and footer keep their Theme colours; a dark scheme does not restyle them.'),

                Forms\Components\Checkbox::make('no_header')
                    ->label('No header')
                    ->helperText('Suppress the header entirely for pages using this template. Wins even if a custom header page is set. Off = inherit the Theme header.'),

                Forms\Components\Checkbox::make('no_footer')
                    ->label('No footer')
                    ->helperText('Suppress the footer entirely. Wins even if a custom footer page is set. Off = inherit the Theme footer.'),
            ])->columnSpan(6),
        ])->columns(12);
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

                    $bundle    = app(ContentExporter::class)->exportTemplates([$this->record->id]);
                    $nameSlug  = Str::slug($this->record->name);
                    $filename  = now()->format('Ymd-His') . '-template-' . $nameSlug . '.json';

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
                        ['with_media' => true],
                    );

                    Notification::make()
                        ->title('Export queued')
                        ->body('Your bundle is being built in the background. You will be notified when it is ready to download.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('exportTemplateWithTheme')
                ->label('Export Template with theme (JSON)')
                ->icon('heroicon-o-paint-brush')
                ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                ->requiresConfirmation()
                ->modalHeading('Export Template with theme')
                ->modalDescription('The exported bundle will include this site\'s theme colours, typography, and button styles alongside the template. When imported elsewhere, the importer will surface a "Replace site theme" prompt — opting in will overwrite the target site\'s Theme editor settings.')
                ->modalSubmitActionLabel('Export with theme')
                ->action(function (): StreamedResponse {
                    abort_unless(auth()->user()?->can('update_page'), 403);

                    $bundle   = app(ContentExporter::class)->exportTemplates([$this->record->id], ['with_design' => true]);
                    $nameSlug = Str::slug($this->record->name);
                    $filename = now()->format('Ymd-His') . '-template-' . $nameSlug . '-with-theme.json';

                    return response()->streamDownload(
                        fn () => print(json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                        $filename,
                        ['Content-Type' => 'application/json'],
                    );
                }),

            Actions\Action::make('exportTemplateWithThemeAndMedia')
                ->label('Export Template with theme & media (zip)')
                ->icon('heroicon-o-rectangle-stack')
                ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                ->requiresConfirmation()
                ->modalHeading('Export Template with theme & media')
                ->modalDescription('The exported bundle will include this site\'s theme colours, typography, and button styles plus all referenced media files alongside the template. When imported elsewhere, the importer will surface a "Replace site theme" prompt — opting in will overwrite the target site\'s Theme editor settings.')
                ->modalSubmitActionLabel('Export with theme & media')
                ->action(function (): void {
                    abort_unless(auth()->user()?->can('update_page'), 403);

                    ExportBundleJob::dispatch(
                        'templates',
                        [$this->record->id],
                        (int) auth()->id(),
                        'template-' . Str::slug($this->record->name) . '-full',
                        ['with_design' => true, 'with_media' => true],
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
            'Edit Page Template',
            'Label',
        ];
    }

    public function getIsNonDefaultProperty(): bool
    {
        return ! $this->record->is_default;
    }

    public function getDefaultTemplateProperty(): ?Template
    {
        return Template::page()->where('is_default', true)->first();
    }

    protected function subNavigationEntryPage(): ?string
    {
        return EditPageTemplate::class;
    }

    protected function additionalSubNavigationPages(): array
    {
        return [
            EditPageTemplateScss::class,
        ];
    }

    protected function recordDetailViewSubPageClass(): ?string
    {
        return EditPageTemplateChrome::class;
    }
}
