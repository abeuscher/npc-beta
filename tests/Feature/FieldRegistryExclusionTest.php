<?php

use App\Importers\ContactFieldRegistry;
use App\Importers\DonationFieldRegistry;
use App\Importers\EventFieldRegistry;
use App\Importers\InvoiceDetailFieldRegistry;
use App\Importers\MembershipFieldRegistry;
use App\Importers\NoteFieldRegistry;
use App\Importers\RegistrationFieldRegistry;
use App\Importers\TransactionFieldRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('does not leak Import Source Id or Import Session Id into any registry dropdown', function () {
    $registries = [
        ContactFieldRegistry::class,
        DonationFieldRegistry::class,
        EventFieldRegistry::class,
        InvoiceDetailFieldRegistry::class,
        MembershipFieldRegistry::class,
        NoteFieldRegistry::class,
        RegistrationFieldRegistry::class,
        TransactionFieldRegistry::class,
    ];

    $leakedLabels = ['Import Source Id', 'Import Session Id'];

    foreach ($registries as $registry) {
        $options = $registry::options();
        foreach ($leakedLabels as $label) {
            expect(in_array($label, $options, true))->toBeFalse(
                "{$registry}::options() should not contain the label '{$label}'"
            );
        }
    }
});

it('contact and event registries do not expose import_source_id or import_session_id field keys', function () {
    foreach ([ContactFieldRegistry::class, EventFieldRegistry::class] as $registry) {
        $fields = $registry::fields();
        expect(array_key_exists('import_source_id', $fields))->toBeFalse(
            "{$registry}::fields() must not expose import_source_id"
        );
        expect(array_key_exists('import_session_id', $fields))->toBeFalse(
            "{$registry}::fields() must not expose import_session_id"
        );
    }
});
