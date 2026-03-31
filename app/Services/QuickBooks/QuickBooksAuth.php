<?php

namespace App\Services\QuickBooks;

use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper;

class QuickBooksAuth
{
    private const TOKEN_KEYS = [
        'qb_access_token',
        'qb_refresh_token',
        'qb_realm_id',
        'qb_token_expires_at',
    ];

    private const SCOPE = 'com.intuit.quickbooks.accounting';

    public function getAuthorizationUrl(): string
    {
        $state = bin2hex(random_bytes(16));
        session(['qb_oauth_state' => $state]);

        $helper = new OAuth2LoginHelper(
            $this->getClientId(),
            $this->getClientSecret(),
            url('/admin/quickbooks/callback'),
            self::SCOPE,
            $state,
        );

        return $helper->getAuthorizationCodeURL();
    }

    public function exchangeCode(string $code, string $realmId): void
    {
        $helper = new OAuth2LoginHelper(
            $this->getClientId(),
            $this->getClientSecret(),
            url('/admin/quickbooks/callback'),
        );

        $token = $helper->exchangeAuthorizationCodeForToken($code, $realmId);

        $this->storeEncrypted('qb_access_token', $token->getAccessToken());
        $this->storeEncrypted('qb_refresh_token', $token->getRefreshToken());
        $this->storeEncrypted('qb_realm_id', $realmId);
        $this->storeEncrypted('qb_token_expires_at', now()->addSeconds($token->getAccessTokenExpiresAt())->toIso8601String());
    }

    public function refreshTokenIfNeeded(): bool
    {
        $refreshToken = $this->getDecrypted('qb_refresh_token');
        if (empty($refreshToken)) {
            return false;
        }

        $expiresAt = $this->getDecrypted('qb_token_expires_at');
        if ($expiresAt && Carbon::parse($expiresAt)->isAfter(now()->addMinutes(5))) {
            return true; // Token is still valid
        }

        try {
            $helper = new OAuth2LoginHelper(
                $this->getClientId(),
                $this->getClientSecret(),
                url('/admin/quickbooks/callback'),
            );

            $token = $helper->refreshAccessTokenWithRefreshToken($refreshToken);

            $this->storeEncrypted('qb_access_token', $token->getAccessToken());
            $this->storeEncrypted('qb_refresh_token', $token->getRefreshToken());
            $this->storeEncrypted('qb_token_expires_at', now()->addSeconds($token->getAccessTokenExpiresAt())->toIso8601String());

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function disconnect(): void
    {
        foreach (self::TOKEN_KEYS as $key) {
            $setting = SiteSetting::where('key', $key)->first();
            if ($setting) {
                $setting->update(['value' => null]);
                Cache::forget("site_setting:{$key}");
            }
        }
    }

    public function isConnected(): bool
    {
        $accessToken = $this->getDecrypted('qb_access_token');
        $realmId = $this->getDecrypted('qb_realm_id');

        if (empty($accessToken) || empty($realmId)) {
            return false;
        }

        return $this->refreshTokenIfNeeded();
    }

    public function getRealmId(): ?string
    {
        return $this->getDecrypted('qb_realm_id') ?: null;
    }

    public function getTokenExpiresAt(): ?string
    {
        return $this->getDecrypted('qb_token_expires_at') ?: null;
    }

    private function getClientId(): string
    {
        return SiteSetting::get('qb_client_id', '') ?: '';
    }

    private function getClientSecret(): string
    {
        return SiteSetting::get('qb_client_secret', '') ?: '';
    }

    private function storeEncrypted(string $key, ?string $value): void
    {
        $encrypted = filled($value) ? Crypt::encryptString($value) : null;

        $setting = SiteSetting::where('key', $key)->first();
        if ($setting) {
            $setting->update(['value' => $encrypted]);
        } else {
            SiteSetting::create([
                'key'   => $key,
                'value' => $encrypted,
                'group' => 'finance',
                'type'  => 'encrypted',
            ]);
        }
        Cache::forget("site_setting:{$key}");
    }

    private function getDecrypted(string $key): ?string
    {
        $value = SiteSetting::get($key);

        return filled($value) ? $value : null;
    }
}
