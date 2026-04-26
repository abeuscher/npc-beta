<?php

namespace App\Services\Import;

use App\Data\SampleLibrary;
use DateTime;
use DateTimeImmutable;
use Faker\Generator as Faker;

class FakeCsvComposer
{
    private DateTimeImmutable $now;

    public function __construct(private Faker $faker, ?DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable();
    }

    private function rel(?string $modifier = null): DateTime
    {
        $immutable = $modifier === null ? $this->now : $this->now->modify($modifier);
        return DateTime::createFromImmutable($immutable);
    }

    /**
     * @return array<int,array<string,string|null>>
     */
    public function composeContacts(int $count): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $first = $this->faker->randomElement(SampleLibrary::firstNames());
            $last  = $this->faker->randomElement(SampleLibrary::lastNames());
            $email = $this->syntheticEmail($first, $last);
            $rows[] = $this->contactRow($first, $last, $email);
        }
        return $rows;
    }

    public function extractContactEmails(array $contactRows): array
    {
        return array_values(array_filter(array_map(fn ($r) => $r['Email'] ?? null, $contactRows)));
    }

    /**
     * Events CSV: one row per registration, plus blank-registration rows for events
     * with no registrants. Distinct events: 8–15. Rows per event: 1–5.
     *
     * @return array<int,array<string,string|null>>
     */
    public function composeEvents(int $totalRows, array $contactEmails): array
    {
        $eventCount = min($totalRows, $this->faker->numberBetween(8, 15));
        $groupSizes = $this->partition($totalRows, $eventCount, 1, 5);

        $pool = $this->pickReferencePool($contactEmails);
        $rows = [];

        foreach ($groupSizes as $groupSize) {
            $eventTitle = $this->faker->randomElement(SampleLibrary::eventTitles())
                . ' ' . $this->faker->numerify('##');
            $startsAt        = $this->faker->dateTimeBetween($this->rel('-1 year'), $this->rel('+6 months'));
            $endsAt          = (clone $startsAt)->modify('+' . $this->faker->numberBetween(30, 240) . ' minutes');
            $eventExternalId = 'E-' . $this->faker->unique()->numerify('######');
            $isFree          = $this->faker->boolean(40);
            $price           = $isFree ? '0.00' : (string) $this->faker->numberBetween(5, 100);
            $capacity        = $this->faker->boolean(50) ? (string) $this->faker->numberBetween(20, 200) : null;
            $blankOnly       = ($groupSize === 1) && $this->faker->boolean(30);

            $eventFields = [
                'Event Title'          => $eventTitle,
                'Event Slug'           => $this->slug($eventTitle) . '-' . strtolower($eventExternalId),
                'Event Description'    => $this->faker->sentence(),
                'Event Status'         => 'published',
                'Event Address Line 1' => $isFree ? null : $this->faker->randomElement(SampleLibrary::streetAddresses()),
                'Event Address Line 2' => null,
                'Event City'           => $this->faker->randomElement(SampleLibrary::cities()),
                'Event State'          => $this->faker->randomElement(SampleLibrary::states()),
                'Event Zip'            => $this->faker->postcode(),
                'Event Starts At'      => $startsAt->format('Y-m-d H:i:s'),
                'Event Ends At'        => $endsAt->format('Y-m-d H:i:s'),
                'Event Price'              => $price,
                'Event Capacity'           => $capacity,
                'Event External ID'        => $eventExternalId,
            ];

            for ($r = 0; $r < $groupSize; $r++) {
                $hasRegistration = ! $blankOnly;
                $email           = $hasRegistration && $pool ? $this->faker->randomElement($pool) : null;
                $registrationFee = $isFree ? '0.00' : $price;

                $rows[] = $eventFields + ($hasRegistration
                    ? [
                        'Registration Ticket Type'              => $this->faker->randomElement(['General', 'VIP', 'Student']),
                        'Registration Ticket Fee'               => $registrationFee,
                        'Registration Status'                   => 'registered',
                        'Registration Payment State (snapshot)' => $isFree ? 'free' : 'paid',
                        'Registration Registered At'            => $this->faker->dateTimeBetween($this->rel('-60 days'), $this->rel())->format('Y-m-d H:i:s'),
                        'Registration Registration Notes'       => null,
                    ] : [
                        'Registration Ticket Type'              => null,
                        'Registration Ticket Fee'               => null,
                        'Registration Status'                   => null,
                        'Registration Payment State (snapshot)' => null,
                        'Registration Registered At'            => null,
                        'Registration Registration Notes'       => null,
                    ]) + [
                    'Contact Email'       => $email,
                    'Contact External ID' => null,
                    'Contact Phone'       => null,
                ] + (($hasRegistration && ! $isFree) ? [
                    'Transaction ID (external)'        => 'TXN-E-' . $this->faker->unique()->numerify('########'),
                    'Transaction Amount'               => $registrationFee,
                    'Payment State'                    => 'paid',
                    'Payment Method'                   => $this->faker->randomElement(['Card', 'Check', 'Cash']),
                    'Payment Channel (online/offline)' => $this->faker->randomElement(['online', 'offline']),
                    'Paid At'                          => $this->faker->dateTimeBetween($this->rel('-60 days'), $this->rel())->format('Y-m-d H:i:s'),
                    'Invoice / Receipt Number'         => 'INV-E-' . $this->faker->unique()->numerify('######'),
                ] : [
                    'Transaction ID (external)'        => null,
                    'Transaction Amount'               => null,
                    'Payment State'                    => null,
                    'Payment Method'                   => null,
                    'Payment Channel (online/offline)' => null,
                    'Paid At'                          => null,
                    'Invoice / Receipt Number'         => null,
                ]);
            }
        }

        return $rows;
    }

    /**
     * @return array<int,array<string,string|null>>
     */
    public function composeDonations(int $count, array $contactEmails): array
    {
        $pool = $this->pickReferencePool($contactEmails);
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $amount    = number_format((float) $this->faker->randomFloat(2, 10, 5000), 2, '.', '');
            $donatedAt = $this->faker->dateTimeBetween($this->rel('-2 years'), $this->rel());
            $type      = $this->faker->randomElement(['one_off', 'one_off', 'one_off', 'recurring']);
            $email     = $pool ? $this->faker->randomElement($pool) : null;

            $rows[] = [
                'Donation Amount'                  => $amount,
                'Donation Date'                    => $donatedAt->format('Y-m-d H:i:s'),
                'Type (one_off / recurring)'       => $type,
                'Status'                           => 'active',
                'External ID'                      => 'D-' . $this->faker->unique()->numerify('######'),
                'Invoice / Receipt Number'         => 'INV-D-' . $this->faker->unique()->numerify('######'),
                'Comment / Notes'                  => null,
                'Transaction ID (external)'        => 'TXN-D-' . $this->faker->unique()->numerify('########'),
                'Transaction Amount'               => $amount,
                'Payment State'                    => 'paid',
                'Payment Method'                   => $this->faker->randomElement(['Card', 'Check', 'Cash']),
                'Payment Channel (online/offline)' => $this->faker->randomElement(['online', 'offline']),
                'Paid At'                          => $donatedAt->format('Y-m-d H:i:s'),
                'Email'                            => $email,
                'User ID'                          => null,
                'Phone'                            => null,
            ];
        }
        return $rows;
    }

    /**
     * @return array<int,array<string,string|null>>
     */
    public function composeMemberships(int $count, array $contactEmails): array
    {
        $pool = $this->pickReferencePool($contactEmails);
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $startsOn  = $this->faker->dateTimeBetween($this->rel('-3 years'), $this->rel());
            $expiresOn = (clone $startsOn)->modify('+1 year');
            $email     = $pool ? $this->faker->randomElement($pool) : null;

            $rows[] = [
                'Membership Level / Tier'  => 'Standard',
                'Membership Status'        => $this->faker->randomElement(['active', 'active', 'active', 'expired']),
                'Member Since'             => $startsOn->format('Y-m-d'),
                'Renewal Due / Expires On' => $expiresOn->format('Y-m-d'),
                'Amount Paid'              => number_format((float) $this->faker->randomFloat(2, 25, 500), 2, '.', ''),
                'Notes'                    => null,
                'External ID'              => 'M-' . $this->faker->unique()->numerify('######'),
                'Email'                    => $email,
                'User ID'                  => null,
                'Phone'                    => null,
            ];
        }
        return $rows;
    }

    /**
     * @return array<int,array<string,string|null>>
     */
    public function composeNotes(int $count, array $contactEmails): array
    {
        $pool = $this->pickReferencePool($contactEmails);
        $rows = [];

        $types      = ['call', 'meeting', 'email', 'note', 'task', 'letter', 'sms'];
        $subjects   = [
            'Intro call', 'Follow-up', 'Gift confirmation', 'Event RSVP',
            'Volunteer check-in', 'Annual renewal', 'Board outreach',
            'Sponsorship discussion', 'Thank-you letter', 'Welcome package',
        ];
        $bodies     = [
            'Left a voicemail; will try again next week.',
            'Discussed upcoming gala and ticket count.',
            'Confirmed recurring gift start date.',
            'Reviewed pledge schedule for the capital campaign.',
            'Shared program updates and scheduled a site visit.',
            'Received thank-you note from constituent.',
            'Met briefly to discuss board nomination.',
            'Walked through online donation flow — no issues reported.',
        ];
        $outcomes   = [
            'Left message', 'Connected', 'Declined', 'Will follow up',
            'Committed to pledge', 'Scheduled next meeting',
        ];

        for ($i = 0; $i < $count; $i++) {
            $type       = $this->faker->randomElement($types);
            $subject    = $this->faker->randomElement($subjects);
            $status     = $this->faker->boolean(80)
                ? 'completed'
                : $this->faker->randomElement(['scheduled', 'no_show', 'cancelled']);
            $body       = $this->faker->randomElement($bodies);
            $occurredAt = $this->faker->dateTimeBetween($this->rel('-2 years'), $this->rel());

            $followUpAt = null;
            if ($status !== 'completed' && $this->faker->boolean(50)) {
                $followUpAt = $this->faker->dateTimeBetween($this->rel(), $this->rel('+6 months'));
            } elseif ($status === 'completed' && $this->faker->boolean(10)) {
                $followUpAt = $this->faker->dateTimeBetween($this->rel(), $this->rel('+3 months'));
            }

            $isInteraction = in_array($type, ['call', 'meeting'], true);
            $outcome       = $isInteraction ? $this->faker->randomElement($outcomes) : null;
            $duration      = $isInteraction ? (string) $this->faker->numberBetween(5, 90) : null;

            $email = $pool ? $this->faker->randomElement($pool) : null;

            $rows[] = [
                'Note Type'               => $type,
                'Note Subject'            => $subject,
                'Note Status'             => $status,
                'Note Body'               => $body,
                'Note Occurred At'        => $occurredAt->format('Y-m-d H:i:s'),
                'Note Follow-up At'       => $followUpAt ? $followUpAt->format('Y-m-d H:i:s') : null,
                'Note Outcome'            => $outcome,
                'Note Duration (minutes)' => $duration,
                'Note External ID'        => 'N-' . $this->faker->unique()->numerify('######'),
                'Email'                   => $email,
                'User ID'                 => null,
                'Phone'                   => null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int,array<string,string|null>>
     */
    public function composeInvoiceDetails(int $totalRows, array $contactEmails): array
    {
        $pool = $this->pickReferencePool($contactEmails);
        $rows = [];

        $maxInvoices  = max(1, $totalRows);
        $minInvoices  = max(1, intdiv($totalRows, 5));
        $invoiceCount = min($totalRows, $this->faker->numberBetween($minInvoices, $maxInvoices));
        $groupSizes   = $this->partition($totalRows, $invoiceCount, 1, 5);

        foreach ($groupSizes as $groupSize) {
            $invoiceNumber = 'INV-I-' . $this->faker->unique()->numerify('######');
            $invoiceDate   = $this->faker->dateTimeBetween($this->rel('-2 years'), $this->rel());
            $paymentDate   = (clone $invoiceDate)->modify('+' . $this->faker->numberBetween(0, 14) . ' days');
            $email         = $pool ? $this->faker->randomElement($pool) : null;

            $parentFields = [
                'Invoice #'      => $invoiceNumber,
                'Invoice Date'   => $invoiceDate->format('Y-m-d'),
                'Origin'         => $this->faker->randomElement(['registration', 'donation', 'product', 'other']),
                'Origin Details' => $this->faker->sentence(3),
                'Ticket Type'    => $this->faker->randomElement(['General', 'VIP', null]),
                'Invoice Status' => $this->faker->randomElement(['paid', 'paid', 'paid', 'pending']),
                'Currency'       => 'USD',
                'Payment Date'   => $paymentDate->format('Y-m-d'),
                'Payment Type'   => $this->faker->randomElement(['Card', 'Check', 'Cash']),
            ];

            for ($r = 0; $r < $groupSize; $r++) {
                $quantity = $this->faker->numberBetween(1, 5);
                $price    = number_format((float) $this->faker->randomFloat(2, 5, 200), 2, '.', '');
                $amount   = number_format($quantity * (float) $price, 2, '.', '');

                $rows[] = $parentFields + [
                    'Item Description' => $this->faker->randomElement(['Registration Fee', 'Merchandise', 'Donation', 'Add-on', 'Service']),
                    'Item Quantity'    => (string) $quantity,
                    'Item Price'       => $price,
                    'Item Amount'      => $amount,
                    'Internal Notes'   => null,
                    'Email'            => $email,
                    'User ID'          => null,
                    'Phone'            => null,
                ];
            }
        }

        return $rows;
    }

    private function contactRow(string $first, string $last, string $email): array
    {
        return [
            'Prefix'                 => $this->faker->boolean(30) ? $this->faker->randomElement(['Mr', 'Ms', 'Mrs', 'Dr', 'Prof']) : null,
            'First Name'             => $first,
            'Last Name'              => $last,
            'Email'                  => $email,
            'Phone'                  => $this->faker->boolean(70) ? $this->faker->numerify('(###) 555-####') : null,
            'Address Line 1'         => $this->faker->boolean(60) ? $this->faker->randomElement(SampleLibrary::streetAddresses()) : null,
            'Address Line 2'         => $this->faker->boolean(10) ? $this->faker->secondaryAddress() : null,
            'City'                   => $this->faker->boolean(60) ? $this->faker->randomElement(SampleLibrary::cities()) : null,
            'State'                  => $this->faker->boolean(60) ? $this->faker->randomElement(SampleLibrary::states()) : null,
            'Postal Code'            => $this->faker->boolean(60) ? $this->faker->postcode() : null,
            'Date Of Birth'          => $this->faker->boolean(40) ? $this->faker->dateTimeBetween($this->rel('-80 years'), $this->rel('-18 years'))->format('Y-m-d') : null,
            'Country'                => 'US',
            'Do Not Contact'         => null,
            'Mailing List Opt In'    => null,
            'Quickbooks Customer Id' => null,
            'External ID'            => 'C-' . $this->faker->unique()->numerify('######'),
            'Organization'           => null,
            'Tags'                   => null,
            'Notes'                  => null,
        ];
    }

    private function syntheticEmail(string $first, string $last): string
    {
        $safeFirst = preg_replace('/[^a-z0-9._-]/', '', strtolower($first));
        $safeLast  = preg_replace('/[^a-z0-9._-]/', '', strtolower($last));
        $suffix    = $this->faker->unique()->numerify('####');
        $domain    = $this->faker->randomElement(SampleLibrary::emailDomains());
        return "{$safeFirst}.{$safeLast}.{$suffix}@{$domain}";
    }

    private function slug(string $s): string
    {
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim($s, '-');
    }

    /**
     * Biased reference pool: ~60% of input emails, so some contacts never appear
     * in referring CSVs while others get multiple records via pigeonhole.
     */
    private function pickReferencePool(array $emails): array
    {
        if ($emails === []) {
            return [];
        }
        $keep     = max(1, (int) round(count($emails) * 0.6));
        $shuffled = $emails;
        shuffle($shuffled);
        return array_slice($shuffled, 0, $keep);
    }

    /**
     * Partition $total rows into $groupCount groups where each group has $min..$max rows.
     * @return array<int,int>
     */
    private function partition(int $total, int $groupCount, int $min, int $max): array
    {
        if ($groupCount <= 0) {
            return [];
        }
        if ($total < $groupCount * $min) {
            $groupCount = max(1, intdiv($total, $min));
        }
        $sizes     = array_fill(0, $groupCount, $min);
        $remaining = $total - $groupCount * $min;

        while ($remaining > 0) {
            $full = 0;
            foreach ($sizes as $s) {
                if ($s >= $max) { $full++; }
            }
            if ($full === $groupCount) {
                break;
            }
            $idx = $this->faker->numberBetween(0, $groupCount - 1);
            if ($sizes[$idx] >= $max) {
                continue;
            }
            $sizes[$idx]++;
            $remaining--;
        }

        shuffle($sizes);
        return $sizes;
    }
}
