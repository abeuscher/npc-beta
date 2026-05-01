<?php

use App\Filament\Pages\ImportContactsPage;
use App\Filament\Pages\ImportDonationsPage;
use App\Importers\ContactFieldRegistry;
use App\Importers\DonationImportFieldRegistry;
use Filament\Forms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Invoke a protected method on a page instance and return its result.
 */
function mappingRow_invokeProtected(object $instance, string $method, array $args = []): mixed
{
    $ref = new \ReflectionMethod($instance, $method);
    $ref->setAccessible(true);

    return $ref->invoke($instance, ...$args);
}

/**
 * Pull the raw extraAttributes closures off a component without evaluating them
 * through Filament's container (which would require a live Livewire context).
 */
function mappingRow_readExtraAttributesClosures(Forms\Components\Component $component): array
{
    $prop = (new \ReflectionObject($component))->getProperty('extraAttributes');
    $prop->setAccessible(true);

    return $prop->getValue($component);
}

/**
 * Stub that satisfies the Forms\Get type-hint without needing a Livewire
 * component bound to the closure's owning container. Pass a single value to
 * return the same value for any path lookup, or a [path => value] map for
 * closures that read multiple state paths.
 */
function mappingRow_stubGet(mixed $valueOrMap): \Filament\Forms\Get
{
    return new class($valueOrMap) extends \Filament\Forms\Get {
        public function __construct(private mixed $valueOrMap) {}

        public function __invoke(string | \Filament\Forms\Components\Component $path = '', bool $isAbsolute = false): mixed
        {
            if (is_array($this->valueOrMap)) {
                return $this->valueOrMap[$path] ?? null;
            }

            return $this->valueOrMap;
        }
    };
}

it('namespaced row builder wraps components in a Group with state-class closure', function () {
    $page = new ImportDonationsPage();
    $page->parsedHeaders = ['Email', 'Amount'];
    $page->data = ['column_map' => ['col_0' => null, 'col_1' => 'donation:amount']];

    $row = mappingRow_invokeProtected($page, 'buildNamespacedMappingRow', [
        'Email',
        DonationImportFieldRegistry::groupedOptions(),
        ['__custom_donation__'],
    ]);

    expect($row)->toBeArray()->toHaveCount(1);
    expect($row[0])->toBeInstanceOf(Forms\Components\Group::class);
    expect($row[0]->getChildComponents())->toHaveCount(5);
});

it('namespaced row Group emits incomplete class when destination is null', function () {
    $page = new ImportDonationsPage();
    $page->parsedHeaders = ['Email'];
    $page->data = ['column_map' => ['col_0' => null]];

    $row = mappingRow_invokeProtected($page, 'buildNamespacedMappingRow', [
        'Email',
        DonationImportFieldRegistry::groupedOptions(),
        ['__custom_donation__'],
    ]);

    $closures = mappingRow_readExtraAttributesClosures($row[0]);
    expect($closures)->toHaveCount(1);

    $attrs = $closures[0](mappingRow_stubGet(null));
    expect($attrs['class'])->toContain('np-import-map-row')
        ->and($attrs['class'])->toContain('np-import-map-row--incomplete');
});

it('namespaced row Group emits complete class when destination is mapped', function () {
    $page = new ImportDonationsPage();
    $page->parsedHeaders = ['Amount'];
    $page->data = ['column_map' => ['col_0' => 'donation:amount']];

    $row = mappingRow_invokeProtected($page, 'buildNamespacedMappingRow', [
        'Amount',
        DonationImportFieldRegistry::groupedOptions(),
        ['__custom_donation__'],
    ]);

    $closures = mappingRow_readExtraAttributesClosures($row[0]);

    $attrs = $closures[0](mappingRow_stubGet('donation:amount'));
    expect($attrs['class'])->toContain('np-import-map-row--complete')
        ->and($attrs['class'])->not->toContain('--incomplete');
});

it('namespaced row Group treats a custom-field sentinel as complete only when label/handle/type are all filled', function () {
    $page = new ImportDonationsPage();
    $page->parsedHeaders = ['Custom Header'];
    $page->data = ['column_map' => ['col_0' => '__custom_donation__']];

    $row = mappingRow_invokeProtected($page, 'buildNamespacedMappingRow', [
        'Custom Header',
        DonationImportFieldRegistry::groupedOptions(),
        ['__custom_donation__'],
    ]);

    $closures = mappingRow_readExtraAttributesClosures($row[0]);

    $attrs = $closures[0](mappingRow_stubGet([
        'column_map.col_0' => '__custom_donation__',
        'cf_label_0'       => 'Custom Header',
        'cf_handle_0'      => 'custom_header',
        'cf_type_0'        => 'text',
    ]));
    expect($attrs['class'])->toContain('np-import-map-row--complete');
});

