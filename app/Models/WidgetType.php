<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WidgetType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'handle',
        'label',
        'render_mode',
        'collections',
        'config_schema',
        'template',
        'css',
        'js',
        'variable_name',
        'code',
    ];

    protected $casts = [
        'collections'   => 'array',
        'config_schema' => 'array',
    ];

    public function pageWidgets(): HasMany
    {
        return $this->hasMany(PageWidget::class);
    }
}
