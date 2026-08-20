<?php

namespace Plugins\MemberPortal;

use App\Plugins\CapabilityRegistry;
use App\Services\WidgetRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Plugins\MemberPortal\Http\Middleware\PortalAuthenticate;
use Plugins\MemberPortal\Widgets\PortalAccountDashboard\PortalAccountDashboardDefinition;
use Plugins\MemberPortal\Widgets\PortalChangePassword\PortalChangePasswordDefinition;
use Plugins\MemberPortal\Widgets\PortalContactEdit\PortalContactEditDefinition;
use Plugins\MemberPortal\Widgets\PortalForgotPassword\PortalForgotPasswordDefinition;
use Plugins\MemberPortal\Widgets\PortalLogin\PortalLoginDefinition;
use Plugins\MemberPortal\Widgets\PortalSignup\PortalSignupDefinition;

class MemberPortalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The member-portal capability (docs/plugin-contract.md surface 13 —
        // the second shipping capability). Presence is enablement: unlike
        // payments there is no credential dimension, so a registered provider
        // is a usable portal. Consumers: the crm-plugin--events portal
        // registration route (registers only when present) and core's
        // auth('portal') reads (PageContext, PageController, ActivityLogger,
        // FormSubmissionController, the portal_member resolver arm), which
        // must not touch the guard on a portal-absent composition — an
        // unconfigured guard name throws.
        $this->app->make(CapabilityRegistry::class)->provide(
            'member-portal',
            fn (): bool => true,
        );

        $this->injectPortalAuthConfig();
    }

    public function boot(): void
    {
        // The first plugin-owned middleware alias (surface 4 note, session
        // 394). Aliases resolve at dispatch, so registration order relative
        // to route files that reference 'portal.auth' does not matter.
        Route::aliasMiddleware('portal.auth', PortalAuthenticate::class);

        View::addNamespace('plugin-member-portal', __DIR__ . '/resources/views');
        View::addNamespace('plugin-member-portal-widgets', __DIR__ . '/Widgets');

        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        $widgets = $this->app->make(WidgetRegistry::class);
        $widgets->register(new PortalSignupDefinition());
        $widgets->register(new PortalLoginDefinition());
        $widgets->register(new PortalContactEditDefinition());
        $widgets->register(new PortalChangePasswordDefinition());
        $widgets->register(new PortalForgotPasswordDefinition());
        $widgets->register(new PortalAccountDashboardDefinition());

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }

    /**
     * Inject the portal guard, its account provider, and the portal password
     * broker into config('auth.*') — the config-injection posture that moved
     * the auth stack with the plugin (session 394 decision 2; the Payments
     * services.stripe precedent). config/auth.php keeps no portal entries:
     * on a portal-absent composition the guard simply is not defined, and
     * every core reach gates on the member-portal capability instead of
     * touching it. PortalAccount itself stays a core model (plan § 6.7).
     */
    private function injectPortalAuthConfig(): void
    {
        config([
            'auth.guards.portal' => [
                'driver'   => 'session',
                'provider' => 'portal_accounts',
            ],
            'auth.providers.portal_accounts' => [
                'driver' => 'eloquent',
                'model'  => \App\Models\PortalAccount::class,
            ],
            'auth.passwords.portal_accounts' => [
                'provider' => 'portal_accounts',
                'table'    => 'portal_password_reset_tokens',
                'expire'   => 60,
                'throttle' => 60,
            ],
        ]);
    }
}
