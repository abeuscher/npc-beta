<?php

namespace App\Filament\Resources\FormResource\Pages;

use App\Filament\Resources\FormResource;
use App\Models\Form;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListForms extends ListRecords
{
    protected static string $resource = FormResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            FormResource::getUrl() => 'Forms',
            'All Forms',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_json')
                ->label('Import JSON')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('secondary')
                ->hidden(fn () => ! auth()->user()?->can('create_form'))
                ->modalHeading('Import Form from JSON')
                ->modalWidth('3xl')
                ->form([
                    Forms\Components\Textarea::make('json_input')
                        ->label('Form JSON')
                        ->required()
                        ->rows(20)
                        ->helperText('Paste a form definition exported from this system. The imported form will be saved as inactive — review and activate it when ready.'),
                ])
                ->action(function (array $data, Actions\Action $action) {
                    abort_unless(auth()->user()?->can('create_form'), 403);

                    $errors = [];

                    // ── Parse ─────────────────────────────────────────────
                    $decoded = json_decode($data['json_input'], true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Notification::make()
                            ->danger()
                            ->title('Invalid JSON')
                            ->body(json_last_error_msg())
                            ->send();
                        $action->halt();
                        return;
                    }

                    // ── Top-level structure ───────────────────────────────
                    if (empty($decoded['title']) || ! is_string($decoded['title'])) {
                        $errors[] = '"title" must be a non-empty string.';
                    }

                    if (empty($decoded['handle']) || ! is_string($decoded['handle'])) {
                        $errors[] = '"handle" must be a non-empty string.';
                    } elseif (! preg_match('/^[a-z0-9_\-]+$/', $decoded['handle'])) {
                        $errors[] = '"handle" must contain only lowercase letters, numbers, hyphens, and underscores.';
                    }

                    if (! isset($decoded['fields']) || ! is_array($decoded['fields'])) {
                        $errors[] = '"fields" must be an array.';
                    }

                    if (! isset($decoded['settings']) || ! is_array($decoded['settings'])) {
                        $errors[] = '"settings" must be an object.';
                    }

                    if ($errors) {
                        $this->sendValidationNotification($errors);
                        $action->halt();
                        return;
                    }

                    // ── Field validation ──────────────────────────────────
                    $allowedTypes = [
                        'text', 'email', 'tel', 'number', 'textarea',
                        'select', 'radio', 'checkbox', 'state', 'country', 'hidden',
                    ];

                    $allowedValidations = [
                        'none', 'email', 'phone', 'zip', 'url',
                        'numbers_only', 'letters_only', 'custom_regex',
                    ];

                    $allowedFieldKeys = [
                        'handle', 'type', 'label', 'placeholder', 'required',
                        'width', 'validation', 'validation_regex', 'validation_message',
                        'hint', 'contact_field', 'default_value', 'options',
                    ];

                    foreach ($decoded['fields'] as $i => $field) {
                        $num = $i + 1;

                        if (empty($field['handle']) || ! is_string($field['handle'])) {
                            $errors[] = "Field {$num}: \"handle\" must be a non-empty string.";
                        } elseif (! preg_match('/^[a-z0-9_]+$/', $field['handle'])) {
                            $errors[] = "Field {$num}: \"handle\" must contain only lowercase letters, numbers, and underscores.";
                        }

                        if (empty($field['type']) || ! in_array($field['type'], $allowedTypes, true)) {
                            $errors[] = "Field {$num}: \"type\" must be one of: " . implode(', ', $allowedTypes) . '.';
                        }

                        if (empty($field['label']) || ! is_string($field['label'])) {
                            $errors[] = "Field {$num}: \"label\" must be a non-empty string.";
                        }

                        if (isset($field['validation']) && ! in_array($field['validation'], $allowedValidations, true)) {
                            $errors[] = "Field {$num}: \"validation\" must be one of: " . implode(', ', $allowedValidations) . '.';
                        }

                        if (isset($field['options']) && ! is_array($field['options'])) {
                            $errors[] = "Field {$num}: \"options\" must be an array.";
                        } elseif (isset($field['options'])) {
                            foreach ($field['options'] as $j => $option) {
                                $optNum = $j + 1;
                                if (empty($option['value']) || ! is_string($option['value'])) {
                                    $errors[] = "Field {$num}, option {$optNum}: \"value\" must be a non-empty string.";
                                }
                                if (empty($option['label']) || ! is_string($option['label'])) {
                                    $errors[] = "Field {$num}, option {$optNum}: \"label\" must be a non-empty string.";
                                }
                            }
                        }

                        // ReDoS check on custom_regex patterns
                        if (($field['validation'] ?? '') === 'custom_regex' && ! empty($field['validation_regex'])) {
                            $pattern = $field['validation_regex'];

                            if (@preg_match($pattern, '') === false) {
                                $errors[] = "Field {$num}: regex pattern is invalid.";
                            } else {
                                $prev = ini_get('pcre.backtrack_limit');
                                ini_set('pcre.backtrack_limit', '1000');
                                @preg_match($pattern, str_repeat('a', 50) . '!');
                                $regexError = preg_last_error();
                                ini_set('pcre.backtrack_limit', $prev);

                                if ($regexError === PREG_BACKTRACK_LIMIT_ERROR) {
                                    $errors[] = "Field {$num}: regex pattern is too complex and may cause performance issues.";
                                }
                            }
                        }
                    }

                    // ── Settings validation ───────────────────────────────
                    $allowedFormTypes    = ['general', 'contact'];
                    $allowedSettingsKeys = ['form_type', 'submit_label', 'success_message', 'honeypot'];

                    if (isset($decoded['settings']['form_type']) &&
                        ! in_array($decoded['settings']['form_type'], $allowedFormTypes, true)) {
                        $errors[] = '"settings.form_type" must be "general" or "contact".';
                    }

                    if ($errors) {
                        $this->sendValidationNotification($errors);
                        $action->halt();
                        return;
                    }

                    // ── Strip unknown keys ────────────────────────────────
                    $cleanedFields = array_map(
                        fn ($field) => array_intersect_key($field, array_flip($allowedFieldKeys)),
                        $decoded['fields']
                    );

                    $cleanedSettings = array_intersect_key(
                        $decoded['settings'],
                        array_flip($allowedSettingsKeys)
                    );

                    // ── Handle uniqueness — auto-suffix if taken ──────────
                    $handle = $decoded['handle'];

                    if (Form::where('handle', $handle)->exists()) {
                        $base   = $handle;
                        $suffix = 2;
                        while (Form::where('handle', "{$base}-{$suffix}")->exists()) {
                            $suffix++;
                        }
                        $handle = "{$base}-{$suffix}";
                    }

                    // ── Create ────────────────────────────────────────────
                    $form = Form::create([
                        'title'     => trim($decoded['title']),
                        'handle'    => $handle,
                        'fields'    => $cleanedFields,
                        'settings'  => $cleanedSettings,
                        'is_active' => false,
                    ]);

                    $handleNote = $handle !== $decoded['handle']
                        ? " Handle changed to \"{$handle}\" to avoid a collision."
                        : '';

                    Notification::make()
                        ->success()
                        ->title('Form imported')
                        ->body("Saved as inactive.{$handleNote} Review and activate when ready.")
                        ->send();

                    $this->redirect(FormResource::getUrl('edit', ['record' => $form]));
                }),

            Actions\CreateAction::make(),
        ];
    }

    private function sendValidationNotification(array $errors): void
    {
        $body = new HtmlString(
            '<ul class="list-disc list-inside space-y-1">' .
            implode('', array_map(fn ($e) => "<li>{$e}</li>", $errors)) .
            '</ul>'
        );

        Notification::make()
            ->danger()
            ->title('Validation failed — ' . count($errors) . ' ' . str('error')->plural(count($errors)))
            ->body($body)
            ->persistent()
            ->send();
    }
}
