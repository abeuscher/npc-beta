<?php

use App\Filament\Resources\CampaignResource;
use App\Filament\Resources\ContactResource;
use App\Filament\Resources\FundResource;
use App\Filament\Resources\OrganizationResource;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\Fund;
use App\Models\Organization;
use App\Services\ListExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
});

function captureStream(\Symfony\Component\HttpFoundation\StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return ob_get_clean();
}

function readXlsxRows(string $body): array
{
    $tempPath = tempnam(sys_get_temp_dir(), 'xlsx-test-');
    file_put_contents($tempPath, $body);

    $reader = new XlsxReader();
    $reader->open($tempPath);

    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->getCells();
        }

        break;
    }

    $reader->close();
    @unlink($tempPath);

    return $rows;
}

it('streams a CSV with the expected header row and first data row', function () {
    Contact::factory()->create([
        'first_name' => 'Ada',
        'last_name'  => 'Lovelace',
        'email'      => 'ada@example.com',
    ]);

    $response = app(ListExportService::class)->stream(
        query: Contact::query()->orderBy('created_at'),
        columnSpec: ContactResource::exportColumnSpec(),
        format: 'csv',
        filename: 'contacts.csv',
        cfModelKey: 'contact',
    );

    $body = captureStream($response);
    $rows = array_map('str_getcsv', preg_split("/\r\n|\n|\r/", trim($body)));

    expect($response->headers->get('Content-Type'))->toBe('text/csv');
    expect($rows[0][0])->toBe('first_name');
    expect($rows[1][0])->toBe('Ada');
    expect($rows[1][2])->toBe('ada@example.com');
});

it('streams a JSON array of objects with expected keys', function () {
    Contact::factory()->create([
        'first_name' => 'Ada',
        'last_name'  => 'Lovelace',
        'email'      => 'ada@example.com',
    ]);

    $response = app(ListExportService::class)->stream(
        query: Contact::query()->orderBy('created_at'),
        columnSpec: ContactResource::exportColumnSpec(),
        format: 'json',
        filename: 'contacts.json',
        cfModelKey: 'contact',
    );

    $body    = captureStream($response);
    $decoded = json_decode($body, true);

    expect($response->headers->get('Content-Type'))->toBe('application/json');
    expect($decoded)->toBeArray()->toHaveCount(1);
    expect($decoded[0])->toHaveKeys(['first_name', 'last_name', 'email']);
    expect($decoded[0]['first_name'])->toBe('Ada');
});

it('flattens custom fields one column per CFDef in CSV', function () {
    CustomFieldDef::create([
        'model_type' => 'contact',
        'handle'     => 'donor_tier',
        'label'      => 'Donor Tier',
        'field_type' => 'text',
        'sort_order' => 1,
    ]);

    Contact::factory()->create([
        'first_name'    => 'Ada',
        'custom_fields' => ['donor_tier' => 'gold'],
    ]);

    $response = app(ListExportService::class)->stream(
        query: Contact::query()->orderBy('created_at'),
        columnSpec: ContactResource::exportColumnSpec(),
        format: 'csv',
        filename: 'contacts.csv',
        cfModelKey: 'contact',
    );

    $body = captureStream($response);
    $rows = array_map('str_getcsv', preg_split("/\r\n|\n|\r/", trim($body)));

    expect($rows[0])->toContain('Donor Tier');
    expect(end($rows[1]))->toBe('gold');
});

it('nests custom fields as a sub-object in JSON', function () {
    CustomFieldDef::create([
        'model_type' => 'contact',
        'handle'     => 'donor_tier',
        'label'      => 'Donor Tier',
        'field_type' => 'text',
        'sort_order' => 1,
    ]);

    Contact::factory()->create([
        'first_name'    => 'Ada',
        'custom_fields' => ['donor_tier' => 'gold'],
    ]);

    $response = app(ListExportService::class)->stream(
        query: Contact::query()->orderBy('created_at'),
        columnSpec: ContactResource::exportColumnSpec(),
        format: 'json',
        filename: 'contacts.json',
        cfModelKey: 'contact',
    );

    $body    = captureStream($response);
    $decoded = json_decode($body, true);

    expect($decoded[0])->toHaveKey('custom_fields');
    expect($decoded[0]['custom_fields'])->toBe(['donor_tier' => 'gold']);
});

