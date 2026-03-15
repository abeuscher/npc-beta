<?php

namespace App\Services\Import;

readonly class ImportResult
{
    public function __construct(
        public int   $imported,
        public int   $updated,
        public int   $skipped,
        public array $errors, // array of ['row' => int, 'message' => string]
    ) {}

    public function errorCount(): int
    {
        return count($this->errors);
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function toArray(): array
    {
        return [
            'imported' => $this->imported,
            'updated'  => $this->updated,
            'skipped'  => $this->skipped,
            'errors'   => $this->errors,
        ];
    }
}
