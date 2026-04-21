<?php

namespace App\Services\Import;

use App\Importers\ContactFieldRegistry;
use App\Importers\DonationFieldRegistry;
use App\Importers\EventFieldRegistry;
use App\Importers\InvoiceDetailFieldRegistry;
use App\Importers\MembershipFieldRegistry;
use App\Importers\NoteFieldRegistry;
use App\Importers\RegistrationFieldRegistry;
use App\Importers\TransactionFieldRegistry;
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

    public static function eventHeaders(): array
    {
        $headers = [];

        foreach (EventFieldRegistry::options() as $label) {
            $headers[] = "Event {$label}";
        }

        foreach (RegistrationFieldRegistry::options() as $label) {
            $headers[] = "Registration {$label}";
        }

        // Contact match columns
        $headers[] = 'Contact Email';
        $headers[] = 'Contact External ID';
        $headers[] = 'Contact Phone';

        // Transaction columns — labels are already self-disambiguating
        // ('Transaction ID (external)', 'Transaction Amount') so no prefix
        // is applied here.
        foreach (TransactionFieldRegistry::options() as $label) {
            $headers[] = $label;
        }

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

    public static function stream(string $type): StreamedResponse
    {
        $headers = match ($type) {
            'contacts'        => static::contactHeaders(),
            'events'          => static::eventHeaders(),
            'donations'       => static::donationHeaders(),
            'memberships'     => static::membershipHeaders(),
            'invoice_details' => static::invoiceDetailHeaders(),
            'notes'           => static::noteHeaders(),
            default           => throw new \InvalidArgumentException("Unknown template type: {$type}"),
        };

        $filename = str_replace('_', '-', $type) . '-template.csv';

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
