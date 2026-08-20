<?php

namespace App\Console\Commands;

use App\Importers\ContactFieldRegistry;
use App\Importers\InvoiceDetailFieldRegistry;
use App\Importers\NoteFieldRegistry;
use App\Models\ImportSource;
use App\Plugins\ImporterRegistry;
use Illuminate\Console\Command;

class SeedFakeImportSourcesCommand extends Command
{
    protected $signature = 'seed:fake-import-sources {--force : Replace existing demo sources with fresh field maps}';

    protected $description = 'Seed six demo import sources (one per content type) whose saved field maps bind to the canonical CSV headers.';

    private const CONTACTS_LABEL    = 'Demo Fake Data — Contacts';
    private const EVENTS_LABEL      = 'Demo Fake Data — Events';
    private const DONATIONS_LABEL   = 'Demo Fake Data — Donations';
    private const MEMBERSHIPS_LABEL = 'Demo Fake Data — Memberships';
    private const INVOICES_LABEL    = 'Demo Fake Data — Invoice Details';
    private const NOTES_LABEL       = 'Demo Fake Data — Notes';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $this->upsert(self::CONTACTS_LABEL, [
            'contacts_field_map'        => $this->contactsFieldMap(),
            'contacts_custom_field_map' => [],
            'contacts_match_key'        => 'email',
            'contacts_match_key_column' => 'email',
        ], $force);

        // The events field map is the Events plugin's importer contribution
        // (contract surface 8); no contribution registered = no events source.
        $eventsImporter = app(ImporterRegistry::class)->find('events');
        if ($eventsImporter !== null) {
            $this->upsert(self::EVENTS_LABEL, [
                'events_field_map'         => ($eventsImporter->fakeSourceFieldMap)(),
                'events_custom_field_map'  => [],
                'events_match_key'         => 'event:external_id',
                'events_match_key_column'  => 'event external id',
                'events_contact_match_key' => 'contact:email',
            ], $force);
        }

        // The donations field map is the Donations plugin's importer
        // contribution (contract surface 8); no contribution registered = no
        // donations source.
        $donationsImporter = app(ImporterRegistry::class)->find('donations');
        if ($donationsImporter !== null) {
            $this->upsert(self::DONATIONS_LABEL, [
                'donations_field_map'         => ($donationsImporter->fakeSourceFieldMap)(),
                'donations_custom_field_map'  => [],
                'donations_contact_match_key' => 'contact:email',
            ], $force);
        }

        // The memberships field map is the Memberships plugin's importer
        // contribution (contract surface 8); no contribution registered = no
        // memberships source.
        $membershipsImporter = app(ImporterRegistry::class)->find('memberships');
        if ($membershipsImporter !== null) {
            $this->upsert(self::MEMBERSHIPS_LABEL, [
                'memberships_field_map'         => ($membershipsImporter->fakeSourceFieldMap)(),
                'memberships_custom_field_map'  => [],
                'memberships_contact_match_key' => 'contact:email',
            ], $force);
        }

        $this->upsert(self::INVOICES_LABEL, [
            'invoices_field_map'         => $this->invoicesFieldMap(),
            'invoices_custom_field_map'  => [],
            'invoices_contact_match_key' => 'contact:email',
        ], $force);

        $this->upsert(self::NOTES_LABEL, [
            'notes_field_map'         => $this->notesFieldMap(),
            'notes_custom_field_map'  => [],
            'notes_contact_match_key' => 'contact:email',
        ], $force);

        $this->info('Seeded demo import sources.');
        return self::SUCCESS;
    }

    private function upsert(string $name, array $attributes, bool $force): void
    {
        $existing = ImportSource::where('name', $name)->first();
        if ($existing === null) {
            ImportSource::create(array_merge(['name' => $name, 'notes' => 'Demo fixture for fake-CSV testing.'], $attributes));
            $this->line("  created {$name}");
            return;
        }

        if (! $force) {
            $this->line("  kept existing {$name} (use --force to replace)");
            return;
        }

        $existing->update($attributes);
        $this->line("  replaced {$name}");
    }

    private function contactsFieldMap(): array
    {
        $map = [];
        foreach (ContactFieldRegistry::fields() as $key => $def) {
            $map[strtolower($def['label'])] = $key;
        }
        return $map;
    }

    private function invoicesFieldMap(): array
    {
        $map = [];
        foreach (InvoiceDetailFieldRegistry::fields() as $key => $def) {
            $map[strtolower($def['label'])] = "invoice:{$key}";
        }
        $map['email']   = 'contact:email';
        $map['user id'] = 'contact:external_id';
        $map['phone']   = 'contact:phone';
        return $map;
    }

    private function notesFieldMap(): array
    {
        $map = [];
        foreach (NoteFieldRegistry::fields() as $key => $def) {
            $map[strtolower('Note ' . $def['label'])] = "note:{$key}";
        }
        $map['email']   = 'contact:email';
        $map['user id'] = 'contact:external_id';
        $map['phone']   = 'contact:phone';
        return $map;
    }
}
