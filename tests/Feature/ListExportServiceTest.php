<?php

use App\Filament\Resources\ContactResource;
use App\Filament\Resources\OrganizationResource;
use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\Organization;
use App\Services\ListExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
