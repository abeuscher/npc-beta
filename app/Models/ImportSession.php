<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'session_label',
        'import_source_id',
        'model_type',
        'status',
        'filename',
        'row_count',
        'tag_ids',
        'imported_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'tag_ids'     => 'array',
    ];

    public function importSource(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class)->withoutGlobalScopes();
    }
}