it('namespaced row Group is incomplete when custom sentinel is picked but Field type is unset', function () {
    $page = new ImportDonationsPage();
    $page->parsedHeaders = ['Custom Header'];
    $page->data = ['column_map' => ['col_0' => '__custom_donation__']];

    $row = mappingRow_invokeProtected($page, 'buildNamespacedMappingRow', [
        'Custom Header',
        DonationImportFieldRegistry::groupedOptions(),
        ['__custom_donation__'],
    ]);

    $closures = mappingRow_readExtraAttributesClosures($row[0]);

    $attrs = $closures[0](mappingRow_stubGet([
        'column_map.col_0' => '__custom_donation__',
        'cf_label_0'       => 'Custom Header',
        'cf_handle_0'      => 'custom_header',
        'cf_type_0'        => null,
    ]));
    expect($attrs['class'])->toContain('np-import-map-row--incomplete');
});

it('namespaced row Group is incomplete when custom sentinel is picked but handle is blank', function () {
    $page = new ImportDonationsPage();
    $page->parsedHeaders = ['Custom Header'];
    $page->data = ['column_map' => ['col_0' => '__custom_donation__']];

    $row = mappingRow_invokeProtected($page, 'buildNamespacedMappingRow', [
        'Custom Header',
        DonationImportFieldRegistry::groupedOptions(),
        ['__custom_donation__'],
    ]);

    $closures = mappingRow_readExtraAttributesClosures($row[0]);

    $attrs = $closures[0](mappingRow_stubGet([
        'column_map.col_0' => '__custom_donation__',
        'cf_label_0'       => 'Custom Header',
        'cf_handle_0'      => '',
        'cf_type_0'        => 'text',
    ]));
    expect($attrs['class'])->toContain('np-import-map-row--incomplete');
});

it('namespaced row Group treats a relational sentinel as complete', function () {
    $page = new ImportDonationsPage();
    $page->parsedHeaders = ['Notes'];
    $page->data = ['column_map' => ['col_0' => '__note_contact__']];

    $row = mappingRow_invokeProtected($page, 'buildNamespacedMappingRow', [
        'Notes',
        DonationImportFieldRegistry::groupedOptions(),
        ['__custom_donation__'],
    ]);

    $closures = mappingRow_readExtraAttributesClosures($row[0]);
    $attrs = $closures[0](mappingRow_stubGet('__note_contact__'));
    expect($attrs['class'])->toContain('np-import-map-row--complete');
});

it('namespaced row Select is searchable and inline-labelled', function () {
    $page = new ImportDonationsPage();
    $page->parsedHeaders = ['Email'];
    $page->data = ['column_map' => ['col_0' => null]];

    $row = mappingRow_invokeProtected($page, 'buildNamespacedMappingRow', [
        'Email',
        DonationImportFieldRegistry::groupedOptions(),
        ['__custom_donation__'],
    ]);

    $select = $row[0]->getChildComponents()[0];

    expect($select)->toBeInstanceOf(Forms\Components\Select::class);
    expect($select->isSearchable())->toBeTrue();
    expect($select->hasInlineLabel())->toBeTrue();
});

it('contacts row builder wraps components in a Group with state-class closure', function () {
    $page = new ImportContactsPage();
    $page->parsedHeaders = ['Email'];
    $page->data = ['column_map' => ['col_0' => null]];

    $row = mappingRow_invokeProtected($page, 'columnMappingRowSchema', [
        'Email',
        ContactFieldRegistry::options(),
    ]);

    expect($row)->toBeArray()->toHaveCount(1);
    expect($row[0])->toBeInstanceOf(Forms\Components\Group::class);
    expect($row[0]->getChildComponents())->toHaveCount(5);
});

it('contacts row Group emits incomplete class when destination is null', function () {
    $page = new ImportContactsPage();
    $page->parsedHeaders = ['Email'];
    $page->data = ['column_map' => ['col_0' => null]];

    $row = mappingRow_invokeProtected($page, 'columnMappingRowSchema', [
        'Email',
        ContactFieldRegistry::options(),
    ]);

    $closures = mappingRow_readExtraAttributesClosures($row[0]);
    $attrs = $closures[0](mappingRow_stubGet(null));
    expect($attrs['class'])->toContain('np-import-map-row--incomplete');
});

