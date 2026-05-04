<?php

namespace App\Services\Import\Fixtures\Transforms;

use App\Services\Import\Fixtures\FixtureBuilder;

interface FixtureTransform
{
    /**
     * Mutate clean rows + manifest entries into the shape's adversarial form.
     * Returns [rows, manifestEntries, customFieldColumns] — custom-field columns
     * may grow (e.g. stress's wide variant).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $manifestEntries
     * @param  array<int, array{header:string,handle:string,type:string}>  $customFieldColumns
     * @return array{0: array, 1: array, 2: array}
     */
    public function apply(
        array $rows,
        array $manifestEntries,
        array $customFieldColumns,
        FixtureBuilder $builder,
        string $preset,
        int $seed,
        ?int $rowsOverride
    ): array;
}
