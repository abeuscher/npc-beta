<?php

use App\Models\Contact;
use App\Models\Note;
use App\Models\User;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use App\WidgetPrimitive\AmbientContexts\RecordDetailAmbientContext;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();

    $this->resolver = app(ContractResolver::class);

    $this->crmEditor = User::factory()->create();
    $this->crmEditor->assignRole('crm_editor');

    $this->cmsEditor = User::factory()->create();
    $this->cmsEditor->assignRole('cms_editor');
});

function noteContract(array $filters = []): DataContract
{
    return new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['note_id', 'note_subject', 'note_body_excerpt', 'note_type', 'note_occurred_at', 'note_author_name'],
        filters: array_merge(['limit' => 5, 'order_by' => 'occurred_at', 'direction' => 'desc'], $filters),
        model: 'note',
        requiredPermission: 'view_note',
    );
}

function recordDetailSlot(\Illuminate\Database\Eloquent\Model $record): SlotContext
{
    return new SlotContext(new RecordDetailAmbientContext($record), publicSurface: false);
}

it('returns empty items when the authenticated user lacks view_note', function () {
    $contact = Contact::factory()->create();
    Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
    ]);

    $this->actingAs($this->cmsEditor);

    $dto = $this->resolver->resolve([noteContract()], recordDetailSlot($contact))[0];

    expect($dto)->toBe(['items' => []]);
});

it('returns empty items when the slot ambient is not record-detail', function () {
    $this->actingAs($this->crmEditor);

    $dto = $this->resolver->resolve([noteContract()], new SlotContext(new PageAmbientContext()))[0];

    expect($dto)->toBe(['items' => []]);
});

it('returns notes attached to the ambient record only — does not leak across contacts', function () {
    $a = Contact::factory()->create();
    $b = Contact::factory()->create();

    Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $a->id,
        'subject'      => 'A subject',
    ]);
    Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $b->id,
        'subject'      => 'B subject',
    ]);

    $this->actingAs($this->crmEditor);

    $dtoA = $this->resolver->resolve([noteContract()], recordDetailSlot($a))[0];
    $dtoB = $this->resolver->resolve([noteContract()], recordDetailSlot($b))[0];

    expect($dtoA['items'])->toHaveCount(1)
        ->and($dtoA['items'][0]['note_subject'])->toBe('A subject')
        ->and($dtoB['items'])->toHaveCount(1)
        ->and($dtoB['items'][0]['note_subject'])->toBe('B subject');
});

it('orders by occurred_at desc by default and respects limit', function () {
    $contact = Contact::factory()->create();

    Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'subject'      => 'Older',
        'occurred_at'  => now()->subDays(5),
    ]);
    Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'subject'      => 'Middle',
        'occurred_at'  => now()->subDays(3),
    ]);
    Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'subject'      => 'Newest',
        'occurred_at'  => now()->subDay(),
    ]);

    $this->actingAs($this->crmEditor);

    $dto = $this->resolver->resolve([noteContract(['limit' => 2])], recordDetailSlot($contact))[0];

    expect($dto['items'])->toHaveCount(2)
        ->and($dto['items'][0]['note_subject'])->toBe('Newest')
        ->and($dto['items'][1]['note_subject'])->toBe('Middle');
});

it('clamps limit to 50 maximum and falls back to default for invalid values', function () {
    $contact = Contact::factory()->create();

    foreach (range(1, 60) as $i) {
        Note::factory()->create([
            'notable_type' => Contact::class,
            'notable_id'   => $contact->id,
            'occurred_at'  => now()->subMinutes($i),
        ]);
    }

    $this->actingAs($this->crmEditor);

    $dtoHigh = $this->resolver->resolve([noteContract(['limit' => 9999])], recordDetailSlot($contact))[0];
    $dtoZero = $this->resolver->resolve([noteContract(['limit' => 0])], recordDetailSlot($contact))[0];

    expect($dtoHigh['items'])->toHaveCount(50)
        ->and($dtoZero['items'])->toHaveCount(5);
});

it('falls back to occurred_at when an unknown order_by is supplied', function () {
    $contact = Contact::factory()->create();

    Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'subject'      => 'Older',
        'occurred_at'  => now()->subDays(5),
    ]);
    Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'subject'      => 'Newest',
        'occurred_at'  => now()->subDay(),
    ]);

    $this->actingAs($this->crmEditor);

    $dto = $this->resolver->resolve([noteContract(['order_by' => 'subject'])], recordDetailSlot($contact))[0];

    expect($dto['items'][0]['note_subject'])->toBe('Newest');
});

it('hits the notes table exactly once per render and eager-loads the author', function () {
    $contact = Contact::factory()->create();

    foreach (range(1, 3) as $i) {
        Note::factory()->create([
            'notable_type' => Contact::class,
            'notable_id'   => $contact->id,
            'occurred_at'  => now()->subDays($i),
        ]);
    }

    $this->actingAs($this->crmEditor);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->resolver->resolve([noteContract()], recordDetailSlot($contact));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $noteSelects = array_values(array_filter($queries, fn ($q) => str_starts_with($q['query'], 'select') && str_contains($q['query'], '"notes"')));
    $userBatchSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select') && str_contains($sql, 'from "users"') && str_contains($sql, '"id" in');
    }));

    expect(count($noteSelects))->toBe(1)
        ->and(count($userBatchSelects))->toBe(1);
});
