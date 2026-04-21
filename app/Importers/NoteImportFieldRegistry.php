<?php

namespace App\Importers;

/**
 * Aggregates Note + Contact-match field registries into a single namespaced
 * dropdown for the notes importer.
 */
class NoteImportFieldRegistry
{
    public const CONTACT_MATCH_KEYS = ['email', 'external_id', 'phone'];

    public static function groupedOptions(): array
    {
        $prefix = fn (string $ns, array $map) => collect($map)
            ->mapWithKeys(fn ($label, $key) => ["{$ns}:{$key}" => $label])
            ->all();

        $contactMatch = collect(ContactFieldRegistry::options())
            ->only(static::CONTACT_MATCH_KEYS)
            ->all();

        return [
            'Note fields'   => $prefix('note',    NoteFieldRegistry::options()),
            'Contact match' => $prefix('contact', $contactMatch),
            'Other'         => [
                '__custom_note__' => '— Store in `meta` (source field) —',
                '__tag_contact__' => '— Apply as Contact tag —',
            ],
        ];
    }

    public static function flatFields(): array
    {
        $out = [];

        foreach (NoteFieldRegistry::options() as $k => $label) {
            $out["note:{$k}"] = "Note — {$label}";
        }

        foreach (static::CONTACT_MATCH_KEYS as $k) {
            $label = ContactFieldRegistry::fields()[$k]['label'] ?? ucfirst($k);
            $out["contact:{$k}"] = "Contact — {$label}";
        }

        return $out;
    }

    public static function split(string $key): array
    {
        if (! str_contains($key, ':')) {
            return [null, null];
        }

        [$ns, $field] = explode(':', $key, 2);

        if (! in_array($ns, ['note', 'contact'], true)) {
            return [null, null];
        }

        return [$ns, $field];
    }
}
