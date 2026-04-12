<?php

use App\Models\WidgetType;
use App\Services\WidgetRegistry;
use App\Widgets\Contracts\WidgetDefinition;
use App\Widgets\Nav\NavDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FakeWidgetDefinition extends WidgetDefinition
{
    public function __construct(
        protected string $handle = 'fake',
        protected array $schemaFields = [],
        protected array $defaultValues = [],
    ) {}

    public function handle(): string { return $this->handle; }
    public function label(): string { return 'Fake'; }
    public function description(): string { return 'Fake widget'; }
    public function schema(): array { return $this->schemaFields; }
    public function defaults(): array { return $this->defaultValues; }
    public function template(): string { return '<div>fake</div>'; }
}

it('registers and retrieves definitions by handle', function () {
    $registry = new WidgetRegistry();
    $def = new FakeWidgetDefinition('alpha');

    $registry->register($def);

    expect($registry->find('alpha'))->toBe($def);
    expect($registry->find('missing'))->toBeNull();
    expect($registry->all())->toHaveKey('alpha');
});

it('validate() throws when defaults is missing a schema key', function () {
    $def = new FakeWidgetDefinition(
        'missing_default',
        [['key' => 'foo', 'type' => 'text'], ['key' => 'bar', 'type' => 'text']],
        ['foo' => 'x'],
    );

    $def->validate();
})->throws(RuntimeException::class, 'bar');

it('validate() passes when every schema key has a default', function () {
    $def = new FakeWidgetDefinition(
        'ok',
        [['key' => 'foo', 'type' => 'text']],
        ['foo' => 'x'],
    );

    $def->validate();
    expect(true)->toBeTrue();
});

it('sync() writes registered widgets to the widget_types table', function () {
    $registry = new WidgetRegistry();
    $registry->register(new NavDefinition());

    $registry->sync();

    $row = WidgetType::where('handle', 'nav')->firstOrFail();
    expect($row->label)->toBe('Navigation');
    expect($row->full_width)->toBeTrue();
    expect($row->category)->toBe(['layout']);
    expect($row->template)->toBe("@include('widgets.nav')");
    expect($row->required_config)->toBe(['keys' => ['navigation_menu_id'], 'message' => 'Select a navigation menu.']);
});

it('sync() is idempotent', function () {
    $registry = new WidgetRegistry();
    $registry->register(new NavDefinition());

    $registry->sync();
    $firstId = WidgetType::where('handle', 'nav')->firstOrFail()->id;

    $registry->sync();
    $secondId = WidgetType::where('handle', 'nav')->firstOrFail()->id;

    expect($secondId)->toBe($firstId);
    expect(WidgetType::where('handle', 'nav')->count())->toBe(1);
});

it('seeder-sourced nav row matches registry-sourced nav row', function () {
    $this->seed(\Database\Seeders\WidgetTypeSeeder::class);

    $row = WidgetType::where('handle', 'nav')->firstOrFail();
    $expected = (new NavDefinition())->toRow();

    expect($row->label)->toBe($expected['label']);
    expect($row->description)->toBe($expected['description']);
    expect($row->category)->toBe($expected['category']);
    expect($row->full_width)->toBe($expected['full_width']);
    expect($row->assets)->toBe($expected['assets']);
    expect($row->template)->toBe($expected['template']);
    expect($row->required_config)->toBe($expected['required_config']);

    $schemaByKey = collect($row->config_schema)->keyBy('key');
    $expectedByKey = collect($expected['config_schema'])->keyBy('key');
    expect($schemaByKey->keys()->all())->toEqualCanonicalizing($expectedByKey->keys()->all());
    foreach ($expectedByKey as $key => $field) {
        expect($schemaByKey[$key])->toEqualCanonicalizing($field);
    }
});
