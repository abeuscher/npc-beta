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
        return [
            ['key' => 'prev_template', 'type' => 'richtext', 'label' => 'Previous link template', 'group' => 'content', 'default' => '<span class="pager-link__title">&larr; {{title}}</span><small>{{author}} | {{date}}</small>'],
            ['key' => 'next_template', 'type' => 'richtext', 'label' => 'Next link template', 'group' => 'content', 'default' => '<span class="pager-link__title">{{title}} &rarr;</span><small>{{author}} | {{date}}</small>'],
        ];
    }

    public function defaults(): array
    {
        return [
            'prev_template' => '<span class="pager-link__title">&larr; {{title}}</span><small>{{author}} | {{date}}</small>',
            'next_template' => '<span class="pager-link__title">{{title}} &rarr;</span><small>{{author}} | {{date}}</small>',
        ];
    }
}
