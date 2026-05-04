<?php

namespace App\Services\Import;

use App\Filament\Pages\ImportDonationsProgressPage;
use App\Filament\Pages\ImportEventsProgressPage;
use App\Filament\Pages\ImportInvoiceDetailsProgressPage;
use App\Filament\Pages\ImportMembershipsProgressPage;
use App\Filament\Pages\ImportNotesProgressPage;
use App\Filament\Pages\ImportOrganizationsProgressPage;
use App\Filament\Pages\ImportProgressPage;
use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\ImportLog;
use App\Models\User;
use App\Services\Import\Fixtures\BuilderRegistry;
use App\Services\PiiScanner;
use Filament\Pages\Page;
use ReflectionClass;
use RuntimeException;

class FixtureRunner
{
    private const PAGE_FOR_IMPORTER = [
        'contacts'        => ImportProgressPage::class,
        'events'          => ImportEventsProgressPage::class,
        'donations'       => ImportDonationsProgressPage::class,
        'memberships'     => ImportMembershipsProgressPage::class,
        'invoice_details' => ImportInvoiceDetailsProgressPage::class,
        'notes'           => ImportNotesProgressPage::class,
        'organizations'   => ImportOrganizationsProgressPage::class,
    ];

    public function __construct(
        private BuilderRegistry $registry,
    ) {}

    /**
     * Run the importer against a CSV fixture. Returns per-row outcomes
     * comparable to the manifest. For pii-shape fixtures, returns a single
     * synthetic outcome list whose entries describe the scanner's violations.
     *
     * Caller is responsible for transaction management — typical use is
     * inside a Pest test with `RefreshDatabase`, or wrapped in a manual
     * `DB::transaction(...)` outside test contexts.
     *
     * @return array<int, array{outcome:string, skip_reason?:?string, error_reason?:?string, pii_violation?:?array, row_number:int}>
     */
    public function runFixture(string $importer, string $csvPath, array $manifest): array
    {
        if ($manifest['shape'] === 'pii') {
            return $this->runPiiScanner($csvPath, $manifest);
        }

        return $this->runProcessOneRow($importer, $csvPath, $manifest);
    }

    private function runPiiScanner(string $csvPath, array $manifest): array
    {
        [$decodedPath, $headers] = $this->decodeFixture($csvPath, $manifest['encoding']);

        $scan = (new PiiScanner())->scan($decodedPath, $headers);

        $outcomes = [];

        foreach ($scan['violations'] as $v) {
            $outcomes[] = [
                'row_number'    => $v['row'],
                'outcome'       => 'pii_rejected',
                'pii_violation' => [
                    'reason' => $v['reason'],
                    'column' => $v['column'],
                ],
            ];
        }

        if ($decodedPath !== $csvPath) {
            @unlink($decodedPath);
        }

        return $outcomes;
    }

    private function runProcessOneRow(string $importer, string $csvPath, array $manifest): array
    {
        if (! isset(self::PAGE_FOR_IMPORTER[$importer])) {
            throw new RuntimeException("Unknown importer: {$importer}");
        }

        $builder    = $this->registry->for($importer);
        $pageClass  = self::PAGE_FOR_IMPORTER[$importer];

        $this->seedCustomFieldDefs($importer, $manifest['custom_field_columns'] ?? []);

        [$decodedPath, $headers] = $this->decodeFixture($csvPath, $manifest['encoding']);

        $log = $this->buildSyntheticLog($builder, $manifest);

        $this->seedContactsFromFixture($builder, $log->column_map, $decodedPath, $headers);

        /** @var Page $page */
        $page = new $pageClass();

        $page->csvHeaders      = $headers;
        $page->importSourceId  = '';
        $page->importSessionId = '';
        $page->importerUserId  = $this->seedFixtureUser()->id;

        if (property_exists($page, 'contactStrategy')) {
            $page->contactStrategy = 'auto_create';
        }

        $reflection = new ReflectionClass($page);

        $buildRowContext = $reflection->getMethod('buildRowContext');
        $buildRowContext->setAccessible(true);
        $context = $buildRowContext->invoke($page, $log);

        $processOneRow = $reflection->getMethod('processOneRow');
        $processOneRow->setAccessible(true);

        $handle = fopen($decodedPath, 'r');
        fgetcsv($handle);  // skip header

        $outcomes  = [];
        $rowNumber = 2;

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $result = $processOneRow->invoke($page, $row, $rowNumber, $context);

                $outcomes[] = $this->normaliseOutcome($result, $rowNumber);
            } catch (\Throwable $e) {
                $outcomes[] = [
                    'row_number'   => $rowNumber,
                    'outcome'      => 'errored',
                    'error_reason' => 'exception',
                    'message'      => $e->getMessage(),
                ];
            }

