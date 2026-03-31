<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Resources\TemplateResource;
use App\Models\Page as PageModel;
use App\Models\PageWidget;
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
                            $headerPage = $this->cloneSystemPage($default->header_page_id, $template, 'header');
                            $template->update(['header_page_id' => $headerPage->id]);
                        }

                        // Copy footer widgets into a new system page
                        if ($default->footer_page_id) {
                            $footerPage = $this->cloneSystemPage($default->footer_page_id, $template, 'footer');
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

    private function cloneSystemPage(string $sourcePageId, Template $template, string $position): PageModel
    {
        $slug = "_{$position}_" . substr($template->id, 0, 8);

        $page = PageModel::create([
            'title'     => ucfirst($position) . ' — ' . $template->name,
            'slug'      => $slug,
            'type'      => 'system',
            'status'    => 'published',
            'author_id' => auth()->id(),
        ]);

        // Copy widgets from the source page
        $this->copyWidgets($sourcePageId, $page->id);

        return $page;
    }

    private function copyWidgets(string $sourcePageId, string $targetPageId, ?string $sourceParentId = null, ?string $targetParentId = null): void
    {
        $widgets = PageWidget::where('page_id', $sourcePageId)
            ->where('parent_widget_id', $sourceParentId)
            ->orderBy('sort_order')
            ->get();

        foreach ($widgets as $widget) {
            $newWidget = PageWidget::create([
                'page_id'          => $targetPageId,
                'parent_widget_id' => $targetParentId,
                'column_index'     => $widget->column_index,
                'widget_type_id'   => $widget->widget_type_id,
                'label'            => $widget->label,
                'config'           => $widget->config,
                'query_config'     => $widget->query_config,
                'style_config'     => $widget->style_config,
                'sort_order'       => $widget->sort_order,
                'is_active'        => $widget->is_active,
            ]);

            // Recursively copy children (column widget children)
            $this->copyWidgets($sourcePageId, $targetPageId, $widget->id, $newWidget->id);
        }
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