it('contacts row Group emits complete class when mapped to a contact field', function () {
    $page = new ImportContactsPage();
    $page->parsedHeaders = ['Email'];
    $page->data = ['column_map' => ['col_0' => 'contact:email']];

    $row = mappingRow_invokeProtected($page, 'columnMappingRowSchema', [
        'Email',
        ContactFieldRegistry::options(),
    ]);

    $closures = mappingRow_readExtraAttributesClosures($row[0]);
    $attrs = $closures[0](mappingRow_stubGet('contact:email'));
    expect($attrs['class'])->toContain('np-import-map-row--complete');
});

it('contacts row Group treats __custom__ sentinel as complete only when label/handle/type are all filled', function () {
    $page = new ImportContactsPage();
    $page->parsedHeaders = ['Note'];
    $page->data = ['column_map' => ['col_0' => '__custom__']];

    $row = mappingRow_invokeProtected($page, 'columnMappingRowSchema', [
        'Note',
        ContactFieldRegistry::options(),
    ]);

    $closures = mappingRow_readExtraAttributesClosures($row[0]);

    $attrs = $closures[0](mappingRow_stubGet([
        'column_map.col_0' => '__custom__',
        'cf_label_0'       => 'Note',
        'cf_handle_0'      => 'note',
        'cf_type_0'        => 'text',
    ]));
    expect($attrs['class'])->toContain('np-import-map-row--complete');
});

it('contacts row Group is incomplete when __custom__ is picked but Field type is unset', function () {
    $page = new ImportContactsPage();
    $page->parsedHeaders = ['Note'];
    $page->data = ['column_map' => ['col_0' => '__custom__']];

    $row = mappingRow_invokeProtected($page, 'columnMappingRowSchema', [
        'Note',
        ContactFieldRegistry::options(),
    ]);

    $closures = mappingRow_readExtraAttributesClosures($row[0]);

    $attrs = $closures[0](mappingRow_stubGet([
        'column_map.col_0' => '__custom__',
        'cf_label_0'       => 'Note',
        'cf_handle_0'      => 'note',
        'cf_type_0'        => null,
    ]));
    expect($attrs['class'])->toContain('np-import-map-row--incomplete');
});

it('contacts row Select is searchable and inline-labelled', function () {
    $page = new ImportContactsPage();
    $page->parsedHeaders = ['Email'];
    $page->data = ['column_map' => ['col_0' => null]];

    $row = mappingRow_invokeProtected($page, 'columnMappingRowSchema', [
        'Email',
        ContactFieldRegistry::options(),
    ]);

    $select = $row[0]->getChildComponents()[0];

    expect($select)->toBeInstanceOf(Forms\Components\Select::class);
    expect($select->isSearchable())->toBeTrue();
    expect($select->hasInlineLabel())->toBeTrue();
});

it('wouldCauseCollision returns true when a destination duplicates another column', function () {
    $page = new ImportContactsPage();
    $page->parsedHeaders = ['Email', 'Email Address'];
    $page->data = ['column_map' => ['col_0' => 'contact:email', 'col_1' => null]];

    expect(mappingRow_invokeProtected($page, 'wouldCauseCollision', ['contact:email', 1]))->toBeTrue();
});

it('wouldCauseCollision returns false when a destination is unique', function () {
    $page = new ImportContactsPage();
    $page->parsedHeaders = ['Email', 'Phone'];
    $page->data = ['column_map' => ['col_0' => 'contact:email', 'col_1' => null]];

    expect(mappingRow_invokeProtected($page, 'wouldCauseCollision', ['contact:phone', 1]))->toBeFalse();
});

it('wouldCauseCollision returns false for the special __custom__ sentinel even when another column uses it', function () {
    $page = new ImportContactsPage();
    $page->parsedHeaders = ['Header A', 'Header B'];
    $page->data = ['column_map' => ['col_0' => '__custom__', 'col_1' => null]];

    expect(mappingRow_invokeProtected($page, 'wouldCauseCollision', ['__custom__', 1]))->toBeFalse();
});

it('wouldCauseCollision returns false for a null/empty new state', function () {
    $page = new ImportContactsPage();
    $page->parsedHeaders = ['Email', 'Phone'];
    $page->data = ['column_map' => ['col_0' => 'contact:email', 'col_1' => 'contact:phone']];

    expect(mappingRow_invokeProtected($page, 'wouldCauseCollision', [null, 1]))->toBeFalse();
});

it('wouldCauseCollision ignores the changing column itself when comparing', function () {
    $page = new ImportContactsPage();
    $page->parsedHeaders = ['Email'];
    $page->data = ['column_map' => ['col_0' => 'contact:email']];

    expect(mappingRow_invokeProtected($page, 'wouldCauseCollision', ['contact:email', 0]))->toBeFalse();
});
