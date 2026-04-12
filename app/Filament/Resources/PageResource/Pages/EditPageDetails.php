<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Filament\Pages\Settings\CmsSettingsPage;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\Template;
use App\Rules\ValidHtmlSnippet;
use App\Services\ImportExport\ContentExporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditPageDetails extends ReadOnlyAwareEditRecord
{
    protected static string $resource = PageResource::class;

    public function getTitle(): string
    {
        return 'Page Details';
    }

    public function form(Form $form): Form
    {
        return $form->schema(
            PageResource::metadataFormSchema(
                type: 'page',
                modelType: 'page',
                tagType: 'page',
                extraTitleFields: [
                    Forms\Components\Placeholder::make('system_slug_display')
                        ->label('Slug')
                        ->content(fn ($record): string => $record?->slug ?? '—')
                        ->helperText('Slug is locked — system page slugs can only be changed via the System Pages Prefix setting.')
                        ->visibleOn('edit')
                        ->hidden(fn (Forms\Get $get): bool => $get('type') !== 'system')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('type')
                        ->label('Page Type')
                        ->options([
                            'default' => 'Web Page',
                            'member'  => 'Member Page',
                        ])
                        ->default('default')
                        ->hiddenOn('edit')
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('type_display')
                        ->label('Page Type')
                        ->content('System Page')
                        ->visibleOn('edit')
                        ->hidden(fn (Forms\Get $get): bool => $get('type') !== 'system')
                        ->columnSpanFull(),
                ],
                templateField: Forms\Components\Select::make('template_id')
                    ->label('Page Template')
                    ->options(fn () => Template::page()->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => Template::page()->where('is_default', true)->value('id'))
                    ->helperText('Header, footer, and styling.'),
                imageFields: [
                    SpatieMediaLibraryFileUpload::make('post_thumbnail')
                        ->label('Thumbnail image')
                        ->helperText('Used in listing widgets and social sharing.')
                        ->collection('post_thumbnail')
                        ->disk('public')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->nullable()
                        ->columnSpanFull(),

                    SpatieMediaLibraryFileUpload::make('og_image')
                        ->label('Open Graph image')
                        ->helperText('Used for social sharing previews.')
                        ->collection('og_image')
                        ->disk('public')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->nullable()
                        ->columnSpanFull(),
                ],
                withSeo: true,
            )
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('editPage')
                ->label('Edit Page')
                ->icon('heroicon-o-pencil-square')
                ->url(fn () => PageResource::getUrl('edit', ['record' => $this->record])),

            Actions\ActionGroup::make([
                Actions\Action::make('exportPage')
                    ->label('Export Page')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                    ->action(function (): StreamedResponse {
                        abort_unless(auth()->user()?->can('update_page'), 403);

                        $bundle   = app(ContentExporter::class)->exportPages([$this->record->id]);
                        $filename = now()->format('Ymd-His') . '-page-' . $this->record->slug . '.json';

                        return response()->streamDownload(
                            fn () => print(json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                            $filename,
                            ['Content-Type' => 'application/json'],
                        );
                    }),

                Actions\Action::make('saveAsContentTemplate')
                    ->label('Save Block Layout as Template')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->hidden(fn () => ! auth()->user()?->can('update_page'))
                    ->form([
                        Forms\Components\TextInput::make('template_name')
                            ->label('Template Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('template_description')
                            ->label('Description')
                            ->rows(2)
                            ->maxLength(1000),
                    ])
                    ->action(function (array $data) {
                        abort_unless(auth()->user()?->can('update_page'), 403);
                        $definition = PageWidget::serializeStack($this->record->id);

                        if (empty($definition)) {
                            Notification::make()
                                ->title('No widgets to save')
                                ->body('This page has no widgets. Add blocks first.')
                                ->warning()
                                ->send();
                            return;
                        }

                        Template::create([
                            'name'        => $data['template_name'],
                            'type'        => 'content',
                            'description' => $data['template_description'] ?: null,
                            'definition'  => $definition,
                            'is_default'  => false,
                            'created_by'  => auth()->id(),
                        ]);

                        Notification::make()
                            ->title("Template saved: {$data['template_name']}")
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Save Block Layout as Content Template')
                    ->modalSubmitActionLabel('Save Template'),

                Actions\Action::make('editSnippets')
                    ->label('Edit Header & Footer Snippets')
                    ->icon('heroicon-o-code-bracket')
                    ->visible(fn () => auth()->user()?->can('edit_page_snippets') ?? false)
                    ->fillForm(fn () => [
                        'head_snippet' => $this->record->head_snippet,
                        'body_snippet' => $this->record->body_snippet,
                    ])
                    ->form([
                        Forms\Components\Placeholder::make('snippet_info')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString(
                                'This control is for per-page code snippets only. If you are trying to install Google Tag Manager or any other site-wide scripts, please use the Site Header and Site Footer fields on the <a href="' . CmsSettingsPage::getUrl() . '" class="underline text-primary-600 dark:text-primary-400" target="_blank">CMS Settings Page</a>.'
                            )),

                        Forms\Components\Textarea::make('head_snippet')
                            ->label('Head snippet (before </head>)')
                            ->rows(4)
                            ->extraInputAttributes(['style' => 'font-family:monospace;font-size:0.85rem;'])
                            ->rules([new ValidHtmlSnippet()]),

                        Forms\Components\Textarea::make('body_snippet')
                            ->label('Body snippet (before </body>)')
                            ->rows(4)
                            ->extraInputAttributes(['style' => 'font-family:monospace;font-size:0.85rem;'])
                            ->rules([new ValidHtmlSnippet()]),
                    ])
                    ->action(function (array $data) {
                        abort_unless(auth()->user()?->can('edit_page_snippets'), 403);
                        $this->record->update([
                            'head_snippet' => $data['head_snippet'],
                            'body_snippet' => $data['body_snippet'],
                        ]);

                        Notification::make()
                            ->title('Snippets saved')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Header & Footer Snippets')
                    ->modalSubmitActionLabel('Save Snippets'),

                Actions\DeleteAction::make()
                    ->label('Delete Page')
                    ->hidden(fn () => $this->record->type === 'system'),
            ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip('More actions'),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ListPages::getUrl() => 'Pages',
            EditPage::getUrl(['record' => $this->record]) => 'Edit Page',
            'Page Details',
        ];
    }
}
