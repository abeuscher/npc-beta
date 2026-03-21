<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactDuplicateDismissal extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'contact_id_a',
        'contact_id_b',
        'dismissed_by',
        'dismissed_at',
    ];

    protected $casts = [
        'dismissed_at' => 'datetime',
    ];

    public function contactA(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id_a');
    }

    public function contactB(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id_b');
    }

    public function dismissedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }
}
