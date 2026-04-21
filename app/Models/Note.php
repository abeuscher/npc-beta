<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'notable_type',
        'notable_id',
        'author_id',
        'type',
        'subject',
        'status',
        'body',
        'occurred_at',
        'follow_up_at',
        'outcome',
        'duration_minutes',
        'meta',
        'import_source_id',
        'import_session_id',
        'external_id',
    ];

    protected $casts = [
        'occurred_at'      => 'datetime',
        'follow_up_at'     => 'datetime',
        'duration_minutes' => 'integer',
        'meta'             => 'array',
    ];

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function importSource(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class);
    }
}
