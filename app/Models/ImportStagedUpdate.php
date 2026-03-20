<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportStagedUpdate extends Model
{
    protected $fillable = [
        'import_session_id',
        'contact_id',
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

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
