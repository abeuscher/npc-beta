<?php

namespace App\Services;

use App\Models\CustomFieldDef;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
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

        $contentType = match ($format) {
            'json' => 'application/json',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'text/csv',
        };

        return response()->streamDownload(
            function () use ($query, $columnSpec, $customDefs, $format) {
                match ($format) {
                    'json' => $this->streamJson($query, $columnSpec, $customDefs),
                    'xlsx' => $this->streamXlsx($query, $columnSpec, $customDefs),
                    default => $this->streamCsv($query, $columnSpec, $customDefs),
                };
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

    private function streamXlsx(Builder $query, array $columnSpec, Collection $customDefs): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx-');

        try {
            $writer = new Writer();
            $writer->openToFile($tempPath);

            $dateStyle     = (new Style())->setFormat('yyyy-mm-dd');
            $datetimeStyle = (new Style())->setFormat('yyyy-mm-dd hh:mm:ss');

            $writer->addRow(Row::fromValues(array_merge(
                array_column($columnSpec, 'header'),
                $customDefs->pluck('label')->toArray(),
            )));

            $query->each(function (Model $model) use ($writer, $columnSpec, $customDefs, $dateStyle, $datetimeStyle) {
                $cells = [];

                foreach ($columnSpec as $col) {
                    $type    = $col['type'] ?? null;
                    $coerced = $this->coerceForXlsx(($col['value'])($model), $type);
                    $style   = match ($type) {
                        'date'     => $dateStyle,
                        'datetime' => $datetimeStyle,
                        default    => null,
                    };

                    $cells[] = Cell::fromValue($coerced, $style);
                }

                foreach ($customDefs as $def) {
                    $cells[] = Cell::fromValue($model->custom_fields[$def->handle] ?? '');
                }

                $writer->addRow(new Row($cells));
            });

            $writer->close();

            readfile($tempPath);
        } finally {
            @unlink($tempPath);
        }
    }

    private function coerceForXlsx(mixed $value, ?string $type): bool|float|int|string|\DateTimeInterface|null
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return match ($type) {
            'date', 'datetime' => Carbon::parse($value),
            'number' => (float) $value,
            'boolean' => (bool) $value,
            default => $value,
        };
    }
}
