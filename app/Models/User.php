<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Whether this user has completed two-factor enrollment — a secret exists
     * and was confirmed with a live code. The enforcement gate (session 359)
     * reads this to decide enrollment vs. challenge vs. pass.
     */
    public function hasConfirmedTwoFactor(): bool
    {
        return ! is_null($this->two_factor_secret) && ! is_null($this->two_factor_confirmed_at);
    }

    /**
     * Override Fortify's QR URL so the authenticator app labels the entry with
     * this install's configured brand (what the admin sees on the panel) rather
     * than the raw APP_NAME. Cascade: admin brand name → site name → app name.
     * The account label stays the user's email so multiple accounts are
     * distinguishable. (session 359)
     */
    public function twoFactorQrCodeUrl(): string
    {
        return app(TwoFactorAuthenticationProvider::class)->qrCodeUrl(
            $this->twoFactorIssuer(),
            $this->email,
            Crypt::decrypt($this->two_factor_secret),
        );
    }

    protected function twoFactorIssuer(): string
    {
        $brand = trim((string) SiteSetting::get('admin_brand_name', ''));

        if ($brand !== '') {
            return $brand;
        }

        return (string) (config('site.name') ?: config('app.name'));
    }

    public function isProtected(): bool
    {
        return $this->id === User::oldest()->value('id');
    }

    public function invitationTokens(): HasMany
    {
        return $this->hasMany(InvitationToken::class);
    }

    public function pendingInvitationToken(): ?InvitationToken
    {
        return $this->invitationTokens()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }
}
