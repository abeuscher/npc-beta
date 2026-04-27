<?php

namespace App\WidgetPrimitive\Projectors;

use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\RecordContextTokens;
use Illuminate\Database\Eloquent\Model;

final class RecordContextProjector
{
    public function __construct(
        private readonly RecordContextTokens $recordContextTokens,
    ) {}

    /**
     * Scalar-map DTO of record-context tokens.
     *
     * SOURCE_RECORD_CONTEXT treats the source itself as the capability boundary
     * — contracts declare no `fields`; the full RecordContextTokens::TOKENS map
     * is returned. Null record yields empty strings for every token (fail-closed).
     *
     * @return array<string, string>
     */
    public function project(DataContract $contract, ?Model $record): array
    {
        $all = $this->recordContextTokens->values($record);

        $dto = [];
        foreach (RecordContextTokens::TOKENS as $token) {
            $dto[$token] = (string) ($all[$token] ?? '');
        }
        return $dto;
    }
}