            $rowNumber++;
        }

        fclose($handle);

        if ($decodedPath !== $csvPath) {
            @unlink($decodedPath);
        }

        return $outcomes;
    }

    private function normaliseOutcome(array $result, int $rowNumber): array
    {
        $out = ['row_number' => $rowNumber];

        $rawOutcome = $result['outcome'] ?? 'imported';

        $out['outcome'] = match ($rawOutcome) {
            'imported', 'updated' => 'imported',
            'skipped'             => 'skipped',
            'error'               => 'errored',
            default               => 'errored',
        };

        if ($out['outcome'] === 'skipped' && isset($result['skipReason'])) {
            $out['skip_reason'] = $result['skipReason'];
        }

        if ($out['outcome'] === 'errored') {
            $out['error_reason'] = $result['skipReason'] ?? 'exception';

            if (isset($result['message'])) {
                $out['message'] = $result['message'];
            }
        }

        return $out;
    }

    /**
     * Pre-create one Contact per non-blank email cell in the fixture so
     * importers that match contacts by email (events/donations/memberships/
     * invoice_details/notes) can resolve them. Importers that auto-create
     * contacts on miss don't strictly need this, but seeding is harmless
     * (firstOrCreate dedups on email).
     *
     * Skipped for builders whose contactMatchKey is null (contacts /
     * organizations — no contact-match step).
     */
    private function seedContactsFromFixture($builder, array $columnMap, string $csvPath, array $headers): void
    {
        if ($builder->contactMatchKey() === null) {
            return;
        }

        $emailHeader = null;
        foreach ($columnMap as $header => $dest) {
            if ($dest === 'contact:email') {
                $emailHeader = $header;
                break;
            }
        }

        if ($emailHeader === null) {
            return;
        }

        $emailIndex = array_search($emailHeader, $headers, true);

        if ($emailIndex === false) {
            return;
        }

        $h = fopen($csvPath, 'r');
        fgetcsv($h);

        while (($row = fgetcsv($h)) !== false) {
            $email = trim((string) ($row[$emailIndex] ?? ''));

            if ($email === '') {
                continue;
            }

            Contact::firstOrCreate(
                ['email' => $email],
                ['first_name' => 'Fixture', 'last_name' => 'Contact', 'source' => 'import']
            );
        }

        fclose($h);
    }

    private function seedFixtureUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'fixture-runner@example.test'],
            ['name' => 'Fixture Runner', 'password' => bcrypt('not-used-in-tests')]
        );
    }

    private function seedCustomFieldDefs(string $importer, array $customFieldColumns): void
    {
        $modelTypeFor = match ($importer) {
            'contacts'        => 'contact',
            'events'          => 'event',
            'donations'       => 'donation',
            'memberships'     => 'membership',
            'invoice_details' => 'transaction',
            'notes'           => 'contact',
            'organizations'   => 'organization',
        };

        foreach ($customFieldColumns as $cf) {
            $modelType = match ($cf['target'] ?? null) {
                'event'        => 'event',
                'registration' => 'event_registration',
                default        => $modelTypeFor,
            };

            CustomFieldDef::firstOrCreate(
                ['model_type' => $modelType, 'handle' => $cf['handle']],
                ['label' => $cf['handle'], 'field_type' => $cf['type']]
            );
        }
    }

    private function buildSyntheticLog($builder, array $manifest): ImportLog
    {
        $columnMap      = $builder->columnMap($manifest['preset']);
        $customFieldMap = [];
        $sentinel       = $builder->customFieldSentinel();

        foreach ($manifest['custom_field_columns'] ?? [] as $cf) {
            $cfg = [
                'handle'     => $cf['handle'],
                'label'      => $cf['handle'],
                'field_type' => $cf['type'],
            ];

            if (isset($cf['target'])) {
                $cfg['target'] = $cf['target'];
            }

            $customFieldMap[$cf['header']] = $cfg;

            if ($sentinel !== null) {
                $columnMap[$cf['header']] = $sentinel;
            }
        }

        $log = new ImportLog();
        $log->column_map        = $columnMap;
        $log->custom_field_map  = $customFieldMap;
        $log->relational_map    = [];
        $log->duplicate_strategy = 'skip';
        $log->match_key         = $builder->defaultMatchKey() === 'name'
            ? 'organization:name'
            : $builder->defaultMatchKey();
        $log->contact_match_key = $builder->contactMatchKey() ?? 'contact:email';
        $log->column_preferences = [];

        return $log;
    }

    /**
     * Strip BOM / transcode Windows-1252 → UTF-8 before invoking the importer
     * (the importer's progress page does the same work in production via
     * fgetcsv defaults; we replicate it ahead of feeding it back in).
     *
     * Returns [decodedPath, headers]. If decoding is needed, decodedPath
     * is a tempfile the caller must clean up. Otherwise it equals the
     * input path.
     */
    private function decodeFixture(string $csvPath, string $encoding): array
    {
        if ($encoding === 'utf8') {
            $headers = $this->readHeaders($csvPath);
            return [$csvPath, $headers];
        }

        $bytes = file_get_contents($csvPath);

        if ($encoding === 'utf8-bom' && str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bytes = substr($bytes, 3);
        }

        if ($encoding === 'windows-1252') {
            $bytes = mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'fixturerunner-');
        file_put_contents($tmp, $bytes);

        $headers = $this->readHeaders($tmp);

        return [$tmp, $headers];
    }

    private function readHeaders(string $path): array
    {
        $h = fopen($path, 'r');
        $headers = array_map('trim', fgetcsv($h) ?: []);
        fclose($h);
        return $headers;
    }
}
