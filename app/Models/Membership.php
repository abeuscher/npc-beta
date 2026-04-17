<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Membership extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'contact_id',
        'tier_id',
        'status',
        'starts_on',
        'expires_on',
        'amount_paid',
        'stripe_session_id',
        'stripe_subscription_id',
        'notes',
        'import_source_id',
        'import_session_id',
        'external_id',
    ];

    protected $casts = [
        'starts_on'   => 'date',
        'expires_on'  => 'date',
        'amount_paid' => 'decimal:2',
    ];

    public function importSource(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class);
    }

    public function importSession(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(MembershipTier::class, 'tier_id');
    }
}
