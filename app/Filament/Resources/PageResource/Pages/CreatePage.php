<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\Template;
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
        $id = $this->contentTemplateId;

        if (! $id || $id === 'none') {
            return;
        }

        $template = Template::content()->find($id);

        if ($template) {
            PageWidget::copyOwnedStack($template, $this->record);
        }
    }
}
