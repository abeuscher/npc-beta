<?php

namespace App\Providers\Filament;

use App\Services\HelpArticleService;
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
use Filament\Support\Facades\FilamentView;
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
            $brandName = SiteSetting::get('admin_brand_name', '');
            $logoPath  = SiteSetting::get('admin_logo_path', '');
        } catch (\Throwable $e) {
            $brandName = '';
            $logoPath  = '';
        }
        $logoSrc   = $logoPath !== ''
            ? Storage::disk('public')->url($logoPath)
            : asset('images/admin-logo.png');


        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups([
                NavigationGroup::make('CRM')->collapsed(),
                NavigationGroup::make('CMS')->collapsed(),
                NavigationGroup::make('Finance')->collapsed(),
                NavigationGroup::make('Tools')->collapsed(),
                NavigationGroup::make('Settings')->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
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
            ->renderHook(
                'panels::head.end',
                fn (): HtmlString => new HtmlString('
                    <link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
                    <script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
                    <script>
                        document.addEventListener("DOMContentLoaded", function () {
                            if (typeof Quill === "undefined") return;
                            var FontAttributor = Quill.import("formats/font");
                            FontAttributor.whitelist = ["serif", "monospace"];
                            Quill.register(FontAttributor, true);
                        });
                    </script>
                ')
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
