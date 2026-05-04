<?php

namespace App\Services\Import;

class EventFieldMapper
{
    public function map(string $sourceColumn, ?string $preset = null): ?string
    {
        $normalized = strtolower(trim($sourceColumn));
        $map = static::presetMap($preset ?? 'generic');

        return $map[$normalized] ?? null;
    }

    public static function presets(): array
    {
        return ['generic', 'wild_apricot', 'bloomerang'];
    }

    public static function presetMap(string $preset): array
    {
        return match ($preset) {
            'bloomerang'   => static::bloomerangMap(),
            'wild_apricot' => static::wildApricotMap(),
            default        => static::genericMap(),
        };
    }

    private static function genericMap(): array
    {
        return [
            // event:external_id
            'event id'                  => 'event:external_id',
            'event_id'                  => 'event:external_id',
            'eventid'                   => 'event:external_id',
            'event external id'         => 'event:external_id',
            'event_external_id'         => 'event:external_id',
            'eventexternalid'           => 'event:external_id',
            'external id'               => 'event:external_id',
            'external_id'               => 'event:external_id',
            'externalid'                => 'event:external_id',

            // event:title
            'event title'               => 'event:title',
            'event_title'               => 'event:title',
            'eventtitle'                => 'event:title',
            'title'                     => 'event:title',
            'name'                      => 'event:title',
            'event name'                => 'event:title',
            'event_name'                => 'event:title',
            'eventname'                 => 'event:title',

            // event:slug
            'event slug'                => 'event:slug',
            'event_slug'                => 'event:slug',
            'eventslug'                 => 'event:slug',
            'slug'                      => 'event:slug',

            // event:description
            'event description'         => 'event:description',
            'event_description'         => 'event:description',
            'eventdescription'          => 'event:description',
            'description'               => 'event:description',

            // event:status
            'event status'              => 'event:status',
            'event_status'              => 'event:status',
            'eventstatus'               => 'event:status',

            // event:starts_at
            'event starts at'           => 'event:starts_at',
            'event_starts_at'           => 'event:starts_at',
            'eventstartsat'             => 'event:starts_at',
            'start date'                => 'event:starts_at',
            'start_date'                => 'event:starts_at',
            'startdate'                 => 'event:starts_at',
            'starts at'                 => 'event:starts_at',
            'starts_at'                 => 'event:starts_at',
            'startsat'                  => 'event:starts_at',
            'begins at'                 => 'event:starts_at',
            'begins_at'                 => 'event:starts_at',
            'beginsat'                  => 'event:starts_at',
            'event start'               => 'event:starts_at',
            'event_start'               => 'event:starts_at',
            'eventstart'                => 'event:starts_at',
            'event start date'          => 'event:starts_at',
            'event_start_date'          => 'event:starts_at',
            'eventstartdate'            => 'event:starts_at',

            // event:ends_at
            'event ends at'             => 'event:ends_at',
            'event_ends_at'             => 'event:ends_at',
            'eventendsat'               => 'event:ends_at',
            'end date'                  => 'event:ends_at',
            'end_date'                  => 'event:ends_at',
            'enddate'                   => 'event:ends_at',
            'ends at'                   => 'event:ends_at',
            'ends_at'                   => 'event:ends_at',
            'endsat'                    => 'event:ends_at',
            'event end'                 => 'event:ends_at',
            'event_end'                 => 'event:ends_at',
            'eventend'                  => 'event:ends_at',
            'event end date'            => 'event:ends_at',
            'event_end_date'            => 'event:ends_at',
            'eventenddate'              => 'event:ends_at',

            // event:address_line_1
            'event location'            => 'event:address_line_1',
            'event_location'            => 'event:address_line_1',
            'eventlocation'             => 'event:address_line_1',
            'event address line 1'      => 'event:address_line_1',
            'event_address_line_1'      => 'event:address_line_1',
            'event address'             => 'event:address_line_1',
            'event_address'             => 'event:address_line_1',
            'eventaddress'              => 'event:address_line_1',
            'location'                  => 'event:address_line_1',
            'venue'                     => 'event:address_line_1',
            'event venue'               => 'event:address_line_1',
            'event_venue'               => 'event:address_line_1',
            'eventvenue'                => 'event:address_line_1',

            // event:address_line_2
            'event address line 2'      => 'event:address_line_2',
            'event_address_line_2'      => 'event:address_line_2',
            'event address 2'           => 'event:address_line_2',

            // event:city
            'event city'                => 'event:city',
            'event_city'                => 'event:city',
            'eventcity'                 => 'event:city',

            // event:state
            'event state'               => 'event:state',
            'event_state'               => 'event:state',
            'eventstate'                => 'event:state',

            // event:zip
            'event zip'                 => 'event:zip',
            'event_zip'                 => 'event:zip',
            'eventzip'                  => 'event:zip',
            'event postal code'         => 'event:zip',
            'event_postal_code'         => 'event:zip',

            // event:price
            'event price'               => 'event:price',
            'event_price'               => 'event:price',
            'eventprice'                => 'event:price',

            // event:capacity
            'event capacity'            => 'event:capacity',
            'event_capacity'            => 'event:capacity',
            'eventcapacity'             => 'event:capacity',
            'capacity'                  => 'event:capacity',

            // contact:external_id
            'contact external id'       => 'contact:external_id',
            'contact_external_id'       => 'contact:external_id',
            'contactexternalid'         => 'contact:external_id',
            'user id'                   => 'contact:external_id',
            'user_id'                   => 'contact:external_id',
            'userid'                    => 'contact:external_id',
            'contact id'                => 'contact:external_id',
            'contact_id'                => 'contact:external_id',
            'contactid'                 => 'contact:external_id',
            'attendee id'               => 'contact:external_id',
            'attendee_id'               => 'contact:external_id',
            'registrant id'             => 'contact:external_id',
            'registrant_id'             => 'contact:external_id',

            // contact:email
            'contact email'             => 'contact:email',
            'contact_email'             => 'contact:email',
            'contactemail'              => 'contact:email',
            'email'                     => 'contact:email',
            'email address'             => 'contact:email',
            'email_address'             => 'contact:email',
            'emailaddress'              => 'contact:email',
            'e-mail'                    => 'contact:email',

            // contact:phone
            'contact phone'             => 'contact:phone',
            'contact_phone'             => 'contact:phone',
            'contactphone'              => 'contact:phone',
            'phone'                     => 'contact:phone',
            'phone number'              => 'contact:phone',
            'phone_number'              => 'contact:phone',
            'phonenumber'               => 'contact:phone',
            'mobile'                    => 'contact:phone',
            'mobile phone'              => 'contact:phone',
            'mobilephone'               => 'contact:phone',

            // registration:ticket_type
            'registration ticket type'                 => 'registration:ticket_type',
            'registration_ticket_type'                 => 'registration:ticket_type',
            'registrationtickettype'                   => 'registration:ticket_type',
            'ticket type'                              => 'registration:ticket_type',
            'ticket_type'                              => 'registration:ticket_type',
            'tickettype'                               => 'registration:ticket_type',
            'ticket type/invitee reply'                => 'registration:ticket_type',
            'ticket'                                   => 'registration:ticket_type',
            'ticket name'                              => 'registration:ticket_type',
            'ticket level'                             => 'registration:ticket_type',

            // registration:ticket_fee
            'registration ticket fee'                  => 'registration:ticket_fee',
            'registration_ticket_fee'                  => 'registration:ticket_fee',
            'registrationticketfee'                    => 'registration:ticket_fee',
            'ticket fee'                               => 'registration:ticket_fee',
            'ticket_fee'                               => 'registration:ticket_fee',
            'ticketfee'                                => 'registration:ticket_fee',
            'ticket type fee'                          => 'registration:ticket_fee',
            'ticket price'                             => 'registration:ticket_fee',
            'ticket_price'                             => 'registration:ticket_fee',
            'registration fee'                         => 'registration:ticket_fee',
            'registration_fee'                         => 'registration:ticket_fee',

            // registration:status
            'registration status'                      => 'registration:status',
            'registration_status'                      => 'registration:status',
            'registrationstatus'                       => 'registration:status',

            // registration:payment_state
            'registration payment state'               => 'registration:payment_state',
            'registration_payment_state'               => 'registration:payment_state',
            'registrationpaymentstate'                 => 'registration:payment_state',
            'registration payment state (snapshot)'    => 'registration:payment_state',

            // registration:registered_at
            'registration registered at'               => 'registration:registered_at',
            'registration_registered_at'               => 'registration:registered_at',
            'event registration date'                  => 'registration:registered_at',
            'event_registration_date'                  => 'registration:registered_at',
            'eventregistrationdate'                    => 'registration:registered_at',
            'registration date'                        => 'registration:registered_at',
            'registration_date'                        => 'registration:registered_at',
            'registrationdate'                         => 'registration:registered_at',
            'registered at'                            => 'registration:registered_at',
            'registered_at'                            => 'registration:registered_at',
            'registered on'                            => 'registration:registered_at',
            'registered_on'                            => 'registration:registered_at',
            'signup date'                              => 'registration:registered_at',
            'signup_date'                              => 'registration:registered_at',

            // registration:notes
            'registration notes'                       => 'registration:notes',
            'registration_notes'                       => 'registration:notes',
            'registrationnotes'                        => 'registration:notes',
            'registration registration notes'          => 'registration:notes',

            // transaction:external_id
            'invoice #'                                => 'transaction:external_id',
            'invoice number'                           => 'transaction:external_id',
            'invoice_number'                           => 'transaction:external_id',
            'invoicenumber'                            => 'transaction:external_id',
            'transaction id'                           => 'transaction:external_id',
            'transaction_id'                           => 'transaction:external_id',
            'transactionid'                            => 'transaction:external_id',
            'transaction id (external)'                => 'transaction:external_id',
            'order id'                                 => 'transaction:external_id',
            'order_id'                                 => 'transaction:external_id',
            'orderid'                                  => 'transaction:external_id',
            'order number'                             => 'transaction:external_id',
            'order_number'                             => 'transaction:external_id',
            'ordernumber'                              => 'transaction:external_id',

            // transaction:amount
            'transaction amount'                       => 'transaction:amount',
            'transaction_amount'                       => 'transaction:amount',
            'transactionamount'                        => 'transaction:amount',
            'total fee incl. extra costs and guests registration fees' => 'transaction:amount',
            'total'                                    => 'transaction:amount',
            'total amount'                             => 'transaction:amount',
            'total_amount'                             => 'transaction:amount',
            'totalamount'                              => 'transaction:amount',
            'order total'                              => 'transaction:amount',
            'order_total'                              => 'transaction:amount',

            // transaction:payment_state
            'payment state'                            => 'transaction:payment_state',
            'payment_state'                            => 'transaction:payment_state',
            'paymentstate'                             => 'transaction:payment_state',
            'transaction payment state'                => 'transaction:payment_state',
            'transaction_payment_state'                => 'transaction:payment_state',
            'payment status'                           => 'transaction:payment_state',
            'payment_status'                           => 'transaction:payment_state',
            'paymentstatus'                            => 'transaction:payment_state',

            // transaction:payment_method
            'payment type'                             => 'transaction:payment_method',
            'payment_type'                             => 'transaction:payment_method',
            'paymenttype'                              => 'transaction:payment_method',
            'payment method'                           => 'transaction:payment_method',
            'payment_method'                           => 'transaction:payment_method',
            'paymentmethod'                            => 'transaction:payment_method',
            'transaction payment method'               => 'transaction:payment_method',
            'transaction_payment_method'               => 'transaction:payment_method',

            // transaction:payment_channel
            'online/offline'                           => 'transaction:payment_channel',
            'payment channel'                          => 'transaction:payment_channel',
            'payment_channel'                          => 'transaction:payment_channel',
            'paymentchannel'                           => 'transaction:payment_channel',
            'payment channel (online/offline)'         => 'transaction:payment_channel',
            'transaction payment channel'              => 'transaction:payment_channel',
            'transaction_payment_channel'              => 'transaction:payment_channel',

            // transaction:occurred_at
            'paid at'                                  => 'transaction:occurred_at',
            'paid_at'                                  => 'transaction:occurred_at',
            'paidat'                                   => 'transaction:occurred_at',
            'transaction paid at'                      => 'transaction:occurred_at',
            'transaction_paid_at'                      => 'transaction:occurred_at',
            'transaction occurred at'                  => 'transaction:occurred_at',
            'transaction_occurred_at'                  => 'transaction:occurred_at',
            'occurred at'                              => 'transaction:occurred_at',
            'occurred_at'                              => 'transaction:occurred_at',

            // transaction:invoice_number
            'invoice / receipt number'                 => 'transaction:invoice_number',
            'receipt number'                           => 'transaction:invoice_number',
            'receipt_number'                           => 'transaction:invoice_number',
            'receiptnumber'                            => 'transaction:invoice_number',
            'transaction invoice number'               => 'transaction:invoice_number',
            'transaction_invoice_number'               => 'transaction:invoice_number',

            // __note_contact__
            'internal notes'                           => '__note_contact__',
            'internal_notes'                           => '__note_contact__',
            'internalnotes'                            => '__note_contact__',
            'admin notes'                              => '__note_contact__',
            'admin_notes'                              => '__note_contact__',
            'adminnotes'                               => '__note_contact__',
            'staff notes'                              => '__note_contact__',
            'staff_notes'                              => '__note_contact__',
            'staffnotes'                               => '__note_contact__',
        ];
    }

