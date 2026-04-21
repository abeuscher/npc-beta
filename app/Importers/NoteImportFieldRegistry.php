<?php

namespace App\Importers;

use App\Importers\Concerns\AggregatingRegistry;

/**
 * Aggregates Note + Contact-match field registries into a single namespaced
 * dropdown for the notes importer.
 */
class NoteImportFieldRegistry extends AggregatingRegistry
{
    protected static function buckets(): array
    {
        return [
            [
                'namespace'         => 'note',
                'registry'          => NoteFieldRegistry::class,
                'group_label'       => 'Note fields',
                'flat_label_prefix' => 'Note',
            ],
            [
                'namespace'         => 'contact',
                'registry'          => ContactFieldRegistry::class,
                'group_label'       => 'Contact match',
                'flat_label_prefix' => 'Contact',
                'fields_subset'     => self::CONTACT_MATCH_KEYS,
            ],
        ];
    }

    protected static function otherOptions(): array
    {
        return [
            '__custom_note__' => '— Store in `meta` (source field) —',
            '__tag_contact__' => '— Apply as Contact tag —',
        ];
    }
}
