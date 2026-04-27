<?php

use App\Models\Contact;
use App\Models\Note;
use App\Models\User;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Projectors\SystemModelProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('projects a Note collection into a row-set DTO with declared concept-named fields only', function () {
    $author = User::factory()->create(['name' => 'Alice Author']);
    $contact = Contact::factory()->create();

    $note = Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'author_id'    => $author->id,
        'type'         => 'call',
        'subject'      => 'Quick check-in',
        'body'         => '<p>Discussed renewal timing.</p>',
        'occurred_at'  => '2026-04-22 14:30:00',
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['note_id', 'note_subject', 'note_body_excerpt', 'note_type', 'note_occurred_at', 'note_author_name'],
        model: 'note',
    );

    $dto = app(SystemModelProjector::class)->project($contract, collect([$note]));

    expect($dto)->toHaveKey('items')
        ->and($dto['items'])->toHaveCount(1)
        ->and(array_keys($dto['items'][0]))->toEqualCanonicalizing(['note_id', 'note_subject', 'note_body_excerpt', 'note_type', 'note_occurred_at', 'note_author_name'])
        ->and($dto['items'][0]['note_id'])->toBe($note->id)
        ->and($dto['items'][0]['note_subject'])->toBe('Quick check-in')
        ->and($dto['items'][0]['note_body_excerpt'])->toBe('Discussed renewal timing.')
        ->and($dto['items'][0]['note_type'])->toBe('call')
        ->and($dto['items'][0]['note_occurred_at'])->toBe('Apr 22, 2026 2:30 pm')
        ->and($dto['items'][0]['note_author_name'])->toBe('Alice Author');
});

it('returns empty strings for undeclared fields like body, meta, and external_id', function () {
    $contact = Contact::factory()->create();

    $note = Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'body'         => 'NOT_LEAKED_BODY_SENTINEL',
        'meta'         => ['NOT_LEAKED_META_SENTINEL' => 'x'],
        'external_id'  => 'NOT_LEAKED_EXTERNAL_ID_SENTINEL',
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['note_id', 'body', 'meta', 'external_id'],
        model: 'note',
    );

    $dto = app(SystemModelProjector::class)->project($contract, collect([$note]));

    expect($dto['items'][0]['note_id'])->toBe($note->id)
        ->and($dto['items'][0]['body'])->toBe('')
        ->and($dto['items'][0]['meta'])->toBe('')
        ->and($dto['items'][0]['external_id'])->toBe('');
});

it('excerpts the body to 140 characters with HTML stripped', function () {
    $longBody = '<p>' . str_repeat('a', 200) . '</p>';
    $contact = Contact::factory()->create();

    $note = Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'body'         => $longBody,
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['note_body_excerpt'],
        model: 'note',
    );

    $dto = app(SystemModelProjector::class)->project($contract, collect([$note]));

    expect($dto['items'][0]['note_body_excerpt'])
        ->toEndWith('...')
        ->and(strlen($dto['items'][0]['note_body_excerpt']))->toBeLessThanOrEqual(143)
        ->and($dto['items'][0]['note_body_excerpt'])->not->toContain('<p>');
});

it('renders an em-dash when the note has no author', function () {
    $contact = Contact::factory()->create();

    $note = Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'author_id'    => null,
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['note_author_name'],
        model: 'note',
    );

    $dto = app(SystemModelProjector::class)->project($contract, collect([$note]));

    expect($dto['items'][0]['note_author_name'])->toBe('—');
});
