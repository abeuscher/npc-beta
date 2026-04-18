<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ImportStagedUpdate extends Model
{
    protected $fillable = [
        'import_session_id',
        'subject_type',
        'subject_id',
        'attributes',
        'tag_ids',
    ];

    protected $casts = [
        'attributes' => 'array',
        'tag_ids'    => 'array',
    ];

    public function importSession(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
