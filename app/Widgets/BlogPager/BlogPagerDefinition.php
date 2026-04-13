<?php

namespace App\Widgets\BlogPager;

use App\Widgets\Contracts\WidgetDefinition;

class BlogPagerDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'blog_pager';
    }

    public function label(): string
    {
        return 'Blog Post Pager';
    }

    public function description(): string
    {
        return 'Previous/next navigation links between blog posts.';
    }

    public function category(): array
    {
        return ['blog'];
    }

    public function allowedPageTypes(): ?array
    {
        return ['post'];
    }

    public function schema(): array
    {
        return [];
    }

    public function defaults(): array
    {
        return [];
    }
}
