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
        'tier',
        'status',
        'starts_on',
        'expires_on',
        'amount_paid',
        'notes',
    ];

    protected $casts = [
        'starts_on'   => 'date',
        'expires_on'  => 'date',
        'amount_paid' => 'decimal:2',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