it('omits empty custom fields from JSON output', function () {
    CustomFieldDef::create([
        'model_type' => 'contact',
        'handle'     => 'donor_tier',
        'label'      => 'Donor Tier',
        'field_type' => 'text',
        'sort_order' => 1,
    ]);

    CustomFieldDef::create([
        'model_type' => 'contact',
        'handle'     => 'anniversary',
        'label'      => 'Anniversary',
        'field_type' => 'date',
        'sort_order' => 2,
    ]);

    Contact::factory()->create([
        'first_name'    => 'Ada',
        'custom_fields' => ['donor_tier' => 'gold', 'anniversary' => ''],
    ]);

    $response = app(ListExportService::class)->stream(
        query: Contact::query()->orderBy('created_at'),
        columnSpec: ContactResource::exportColumnSpec(),
        format: 'json',
        filename: 'contacts.json',
        cfModelKey: 'contact',
    );

    $body    = captureStream($response);
    $decoded = json_decode($body, true);

    expect($decoded[0]['custom_fields'])->toHaveKey('donor_tier');
    expect($decoded[0]['custom_fields'])->not->toHaveKey('anniversary');
});

it('emits empty JSON array when query has no rows', function () {
    $response = app(ListExportService::class)->stream(
        query: Organization::query(),
        columnSpec: OrganizationResource::exportColumnSpec(),
        format: 'json',
        filename: 'organizations.json',
    );

    expect(captureStream($response))->toBe('[]');
});

it('emits CSV header row only when query has no rows', function () {
    $response = app(ListExportService::class)->stream(
        query: Organization::query(),
        columnSpec: OrganizationResource::exportColumnSpec(),
        format: 'csv',
        filename: 'organizations.csv',
    );

    $body = captureStream($response);
    $rows = array_map('str_getcsv', preg_split("/\r\n|\n|\r/", trim($body)));

    expect($rows)->toHaveCount(1);
    expect($rows[0])->toContain('name');
});

it('omits the custom_fields key from JSON when no CF defs exist', function () {
    Organization::factory()->create(['name' => 'Acme']);

    $response = app(ListExportService::class)->stream(
        query: Organization::query()->orderBy('created_at'),
        columnSpec: OrganizationResource::exportColumnSpec(),
        format: 'json',
        filename: 'organizations.json',
        cfModelKey: 'organization',
    );

    $body    = captureStream($response);
    $decoded = json_decode($body, true);

    expect($decoded[0])->not->toHaveKey('custom_fields');
});

it('honors filter state on the supplied query', function () {
    Organization::factory()->create(['name' => 'Acme', 'type' => 'nonprofit']);
    Organization::factory()->create(['name' => 'Beta', 'type' => 'for_profit']);

    $response = app(ListExportService::class)->stream(
        query: Organization::query()->where('type', 'nonprofit')->orderBy('created_at'),
        columnSpec: OrganizationResource::exportColumnSpec(),
        format: 'json',
        filename: 'organizations.json',
        cfModelKey: 'organization',
    );

    $body    = captureStream($response);
    $decoded = json_decode($body, true);

    expect($decoded)->toHaveCount(1);
    expect($decoded[0]['name'])->toBe('Acme');
});

it('streams an XLSX with the canonical Excel content type and parses cleanly via OpenSpout Reader', function () {
    Contact::factory()->create([
        'first_name'    => 'Ada',
        'last_name'     => 'Lovelace',
        'email'         => 'ada@example.com',
        'date_of_birth' => '1815-12-10',
    ]);

    $response = app(ListExportService::class)->stream(
        query: Contact::query()->orderBy('created_at'),
        columnSpec: ContactResource::exportColumnSpec(),
        format: 'xlsx',
        filename: 'contacts.xlsx',
        cfModelKey: 'contact',
    );

    expect($response->headers->get('Content-Type'))
        ->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $rows = readXlsxRows(captureStream($response));

    expect($rows)->toHaveCount(2);
    expect($rows[0][0]->getValue())->toBe('first_name');
    expect($rows[1][0]->getValue())->toBe('Ada');
    expect($rows[1][2]->getValue())->toBe('ada@example.com');
});

