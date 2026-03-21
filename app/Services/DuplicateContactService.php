<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactDuplicateDismissal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DuplicateContactService
{
    /**
     * Check a single contact (model or plain array) for hard and probable duplicates.
     *
     * Returns:
     *   'hard'     => ?Contact  — exact email match
     *   'probable' => Collection — same last_name + normalised postal_code
     */
    public function check(Contact|array $subject, ?string $excludeId = null): array
    {
        $email      = $this->extractField($subject, 'email');
        $lastName   = $this->extractField($subject, 'last_name');
        $postalCode = $this->extractField($subject, 'postal_code');

        $hard = null;

        if (filled($email)) {
            $query = Contact::whereRaw('LOWER(email) = ?', [strtolower(trim($email))]);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            $hard = $query->first();
        }

        $probable = collect();

        if (filled($lastName) && filled($postalCode)) {
            $normalisedPostal = $this->normalisePostal($postalCode);

            $query = Contact::whereRaw('LOWER(last_name) = ?', [strtolower(trim($lastName))])
                ->whereRaw("UPPER(REPLACE(postal_code, ' ', '')) = ?", [$normalisedPostal]);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if ($hard) {
                $query->where('id', '!=', $hard->id);
            }

            if ($excludeId) {
                $dismissed = $this->getDismissedPartnerIds($excludeId);

                if ($dismissed->isNotEmpty()) {
                    $query->whereNotIn('id', $dismissed);
                }
            }

            $probable = $query->get();
        }

        return [
            'hard'     => $hard,
            'probable' => $probable,
        ];
    }

    /**
     * Find all probable duplicate pairs across all non-deleted contacts.
     *
     * Returns a Collection of ['a_id' => uuid, 'b_id' => uuid] arrays,
     * where a_id < b_id (string comparison) and dismissed pairs are excluded.
     */
    public function findAllProbablePairs(): Collection
    {
        $contacts = Contact::whereNotNull('last_name')
            ->whereNotNull('postal_code')
            ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'postal_code', 'created_at']);

        $dismissed = ContactDuplicateDismissal::all(['contact_id_a', 'contact_id_b'])
            ->mapWithKeys(fn ($d) => [$d->contact_id_a . '|' . $d->contact_id_b => true]);

        $groups = $contacts->groupBy(
            fn (Contact $c) => strtolower($c->last_name) . '|' . $this->normalisePostal($c->postal_code)
        );

        $pairs = collect();

        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $items = $group->values();

            for ($i = 0; $i < $items->count(); $i++) {
                for ($j = $i + 1; $j < $items->count(); $j++) {
                    $a = $items[$i];
                    $b = $items[$j];

                    // Ensure a_id < b_id for consistent dismissal lookup
                    if ($a->id > $b->id) {
                        [$a, $b] = [$b, $a];
                    }

                    if ($dismissed->has($a->id . '|' . $b->id)) {
                        continue;
                    }

                    $pairs->push(['a_id' => $a->id, 'b_id' => $b->id]);
                }
            }
        }

        return $pairs;
    }

    /**
     * Merge two contacts: copy non-blank fields from discard to survivor,
     * reassign all related records, then soft-delete the discarded contact.
     */
    public function mergeContacts(string $survivorId, string $discardId): void
    {
        DB::transaction(function () use ($survivorId, $discardId) {
            $survivor = Contact::findOrFail($survivorId);
            $discard  = Contact::findOrFail($discardId);

            $scalarFields = [
                'prefix', 'first_name', 'last_name', 'email', 'phone',
                'address_line_1', 'address_line_2', 'city', 'state',
                'postal_code', 'date_of_birth', 'country',
                'organization_id', 'household_id',
            ];

            foreach ($scalarFields as $field) {
                if (blank($survivor->$field) && filled($discard->$field)) {
                    $survivor->$field = $discard->$field;
                }
            }

            $survivorCf = $survivor->custom_fields ?? [];
            $discardCf  = $discard->custom_fields ?? [];

            foreach ($discardCf as $key => $value) {
                if (! isset($survivorCf[$key]) || blank($survivorCf[$key])) {
                    $survivorCf[$key] = $value;
                }
            }

            $survivor->custom_fields = $survivorCf;
            $survivor->save();

            // Notes
            DB::table('notes')
                ->where('notable_type', Contact::class)
                ->where('notable_id', $discardId)
                ->update(['notable_id' => $survivorId]);

            // Tags — move tags the survivor doesn't already have, drop duplicates
            $survivorTagIds = DB::table('taggables')
                ->where('taggable_type', Contact::class)
                ->where('taggable_id', $survivorId)
                ->pluck('tag_id');

            DB::table('taggables')
                ->where('taggable_type', Contact::class)
                ->where('taggable_id', $discardId)
                ->whereNotIn('tag_id', $survivorTagIds)
                ->update(['taggable_id' => $survivorId]);

            DB::table('taggables')
                ->where('taggable_type', Contact::class)
                ->where('taggable_id', $discardId)
                ->delete();

            // Donations
            DB::table('donations')
                ->where('contact_id', $discardId)
                ->update(['contact_id' => $survivorId]);

            // Memberships
            DB::table('memberships')
                ->where('contact_id', $discardId)
                ->update(['contact_id' => $survivorId]);

            // Event registrations
            DB::table('event_registrations')
                ->where('contact_id', $discardId)
                ->update(['contact_id' => $survivorId]);

            $discard->delete();
        });
    }

    private function extractField(Contact|array $subject, string $field): ?string
    {
        if ($subject instanceof Contact) {
            return $subject->$field;
        }

        return $subject[$field] ?? null;
    }

    private function normalisePostal(?string $postal): string
    {
        return strtoupper(str_replace(' ', '', (string) $postal));
    }

    private function getDismissedPartnerIds(string $contactId): Collection
    {
        return ContactDuplicateDismissal::where('contact_id_a', $contactId)
            ->orWhere('contact_id_b', $contactId)
            ->get()
            ->flatMap(fn ($d) => [$d->contact_id_a, $d->contact_id_b])
            ->filter(fn ($id) => $id !== $contactId)
            ->unique()
            ->values();
    }
}
