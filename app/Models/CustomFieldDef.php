<?php

namespace App\Models;

use App\Observers\CustomFieldDefObserver;
use Filament\Forms;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(CustomFieldDefObserver::class)]
class CustomFieldDef extends Model
{
    protected $fillable = [
        'model_type',
        'handle',
        'label',
        'field_type',
        'options',
        'sort_order',
        'is_filterable',
    ];

    protected $casts = [
        'options'       => 'array',
        'sort_order'    => 'integer',
        'is_filterable' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForModel($query, string $modelType)
    {
        return $query->where('model_type', $modelType)->orderBy('sort_order');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the Filament form component for this field definition.
     * The state key uses dot notation so Filament maps it into the model's
     * custom_fields array cast: custom_fields.{handle}
     */
    public function toFilamentFormComponent(): Forms\Components\Component
    {
        $key = "custom_fields.{$this->handle}";

        return match ($this->field_type) {
            'number'    => Forms\Components\TextInput::make($key)
                ->label($this->label)
                ->numeric(),

            'date'      => Forms\Components\DatePicker::make($key)
                ->label($this->label),

            'boolean'   => Forms\Components\Toggle::make($key)
                ->label($this->label),

            'select'    => Forms\Components\Select::make($key)
                ->label($this->label)
                ->options($this->selectOptions())
                ->nullable(),

            'rich_text' => \App\Forms\Components\QuillEditor::make($key)
                ->label($this->label),

            default     => Forms\Components\TextInput::make($key)
                ->label($this->label),
        };
    }

    /**
     * Convert options array (stored as [{value, label}]) to a flat key=>value
     * map suitable for Filament Select::options().
     */
    private function selectOptions(): array
    {
        if (empty($this->options)) {
            return [];
        }

        $result = [];

        foreach ($this->options as $opt) {
            $value        = $opt['value'] ?? $opt;
            $label        = $opt['label'] ?? $opt['value'] ?? $opt;
            $result[$value] = $label;
        }

        return $result;
    }
}