it('emits header-row-only XLSX when query has no rows', function () {
    $response = app(ListExportService::class)->stream(
        query: Organization::query(),
        columnSpec: OrganizationResource::exportColumnSpec(),
        format: 'xlsx',
        filename: 'organizations.xlsx',
    );

    $rows = readXlsxRows(captureStream($response));

    expect($rows)->toHaveCount(1);
    expect($rows[0][0]->getValue())->toBe('name');
});

it('flattens custom fields one column per CFDef in XLSX (matches CSV shape)', function () {
    CustomFieldDef::create([
        'model_type' => 'contact',
        'handle'     => 'donor_tier',
        'label'      => 'Donor Tier',
        'field_type' => 'text',
        'sort_order' => 1,
    ]);

    Contact::factory()->create([
        'first_name'    => 'Ada',
        'custom_fields' => ['donor_tier' => 'gold'],
    ]);

    $response = app(ListExportService::class)->stream(
        query: Contact::query()->orderBy('created_at'),
        columnSpec: ContactResource::exportColumnSpec(),
        format: 'xlsx',
        filename: 'contacts.xlsx',
        cfModelKey: 'contact',
    );

    $rows           = readXlsxRows(captureStream($response));
    $headerValues   = array_map(fn ($cell) => $cell->getValue(), $rows[0]);
    $dataValues     = array_map(fn ($cell) => $cell->getValue(), $rows[1]);

    expect($headerValues)->toContain('Donor Tier');
    expect(end($dataValues))->toBe('gold');
});

it('coerces date / datetime / number / boolean cells per the column-spec type hint (Option B)', function () {
    Fund::factory()->create([
        'name'        => 'General Fund',
        'is_active'   => true,
        'is_archived' => false,
    ]);

    Campaign::factory()->create([
        'name'        => 'Spring 2026',
        'goal_amount' => 5432.10,
        'starts_on'   => '2026-03-01',
        'is_active'   => true,
    ]);

    $fundResponse = app(ListExportService::class)->stream(
        query: Fund::query()->orderBy('created_at'),
        columnSpec: FundResource::exportColumnSpec(),
        format: 'xlsx',
        filename: 'funds.xlsx',
    );

    $fundRows = readXlsxRows(captureStream($fundResponse));
    $fundData = $fundRows[1];

    $isActiveIdx = array_search('is_active', array_map(fn ($c) => $c->getValue(), $fundRows[0]), true);
    $createdIdx  = array_search('created_at', array_map(fn ($c) => $c->getValue(), $fundRows[0]), true);

    expect($fundData[$isActiveIdx]->getValue())->toBeBool();
    expect($fundData[$isActiveIdx]->getValue())->toBeTrue();
    expect($fundData[$createdIdx]->getValue())->toBeInstanceOf(\DateTimeInterface::class);

    $campaignResponse = app(ListExportService::class)->stream(
        query: Campaign::query()->orderBy('created_at'),
        columnSpec: CampaignResource::exportColumnSpec(),
        format: 'xlsx',
        filename: 'campaigns.xlsx',
    );

    $campaignRows = readXlsxRows(captureStream($campaignResponse));
    $campaignData = $campaignRows[1];
    $headerValues = array_map(fn ($c) => $c->getValue(), $campaignRows[0]);

    $goalIdx     = array_search('goal_amount', $headerValues, true);
    $startsIdx   = array_search('starts_on', $headerValues, true);

    expect($campaignData[$goalIdx]->getValue())->toBeFloat();
    expect($campaignData[$goalIdx]->getValue())->toEqual(5432.10);
    expect($campaignData[$startsIdx]->getValue())->toBeInstanceOf(\DateTimeInterface::class);
});

it('passes through values without a type hint as strings', function () {
    Contact::factory()->create([
        'first_name' => 'Ada',
        'email'      => 'ada@example.com',
    ]);

    $response = app(ListExportService::class)->stream(
        query: Contact::query()->orderBy('created_at'),
        columnSpec: ContactResource::exportColumnSpec(),
        format: 'xlsx',
        filename: 'contacts.xlsx',
        cfModelKey: 'contact',
    );

    $rows = readXlsxRows(captureStream($response));

    expect($rows[1][0]->getValue())->toBeString();
    expect($rows[1][0]->getValue())->toBe('Ada');
    expect($rows[1][2]->getValue())->toBeString();
    expect($rows[1][2]->getValue())->toBe('ada@example.com');
});

