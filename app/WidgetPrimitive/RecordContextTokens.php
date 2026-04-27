<?php

namespace App\WidgetPrimitive;

use Illuminate\Database\Eloquent\Model;

class RecordContextTokens
{
    /**
     * Closed token registry for SOURCE_RECORD_CONTEXT. Adding a token is a
     * grep-visible edit here. Mirror of PageContextTokens::TOKENS — same
     * source-as-capability discipline.
     */
    public const TOKENS = [
        'record_id',
        'record_type',
    ];

    /**
     * Resolve every supported token into a concrete string. Null record yields
     * empty strings for every token (fail-closed).
     *
     * @return array<string, string>
     */
    public function values(?Model $record): array
    {
        return [
            'record_id'   => $record !== null ? (string) $record->getKey() : '',
            'record_type' => $record !== null ? class_basename($record) : '',
        ];
    }
}
