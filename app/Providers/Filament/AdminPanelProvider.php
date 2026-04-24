<?php

namespace App\Providers\Filament;

use App\Services\HelpArticleService;
use App\Services\WidgetAssetResolver;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        try {
            $brandName      = SiteSetting::get('admin_brand_name', '');
            $logoPath       = SiteSetting::get('admin_logo_path', '');
            $primaryColor   = SiteSetting::get('admin_primary_color', '#f59e0b');
            $secondaryColor = SiteSetting::get('admin_secondary_color', '#73bbbb');
        } catch (\Throwable $e) {
            $brandName      = '';
            $logoPath       = '';
            $primaryColor   = '#f59e0b';
            $secondaryColor = '#73bbbb';
        }
        $hasLogo = is_string($logoPath) && trim($logoPath) !== '';
        $logoSrc = $hasLogo ? Storage::disk('public')->url($logoPath) : '';


        return $panel
            ->default()
            ->id('admin')
            ->path(env('ADMIN_PATH', 'admin'))
            ->login()
            ->routes(function () {
                \Illuminate\Support\Facades\Route::get('/invitation/{token}', [\App\Http\Controllers\Admin\InvitationController::class, 'show'])
                    ->name('invitation.show');
                \Illuminate\Support\Facades\Route::post('/invitation/{token}', [\App\Http\Controllers\Admin\InvitationController::class, 'store'])
                    ->name('invitation.store');
                \Illuminate\Support\Facades\Route::post('/inline-image-upload', [\App\Http\Controllers\Admin\InlineImageUploadController::class, 'store'])
                    ->name('inline-image-upload')
                    ->middleware(\Filament\Http\Middleware\Authenticate::class);

                // QuickBooks OAuth
                \Illuminate\Support\Facades\Route::get('/quickbooks/connect', [\App\Http\Controllers\QuickBooksCallbackController::class, 'connect'])
                    ->name('quickbooks.connect')
                    ->middleware(\Filament\Http\Middleware\Authenticate::class);
                \Illuminate\Support\Facades\Route::get('/quickbooks/callback', [\App\Http\Controllers\QuickBooksCallbackController::class, 'callback'])
                    ->name('quickbooks.callback')
                    ->middleware(\Filament\Http\Middleware\Authenticate::class);
                \Illuminate\Support\Facades\Route::post('/quickbooks/disconnect', [\App\Http\Controllers\QuickBooksCallbackController::class, 'disconnect'])
                    ->name('quickbooks.disconnect')
                    ->middleware(\Filament\Http\Middleware\Authenticate::class);

                // Page builder API (Vue editor)
                \Illuminate\Support\Facades\Route::prefix('api/page-builder')
                    ->middleware(\Filament\Http\Middleware\Authenticate::class)
                    ->group(base_path('routes/admin-api.php'));

                // Dashboard builder API (Vue editor, dashboard mode)
                \Illuminate\Support\Facades\Route::prefix('api/dashboard-builder')
                    ->middleware(\Filament\Http\Middleware\Authenticate::class)
                    ->group(base_path('routes/admin-dashboard-api.php'));

                // Theme editor (Vue typography island + SCSS export)
                \Illuminate\Support\Facades\Route::middleware(\Filament\Http\Middleware\Authenticate::class)
                    ->group(function () {
                        \Illuminate\Support\Facades\Route::post('/api/theme/typography', [\App\Http\Controllers\Admin\ThemeTypographyController::class, 'update'])
                            ->name('theme.typography.update');
                        \Illuminate\Support\Facades\Route::get('/design-system/typography/export.scss', [\App\Http\Controllers\Admin\ThemeTypographyController::class, 'export'])
                            ->name('theme.typography.export');
                    });
            })
            ->colors([
                'primary'   => Color::hex($primaryColor),
                'secondary' => Color::hex($secondaryColor),
            ])
            ->navigationGroups([
                NavigationGroup::make('CRM')->collapsed(),
                NavigationGroup::make('CMS')->collapsed(),
                NavigationGroup::make('Finance')->collapsed(),
                NavigationGroup::make('Tools')->collapsed(),
                NavigationGroup::make('Settings')->collapsed(),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \App\Filament\Widgets\DashboardSlotGridWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandLogo(new HtmlString(
                ($hasLogo ? '<img src="' . e($logoSrc) . '">' : '') .
                '<h1>' . e($brandName) . '</h1>'
            ))
            // Admin Alpine components — helpSearch, buttonPreview, fullscreenToggle,
            // widgetPickerModal, permissionTable, quillEditor, customSelect.
            ->renderHook(
                'panels::head.end',
                fn (): HtmlString => new HtmlString(
                    app(\Illuminate\Foundation\Vite::class)('resources/js/admin.js')
                )
            )
            // Library bundle manifest for admin preview JS loading.
            // Injected as a global JS object so the page builder can load
            // per-library bundles on demand when a widget preview renders.
            ->renderHook(
                'panels::head.end',
                fn (): HtmlString => new HtmlString(
                    '<script>window.__widgetLibs = ' . json_encode(app(WidgetAssetResolver::class)->allLibs(), JSON_FORCE_OBJECT) . ';</script>'
                )
            )
            // Public site CSS for widget preview in the page builder.
            // The build server bundle includes ALL public styles (base + widgets).
            // Base element selectors are namespaced under .np-site in the SCSS source,
            // so they only apply inside the preview container (which carries .np-site).
            // JS-dependent widgets (carousels, maps, charts) render HTML/CSS only —
            // interactive JS loading is deferred to session 140.
            ->renderHook(
                'panels::head.end',
                function (): HtmlString {
                    $url = app(WidgetAssetResolver::class)->widgetCss();
                    return $url ? new HtmlString('<link rel="stylesheet" href="' . e($url) . '">') : new HtmlString('');
                }
            )
            // Public widget JS bundle — exposes per-widget Alpine factories
            // (window.NPWidgets.*) so the page builder preview can initialize
            // interactive widgets (blog/events listing carousels, bar chart,
            // event calendar). The bundle defines no globals besides NPWidgets
            // and does not touch Alpine itself, so it coexists with Filament.
            ->renderHook(
                'panels::head.end',
                function (): HtmlString {
                    $url = app(WidgetAssetResolver::class)->widgetJs();
                    return $url ? new HtmlString('<script src="' . e($url) . '"></script>') : new HtmlString('');
                }
            )
            // Per-widget library bundles (swiper, chart.js, jcalendar). Emitted
            // synchronously in the admin head so widget-primitive slots rendered
            // inside Livewire (e.g. DashboardSlotGridWidget) find window.Swiper
            // et al. already defined when their Alpine x-data init() fires —
            // external <script src> injected via Livewire morph would load
            // asynchronously and race the Alpine init tick.
            // Interim: all manifest libs load on every admin page. A future
            // session narrows this to the libs a page's slots actually need
            // via a page-level required-libs registry; the resolver already
            // supports the narrower call via libsForWidgets().
            ->renderHook(
                'panels::head.end',
                function (): HtmlString {
                    $html = '';
                    foreach (app(WidgetAssetResolver::class)->allLibs() as $lib => $entry) {
                        if (! empty($entry['css'])) {
                            $html .= '<link rel="stylesheet" href="' . e($entry['css']) . '" data-widget-lib="' . e($lib) . '">';
                        }
                        if (! empty($entry['js'])) {
                            $html .= '<script src="' . e($entry['js']) . '" data-widget-lib="' . e($lib) . '"></script>';
                        }
                    }
                    return new HtmlString($html);
                }
            )
            // Fullscreen mode bootstrap — set the html class from localStorage before
            // first paint so the layout doesn't flash. The topbar toggle button keeps
            // this in sync with the sidebar collapse state at runtime.
            ->renderHook(
                'panels::head.end',
                fn (): HtmlString => new HtmlString(
                    "<script>(function(){try{if(localStorage.getItem('np-fullscreen')==='1'){document.documentElement.classList.add('np-fullscreen');}}catch(e){}})();</script>"
                )
            )
            // Load Quill v2 on all admin pages — used by the page builder and any
            // QuillEditor form fields (e.g. event description, meeting details).
            // Quill toolbar visual language + dark mode overrides live in
            // public/css/admin.css and must load AFTER quill.snow.css to win
            // cascade ties against Quill's default chrome.
            ->renderHook(
                'panels::head.end',
                fn (): HtmlString => new HtmlString('
                    <link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
                    <style>.ql-editor { min-height: 16rem; }</style>
                    <script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
                ')
            )
            // Admin panel style overrides (form borders, Trix toolbar, Quill
            // toolbar visual language). Loaded last among admin-side stylesheets
            // so its rules win equal-specificity cascade ties.
            ->renderHook(
                'panels::head.end',
                fn (): HtmlString => new HtmlString(
                    '<link rel="stylesheet" href="/css/admin.css">'
                )
            )
            // Site-wide Livewire loading bar — fixed top bar that appears on any
            // server round-trip after a 200 ms delay (so instant clicks don't flash).
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): HtmlString => new HtmlString(
                    '<div wire:loading.delay class="fixed inset-x-0 top-0 z-[200] h-0.5 bg-primary-500 animate-pulse pointer-events-none"></div>'
                )
            )
            // Help search component in the top bar.
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): HtmlString => new HtmlString(
                    \Livewire\Livewire::mount('help-search')
                )
            )
            // Fullscreen toggle button — left of the user menu in the topbar.
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): \Illuminate\Contracts\View\View => view('components.admin-fullscreen-toggle')
            )
            // "View public site" link on the left side of the topbar.
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): HtmlString => new HtmlString(
                    '<a href="' . e(url('/')) . '" target="_blank" rel="noopener noreferrer"'
                    . ' class="ms-3 flex items-center gap-1 rounded px-2 py-1 text-xs text-gray-500 ring-1 ring-gray-200 hover:text-gray-800 hover:ring-gray-400 dark:text-gray-400 dark:ring-gray-700 dark:hover:text-gray-200">'
                    . '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">'
                    . '<path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>'
                    . '</svg>'
                    . 'View site</a>'
                )
            )
            // Context-sensitive help: ? icon in the page header that opens a slide-over.
            ->renderHook(
                PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
                function (): \Illuminate\Contracts\View\View {
                    $routeName = \Illuminate\Support\Facades\Route::currentRouteName() ?? '';
                    $article = app(HelpArticleService::class)->forRoute($routeName);
                    return view('components.help-slide-over', ['article' => $article]);
                }
            );
    }
}
