<?php

use App\Filament\Resources\MailingListResource\Pages\CreateMailingList;
use App\Filament\Resources\MailingListResource\Pages\EditMailingList;
use App\Models\Contact;
use App\Models\MailingList;
use App\Models\Tag;
use App\Models\User;
use App\Services\MailingListQueryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $this->optedInPortland = Contact::factory()->create([
        'city'                => 'Portland',
        'do_not_contact'      => false,
        'mailing_list_opt_in' => true,
    ]);

    $this->optedInSeattle = Contact::factory()->create([
        'city'                => 'Seattle',
        'do_not_contact'      => false,
        'mailing_list_opt_in' => true,
    ]);

    $this->notOptedIn = Contact::factory()->create([
        'city'                => 'Portland',
        'do_not_contact'      => false,
        'mailing_list_opt_in' => false,
    ]);
});

function firstFilterState(array $state): array
{
    return array_values($state)[0];
}

it('round-trips a select filter (opt-in equals Yes): form save → DB value → query match → form refill', function () {
    Livewire::test(CreateMailingList::class)
        ->fillForm([
            'name'        => 'Opted In',
            'conjunction' => 'and',
            'filters'     => [
                ['field' => 'mailing_list_opt_in', 'operator' => 'equals', 'value_select' => '1'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $list = MailingList::where('name', 'Opted In')->firstOrFail();

    expect($list->filters)->toHaveCount(1)
        ->and($list->filters[0]->value)->toBe('1');

    expect(MailingListQueryBuilder::build($list)->count())->toBe(2);

    Livewire::test(EditMailingList::class, ['record' => $list->id])
        ->assertFormSet(function (array $state) {
            $filter = firstFilterState($state['filters']);

            expect($filter['field'])->toBe('mailing_list_opt_in')
                ->and($filter['value_select'])->toBe('1');
        });
});

it('round-trips a text filter (city equals Portland): form save → DB value → query match → form refill', function () {
    Livewire::test(CreateMailingList::class)
        ->fillForm([
            'name'        => 'Portlanders',
            'conjunction' => 'and',
            'filters'     => [
                ['field' => 'city', 'operator' => 'equals', 'value_text' => 'Portland'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $list = MailingList::where('name', 'Portlanders')->firstOrFail();

    expect($list->filters)->toHaveCount(1)
        ->and($list->filters[0]->value)->toBe('Portland');

    expect(MailingListQueryBuilder::build($list)->count())->toBe(1);

    Livewire::test(EditMailingList::class, ['record' => $list->id])
        ->assertFormSet(function (array $state) {
            $filter = firstFilterState($state['filters']);

            expect($filter['field'])->toBe('city')
                ->and($filter['value_text'])->toBe('Portland');
        });
});

it('round-trips a tag filter (includes tag): form save → DB value → query match → form refill', function () {
    $tag = Tag::create(['name' => 'Volunteer', 'type' => 'contact']);
    $this->optedInPortland->tags()->attach($tag->id);

    Livewire::test(CreateMailingList::class)
        ->fillForm([
            'name'        => 'Volunteers',
            'conjunction' => 'and',
            'filters'     => [
                ['field' => 'tags', 'operator' => 'includes', 'value_tag' => 'Volunteer'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $list = MailingList::where('name', 'Volunteers')->firstOrFail();

    expect($list->filters)->toHaveCount(1)
        ->and($list->filters[0]->value)->toBe('Volunteer');

    expect(MailingListQueryBuilder::build($list)->count())->toBe(1);

    Livewire::test(EditMailingList::class, ['record' => $list->id])
        ->assertFormSet(function (array $state) {
            $filter = firstFilterState($state['filters']);

            expect($filter['field'])->toBe('tags')
                ->and($filter['value_tag'])->toBe('Volunteer');
        });
});

it('saves the value matching the filter type when an existing filter changes type on edit', function () {
    $list = MailingList::create([
        'name'        => 'Changing List',
        'conjunction' => 'and',
        'is_active'   => true,
    ]);

    $filter = $list->filters()->create([
        'field'      => 'city',
        'operator'   => 'equals',
        'value'      => 'Portland',
        'sort_order' => 0,
    ]);

    Livewire::test(EditMailingList::class, ['record' => $list->id])
        ->fillForm([
            'filters' => [
                "record-{$filter->id}" => ['field' => 'mailing_list_opt_in', 'operator' => 'equals', 'value_select' => '1'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $list->refresh();

    expect($list->filters)->toHaveCount(1)
        ->and($list->filters[0]->field)->toBe('mailing_list_opt_in')
        ->and($list->filters[0]->value)->toBe('1');
});
