<?php

namespace App\Widgets;

use App\Models\Post;
use Filament\Forms;

class BlogRollWidget extends Widget
{
    public static function handle(): string
    {
        return 'blog_roll';
    }

    public static function label(): string
    {
        return 'Blog Roll';
    }

    public static function configSchema(): array
    {
        return [
            Forms\Components\TextInput::make('config.heading')
                ->label('Heading')
                ->maxLength(255),

            Forms\Components\TextInput::make('config.limit')
                ->label('Limit')
                ->numeric()
                ->default(5),

            Forms\Components\Toggle::make('config.show_excerpt')
                ->label('Show Excerpt')
                ->default(true),
        ];
    }

    public function resolveData(array $config): array
    {
        $limit = isset($config['limit']) && $config['limit'] !== '' && $config['limit'] !== null
            ? (int) $config['limit']
            : 5;

        $query = Post::where('is_published', true)
            ->orderBy('published_at', 'desc')
            ->limit($limit);

        return $query->get()->map(fn (Post $post) => [
            'id'           => $post->id,
            'title'        => $post->title,
            'slug'         => $post->slug,
            'excerpt'      => $post->excerpt,
            'published_at' => $post->published_at,
        ])->all();
    }

    public function view(): string
    {
        return 'widgets.blog-roll';
    }
}
