<?php

namespace App\Services\ImportExport\Import;

use App\Models\Contact;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Organization;
use App\Models\Page;
use App\Models\TicketTier;
use App\Services\ImportExport\ImportLog;
use Illuminate\Support\Facades\Storage;

/**
 * Imports a serialized event: upsert by slug, replace tiers wholesale, replace
 * registrations when present, and rewire event-level media. Registrations
 * resolve contact/organization by email/name when those rows exist on the
 * target; tier back-references resolve by name. Session A001.
 */
class EventImporter
{
    public function __construct(private BundleAuthorResolver $authors) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function import(array $data, ImportLog $log, BundleMediaArchive $archive): void
    {
        $eventData = $data['event'] ?? null;
        $slug      = $eventData['slug'] ?? null;

        if (! is_array($eventData) || ! is_string($slug) || $slug === '') {
            $log->warning('Event entry missing event.slug, skipped.');

            return;
        }

        $landingPageId = null;
        if (! empty($eventData['landing_page_slug'])) {
            $landingPageId = Page::where('slug', $eventData['landing_page_slug'])->value('id');
            if (! $landingPageId) {
                $log->warning("Event \"{$slug}\": landing page slug '{$eventData['landing_page_slug']}' not found on this install.");
            }
        }

        $sponsorOrgId = null;
        if (! empty($eventData['sponsor_organization_name'])) {
            $sponsorOrgId = Organization::where('name', $eventData['sponsor_organization_name'])->value('id');
        }

        $attributes = [
            'title'                       => $eventData['title'] ?? 'Untitled',
            'slug'                        => $slug,
            'description'                 => $eventData['description'] ?? null,
            'status'                      => $eventData['status'] ?? 'draft',
            'address_line_1'              => $eventData['address_line_1'] ?? null,
            'address_line_2'              => $eventData['address_line_2'] ?? null,
            'city'                        => $eventData['city'] ?? null,
            'state'                       => $eventData['state'] ?? null,
            'zip'                         => $eventData['zip'] ?? null,
            'map_url'                     => $eventData['map_url'] ?? null,
            'map_label'                   => $eventData['map_label'] ?? null,
            'meeting_url'                 => $eventData['meeting_url'] ?? null,
            'meeting_label'               => $eventData['meeting_label'] ?? null,
            'meeting_details'             => $eventData['meeting_details'] ?? null,
            'registration_mode'           => $eventData['registration_mode'] ?? 'open',
            'external_registration_url'   => $eventData['external_registration_url'] ?? null,
            'auto_create_contacts'        => (bool) ($eventData['auto_create_contacts'] ?? true),
            'mailing_list_opt_in_enabled' => (bool) ($eventData['mailing_list_opt_in_enabled'] ?? false),
            'custom_fields'               => $eventData['custom_fields'] ?? [],
            'starts_at'                   => ! empty($eventData['starts_at']) ? \Carbon\Carbon::parse($eventData['starts_at']) : null,
            'ends_at'                     => ! empty($eventData['ends_at']) ? \Carbon\Carbon::parse($eventData['ends_at']) : null,
            'published_at'                => ! empty($eventData['published_at']) ? \Carbon\Carbon::parse($eventData['published_at']) : null,
            'landing_page_id'             => $landingPageId,
            'sponsor_organization_id'     => $sponsorOrgId,
        ];

        $existing = Event::where('slug', $slug)->first();
        if ($existing) {
            $existing->update($attributes);
            $event = $existing;
        } else {
            $attributes['author_id'] = $this->authors->resolve();
            $event = Event::create($attributes);
        }

        // Tiers are canonical from the bundle. Wipe + re-insert. The cascade
        // would null registration.ticket_tier_id, so registrations re-resolve
        // by name after both passes land.
        TicketTier::where('event_id', $event->id)->delete();
        $tierIdsByName = [];
        foreach ($data['tiers'] ?? [] as $sortIndex => $tierRow) {
            if (! is_array($tierRow)) {
                continue;
            }
            $tier = TicketTier::create([
                'event_id'   => $event->id,
                'name'       => $tierRow['name'] ?? 'General',
                'price'      => $tierRow['price'] ?? 0,
                'capacity'   => $tierRow['capacity'] ?? null,
                'sort_order' => (int) ($tierRow['sort_order'] ?? $sortIndex),
            ]);
            $tierIdsByName[$tier->name] = $tier->id;
        }

        // Registrations wipe + re-insert when the bundle carries them.
        // Absence (with_registrations=false at export time) leaves any
        // existing registrations untouched.
        if (array_key_exists('registrations', $data) && is_array($data['registrations'])) {
            EventRegistration::where('event_id', $event->id)->delete();
            foreach ($data['registrations'] as $regRow) {
                if (! is_array($regRow)) {
                    continue;
                }

                $contactId = null;
                if (! empty($regRow['contact_email'])) {
                    $contactId = Contact::where('email', $regRow['contact_email'])->value('id');
                }

                $orgId = null;
                if (! empty($regRow['organization_name'])) {
                    $orgId = Organization::where('name', $regRow['organization_name'])->value('id');
                }

                $tierId = null;
                if (! empty($regRow['ticket_tier_name'])) {
                    $tierId = $tierIdsByName[$regRow['ticket_tier_name']] ?? null;
                }

                // Imported registrations are historical data, not live sign-ups:
                // suppress the EventRegistrationObserver (confirmation email +
                // contact auto-create). Mirrors RandomDataGenerator's seam.
                EventRegistration::withoutEvents(fn () => EventRegistration::create([
                    'event_id'            => $event->id,
                    'ticket_tier_id'      => $tierId,
                    'quantity'            => (int) ($regRow['quantity'] ?? 1),
                    'contact_id'          => $contactId,
                    'organization_id'     => $orgId,
                    'name'                => $regRow['name'] ?? '',
                    'email'               => $regRow['email'] ?? '',
                    'phone'               => $regRow['phone'] ?? null,
                    'company'             => $regRow['company'] ?? null,
                    'address_line_1'      => $regRow['address_line_1'] ?? null,
                    'address_line_2'      => $regRow['address_line_2'] ?? null,
                    'city'                => $regRow['city'] ?? null,
                    'state'               => $regRow['state'] ?? null,
                    'zip'                 => $regRow['zip'] ?? null,
                    'status'              => $regRow['status'] ?? 'registered',
                    'registered_at'       => ! empty($regRow['registered_at']) ? \Carbon\Carbon::parse($regRow['registered_at']) : now(),
                    'notes'               => $regRow['notes'] ?? null,
                    'mailing_list_opt_in' => (bool) ($regRow['mailing_list_opt_in'] ?? false),
                    'ticket_type'         => $regRow['ticket_type'] ?? null,
                    'ticket_fee'          => $regRow['ticket_fee'] ?? null,
                    'payment_state'       => $regRow['payment_state'] ?? null,
                    'custom_fields'       => EventRegistration::sanitizeRichTextCustomFields(
                        is_array($regRow['custom_fields'] ?? null) ? $regRow['custom_fields'] : [],
                    ),
                ]));
            }
        }

        $this->rewireEventMedia($event, $data['media'] ?? [], $log, $archive);
    }

