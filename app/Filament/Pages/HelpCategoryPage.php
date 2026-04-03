<?php

namespace App\Filament\Pages;

use App\Models\HelpArticle;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class HelpCategoryPage extends Page
{
    protected static string $view = 'filament.pages.help-category';

    protected static ?string $slug = 'help/category/{category}';

    protected static bool $shouldRegisterNavigation = false;

    public string $category = '';

    public array $articles = [];

    private const VALID_CATEGORIES = ['crm', 'cms', 'finance', 'tools', 'settings', 'general'];

    private const CATEGORY_LABELS = [
        'crm' => 'CRM',
        'cms' => 'CMS',
        'finance' => 'Finance',
        'tools' => 'Tools',
        'settings' => 'Settings',
        'general' => 'General',
    ];

    public static function categoryUrl(string $category): string
    {
        return Filament::getCurrentPanel()->getUrl() . '/help/category/' . $category;
    }

    public static function categoryLabel(string $category): string
    {
        return self::CATEGORY_LABELS[$category] ?? ucfirst($category);
    }

    public function mount(string $category): void
    {
        if (! in_array($category, self::VALID_CATEGORIES, true)) {
            abort(404);
        }

        $this->category = $category;

        $this->articles = HelpArticle::query()
            ->where('category', $category)
            ->orderBy('title')
            ->get()
            ->toArray();
    }

    public function getTitle(): string
    {
        return self::categoryLabel($this->category) . ' Help';
    }

    public function getBreadcrumbs(): array
    {
        return [
            HelpIndexPage::helpUrl() => 'Help',
            self::categoryLabel($this->category),
        ];
    }
}