    private static function wildApricotMap(): array
    {
        return [
            'event id'                  => 'event:external_id',
            'event title'               => 'event:title',
            'start date'                => 'event:starts_at',
            'end date'                  => 'event:ends_at',
            'event location'            => 'event:address_line_1',

            'user id'                   => 'contact:external_id',
            'email'                     => 'contact:email',
            'email address'             => 'contact:email',
            'phone'                     => 'contact:phone',
            'phone number'              => 'contact:phone',

            'ticket type'                              => 'registration:ticket_type',
            'ticket type/invitee reply'                => 'registration:ticket_type',
            'ticket fee'                               => 'registration:ticket_fee',
            'ticket type fee'                          => 'registration:ticket_fee',
            'event registration date'                  => 'registration:registered_at',

            'invoice #'                                => 'transaction:external_id',
            'invoice number'                           => 'transaction:external_id',
            'transaction id'                           => 'transaction:external_id',
            'total fee incl. extra costs and guests registration fees' => 'transaction:amount',
            'transaction amount'                       => 'transaction:amount',
            'payment state'                            => 'transaction:payment_state',
            'payment type'                             => 'transaction:payment_method',
            'online/offline'                           => 'transaction:payment_channel',

            'internal notes'                           => '__note_contact__',
        ];
    }

