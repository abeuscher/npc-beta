<?php

namespace App\Services;

use App\Models\Tag;

class MailingListFieldRegistry
{
    private static array $definitions = [
        'first_name'  => ['operators' => ['equals', 'not_equals', 'contains', 'not_contains', 'is_empty', 'is_not_empty'], 'value_type' => 'text'],
        'last_name'   => ['operators' => ['equals', 'not_equals', 'contains', 'not_contains', 'is_empty', 'is_not_empty'], 'value_type' => 'text'],
        'email'       => ['operators' => ['equals', 'not_equals', 'contains', 'not_contains', 'is_empty', 'is_not_empty'], 'value_type' => 'text'],
        'phone'       => ['operators' => ['equals', 'not_equals', 'contains', 'not_contains', 'is_empty', 'is_not_empty'], 'value_type' => 'text'],
        'city'        => ['operators' => ['equals', 'not_equals', 'contains', 'not_contains', 'is_empty', 'is_not_empty'], 'value_type' => 'text'],
        'state'       => ['operators' => ['equals', 'not_equals', 'contains', 'not_contains', 'is_empty', 'is_not_empty'], 'value_type' => 'text'],
        'postal_code' => ['operators' => ['equals', 'not_equals', 'contains', 'not_contains', 'is_empty', 'is_not_empty'], 'value_type' => 'text'],
        'tags'        => ['operators' => ['includes', 'not_includes'],                                                      'value_type' => 'tag_picker'],
        'mailing_list_opt_in' => ['operators' => ['equals', 'not_equals'],                                                  'value_type' => 'select'],
    ];

    private static array $operatorLabels = [
        'equals'       => 'equals',
        'not_equals'   => 'not equals',
        'contains'     => 'contains',
        'not_contains' => 'does not contain',
        'includes'     => 'includes tag',
        'not_includes' => 'excludes tag',
        'is_empty'     => 'is empty',
        'is_not_empty' => 'is not empty',
    ];

    public static function fields(): array
    {
        return [
            'first_name'          => 'First Name',
            'last_name'           => 'Last Name',
            'email'               => 'Email',
            'phone'               => 'Phone',
            'city'                => 'City',
            'state'               => 'State',
            'postal_code'         => 'Postal Code',
            'mailing_list_opt_in' => 'Mailing List Opt-In',
            'tags'                => 'Tags',
        ];
    }

    public static function operatorsFor(?string $field): array
    {
        $keys = static::$definitions[$field]['operators'] ?? array_keys(static::$operatorLabels);

        return array_intersect_key(static::$operatorLabels, array_flip($keys));
    }

    public static function valueTypeFor(?string $field): string
    {
        return static::$definitions[$field]['value_type'] ?? 'text';
    }

    public static function tagOptions(): array
    {
        return Tag::where('type', 'contact')->orderBy('name')->pluck('name', 'name')->toArray();
    }
}
