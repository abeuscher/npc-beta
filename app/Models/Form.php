<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    protected $fillable = [
        'title',
        'handle',
        'description',
        'fields',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'fields'    => 'array',
        'settings'  => 'array',
        'is_active' => 'boolean',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function fieldValidationRules(): array
    {
        $presetMap = [
            'none'         => [],
            'email'        => ['email'],
            'phone'        => ['regex:/^[\d\s\-\(\)\+]{7,15}$/'],
            'zip'          => ['regex:/^\d{5}(-\d{4})?$/'],
            'url'          => ['url'],
            'numbers_only' => ['regex:/^\d+$/'],
            'letters_only' => ['regex:/^[a-zA-Z\s]+$/'],
            'custom_regex' => [],
        ];

        $rules = [];

        foreach ($this->fields ?? [] as $field) {
            $handle = $field['handle'] ?? null;

            if (! $handle || ($field['type'] ?? '') === 'hidden') {
                continue;
            }

            $fieldRules = [];

            if (! empty($field['required'])) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            $preset = $field['validation'] ?? 'none';
            $presetRules = $presetMap[$preset] ?? [];

            if ($preset === 'custom_regex' && ! empty($field['validation_regex'])) {
                try {
                    preg_match($field['validation_regex'], '');
                    $presetRules = ['regex:' . $field['validation_regex']];
                } catch (\Throwable) {
                    // malformed regex — skip silently
                }
            }

            $rules[$handle] = array_merge($fieldRules, $presetRules);
        }

        return $rules;
    }

    public function fieldValidationMessages(): array
    {
        $messages = [];

        foreach ($this->fields ?? [] as $field) {
            $handle  = $field['handle'] ?? null;
            $message = $field['validation_message'] ?? '';

            if ($handle && $message !== '') {
                $messages["{$handle}.regex"] = $message;
            }
        }

        return $messages;
    }
}
