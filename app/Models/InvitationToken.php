<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InvitationToken extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'  => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return is_null($this->accepted_at) && $this->expires_at->isFuture();
    }

    public static function createForUser(User $user): array
    {
        // Delete any existing unaccepted token for this user.
        static::where('user_id', $user->id)->whereNull('accepted_at')->delete();

        $plain = Str::random(64);

        $token = static::create([
            'user_id'    => $user->id,
            'token'      => hash('sha256', $plain),
            'expires_at' => now()->addHours(48),
        ]);

        return [$plain, $token];
    }

    public static function findByPlainToken(string $plain): ?static
    {
        return static::where('token', hash('sha256', $plain))->first();
    }
}
