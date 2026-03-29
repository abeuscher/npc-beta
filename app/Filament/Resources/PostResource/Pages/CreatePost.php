<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
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
        $textBlock = WidgetType::where('handle', 'text_block')->first();
        $blogPager = WidgetType::where('handle', 'blog_pager')->first();

        if ($textBlock) {
            PageWidget::create([
                'page_id'        => $this->record->id,
                'widget_type_id' => $textBlock->id,
                'label'          => 'Content',
                'config'         => ['content' => ''],
                'sort_order'     => 1,
                'is_active'      => true,
            ]);
        }

        if ($blogPager) {
            PageWidget::create([
                'page_id'        => $this->record->id,
                'widget_type_id' => $blogPager->id,
                'label'          => 'Post Pager',
                'config'         => [],
                'sort_order'     => 2,
                'is_active'      => true,
            ]);
        }
    }
}
