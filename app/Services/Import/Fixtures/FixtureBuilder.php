<?php

namespace App\Services\Import\Fixtures;

use Faker\Generator as Faker;

abstract class FixtureBuilder
{
    abstract public function importer(): string;

    abstract public function supportedPresets(): array;

    abstract public function headers(string $preset): array;

    abstract public function customFieldColumns(string $preset): array;

    abstract public function cleanRow(int $rowIndex, string $preset, Faker $faker): array;

    /**
     * Per-importer column_map: header => destField. Headers that don't appear
     * in this map will be unmapped (importer's column-walking ignores them).
     * Custom-field columns are added by the runner via customFieldSentinel().
     */
    abstract public function columnMap(string $preset): array;

    /**
     * The importer's custom-field column-map sentinel, or null for the
     * contacts importer (which processes custom fields via custom_field_map
     * only, not via column_map).
     */
    public function customFieldSentinel(): ?string
    {
        return null;
    }

    public function defaultMatchKey(): string
    {
        return 'external_id';
    }

    /**
     * The importer's contact-match key (for namespaced importers that match
     * inbound rows to existing contacts). Defaults to 'contact:email'.
     * Returns null for importers that don't have a contact-match step
     * (contacts itself, organizations).
     */
    public function contactMatchKey(): ?string
    {
        return 'contact:email';
    }
}
