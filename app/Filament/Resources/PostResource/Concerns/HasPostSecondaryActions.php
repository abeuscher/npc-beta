<?php

namespace App\Filament\Resources\PostResource\Concerns;

use App\Filament\Pages\Settings\CmsSettingsPage;
use App\Filament\Resources\PostResource;
use App\Jobs\ExportBundleJob;
use App\Models\PageWidget;
use App\Models\Template;
use App\Rules\ValidHtmlSnippet;
use App\Services\ImportExport\ContentExporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The "More actions" ellipsis group for a Post record (duplicate, export
 * variants, save as content template, header/footer snippets, delete). Shared
 * by the page-builder editor and the post-details view so both surfaces expose
 * the same secondary actions without divergence — mirrors
 * PageResource\Concerns\HasPageSecondaryActions.
 */
trait HasPostSecondaryActions
{
    protected function postSecondaryActionsGroup(): Actions\ActionGroup
    {
        return Actions\ActionGroup::make([
            Actions\Action::make('duplicatePost')
                ->label('Duplicate Post')
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                ->requiresConfirmation()
                ->modalHeading('Duplicate post')
                ->modalDescription('Creates a draft copy of this post, including its blocks. The copy opens in the editor.')
                ->modalSubmitActionLabel('Duplicate')
                ->action(function () {
                    abort_unless(auth()->user()?->can('update_page'), 403);

                    $copy = $this->record->duplicate();

                    Notification::make()
                        ->title('Post duplicated')
                        ->body('A draft copy was created. You are now editing the copy.')
                        ->success()
                        ->send();

                    return redirect(PostResource::getUrl('edit', ['record' => $copy]));
                }),

            Actions\Action::make('exportPost')
                ->label('Export Post')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                ->action(function (): StreamedResponse {
                    abort_unless(auth()->user()?->can('update_page'), 403);

                    $bundle   = app(ContentExporter::class)->exportPages([$this->record->id]);
                    $filename = now()->format('Ymd-His') . '-post-' . $this->record->slug . '.json';

                    return response()->streamDownload(
                        fn () => print(json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                        $filename,
                        ['Content-Type' => 'application/json'],
                    );
                }),

            Actions\Action::make('exportPostWithMedia')
                ->label('Export Post with media (zip)')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                ->action(function (): void {
                    abort_unless(auth()->user()?->can('update_page'), 403);

                    ExportBundleJob::dispatch(
                        'pages',
                        [$this->record->id],
                        (int) auth()->id(),
                        'post-' . $this->record->slug,
                        ['with_media' => true],
                    );

                    Notification::make()
                        ->title('Export queued')
                        ->body('Your bundle is being built in the background. You will be notified when it is ready to download.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('exportPostWithTheme')
                ->label('Export Post with theme (JSON)')
                ->icon('heroicon-o-paint-brush')
                ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                ->requiresConfirmation()
                ->modalHeading('Export Post with theme')
                ->modalDescription('The exported bundle will include this site\'s theme colours, typography, and button styles alongside the post. When imported elsewhere, the importer will surface a "Replace site theme" prompt — opting in will overwrite the target site\'s Theme editor settings.')
                ->modalSubmitActionLabel('Export with theme')
                ->action(function (): StreamedResponse {
                    abort_unless(auth()->user()?->can('update_page'), 403);

                    $bundle   = app(ContentExporter::class)->exportPages([$this->record->id], ['with_design' => true]);
                    $filename = now()->format('Ymd-His') . '-post-' . $this->record->slug . '-with-theme.json';

                    return response()->streamDownload(
                        fn () => print(json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                        $filename,
                        ['Content-Type' => 'application/json'],
                    );
                }),

            Actions\Action::make('exportPostWithThemeAndMedia')
                ->label('Export Post with theme & media (zip)')
                ->icon('heroicon-o-rectangle-stack')
                ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                ->requiresConfirmation()
                ->modalHeading('Export Post with theme & media')
                ->modalDescription('The exported bundle will include this site\'s theme colours, typography, and button styles plus all referenced media files alongside the post. When imported elsewhere, the importer will surface a "Replace site theme" prompt — opting in will overwrite the target site\'s Theme editor settings.')
                ->modalSubmitActionLabel('Export with theme & media')
                ->action(function (): void {
                    abort_unless(auth()->user()?->can('update_page'), 403);

                    ExportBundleJob::dispatch(
                        'pages',
                        [$this->record->id],
                        (int) auth()->id(),
                        'post-' . $this->record->slug . '-full',
                        ['with_design' => true, 'with_media' => true],
                    );

                    Notification::make()
                        ->title('Export queued')
                        ->body('Your bundle is being built in the background. You will be notified when it is ready to download.')
                        ->success()
                        ->send();
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

                    if (! $this->record->widgets()->exists() && ! $this->record->layouts()->exists()) {
                        Notification::make()
                            ->title('No widgets to save')
                            ->body('This post has no widgets. Add blocks first.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $template = Template::create([
                        'name'        => $data['template_name'],
                        'type'        => 'content',
                        'description' => $data['template_description'] ?: null,
                        'is_default'  => false,
                        'created_by'  => auth()->id(),
                    ]);

                    PageWidget::copyOwnedStack($this->record, $template);

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
                            'This control is for per-post code snippets only. If you are trying to install Google Tag Manager or any other site-wide scripts, please use the Site Header and Site Footer fields on the <a href="' . CmsSettingsPage::getUrl() . '" class="underline text-primary-600 dark:text-primary-400" target="_blank">CMS Settings Page</a>.'
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
                ->label('Delete Post'),
        ])
            ->icon('heroicon-m-ellipsis-vertical')
            ->tooltip('More actions');
    }
}