    private static function bloomerangMap(): array
    {
        return [
            'event id'                  => 'event:external_id',
            'event title'               => 'event:title',
            'start date'                => 'event:starts_at',
            'end date'                  => 'event:ends_at',
            'event location'            => 'event:address_line_1',

            'user id'                   => 'contact:external_id',
            'email'                     => 'contact:email',
            'email address'             => 'contact:email',
            'phone'                     => 'contact:phone',
            'phone number'              => 'contact:phone',

            'ticket type'                              => 'registration:ticket_type',
            'ticket type/invitee reply'                => 'registration:ticket_type',
            'ticket fee'                               => 'registration:ticket_fee',
            'ticket type fee'                          => 'registration:ticket_fee',
            'event registration date'                  => 'registration:registered_at',

            'invoice #'                                => 'transaction:external_id',
            'invoice number'                           => 'transaction:external_id',
            'transaction id'                           => 'transaction:external_id',
            'total fee incl. extra costs and guests registration fees' => 'transaction:amount',
            'transaction amount'                       => 'transaction:amount',
            'payment state'                            => 'transaction:payment_state',
            'payment type'                             => 'transaction:payment_method',
            'online/offline'                           => 'transaction:payment_channel',

            'internal notes'                           => '__note_contact__',
        ];
    }
}
