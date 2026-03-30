<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Filament\Pages\Settings\CmsSettingsPage;
use App\Models\SiteSetting;
use App\Rules\ValidHtmlSnippet;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    public function getTitle(): string
    {
        $typeLabel = match ($this->record->type) {
            'system' => 'System Page',
            'member' => 'Member Page',
            'post'   => 'Post',
            'event'  => 'Event Landing Page',
            default  => 'Page',
        };

        return 'Edit ' . $typeLabel;
    }

    protected function getHeaderActions(): array
    {
        $base = rtrim(SiteSetting::get('base_url', config('app.url')), '/');
        $path = $this->record->slug === 'home' ? '/' : '/' . $this->record->slug;
        $url  = $base . $path;

        return [
            Actions\Action::make('publicUrl')
                ->label($url)
                ->url($url)
                ->openUrlInNewTab()
                ->link()
                ->color('primary')
                ->extraAttributes([
                    'style' => 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:40vw;display:block;font-family:monospace;font-size:0.8125rem;',
                    'title' => $url,
                ]),

            Actions\DeleteAction::make()
                ->hidden(fn () => $this->record->type === 'system'),

            Actions\ActionGroup::make([
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
            ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip('More actions'),
        ];
    }
}
