<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PortalAccount extends Authenticatable implements MustVerifyEmailContract
{
    use MustVerifyEmail, Notifiable;

    protected $fillable = [
        'contact_id',
        'email',
        'password',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        \Illuminate\Support\Facades\Mail::to($this->email)
            ->send(new \App\Mail\PortalEmailVerification($this));
    }
}