    /**
     * Mirrors PageImporter::rewirePageMedia() for event-level media collections.
     *
     * @param  array<int, array<string, mixed>>  $descriptors
     */
    protected function rewireEventMedia(Event $event, array $descriptors, ImportLog $log, BundleMediaArchive $archive): void
    {
        if (empty($descriptors)) {
            return;
        }

        foreach ($descriptors as $desc) {
            $collectionName = $desc['collection_name'] ?? null;
            $disk           = $desc['disk'] ?? 'public';
            $path           = $desc['path'] ?? null;

            if (! $collectionName || ! $path) {
                $log->warning("Event \"{$event->slug}\": media descriptor missing collection/path, skipped.");

                continue;
            }

            if (str_contains($path, '..') || str_starts_with($path, '/')) {
                $log->warning("Event \"{$event->slug}\": media descriptor for collection '{$collectionName}' has unsafe path, skipped.");

                continue;
            }

            $event->clearMediaCollection($collectionName);

            $archiveAbs = $archive->archiveFile($path);
            if ($archiveAbs !== null) {
                $event
                    ->addMedia($archiveAbs)
                    ->preservingOriginal()
                    ->usingFileName(basename($path))
                    ->toMediaCollection($collectionName, $disk);
            } elseif (Storage::disk($disk)->exists($path)) {
                $event
                    ->addMediaFromDisk($path, $disk)
                    ->preservingOriginal()
                    ->toMediaCollection($collectionName, $disk);
            } else {
                $log->warning("Event \"{$event->slug}\": media file for collection '{$collectionName}' not found at '{$path}' on disk '{$disk}', skipped.");

                continue;
            }
        }
    }
}
