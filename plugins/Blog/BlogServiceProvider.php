<?php

namespace Plugins\Blog;

use App\Plugins\AdminContribution;
use App\Plugins\PluginAdminRegistry;
use App\Services\WidgetRegistry;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Plugins\Blog\Widgets\BlogListing\BlogListingDefinition;
use Plugins\Blog\Widgets\BlogPager\BlogPagerDefinition;

class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Admin socket (docs/plugin-contract.md surfaces 3/4): the Post
        // resource and its four pages are discovered from the plugin. No
        // permission names declared — view_any_post and its siblings are part
        // of core's PermissionSeeder role matrix (the extracted-vertical
        // nuance, surface 3). No capability declared — nothing consumes blog
        // presence; the presence signal, were one ever needed, is route
        // presence (Route::has('posts.index')).
        $this->app->make(PluginAdminRegistry::class)->register(new AdminContribution(
            plugin: 'blog',
            resourcesPath: __DIR__ . '/Filament/Resources',
            resourcesNamespace: 'Plugins\\Blog\\Filament\\Resources',
        ));
    }

    public function boot(): void
    {
        View::addNamespace('plugin-blog', __DIR__ . '/resources/views');
        View::addNamespace('plugin-blog-widgets', __DIR__ . '/Widgets');

        $widgets = $this->app->make(WidgetRegistry::class);
        $widgets->register(new BlogListingDefinition());
        $widgets->register(new BlogPagerDefinition());

        // The two blog_prefix routes register ahead of core's page-slug
        // catch-all — provider boot() runs before routes/web.php loads
        // (contract surface 4, front-of-house half).
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }
}
