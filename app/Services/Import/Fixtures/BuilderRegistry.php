<?php

namespace App\Services\Import\Fixtures;

use App\Services\Import\Fixtures\Importers\ContactsFixtureBuilder;
use App\Services\Import\Fixtures\Importers\DonationsFixtureBuilder;
use App\Services\Import\Fixtures\Importers\EventsFixtureBuilder;
use App\Services\Import\Fixtures\Importers\InvoiceDetailsFixtureBuilder;
use App\Services\Import\Fixtures\Importers\MembershipsFixtureBuilder;
use App\Services\Import\Fixtures\Importers\NotesFixtureBuilder;
use App\Services\Import\Fixtures\Importers\OrganizationsFixtureBuilder;
use InvalidArgumentException;

class BuilderRegistry
{
    public const IMPORTERS = [
        'contacts',
        'events',
        'donations',
        'memberships',
        'invoice_details',
        'notes',
        'organizations',
    ];

    public function for(string $importer): FixtureBuilder
    {
        return match ($importer) {
            'contacts'        => new ContactsFixtureBuilder(),
            'events'          => new EventsFixtureBuilder(),
            'donations'       => new DonationsFixtureBuilder(),
            'memberships'     => new MembershipsFixtureBuilder(),
            'invoice_details' => new InvoiceDetailsFixtureBuilder(),
            'notes'           => new NotesFixtureBuilder(),
            'organizations'   => new OrganizationsFixtureBuilder(),
            default           => throw new InvalidArgumentException("Unknown importer: {$importer}"),
        };
    }
}
