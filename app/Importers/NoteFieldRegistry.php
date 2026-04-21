<?php

namespace App\Importers;

use App\Importers\Concerns\FieldRegistry;

/**
 * Importable Note fields. Keys are raw column names; the aggregate
 * NoteImportFieldRegistry namespaces them to `note:*`.
 */
class NoteFieldRegistry extends FieldRegistry
{
    public static function fields(): array
    {
        return [
            'type'             => ['label' => 'Type', 'type' => 'text'],
            'subject'          => ['label' => 'Subject', 'type' => 'text'],
            'status'           => ['label' => 'Status', 'type' => 'text'],
            'body'             => ['label' => 'Body', 'type' => 'textarea'],
            'occurred_at'      => ['label' => 'Occurred At', 'type' => 'datetime'],
            'follow_up_at'     => ['label' => 'Follow-up At', 'type' => 'datetime'],
            'outcome'          => ['label' => 'Outcome', 'type' => 'textarea'],
            'duration_minutes' => ['label' => 'Duration (minutes)', 'type' => 'integer'],
            'external_id'      => ['label' => 'External ID', 'type' => 'external_id'],
        ];
    }
}
