<?php

namespace App\Services\Import;

use App\Importers\ContactFieldRegistry;
use App\Importers\DonationFieldRegistry;
use App\Importers\InvoiceDetailFieldRegistry;
use App\Importers\MembershipFieldRegistry;
use App\Importers\NoteFieldRegistry;
use App\Importers\OrganizationFieldRegistry;
use App\Importers\TransactionFieldRegistry;
use App\Plugins\ImporterRegistry;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates header-only CSV templates for each import content type. Column
 * names are chosen to match the guessDestination() heuristics so re-importing
 * a filled template auto-maps perfectly.
 */
class CsvTemplateService
{
    public static function contactHeaders(): array
    {
        $headers = [];

        foreach (ContactFieldRegistry::fields() as $key => $def) {
            $headers[] = $def['label'];
        }

        // Relational columns not in the registry
        $headers[] = 'Organization';
        $headers[] = 'Tags';
        $headers[] = 'Notes';

        return $headers;
    }

    public static function donationHeaders(): array
    {
        $headers = [];

        foreach (DonationFieldRegistry::options() as $label) {
            $headers[] = $label;
        }

        foreach (TransactionFieldRegistry::options() as $key => $label) {
            // Skip duplicated fields
            if ($key === 'invoice_number') {
                continue;
            }
            $headers[] = $label;
        }

        // Contact match columns
        $headers[] = 'Email';
        $headers[] = 'User ID';
        $headers[] = 'Phone';

        return $headers;
    }

    public static function membershipHeaders(): array
    {
        $headers = [];

        foreach (MembershipFieldRegistry::options() as $label) {
            $headers[] = $label;
        }

        // Contact match columns
        $headers[] = 'Email';
        $headers[] = 'User ID';
        $headers[] = 'Phone';

        return $headers;
    }

    public static function invoiceDetailHeaders(): array
    {
        $headers = [];

        foreach (InvoiceDetailFieldRegistry::options() as $label) {
            $headers[] = $label;
        }

        // Contact match columns
        $headers[] = 'Email';
        $headers[] = 'User ID';
        $headers[] = 'Phone';

        return $headers;
    }

    public static function noteHeaders(): array
    {
        $headers = [];

        foreach (NoteFieldRegistry::options() as $label) {
            $headers[] = "Note {$label}";
        }

        // Contact match columns
        $headers[] = 'Email';
        $headers[] = 'User ID';
        $headers[] = 'Phone';

        return $headers;
    }

    public static function organizationHeaders(): array
    {
        $headers = [];

        foreach (OrganizationFieldRegistry::options() as $label) {
            $headers[] = $label;
        }

        // Relational columns not in the registry
        $headers[] = 'Tags';
        $headers[] = 'Notes';

        return $headers;
    }

    /**
     * Header row for a template type: the core types are hard-wired here;
     * plugin importers (docs/plugin-contract.md surface 8) contribute theirs
     * through the ImporterRegistry.
     */
    public static function headersFor(string $type): array
    {
        return match ($type) {
            'contacts'        => static::contactHeaders(),
            'donations'       => static::donationHeaders(),
            'memberships'     => static::membershipHeaders(),
            'invoice_details' => static::invoiceDetailHeaders(),
            'notes'           => static::noteHeaders(),
            'organizations'   => static::organizationHeaders(),
            default           => (function () use ($type): array {
                $contribution = app(ImporterRegistry::class)->find($type);
                if ($contribution === null) {
                    throw new \InvalidArgumentException("Unknown template type: {$type}");
                }

                return ($contribution->templateHeaders)();
            })(),
        };
    }

    public static function stream(string $type): StreamedResponse
    {
        $headers = static::headersFor($type);

        $filename = str_replace('_', '-', $type) . '-template.csv';

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
