<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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

    public function isDemo(): bool
    {
        return $this->hasRole('demo');
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
