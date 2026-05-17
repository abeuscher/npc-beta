<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Actions\ImportBundleAction;
use App\Filament\Resources\TemplateResource;
use App\Models\Template;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListTemplates extends ListRecords
{
    protected static string $resource = TemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\CreateAction::make('createBlank')
                    ->label('Blank Page Template'),

                Actions\Action::make('createFromDefault')
                    ->label('New Page Template From Default')
                    ->icon('heroicon-o-document-duplicate')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->maxLength(1000),
                    ])
                    ->action(function (array $data) {
                        $default = Template::page()->where('is_default', true)->first();

                        if (! $default) {
                            Notification::make()->title('No default template found')->danger()->send();
                            return;
                        }

                        // Copy inheritable fields from the default. Colour is
                        // no longer per-template (session-297 relocation) — it
                        // lives in the site-wide Theme palette now.
                        $template = Template::create([
                            'name'             => $data['name'],
                            'type'             => 'page',
                            'description'      => $data['description'] ?? null,
                            'is_default'       => false,
                            'custom_scss'      => $default->custom_scss,
                            'created_by'       => auth()->id(),
                        ]);

                        // Copy header widgets into a new system page
                        if ($default->header_page_id) {
                            $headerPage = $template->createChromePage('header', $default->header_page_id);
                            $template->update(['header_page_id' => $headerPage->id]);
                        }

                        // Copy footer widgets into a new system page
                        if ($default->footer_page_id) {
                            $footerPage = $template->createChromePage('footer', $default->footer_page_id);
                            $template->update(['footer_page_id' => $footerPage->id]);
                        }

                        Notification::make()
                            ->title("Template \"{$template->name}\" created from default")
                            ->success()
                            ->send();

                        $this->redirect(EditPageTemplate::getUrl(['record' => $template]));
                    }),
            ])
                ->label('New Page Template')
                ->button()
                ->icon('heroicon-m-plus'),

            ImportBundleAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'content' => Tab::make('Content Templates')
                ->modifyQueryUsing(fn ($query) => $query->where('type', 'content'))
                ->badge(Template::content()->count()),

            'page' => Tab::make('Page Templates')
                ->modifyQueryUsing(fn ($query) => $query->where('type', 'page'))
                ->badge(Template::page()->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'content';
    }
}
