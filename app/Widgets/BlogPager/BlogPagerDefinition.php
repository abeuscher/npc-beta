<?php

namespace App\Widgets\BlogPager;

use App\Support\DateFormat;
use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\DataContract;

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
            ['key' => 'prev_template', 'type' => 'richtext', 'label' => 'Previous link template', 'group' => 'content', 'default' => '<span class="pager-link__title">&larr; {{item.title}}</span><small>{{item.author_name}} | {{item.post_date}}</small>'],
            ['key' => 'next_template', 'type' => 'richtext', 'label' => 'Next link template', 'group' => 'content', 'default' => '<span class="pager-link__title">{{item.title}} &rarr;</span><small>{{item.author_name}} | {{item.post_date}}</small>'],
            ['key' => 'date_format',   'type' => 'select',   'label' => 'Date format', 'options' => DateFormat::postDateOptions(), 'default' => DateFormat::LONG_DATE, 'group' => 'content'],
        ];
    }

    public function defaults(): array
    {
        return [
            'prev_template' => '<span class="pager-link__title">&larr; {{item.title}}</span><small>{{item.author_name}} | {{item.post_date}}</small>',
            'next_template' => '<span class="pager-link__title">{{item.title}} &rarr;</span><small>{{item.author_name}} | {{item.post_date}}</small>',
            'date_format'   => DateFormat::LONG_DATE,
        ];
    }

    public function dataContract(array $config): ?DataContract
    {
        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SYSTEM_MODEL,
            fields: ['id', 'title', 'slug', 'url', 'post_date', 'image', 'author_name'],
            model: 'post',
            formatHints: ['post_date' => $config['date_format'] ?? DateFormat::LONG_DATE],
        );
    }
}
