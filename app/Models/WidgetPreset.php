<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidgetPreset extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'widget_type_id',
        'handle',
        'label',
        'description',
        'config',
        'appearance_config',
    ];

    protected $casts = [
        'config'            => 'array',
        'appearance_config' => 'array',
    ];

    public function widgetType(): BelongsTo
    {
        return $this->belongsTo(WidgetType::class);
    }
}
