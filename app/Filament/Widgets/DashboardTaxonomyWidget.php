<?php

namespace App\Filament\Widgets;

use App\Models\HelpArticle;
use Filament\Widgets\Widget;
use Illuminate\Support\Str;

class DashboardTaxonomyWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-taxonomy-widget';

    protected static ?int $sort = 10;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->check();
    }

    public function getArticleHtml(): string
    {
        $article = HelpArticle::where('slug', 'taxonomy')->first();

        if (! $article) {
            return '<p class="text-sm text-gray-400 italic">Taxonomy document not found. Run <code>php artisan help:sync</code>.</p>';
        }

        return Str::markdown($article->content);
    }
}
