<?php

namespace App\Services\ImportExport;

class ImportLog
{
    /** @var array<int, array{level: string, message: string, context: array}> */
    protected array $entries = [];

    public function warning(string $message, array $context = []): void
    {
        $this->entries[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
    }

    public function info(string $message, array $context = []): void
    {
        $this->entries[] = ['level' => 'info', 'message' => $message, 'context' => $context];
    }

    /**
     * @return array<int, array{level: string, message: string, context: array}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return array<int, array{level: string, message: string, context: array}>
     */
    public function warnings(): array
    {
        return array_values(array_filter($this->entries, fn ($e) => $e['level'] === 'warning'));
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings());
    }
}
