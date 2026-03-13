<?php

use App\Models\Collection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can create a collection with a valid field schema', function () {
    $collection = Collection::create([
        'name'        => 'Board Members',
        'handle'      => 'board_members',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'name',  'label' => 'Full Name', 'type' => 'text',     'required' => true,  'helpText' => '', 'options' => []],
            ['key' => 'title', 'label' => 'Title',     'type' => 'text',     'required' => false, 'helpText' => '', 'options' => []],
            ['key' => 'bio',   'label' => 'Biography', 'type' => 'textarea', 'required' => false, 'helpText' => '', 'options' => []],
        ],
    ]);

    expect($collection->name)->toBe('Board Members')
        ->and($collection->fields)->toHaveCount(3);
});

it('auto-generates a handle from the name via Spatie sluggable', function () {
    $collection = Collection::create([
        'name'        => 'Staff Profiles',
        'source_type' => 'custom',
        'is_public'   => false,
        'is_active'   => true,
        'fields'      => [],
    ]);

    expect($collection->handle)->toBe('staff-profiles');
});

it('getFormSchema returns a TextInput for a text field', function () {
    $collection = Collection::create([
        'name'        => 'Sponsors',
        'handle'      => 'sponsors',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'company', 'label' => 'Company Name', 'type' => 'text', 'required' => false, 'helpText' => '', 'options' => []],
        ],
    ]);

    $schema = $collection->getFormSchema();

    expect($schema)->toHaveCount(1)
        ->and($schema[0])->toBeInstanceOf(TextInput::class);
});

it('getFormSchema returns a Select for a select field', function () {
    $collection = Collection::create([
        'name'        => 'FAQs',
        'handle'      => 'faqs',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'category', 'label' => 'Category', 'type' => 'select', 'required' => false, 'helpText' => '', 'options' => [
                ['value' => 'general',  'label' => 'General'],
                ['value' => 'programs', 'label' => 'Programs'],
            ]],
        ],
    ]);

    $schema = $collection->getFormSchema();

    expect($schema)->toHaveCount(1)
        ->and($schema[0])->toBeInstanceOf(Select::class);
});

it('getFormSchema marks required fields as required', function () {
    $collection = Collection::create([
        'name'        => 'Testimonials',
        'handle'      => 'testimonials',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'quote',  'label' => 'Quote',       'type' => 'textarea', 'required' => true,  'helpText' => '', 'options' => []],
            ['key' => 'author', 'label' => 'Author Name', 'type' => 'text',     'required' => false, 'helpText' => '', 'options' => []],
        ],
    ]);

    $schema = $collection->getFormSchema();

    expect($schema[0]->isRequired())->toBeTrue()
        ->and($schema[1]->isRequired())->toBeFalse();
});

it('isSystemCollection returns true for non-custom source types', function () {
    $system = Collection::create([
        'name'        => 'Events Test',
        'handle'      => 'events_test',
        'source_type' => 'events',
        'is_public'   => false,
        'is_active'   => true,
        'fields'      => [],
    ]);

    expect($system->isSystemCollection())->toBeTrue();
});

it('isSystemCollection returns false for custom source type', function () {
    $custom = Collection::create([
        'name'        => 'Programs',
        'handle'      => 'programs',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [],
    ]);

    expect($custom->isSystemCollection())->toBeFalse();
});
