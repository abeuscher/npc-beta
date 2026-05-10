<?php

use App\Filament\Pages\ImportProgressPage;
use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
});

function writeImporterCsv(array $rows, string $filename): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    Storage::disk('local')->put("imports/{$filename}", $content);

    return "imports/{$filename}";
}

it('sanitises rich_text custom_fields when imported through the Contacts importer', function () {
    $payload = '<p>About me.</p><script>alert(1)</script><a href="javascript:bad">x</a>';

    $path = writeImporterCsv([
        ['email', 'Bio'],
        ['victim@example.com', $payload],
    ], 'rich-text-sanitization-import.csv');

    $log = ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => 'rich-text-sanitization-import.csv',
        'row_count'          => 1,
        'column_map'         => ['email' => 'email', 'Bio' => null],
        'custom_field_map'   => ['Bio' => ['handle' => 'bio', 'label' => 'Bio', 'field_type' => 'rich_text']],
        'duplicate_strategy' => 'skip',
        'status'             => 'pending',
        'storage_path'       => $path,
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo('import_data');
    $this->actingAs($user);

    $page              = new ImportProgressPage();
    $page->importLogId = $log->id;
    $page->mount();
    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    expect(CustomFieldDef::where('handle', 'bio')->where('model_type', 'contact')->where('field_type', 'rich_text')->exists())
        ->toBeTrue();

    $contact = Contact::withoutGlobalScopes()->where('email', 'victim@example.com')->first();

    expect($contact)->not->toBeNull()
        ->and($contact->custom_fields['bio'])->toBe('<p>About me.</p><a>x</a>');
});
