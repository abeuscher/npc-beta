<?php

namespace App\Services;

use App\Models\CustomFieldDef;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListExportService
{
    public function stream(
        Builder $query,
        array $columnSpec,
        string $format,
        string $filename,
        ?string $cfModelKey = null,
    ): StreamedResponse {
        $customDefs = $cfModelKey
            ? CustomFieldDef::forModel($cfModelKey)->get()
            : collect();

        $contentType = $format === 'json' ? 'application/json' : 'text/csv';

        return response()->streamDownload(
            function () use ($query, $columnSpec, $customDefs, $format) {
                if ($format === 'json') {
                    $this->streamJson($query, $columnSpec, $customDefs);
                } else {
                    $this->streamCsv($query, $columnSpec, $customDefs);
                }
            },
            $filename,
            ['Content-Type' => $contentType],
        );
    }

    private function streamCsv(Builder $query, array $columnSpec, Collection $customDefs): void
    {
        $handle = fopen('php://output', 'w');

        fputcsv($handle, array_merge(
            array_column($columnSpec, 'header'),
            $customDefs->pluck('label')->toArray(),
        ));

        $query->each(function (Model $model) use ($handle, $columnSpec, $customDefs) {
            $standardValues = array_map(
                fn (array $col) => ($col['value'])($model),
                $columnSpec,
            );

            $customValues = $customDefs
                ->map(fn ($def) => $model->custom_fields[$def->handle] ?? '')
                ->toArray();

            fputcsv($handle, array_merge($standardValues, $customValues));
        });

        fclose($handle);
    }

    private function streamJson(Builder $query, array $columnSpec, Collection $customDefs): void
    {
        echo '[';

        $first = true;

        $query->each(function (Model $model) use ($columnSpec, $customDefs, &$first) {
            $row = [];

            foreach ($columnSpec as $col) {
                $row[$col['key']] = ($col['value'])($model);
            }

            if ($customDefs->isNotEmpty()) {
                $cf = [];

                foreach ($customDefs as $def) {
                    $value = $model->custom_fields[$def->handle] ?? null;

                    if ($value !== null && $value !== '') {
                        $cf[$def->handle] = $value;
                    }
                }

                if (! empty($cf)) {
                    $row['custom_fields'] = $cf;
                }
            }

            echo ($first ? '' : ',') . json_encode($row);
            $first = false;
        });

        echo ']';
    }
}
