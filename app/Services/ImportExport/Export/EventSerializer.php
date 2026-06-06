<?php

namespace App\Services\ImportExport\Export;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\TicketTier;

/**
 * Serializes Event rows (with tiers, optional registrations, and event-level
 * media) into the bundle's portable event shape. Registrations are opt-in
 * because they are operator data, not template content. Session A001.
 */
class EventSerializer
{
    /**
     * @param  array<int, string>  $eventIds
     * @return \Illuminate\Database\Eloquent\Collection<int, Event>
     */
    public function collectForExport(array $eventIds): \Illuminate\Database\Eloquent\Collection
    {
        if (empty($eventIds)) {
            return Event::whereRaw('1 = 0')->get();
        }

        return Event::whereIn('id', $eventIds)
            ->with([
                'ticketTiers' => fn ($q) => $q->orderBy('sort_order'),
                'registrations.ticketTier',
                'registrations.contact',
                'registrations.organization',
                'landingPage',
                'sponsorOrganization',
                'media',
            ])
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Event $event, bool $withRegistrations): array
    {
        $tierNamesById = $event->ticketTiers->pluck('name', 'id')->all();

        $entry = [
            'event' => [
                'title'                        => $event->title,
                'slug'                         => $event->slug,
                'description'                  => $event->description,
                'status'                       => $event->status,
                'address_line_1'               => $event->address_line_1,
                'address_line_2'               => $event->address_line_2,
                'city'                         => $event->city,
                'state'                        => $event->state,
                'zip'                          => $event->zip,
                'map_url'                      => $event->map_url,
                'map_label'                    => $event->map_label,
                'meeting_url'                  => $event->meeting_url,
                'meeting_label'                => $event->meeting_label,
                'meeting_details'              => $event->meeting_details,
                'registration_mode'            => $event->registration_mode,
                'external_registration_url'    => $event->external_registration_url,
                'auto_create_contacts'         => (bool) $event->auto_create_contacts,
                'mailing_list_opt_in_enabled'  => (bool) $event->mailing_list_opt_in_enabled,
                'custom_fields'                => $event->custom_fields ?? [],
                'starts_at'                    => $event->starts_at?->toIso8601String(),
                'ends_at'                      => $event->ends_at?->toIso8601String(),
                'published_at'                 => $event->published_at?->toIso8601String(),
                'landing_page_slug'            => $event->landingPage?->slug,
                'sponsor_organization_name'    => $event->sponsorOrganization?->name,
            ],
            'tiers' => $event->ticketTiers->map(fn (TicketTier $t) => [
                'name'       => $t->name,
                'price'      => $t->price,
                'capacity'   => $t->capacity,
                'sort_order' => (int) $t->sort_order,
            ])->all(),
            'media' => $this->serializeEventMedia($event),
        ];

        // Registrations key is present iff the operator opted in. Absence on
        // re-import means "leave existing registrations alone"; presence
        // (even when empty) means "registrations are canonical from bundle".
        if ($withRegistrations) {
            $entry['registrations'] = $event->registrations
                ->map(fn (EventRegistration $r) => $this->serializeRegistration($r, $tierNamesById))
                ->all();
        }

        return $entry;
    }

    /**
     * @param  array<string, string>  $tierNamesById
     * @return array<string, mixed>
     */
    protected function serializeRegistration(EventRegistration $r, array $tierNamesById): array
    {
        return [
            'ticket_tier_name'         => $r->ticket_tier_id ? ($tierNamesById[$r->ticket_tier_id] ?? null) : null,
            'quantity'                 => (int) ($r->quantity ?? 1),
            'name'                     => $r->name,
            'email'                    => $r->email,
            'phone'                    => $r->phone,
            'company'                  => $r->company,
            'address_line_1'           => $r->address_line_1,
            'address_line_2'           => $r->address_line_2,
            'city'                     => $r->city,
            'state'                    => $r->state,
            'zip'                      => $r->zip,
            'status'                   => $r->status,
            'registered_at'            => $r->registered_at?->toIso8601String(),
            'notes'                    => $r->notes,
            'mailing_list_opt_in'      => (bool) $r->mailing_list_opt_in,
            'ticket_type'              => $r->ticket_type,
            'ticket_fee'               => $r->ticket_fee,
            'payment_state'            => $r->payment_state,
            'custom_fields'            => $r->custom_fields ?? [],
            'contact_email'            => $r->contact?->email,
            'organization_name'        => $r->organization?->name,
        ];
    }

    /**
     * Mirrors serializePageMedia() for the event media collections.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeEventMedia(Event $event): array
    {
        $descriptors = [];

        foreach (['event_thumbnail', 'event_header', 'event_og_image'] as $collection) {
            $media = $event->getFirstMedia($collection);
            if (! $media) {
                continue;
            }

            $descriptors[] = [
                'collection_name' => $collection,
                'file_name'       => $media->file_name,
                'disk'            => $media->disk,
                'id'              => $media->id,
                'path'            => $media->getPathRelativeToRoot(),
                'mime_type'       => $media->mime_type,
                'size'            => $media->size,
            ];
        }

        return $descriptors;
    }
}
