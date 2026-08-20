<?php

use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\PageBuilderDataSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 397 (Plugin Architecture arc D7): the schema twin for the fifth
// squash-boundary redraw. The two form tables left core's dump for
// plugin-owned byte-faithful migrations — the first redraw carrying TWO
// sequences (forms_id_seq + form_submissions_id_seq). On the full
// composition the tables exist, both sequences allocate ids, and the core
// reads gated on route presence resolve normally.

it('creates both form tables through the plugin-owned migrations', function () {
    expect(Schema::hasTable('forms'))->toBeTrue()
        ->and(Schema::hasTable('form_submissions'))->toBeTrue();
});

it('registers the plugin migration path on the migrator', function () {
    expect(app('migrator')->paths())->toContain(base_path('plugins/Forms/database/migrations'));
});

it('allocates ids through both traveling sequences on real writes', function () {
    $form = Form::create([
        'title'     => 'Sequence Proof',
        'handle'    => 'sequence-proof',
        'fields'    => [['type' => 'text', 'label' => 'Name', 'handle' => 'name']],
        'settings'  => ['form_type' => 'general'],
        'is_active' => true,
    ]);

    $submission = FormSubmission::create([
        'form_id'    => $form->id,
        'data'       => ['name' => 'Seq'],
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    expect($form->id)->toBeInt()->toBeGreaterThan(0)
        ->and($submission->id)->toBeInt()->toBeGreaterThan(0);
});

it('resolves the forms picker options and the PageContext lookup on the full composition', function () {
    Form::create([
        'title'     => 'Pickable',
        'handle'    => 'pickable',
        'fields'    => [],
        'settings'  => [],
        'is_active' => true,
    ]);

    expect(PageBuilderDataSources::resolve('forms'))->toBe(['pickable' => 'Pickable'])
        ->and((new \App\Services\PageContext())->form('pickable'))->not->toBeNull();
});