it('neutralises spreadsheet formula-injection across trigger chars while preserving numbers in CSV', function () {
    // A stored value whose first char is a formula trigger executes when the
    // exported file is re-opened in Excel/Sheets. The exporter prefixes a
    // leading apostrophe to force literal text — except plain numbers, which
    // can never be a formula and stay numeric.
    $cases = [
        'f_eq'   => ['=1+2',      "'=1+2"],
        'f_plus' => ['+cmd|calc', "'+cmd|calc"],
        'f_at'   => ['@SUM(A1)',  "'@SUM(A1)"],
        'f_dash' => ['-2+3+cmd',  "'-2+3+cmd"],
        'f_neg'  => ['-50.00',    '-50.00'],
    ];

    $sort = 1;
    foreach ($cases as $handle => $case) {
        CustomFieldDef::create([
            'model_type' => 'contact',
            'handle'     => $handle,
            'label'      => $handle,
            'field_type' => 'text',
            'sort_order' => $sort++,
        ]);
    }

    Contact::factory()->create([
        'first_name'    => 'Mallory',
        'custom_fields' => array_map(fn ($c) => $c[0], $cases),
    ]);

    $response = app(ListExportService::class)->stream(
        query: Contact::query()->orderBy('created_at'),
        columnSpec: ContactResource::exportColumnSpec(),
        format: 'csv',
        filename: 'contacts.csv',
        cfModelKey: 'contact',
    );

    $rows   = array_map('str_getcsv', preg_split("/\r\n|\n|\r/", trim(captureStream($response))));
    $header = $rows[0];
    $data   = $rows[1];

    foreach ($cases as $handle => [$raw, $expected]) {
        $idx = array_search($handle, $header, true);
        expect($data[$idx])->toBe($expected);
    }
});

it('neutralises formula-injection in XLSX exports', function () {
    CustomFieldDef::create([
        'model_type' => 'contact',
        'handle'     => 'note',
        'label'      => 'Note',
        'field_type' => 'text',
        'sort_order' => 1,
    ]);

    Contact::factory()->create([
        'first_name'    => 'Mallory',
        'custom_fields' => ['note' => '=HYPERLINK("http://evil","x")'],
    ]);

    $response = app(ListExportService::class)->stream(
        query: Contact::query()->orderBy('created_at'),
        columnSpec: ContactResource::exportColumnSpec(),
        format: 'xlsx',
        filename: 'contacts.xlsx',
        cfModelKey: 'contact',
    );

    $rows = readXlsxRows(captureStream($response));
    $data = array_map(fn ($cell) => $cell->getValue(), $rows[1]);

    expect(end($data))->toBe('\'=HYPERLINK("http://evil","x")');
});

it('cleans up the temp file even when row iteration throws mid-stream', function () {
    Contact::factory()->count(3)->create();

    $tempDir = sys_get_temp_dir();
    $before  = glob($tempDir . '/xlsx-*');

    $columnSpec = [
        ['key' => 'first_name', 'header' => 'first_name', 'value' => fn (Contact $c) => $c->first_name],
        [
            'key'    => 'boom',
            'header' => 'boom',
            'value'  => function (Contact $c) {
                static $callCount = 0;
                $callCount++;

                if ($callCount >= 2) {
                    throw new \RuntimeException('Synthetic mid-iteration failure');
                }

                return 'ok';
            },
        ],
    ];

    $response = app(ListExportService::class)->stream(
        query: Contact::query()->orderBy('created_at'),
        columnSpec: $columnSpec,
        format: 'xlsx',
        filename: 'contacts.xlsx',
    );

    ob_start();
    try {
        $response->sendContent();
    } catch (\RuntimeException $e) {
        // Swallow synthetic exception — we're testing the finally-block cleanup.
    }
    ob_end_clean();

    $after = glob($tempDir . '/xlsx-*');

    expect(count($after))->toBe(count($before));
});
