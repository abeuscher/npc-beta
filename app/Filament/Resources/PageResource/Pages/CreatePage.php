<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\Template;
use App\Models\WidgetType;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    public ?string $contentTemplateId = null;

    public function getTitle(): string
    {
        $type = data_get($this->data, 'type', 'default');

        return match ($type) {
            'member' => 'New Member Page',
            'post'   => 'New Post',
            'event'  => 'New Event Landing Page',
            default  => 'New Page',
        };
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Extract content template ID and remove from data (not a real column)
        $this->contentTemplateId = $data['content_template_id'] ?? null;
        unset($data['content_template_id']);

        $base = Str::slug($data['title']);
        $slug = $base;
        $i    = 2;

        while (Page::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $data['slug']       = $slug;
        $data['meta_title'] = $data['title'];
        $data['author_id']  = auth()->id();

        $type = $data['type'] ?? 'default';
        $autoPublish = match ($type) {
            'post'  => SiteSetting::get('auto_publish_posts', 'true') === 'true',
            default => SiteSetting::get('auto_publish_pages', 'true') === 'true',
        };

        if (!isset($data['status'])) {
            $data['status']       = $autoPublish ? 'published' : 'draft';
            $data['published_at'] = $autoPublish ? now() : null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->contentTemplateId) {
            return;
        }

        $template = Template::content()->find($this->contentTemplateId);

        if (! $template || empty($template->definition)) {
            return;
        }

        $this->hydrateWidgets($this->record, $template->definition);
    }

    private function hydrateWidgets(Page $page, array $definitions, ?string $parentWidgetId = null): void
    {
        foreach ($definitions as $def) {
            $widgetType = WidgetType::where('handle', $def['handle'] ?? '')->first();

            if (! $widgetType) {
                continue;
            }

            $widget = PageWidget::create([
                'page_id'          => $page->id,
                'parent_widget_id' => $parentWidgetId,
                'column_index'     => $def['column_index'] ?? null,
                'widget_type_id'   => $widgetType->id,
                'label'            => $def['label'] ?? null,
                'config'           => $def['config'] ?? [],
                'query_config'     => $def['query_config'] ?? [],
                'style_config'     => $def['style_config'] ?? [],
                'sort_order'       => $def['sort_order'] ?? 0,
                'is_active'        => $def['is_active'] ?? true,
            ]);

            // Recursively create nested children (for column widgets)
            if (! empty($def['children'])) {
                $this->hydrateWidgets($page, $def['children'], $widget->id);
            }
        }
    }
}
