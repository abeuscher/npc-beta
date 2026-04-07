<?php

namespace App\Providers\Filament;

use App\Services\HelpArticleService;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
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
            $brandName    = SiteSetting::get('admin_brand_name', '');
            $logoPath     = SiteSetting::get('admin_logo_path', '');
            $primaryColor = SiteSetting::get('admin_primary_color', '#f59e0b');
        } catch (\Throwable $e) {
            $brandName    = '';
            $logoPath     = '';
            $primaryColor = '#f59e0b';
        }
        $logoSrc   = $logoPath !== ''
            ? Storage::disk('public')->url($logoPath)
            : asset('images/admin-logo.png');


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

                // Page preview for the page builder (renders full page with public layout)
                \Illuminate\Support\Facades\Route::get('/preview/{page}', [\App\Http\Controllers\Admin\PagePreviewController::class, 'show'])
                    ->name('page-preview')
                    ->middleware(\Filament\Http\Middleware\Authenticate::class);

                // Content width toggle
                \Illuminate\Support\Facades\Route::post('/toggle-full-width', \App\Http\Controllers\Admin\ToggleFullWidthController::class)
                    ->name('toggle-full-width')
                    ->middleware(\Filament\Http\Middleware\Authenticate::class);
            })
            ->colors([
                'primary' => Color::hex($primaryColor),
            ])
            ->navigationGroups([
                NavigationGroup::make('CRM')->collapsed(),
                NavigationGroup::make('CMS')->collapsed(),
                NavigationGroup::make('Finance')->collapsed(),
                NavigationGroup::make('Tools')->collapsed(),
                NavigationGroup::make('Settings')->collapsed(),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->userMenuItems([
                'layout-toggle' => MenuItem::make()
                    ->label(fn (): string => session('admin_full_width') ? 'Restricted width' : 'Full width')
                    ->icon(fn (): string => session('admin_full_width') ? 'heroicon-m-arrows-pointing-in' : 'heroicon-m-arrows-pointing-out')
                    ->postAction(fn (): string => route('filament.admin.toggle-full-width')),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
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
                '<img src="' . e($logoSrc) . '">' .
                '<h1>' . e($brandName) . '</h1>'
            ))
            // Admin panel style overrides (form borders, Trix toolbar).
            ->renderHook(
                'panels::head.end',
                fn (): HtmlString => new HtmlString(
                    '<link rel="stylesheet" href="/css/admin.css">'
                )
            )
            // Library bundle manifest for admin preview JS loading.
            // Injected as a global JS object so the page builder can load
            // per-library bundles on demand when a widget preview renders.
            ->renderHook(
                'panels::head.end',
                function (): HtmlString {
                    $widgetManifest = json_decode(@file_get_contents(public_path('build/widgets/manifest.json')) ?: '{}', true);
                    $libs = $widgetManifest['libs'] ?? [];

                    return new HtmlString(
                        '<script>window.__widgetLibs = ' . json_encode($libs, JSON_FORCE_OBJECT) . ';</script>'
                    );
                }
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
                    $widgetManifest = json_decode(@file_get_contents(public_path('build/widgets/manifest.json')) ?: '{}', true);
                    $widgetCss = $widgetManifest['css'] ?? null;

                    return $widgetCss
                        ? new HtmlString('<link rel="stylesheet" href="/build/widgets/' . $widgetCss . '">')
                        : new HtmlString('');
                }
            )
            // Full-width content toggle — removes max-width cap on main content area.
            ->renderHook(
                'panels::head.end',
                fn (): HtmlString => session('admin_full_width')
                    ? new HtmlString('<style>.fi-main { max-width: 100% !important; }</style>')
                    : new HtmlString('')
            )
            // Page builder Alpine.data() components (extracted inline JS).
            ->renderHook(
                'panels::head.end',
                fn (): HtmlString => new HtmlString(
                    app(\Illuminate\Foundation\Vite::class)('resources/js/page-builder/index.js')
                )
            )
            // @alpinejs/sort enables drag-to-reorder in the page builder.
            // If CSP blocks this (eval() error), the Up/Down fallback in the
            // block ellipsis menu still works — see page-builder.blade.php.
            ->renderHook(
                'panels::head.end',
                fn (): HtmlString => new HtmlString(
                    '<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/sort@3.14.3/dist/cdn.min.js"></script>'
                )
            )
            // Load Quill v2 on all admin pages — used by the page builder and any
            // QuillEditor form fields (e.g. event description, meeting details).
            // Quill dark mode + inline editable styles live in public/css/admin.css.
            ->renderHook(
                'panels::head.end',
                fn (): HtmlString => new HtmlString('
                    <link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
                    <style>.ql-editor { min-height: 16rem; }</style>
                    <script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
                ')
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
