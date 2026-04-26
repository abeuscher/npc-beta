<?php

namespace App\Console\Commands;

use App\Services\Import\CsvTemplateService;
use App\Services\Import\FakeCsvComposer;
use DateTimeImmutable;
use Faker\Factory as FakerFactory;
use Illuminate\Console\Command;
use RuntimeException;

class GenerateFakeImportCsvsCommand extends Command
{
    protected $signature = 'csv:generate-fake-imports
        {--out=storage/app/fake-csvs : Output directory relative to project base_path}
        {--seed= : PRNG seed for reproducibility}';

    protected $description = 'Emit a self-consistent bundle of fake import CSVs (contacts, events, donations, memberships, invoice_details, notes) to a directory.';

    public function handle(): int
    {
        $seed = $this->option('seed');
        $faker = FakerFactory::create();
        $pinnedNow = null;
        if ($seed !== null && $seed !== '') {
            $seedInt = (int) $seed;
            $faker->seed($seedInt);
            mt_srand($seedInt);
            $pinnedNow = new DateTimeImmutable('@1735689600');
        }

        $out = $this->option('out');
        if (! str_starts_with($out, '/')) {
            $out = base_path($out);
        }
        if (! is_dir($out)) {
            mkdir($out, 0755, true);
        }

        $composer = new FakeCsvComposer($faker, $pinnedNow);

        $contactCount   = $faker->numberBetween(200, 250);
        $eventRows      = $faker->numberBetween(20, 40);
        $donationCount  = $faker->numberBetween(300, 500);
        $membershipCount = $faker->numberBetween(50, 100);
        $invoiceRows    = $faker->numberBetween(100, 300);
        $noteCount      = $faker->numberBetween(300, 600);

        $contacts = $composer->composeContacts($contactCount);
        $emails   = $composer->extractContactEmails($contacts);

        $events    = $composer->composeEvents($eventRows, $emails);
        $donations = $composer->composeDonations($donationCount, $emails);
        $memberships = $composer->composeMemberships($membershipCount, $emails);
        $invoices  = $composer->composeInvoiceDetails($invoiceRows, $emails);
        $notes     = $composer->composeNotes($noteCount, $emails);

        $this->writeCsv($out . '/contacts.csv', CsvTemplateService::contactHeaders(), $contacts, 'contacts');
        $this->writeCsv($out . '/events.csv', CsvTemplateService::eventHeaders(), $events, 'events');
        $this->writeCsv($out . '/donations.csv', CsvTemplateService::donationHeaders(), $donations, 'donations');
        $this->writeCsv($out . '/memberships.csv', CsvTemplateService::membershipHeaders(), $memberships, 'memberships');
        $this->writeCsv($out . '/invoice_details.csv', CsvTemplateService::invoiceDetailHeaders(), $invoices, 'invoice_details');
        $this->writeCsv($out . '/notes.csv', CsvTemplateService::noteHeaders(), $notes, 'notes');

        $this->info("Wrote fake import CSVs to {$out}");
        $this->line("  contacts.csv:         " . count($contacts) . ' rows');
        $this->line("  events.csv:           " . count($events) . ' rows');
        $this->line("  donations.csv:        " . count($donations) . ' rows');
        $this->line("  memberships.csv:      " . count($memberships) . ' rows');
        $this->line("  invoice_details.csv:  " . count($invoices) . ' rows');
        $this->line("  notes.csv:            " . count($notes) . ' rows');

        return self::SUCCESS;
    }

    private function writeCsv(string $path, array $headers, array $rows, string $type): void
    {
        $this->validateHeaders($headers, $rows, $type);

        $fp = fopen($path, 'w');
        if ($fp === false) {
            throw new RuntimeException("Failed to open {$path} for writing.");
        }
        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            $line = array_map(fn ($h) => $row[$h] ?? null, $headers);
            fputcsv($fp, $line);
        }
        fclose($fp);
    }

    private function validateHeaders(array $canonical, array $rows, string $type): void
    {
        if ($rows === []) {
            throw new RuntimeException("Composer returned zero rows for {$type}.");
        }
        $got     = array_keys($rows[0]);
        $missing = array_diff($canonical, $got);
        $extra   = array_diff($got, $canonical);
        if ($missing !== [] || $extra !== []) {
            throw new RuntimeException(
                "Header drift for {$type}. Missing: " . json_encode(array_values($missing)) .
                ' Extra: ' . json_encode(array_values($extra))
            );
        }
    }
}
