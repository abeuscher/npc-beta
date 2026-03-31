<?php

namespace App\Filament\Resources\TemplateResource\Pages;

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

                        // Copy all inheritable fields from the default
                        $template = Template::create([
                            'name'             => $data['name'],
                            'type'             => 'page',
                            'description'      => $data['description'] ?? null,
                            'is_default'       => false,
                            'primary_color'    => $default->primary_color,
                            'heading_font'     => $default->heading_font,
                            'body_font'        => $default->body_font,
                            'header_bg_color'  => $default->header_bg_color,
                            'footer_bg_color'  => $default->footer_bg_color,
                            'nav_link_color'   => $default->nav_link_color,
                            'nav_hover_color'  => $default->nav_hover_color,
                            'nav_active_color' => $default->nav_active_color,
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
