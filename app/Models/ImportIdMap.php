<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportIdMap extends Model
{
    use HasUuids;

    protected $fillable = [
        'import_source_id',
        'model_type',
        'source_id',
        'model_uuid',
    ];

    public function importSource(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class);
    }
}
