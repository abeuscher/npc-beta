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
                // Two-factor authentication flow (session 359, A5). Registered
                // here rather than via page auto-discovery because SimplePage
                // isn't routable on its own — this gives the enrollment and
                // challenge screens the simple, sidebar-free auth layout and the
                // canonical `filament.admin.pages.*` route names. They sit behind
                // Authenticate (must be logged in) but deliberately OUTSIDE the
                // panel's authMiddleware, so the 2FA enforcement gate that lives
                // there can never trap these pages in a redirect loop.
                \Illuminate\Support\Facades\Route::get('/two-factor-setup', \App\Filament\Pages\TwoFactorSetup::class)
                    ->name('pages.two-factor-setup')
                    ->middleware(\Filament\Http\Middleware\Authenticate::class);
                \Illuminate\Support\Facades\Route::get('/two-factor-challenge', \App\Filament\Pages\TwoFactorChallenge::class)
                    ->name('pages.two-factor-challenge')
                    ->middleware(\Filament\Http\Middleware\Authenticate::class);

                \Illuminate\Support\Facades\Route::get('/invitation/{token}', [\App\Http\Controllers\Admin\InvitationController::class, 'show'])
                    ->name('invitation.show');
                \Illuminate\Support\Facades\Route::post('/invitation/{token}', [\App\Http\Controllers\Admin\InvitationController::class, 'store'])
                    ->name('invitation.store');
                // New-file upload — gated against the demo role (session 329).
                \Illuminate\Support\Facades\Route::post('/inline-image-upload', [\App\Http\Controllers\Admin\InlineImageUploadController::class, 'store'])
                    ->name('inline-image-upload')
                    ->middleware([\Filament\Http\Middleware\Authenticate::class, \App\Http\Middleware\BlockDemoUploads::class]);

                \Illuminate\Support\Facades\Route::post('/media-dedup-check', [\App\Http\Controllers\Admin\MediaDedupController::class, 'check'])
                    ->name('media-dedup-check')
                    ->middleware(\Filament\Http\Middleware\Authenticate::class);

                \Illuminate\Support\Facades\Route::get('/heroicons', [\App\Http\Controllers\Admin\HeroiconController::class, 'index'])
                    ->name('heroicons.index')
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

                // Record detail view builder API (Vue editor, record_detail mode)
                \Illuminate\Support\Facades\Route::prefix('api/record-detail-view-builder')
                    ->middleware(\Filament\Http\Middleware\Authenticate::class)
                    ->group(base_path('routes/admin-record-detail-view-api.php'));

                // Theme editor (Vue typography island + SCSS export)
                \Illuminate\Support\Facades\Route::middleware(\Filament\Http\Middleware\Authenticate::class)
                    ->group(function () {
                        \Illuminate\Support\Facades\Route::post('/api/theme/typography', [\App\Http\Controllers\Admin\ThemeTypographyController::class, 'update'])
                            ->name('theme.typography.update');
                        \Illuminate\Support\Facades\Route::get('/design-system/typography/export.scss', [\App\Http\Controllers\Admin\ThemeTypographyController::class, 'export'])
                            ->name('theme.typography.export');
                        \Illuminate\Support\Facades\Route::post('/api/theme/typography/rebuild', [\App\Http\Controllers\Admin\ThemeTypographyController::class, 'rebuild'])
                            ->name('theme.typography.rebuild');
                    });

                // Dev tools — Random Data Generator
                \Illuminate\Support\Facades\Route::middleware(\Filament\Http\Middleware\Authenticate::class)
                    ->prefix('dev-tools')
                    ->name('dev-tools.')
                    ->group(function () {
                        \Illuminate\Support\Facades\Route::post('/random-data', [\App\Http\Controllers\Admin\RandomDataGeneratorController::class, 'store'])
                            ->name('random-data.store');
                        \Illuminate\Support\Facades\Route::post('/random-data/wipe', [\App\Http\Controllers\Admin\RandomDataGeneratorController::class, 'wipe'])
                            ->name('random-data.wipe');
                        \Illuminate\Support\Facades\Route::post('/random-data/seed-collections', [\App\Http\Controllers\Admin\RandomDataGeneratorController::class, 'seedCollections'])
                            ->name('random-data.seed-collections');
                    });

                // Setup checklist widget actions
                \Illuminate\Support\Facades\Route::middleware(\Filament\Http\Middleware\Authenticate::class)
                    ->prefix('setup-checklist')
                    ->name('setup-checklist.')
                    ->group(function () {
                        \Illuminate\Support\Facades\Route::post('/mark-complete', [\App\Http\Controllers\Admin\SetupChecklistController::class, 'markComplete'])
                            ->name('mark-complete');
                        \Illuminate\Support\Facades\Route::post('/reset', [\App\Http\Controllers\Admin\SetupChecklistController::class, 'reset'])
                            ->name('reset');
                    });

                // Gated download of a queued export artifact (session 303).
                \Illuminate\Support\Facades\Route::get('/exports/bundles/{token}', \App\Http\Controllers\Admin\BundleExportDownloadController::class)
                    ->name('exports.bundle.download')
                    ->middleware(\Filament\Http\Middleware\Authenticate::class);
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
            // Persistent notification bell — the delivery surface for queued
            // export/import results (session 303, media-portability draft
            // decision #5). Backed by the standard Laravel notifications table.
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
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
                // Client-billing suspension gate (contract v2.6.0). First in the
                // base stack so a locked node short-circuits before session/cookie
                // work. This base group wraps both the discovered panel pages AND
                // the in-panel API route groups registered via ->routes() below,
                // so a single registration covers the whole admin surface (panel,
                // login, page-builder/theme/dev-tools APIs). Absent flag = none =
                // no-op, so every existing install is unaffected.
                \App\Http\Middleware\EnforceSuspensionState::class . ':' . \App\Http\Middleware\EnforceSuspensionState::SURFACE_ADMIN,
                // Perimeter security headers (session 370, Security S1). The
                // admin surface takes the Report-Only CSP (Filament/Alpine/
                // Livewire compatibility is unproven — see config/security.php);
                // the header baseline (HSTS, X-Frame-Options, etc.) is enforced.
                \App\Http\Middleware\SecurityHeaders::class,
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
                // Mandatory admin 2FA enforcement gate (session 359, A5). Runs
                // after Authenticate, so only on authenticated panel routes:
                // redirects un-enrolled users to enrollment and un-challenged
                // sessions to the challenge. Bypasses entirely in demo mode and
                // (by default) in the test environment. The two-factor flow
                // routes sit outside authMiddleware, so they never loop here.
                \App\Http\Middleware\EnsureTwoFactorAuthenticated::class,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandLogo(new HtmlString(
                ($hasLogo ? '<img src="' . e($logoSrc) . '">' : '') .
                '<h1>' . e($brandName) . '</h1>'
            ))
            // (Notification-bell unread-count styling moved to public/css/admin.css
            // at session 345 — it loads on every admin page via the head.end link.)
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
            // The EDITOR variant of the bundle is served here: width-keyed @media
            // rules rewritten to @container np-viewport so the preview honours
            // breakpoints at the simulated viewport width, not the browser window
            // (the preview scope is the np-viewport query container). Falls back
            // to the public bundle when the manifest predates the variant.
            // JS-dependent widgets (carousels, maps, charts) render HTML/CSS only —
            // interactive JS loading is deferred to session 140.
            ->renderHook(
                'panels::head.end',
                function (): HtmlString {
                    $url = app(WidgetAssetResolver::class)->widgetEditorCss();
                    return $url ? new HtmlString('<link rel="stylesheet" href="' . e($url) . '">') : new HtmlString('');
                }
            )
            // Public widget JS bundle — exposes per-widget Alpine factories
            // (window.NPWidgets.*) so the page builder preview can initialize
            // interactive widgets (blog/events listing carousels, bar chart).
            // The bundle defines no globals besides NPWidgets and does not
            // touch Alpine itself, so it coexists with Filament.
            ->renderHook(
                'panels::head.end',
                function (): HtmlString {
                    $url = app(WidgetAssetResolver::class)->widgetJs();
                    return $url ? new HtmlString('<script src="' . e($url) . '"></script>') : new HtmlString('');
                }
            )
            // Per-widget library bundles (swiper, chart.js). Emitted
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
                            // Vendor CSS into `@layer reset` (sessions 332/333) so widget
                            // styles (`@layer widgets`) win by layer order — matching the
                            // public bundle. A plain unlayered <link> beats every layer,
                            // which made the editor render Swiper's default pager instead
                            // of the designed one (an editor↔public parity break).
                            $html .= '<style data-widget-lib="' . e($lib) . '">@import url("' . e($entry['css']) . '") layer(reset);</style>';
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
            // Quill v2 is self-hosted (session 370, Security S1): the library +
            // its snow theme CSS are bundled into resources/js/admin.js (loaded by
            // the first head.end hook above) and exposed as window.Quill, retiring
            // the former cdn.jsdelivr.net <script>/<link>. The `.ql-editor`
            // min-height and the toolbar visual-language overrides live in
            // public/css/admin.css, which loads after the bundle's quill.snow.css
            // so it still wins cascade ties against Quill's default chrome.
            // Admin panel style overrides (form borders, Trix toolbar, Quill
            // toolbar visual language). Loaded last among admin-side stylesheets
            // so its rules win equal-specificity cascade ties.
            ->renderHook(
                'panels::head.end',
                fn (): HtmlString => new HtmlString(
                    '<link rel="stylesheet" href="/css/admin.css">'
                )
            )
            // Demo mode only: a "Re-enter the demo" button below the login form so
            // a logged-out demo visitor can get back in without credentials
            // (auto-login via the demo.enter route). Renders nothing otherwise.
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): HtmlString => new HtmlString(
                    isDemoMode()
                        ? '<div class="mt-6 border-t border-gray-200 pt-6 text-center dark:border-gray-700">'
                            . '<p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Just exploring? Skip the sign-in.</p>'
                            . '<a href="' . e(route('demo.enter')) . '"'
                            . ' class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2">'
                            . '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">'
                            . '<path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>'
                            . '</svg>'
                            . 'Re-enter the demo</a>'
                            . '</div>'
                        : ''
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
            // Slim panel-wide client-billing warning (CB2 / session 367). On every
            // admin page, for manage_account holders only, when the pushed
            // billing-state document flags a pre-lock delinquency (past-due /
            // grace) — so the person who can fix it sees it before the admin panel
            // locks. Renders nothing otherwise. Suppressed on the Account page,
            // which shows its own prominent banner. Reads through the same
            // per-request BillingStateReader singleton; no Stripe reference here —
            // it renders the pushed document alone.
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                function (array $scopes = []): \Illuminate\Contracts\Support\Htmlable|string {
                    if (! (auth()->user()?->can('manage_account') ?? false)) {
                        return '';
                    }

                    if (in_array(\App\Filament\Pages\Settings\AccountPage::class, $scopes, true)) {
                        return '';
                    }

                    $state = app(\App\Services\Billing\BillingStateReader::class)->read();

                    if (! $state->needsBillingAttention()) {
                        return '';
                    }

                    $locksAt = null;
                    if (is_string($state->graceEndsAt())) {
                        try {
                            $locksAt = \App\Support\DateFormat::format(
                                \Illuminate\Support\Carbon::parse($state->graceEndsAt()),
                                \App\Support\DateFormat::LONG_DATE,
                            );
                        } catch (\Throwable) {
                            $locksAt = null;
                        }
                    }

                    return view('filament.billing.panel-warning-banner', [
                        'locksAt' => $locksAt,
                        'accountUrl' => \App\Filament\Pages\Settings\AccountPage::getUrl(),
                    ]);
                }
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
            // Resolve from the render-hook scope (the page/resource class Filament
            // passes through) rather than Route::currentRouteName(), so the lookup
            // keeps working through Livewire roundtrips where the active route is
            // livewire.update, not the page route.
            ->renderHook(
                PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
                function (array $scopes = []): \Illuminate\Contracts\View\View {
                    $service = app(HelpArticleService::class);
                    $article = null;

                    foreach ($scopes as $scope) {
                        if (! is_string($scope) || ! method_exists($scope, 'getRouteName')) {
                            continue;
                        }

                        try {
                            $article = $service->forRoute($scope::getRouteName());
                        } catch (\Throwable) {
                            continue;
                        }

                        if ($article) {
                            break;
                        }
                    }

                    if (! $article) {
                        $routeName = \Illuminate\Support\Facades\Route::currentRouteName() ?? '';
                        if ($routeName !== '') {
                            $article = $service->forRoute($routeName);
                        }
                    }

                    return view('components.help-slide-over', ['article' => $article]);
                }
            )
            // === Guided single-area tours (session 362; supersedes the session-338
            // multi-page walkthrough) ===
            // Owned, stable `data-tour` anchors injected onto admin chrome so the
            // driver.js tours pin selectors we control, not Filament's churn-prone
            // classes (the session 249 selector-fragility mitigation). Markers are
            // display:none (admin.css); a tour resolves the real target by
            // DOM-traversing from each marker (next sibling / parent).
            ->renderHook(
                PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE,
                fn (): HtmlString => new HtmlString('<div data-tour="resource.records" class="np-tour-anchor"></div>')
            )
            // Tour URL map: each nav-anchored step locates its sidebar link (or
            // group) by route URL, and each URL is emitted only when the current
            // viewer's role can reach the page — a step whose target is absent
            // falls back to a centered popover, never a 404.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function (): HtmlString {
                    $urls = [];

                    try {
                        $urls['dashboard'] = \App\Filament\Pages\Dashboard::getUrl();
                    } catch (\Throwable) {
                    }

                    // Resources the tours point at — sidebar anchors and the two
                    // deep-dive home pages (contacts / pages).
                    $resources = [
                        'contacts'          => \App\Filament\Resources\ContactResource::class,
                        'memberships'       => \App\Filament\Resources\MembershipResource::class,
                        'donations'         => \App\Filament\Resources\DonationResource::class,
                        'events'            => \App\Filament\Resources\EventResource::class,
                        'mailingLists'      => \App\Filament\Resources\MailingListResource::class,
                        'notes'             => \App\Filament\Resources\NoteResource::class,
                        'recordDetailViews' => \App\Filament\Resources\RecordDetailViewResource::class,
                        'pages'             => \App\Filament\Resources\PageResource::class,
                        'forms'             => \App\Filament\Resources\FormResource::class,
                        'templates'         => \App\Filament\Resources\TemplateResource::class,
                        'widgetManager'     => \App\Filament\Resources\WidgetTypeResource::class,
                    ];
                    foreach ($resources as $key => $resource) {
                        try {
                            if ($resource::canViewAny()) {
                                $urls[$key] = $resource::getUrl();
                            }
                        } catch (\Throwable) {
                        }
                    }

                    $pages = [
                        'importer'     => \App\Filament\Pages\ImporterPage::class,
                        'theme'        => \App\Filament\Pages\DesignSystemPage::class,
                        'siteSettings' => \App\Filament\Pages\Settings\CmsSettingsPage::class,
                    ];
                    foreach ($pages as $key => $page) {
                        try {
                            if ($page::canAccess()) {
                                $urls[$key] = $page::getUrl();
                            }
                        } catch (\Throwable) {
                        }
                    }

                    // The contact the CRM tour drills into: the seeded "hero"
                    // (rich, multi-stage-loading record) when present, else the
                    // newest contact (the contacts list sorts created_at desc, so
                    // it is the first row). The tour highlights exactly this
                    // contact's row by matching its URL, so both stay in lockstep.
                    try {
                        if (\App\Filament\Resources\ContactResource::canViewAny()) {
                            $hero = \App\Models\Contact::query()
                                ->where('email', 'tour.hero@nphelper.demo')
                                ->first()
                                ?? \App\Models\Contact::query()->latest()->first();
                            if ($hero) {
                                $urls['contactRecord'] = \App\Filament\Resources\ContactResource::getUrl('edit', ['record' => $hero]);
                            }
                        }
                    } catch (\Throwable) {
                    }

                    return new HtmlString(
                        '<script>window.__npTour=' . json_encode(['urls' => $urls]) . ';</script>'
                    );
                }
            );
    }
}
