<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\Template;
use App\Models\WidgetType;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    public ?string $contentTemplateId = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->contentTemplateId = $data['content_template_id'] ?? null;
        unset($data['content_template_id']);

        $blogPrefix = config('site.blog_prefix', 'news');
        $base       = $blogPrefix . '/' . Str::slug($data['title']);
        $slug       = $base;
        $i          = 2;

        while (Page::where('slug', $slug)->exists()) {
            $slug = $blogPrefix . '/' . Str::slug($data['title']) . '-' . $i++;
        }

        $data['slug'] = $slug;
        $data['type'] = 'post';

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
