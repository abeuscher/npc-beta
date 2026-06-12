<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\PortalAccount;
use Illuminate\Database\Seeder;

/**
 * Shared demo data for the portal (member) widgets — PortalAccountDashboard,
 * PortalContactEdit, PortalEventRegistrations, PortalChangePassword. Those
 * widgets render against auth('portal')->user() and stay blank in the isolated
 * thumbnail render unless a member is logged in. This seeder mints exactly one
 * stand-in member (a Contact, its PortalAccount, and one EventRegistration
 * against the DemoEventSeeder event) so WidgetDemoController can authenticate
 * it for the capture. Keyed on ACCOUNT_EMAIL; idempotent.
 */
class DemoPortalMemberSeeder extends Seeder
{
    public const ACCOUNT_EMAIL = 'jordan.rivera@example.org';

    public function run(): void
    {
        $this->call(DemoEventSeeder::class);

        $contact = Contact::updateOrCreate(
            ['email' => self::ACCOUNT_EMAIL],
            [
                'first_name'  => 'Jordan',
                'last_name'   => 'Rivera',
                'city'        => 'Springfield',
                'state'       => 'IL',
                'postal_code' => '62704',
                'country'     => 'United States',
            ]
        );

        PortalAccount::updateOrCreate(
            ['email' => self::ACCOUNT_EMAIL],
            [
                'contact_id'        => $contact->id,
                'password'          => 'demo-portal-password',
                'email_verified_at' => now(),
                'is_active'         => true,
            ]
        );

        $event = Event::where('slug', DemoEventSeeder::EVENT_SLUG)->first();

        if ($event) {
            EventRegistration::updateOrCreate(
                ['event_id' => $event->id, 'contact_id' => $contact->id],
                [
                    'name'          => 'Jordan Rivera',
                    'email'         => self::ACCOUNT_EMAIL,
                    'status'        => 'registered',
                    'registered_at' => now(),
                ]
            );
        }
    }
}
