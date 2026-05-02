<?php

namespace App\Enums;

/**
 * Enumerates the six content types an ImportSession can target. Values match
 * the string values persisted in `import_sessions.model_type` — the Laravel
 * cast on `ImportSession::$casts` coerces DB strings to cases on read and
 * accepts either a case or a matching string on write.
 *
 * Adding a new case requires no migration, but every consumer switching on
 * `$session->model_type` (primarily `ImportSessionActions`,
 * `ImportSessionPreview`, and the per-type wizards) must be reviewed.
 */
enum ImportModelType: string
{
    case Contact       = 'contact';
    case Event         = 'event';
    case Donation      = 'donation';
    case Membership    = 'membership';
    case InvoiceDetail = 'invoice_detail';
    case Note          = 'note';
    case Organization  = 'organization';

    public function label(): string
    {
        return match ($this) {
            self::Contact       => 'Contact',
            self::Event         => 'Event',
            self::Donation      => 'Donation',
            self::Membership    => 'Membership',
            self::InvoiceDetail => 'Invoice Detail',
            self::Note          => 'Note',
            self::Organization  => 'Organization',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Contact       => 'Contacts',
            self::Event         => 'Events',
            self::Donation      => 'Donations',
            self::Membership    => 'Memberships',
            self::InvoiceDetail => 'Invoice Details',
            self::Note          => 'Notes',
            self::Organization  => 'Organizations',
        };
    }
}
