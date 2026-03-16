<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'model_type',
        'filename',
        'storage_path',
        'column_map',
        'custom_field_map',
        'custom_field_log',
        'row_count',
        'imported_count',
        'updated_count',
        'skipped_count',
        'error_count',
        'errors',
        'duplicate_strategy',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'column_map'        => 'array',
        'custom_field_map'  => 'array',
        'custom_field_log'  => 'array',
        'errors'            => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
