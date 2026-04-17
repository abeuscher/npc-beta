<?php

namespace App\Importers;

/**
 * Aggregates Donation / Transaction / Contact-match field registries into a
 * single namespaced dropdown for the donations importer.
 */
class DonationImportFieldRegistry
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
            'Donation fields'    => $prefix('donation',    DonationFieldRegistry::options()),
            'Transaction fields' => $prefix('transaction', TransactionFieldRegistry::options()),
            'Contact match'      => $prefix('contact',     $contactMatch),
            'Other'              => [
                '__custom_donation__' => '— Create as Donation custom field —',
                '__tag_contact__'     => '— Apply as Contact tag —',
                '__note_contact__'    => '— Create as Contact note —',
                '__org_contact__'     => '— Link to Contact Organization —',
            ],
        ];
    }

    public static function flatFields(): array
    {
        $out = [];

        foreach (DonationFieldRegistry::options() as $k => $label) {
            $out["donation:{$k}"] = "Donation — {$label}";
        }

        foreach (TransactionFieldRegistry::options() as $k => $label) {
            $out["transaction:{$k}"] = "Transaction — {$label}";
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

        if (! in_array($ns, ['donation', 'contact', 'transaction'], true)) {
            return [null, null];
        }

        return [$ns, $field];
    }
}
