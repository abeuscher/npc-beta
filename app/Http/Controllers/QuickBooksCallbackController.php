<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Services\ActivityLogger;
use App\Services\QuickBooks\QuickBooksAuth;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class QuickBooksCallbackController extends Controller
{
    public function connect(QuickBooksAuth $auth): RedirectResponse
    {
        $this->authorizeFinance();

        if (empty(SiteSetting::get('qb_client_id', '')) || empty(SiteSetting::get('qb_client_secret', ''))) {
            Notification::make()
                ->title('QuickBooks not configured')
                ->body('Set the Client ID and Client Secret on the Finance Settings page before connecting.')
                ->danger()
                ->send();

            return redirect()->route('filament.admin.pages.finance-settings-page');
        }

        return redirect()->away($auth->getAuthorizationUrl());
    }

    public function callback(Request $request, QuickBooksAuth $auth): RedirectResponse
    {
        $this->authorizeFinance();

        // Validate CSRF state parameter
        $expectedState = session()->pull('qb_oauth_state');
        if (empty($expectedState) || $request->query('state') !== $expectedState) {
            Notification::make()
                ->title('QuickBooks connection failed')
                ->body('Invalid state parameter — the request may have been tampered with. Please try again.')
                ->danger()
                ->send();

            return redirect()->route('filament.admin.pages.finance-settings-page');
        }

        if ($request->has('error')) {
            Notification::make()
                ->title('QuickBooks connection failed')
                ->body('Authorization was denied: ' . $request->query('error'))
                ->danger()
                ->send();

            return redirect()->route('filament.admin.pages.finance-settings-page');
        }

        $code = $request->query('code');
        $realmId = $request->query('realmId');

        if (empty($code) || empty($realmId)) {
            Notification::make()
                ->title('QuickBooks connection failed')
                ->body('Missing authorization code or company ID.')
                ->danger()
                ->send();

            return redirect()->route('filament.admin.pages.finance-settings-page');
        }

        try {
            $auth->exchangeCode($code, $realmId);

            $setting = SiteSetting::where('key', 'qb_realm_id')->first();
            if ($setting) {
                ActivityLogger::log($setting, 'quickbooks_connected', "QuickBooks connected (Realm ID: {$realmId})");
            }

            Notification::make()
                ->title('QuickBooks connected')
                ->body("Successfully connected to QuickBooks company {$realmId}.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('QuickBooks connection failed')
                ->body('Could not exchange authorization code. Please try again.')
                ->danger()
                ->send();
        }

        return redirect()->route('filament.admin.pages.finance-settings-page');
    }

    public function disconnect(QuickBooksAuth $auth): RedirectResponse
    {
        $this->authorizeFinance();

        $realmId = $auth->getRealmId();
        $setting = SiteSetting::where('key', 'qb_realm_id')->first();

        $auth->disconnect();

        if ($setting) {
            ActivityLogger::log($setting, 'quickbooks_disconnected', "QuickBooks disconnected (Realm ID: {$realmId})");
        }

        Notification::make()
            ->title('QuickBooks disconnected')
            ->success()
            ->send();

        return redirect()->route('filament.admin.pages.finance-settings-page');
    }

    private function authorizeFinance(): void
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole('super_admin') && ! $user->can('manage_financial_settings'))) {
            throw new AccessDeniedHttpException();
        }
    }
}
