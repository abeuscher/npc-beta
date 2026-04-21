<?php

namespace App\Importers\Concerns;

/**
 * Base for the per-type importer aggregators (Event, Donation, Membership,
 * Invoice, Note). Concrete classes declare an ordered list of buckets plus
 * an "Other" options block; this base produces the namespaced dropdown
 * shape (`groupedOptions`), the flat match-key list (`flatFields`), and
 * the namespace validator (`split`).
 *
 * Every aggregator has a Contact-match bucket that exposes only the three
 * canonical lookup keys (email / external_id / phone). Concrete classes
 * declare this bucket alongside their entity buckets by using the
 * `CONTACT_MATCH_KEYS` constant in a `fields_subset` entry — see
 * `EventImportFieldRegistry` for the canonical example.
 */
abstract class AggregatingRegistry
{
    public const CONTACT_MATCH_KEYS = ['email', 'external_id', 'phone'];

    /**
     * Ordered list of buckets. Each bucket declares:
     *   - namespace         string — prefix for keys in this bucket
     *   - registry          string — FieldRegistry class (fields source)
     *   - group_label       string — section label shown in groupedOptions
     *   - flat_label_prefix string — per-option prefix in flatFields
     *   - fields_subset     array? — restrict to these field keys only
     */
    abstract protected static function buckets(): array;

    /**
     * "Other" section — relational / sentinel entries shown at the bottom of
     * the grouped dropdown.
     */
    abstract protected static function otherOptions(): array;

    public static function groupedOptions(): array
    {
        $groups = [];

        foreach (static::buckets() as $bucket) {
            $groups[$bucket['group_label']] = collect(static::bucketOptions($bucket))
                ->mapWithKeys(fn ($label, $key) => ["{$bucket['namespace']}:{$key}" => $label])
                ->all();
        }

        $groups['Other'] = static::otherOptions();

        return $groups;
    }

    public static function flatFields(): array
    {
        $out = [];

        foreach (static::buckets() as $bucket) {
            foreach (static::bucketOptions($bucket) as $key => $label) {
                $out["{$bucket['namespace']}:{$key}"] = "{$bucket['flat_label_prefix']} — {$label}";
            }
        }

        return $out;
    }

    public static function split(string $key): array
    {
        if (! str_contains($key, ':')) {
            return [null, null];
        }

        [$ns, $field] = explode(':', $key, 2);

        $allowedNs = array_column(static::buckets(), 'namespace');

        if (! in_array($ns, $allowedNs, true)) {
            return [null, null];
        }

        return [$ns, $field];
    }

    protected static function bucketOptions(array $bucket): array
    {
        $registry = $bucket['registry'];
        $options  = $registry::options();

        if (isset($bucket['fields_subset']) && is_array($bucket['fields_subset'])) {
            $options = collect($options)->only($bucket['fields_subset'])->all();
        }

        return $options;
    }
}
