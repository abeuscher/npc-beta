<?php

namespace App\Services\Import;

class DonationFieldMapper
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
            // contact:external_id
            'contact external id'              => 'contact:external_id',
            'contact_external_id'              => 'contact:external_id',
            'user id'                          => 'contact:external_id',
            'user_id'                          => 'contact:external_id',
            'userid'                           => 'contact:external_id',
            'contact id'                       => 'contact:external_id',
            'contact_id'                       => 'contact:external_id',
            'contactid'                        => 'contact:external_id',
            'donor id'                         => 'contact:external_id',
            'donor_id'                         => 'contact:external_id',
            'donorid'                          => 'contact:external_id',
            'constituent id'                   => 'contact:external_id',
            'constituent_id'                   => 'contact:external_id',

            // contact:email
            'contact email'                    => 'contact:email',
            'contact_email'                    => 'contact:email',
            'contactemail'                     => 'contact:email',
            'email'                            => 'contact:email',
            'email address'                    => 'contact:email',
            'email_address'                    => 'contact:email',
            'emailaddress'                     => 'contact:email',
            'e-mail'                           => 'contact:email',
            'donor email'                      => 'contact:email',
            'donor_email'                      => 'contact:email',

            // contact:phone
            'contact phone'                    => 'contact:phone',
            'contact_phone'                    => 'contact:phone',
            'contactphone'                     => 'contact:phone',
            'phone'                            => 'contact:phone',
            'phone number'                     => 'contact:phone',
            'phone_number'                     => 'contact:phone',
            'phonenumber'                      => 'contact:phone',
            'mobile'                           => 'contact:phone',
            'mobile phone'                     => 'contact:phone',
            'mobilephone'                      => 'contact:phone',

            // donation:donated_at
            'donation date'                    => 'donation:donated_at',
            'donation_date'                    => 'donation:donated_at',
            'donationdate'                     => 'donation:donated_at',
            'date'                             => 'donation:donated_at',
            'gift date'                        => 'donation:donated_at',
            'gift_date'                        => 'donation:donated_at',
            'giftdate'                         => 'donation:donated_at',
            'date donated'                     => 'donation:donated_at',
            'donated at'                       => 'donation:donated_at',
            'donated_at'                       => 'donation:donated_at',
            'donatedat'                        => 'donation:donated_at',
            'donation donated at'              => 'donation:donated_at',
            'donation_donated_at'              => 'donation:donated_at',

            // donation:amount
            'amount'                           => 'donation:amount',
            'donation amount'                  => 'donation:amount',
            'donation_amount'                  => 'donation:amount',
            'donationamount'                   => 'donation:amount',
            'gift amount'                      => 'donation:amount',
            'gift_amount'                      => 'donation:amount',
            'giftamount'                       => 'donation:amount',
            'donation total'                   => 'donation:amount',

            // donation:type
            'type'                             => 'donation:type',
            'donation type'                    => 'donation:type',
            'donation_type'                    => 'donation:type',
            'donationtype'                     => 'donation:type',
            'type (one_off / recurring)'       => 'donation:type',
            'gift type'                        => 'donation:type',
            'gift_type'                        => 'donation:type',

            // donation:status
            'status'                           => 'donation:status',
            'donation status'                  => 'donation:status',
            'donation_status'                  => 'donation:status',
            'donationstatus'                   => 'donation:status',
            'gift status'                      => 'donation:status',
            'gift_status'                      => 'donation:status',

            // donation:external_id
            'external id'                      => 'donation:external_id',
            'external_id'                      => 'donation:external_id',
            'externalid'                       => 'donation:external_id',
            'donation external id'             => 'donation:external_id',
            'donation_external_id'             => 'donation:external_id',

            // donation:invoice_number
            'number'                           => 'donation:invoice_number',
            'invoice number'                   => 'donation:invoice_number',
            'invoice_number'                   => 'donation:invoice_number',
            'invoicenumber'                    => 'donation:invoice_number',
            'invoice / receipt number'         => 'donation:invoice_number',
            'receipt number'                   => 'donation:invoice_number',
            'receipt_number'                   => 'donation:invoice_number',
            'receiptnumber'                    => 'donation:invoice_number',
            'donation invoice number'          => 'donation:invoice_number',
            'donation_invoice_number'          => 'donation:invoice_number',
            'donation number'                  => 'donation:invoice_number',
            'donation_number'                  => 'donation:invoice_number',

            // donation:comment
            'comment'                          => 'donation:comment',
            'comments for payer'               => 'donation:comment',
            'comments'                         => 'donation:comment',
            'comment / notes'                  => 'donation:comment',
            'donor comment'                    => 'donation:comment',
            'donor_comment'                    => 'donation:comment',
            'donation comment'                 => 'donation:comment',
            'donation_comment'                 => 'donation:comment',
            'donation comment / notes'         => 'donation:comment',
            'message'                          => 'donation:comment',

            // transaction:amount
            'transaction amount'               => 'transaction:amount',
            'transaction_amount'               => 'transaction:amount',
            'transactionamount'                => 'transaction:amount',
            'total'                            => 'transaction:amount',
            'total amount'                     => 'transaction:amount',
            'total_amount'                     => 'transaction:amount',

            // transaction:payment_state
            'payment state'                    => 'transaction:payment_state',
            'payment_state'                    => 'transaction:payment_state',
            'paymentstate'                     => 'transaction:payment_state',
            'transaction payment state'        => 'transaction:payment_state',
            'transaction_payment_state'        => 'transaction:payment_state',
            'payment status'                   => 'transaction:payment_state',
            'payment_status'                   => 'transaction:payment_state',
            'paymentstatus'                    => 'transaction:payment_state',

            // transaction:payment_method
            'payment type'                     => 'transaction:payment_method',
            'payment_type'                     => 'transaction:payment_method',
            'paymenttype'                      => 'transaction:payment_method',
            'payment method'                   => 'transaction:payment_method',
            'payment_method'                   => 'transaction:payment_method',
            'paymentmethod'                    => 'transaction:payment_method',
            'transaction payment method'       => 'transaction:payment_method',

            // transaction:payment_channel
            'online/offline'                   => 'transaction:payment_channel',
            'payment channel'                  => 'transaction:payment_channel',
            'payment_channel'                  => 'transaction:payment_channel',
            'paymentchannel'                   => 'transaction:payment_channel',
            'payment channel (online/offline)' => 'transaction:payment_channel',
            'transaction payment channel'      => 'transaction:payment_channel',

            // transaction:occurred_at
            'paid at'                          => 'transaction:occurred_at',
            'paid_at'                          => 'transaction:occurred_at',
            'paidat'                           => 'transaction:occurred_at',
            'transaction paid at'              => 'transaction:occurred_at',
            'transaction occurred at'          => 'transaction:occurred_at',
            'transaction_occurred_at'          => 'transaction:occurred_at',
            'occurred at'                      => 'transaction:occurred_at',
            'occurred_at'                      => 'transaction:occurred_at',

            // transaction:external_id
            'payment method id'                => 'transaction:external_id',
            'payment_method_id'                => 'transaction:external_id',
            'paymentmethodid'                  => 'transaction:external_id',
            'transaction id'                   => 'transaction:external_id',
            'transaction_id'                   => 'transaction:external_id',
            'transactionid'                    => 'transaction:external_id',
            'transaction id (external)'        => 'transaction:external_id',

            // __note_contact__
            'internal notes'                   => '__note_contact__',
            'internal_notes'                   => '__note_contact__',
            'internalnotes'                    => '__note_contact__',
            'admin notes'                      => '__note_contact__',
            'admin_notes'                      => '__note_contact__',
            'adminnotes'                       => '__note_contact__',
            'staff notes'                      => '__note_contact__',
            'staff_notes'                      => '__note_contact__',
            'staffnotes'                       => '__note_contact__',
        ];
    }

    private static function wildApricotMap(): array
    {
        return [
            'user id'                          => 'contact:external_id',
            'email'                            => 'contact:email',
            'email address'                    => 'contact:email',
            'phone'                            => 'contact:phone',
            'phone number'                     => 'contact:phone',

            'donation date'                    => 'donation:donated_at',
            'amount'                           => 'donation:amount',
            'donation amount'                  => 'donation:amount',
            'number'                           => 'donation:invoice_number',
            'comment'                          => 'donation:comment',
            'comments for payer'               => 'donation:comment',

            'transaction amount'               => 'transaction:amount',
            'payment state'                    => 'transaction:payment_state',
            'payment type'                     => 'transaction:payment_method',
            'online/offline'                   => 'transaction:payment_channel',
            'payment method id'                => 'transaction:external_id',

            'internal notes'                   => '__note_contact__',
        ];
    }

    private static function bloomerangMap(): array
    {
        return [
            'user id'                          => 'contact:external_id',
            'email'                            => 'contact:email',
            'email address'                    => 'contact:email',
            'phone'                            => 'contact:phone',
            'phone number'                     => 'contact:phone',

            'donation date'                    => 'donation:donated_at',
            'amount'                           => 'donation:amount',
            'donation amount'                  => 'donation:amount',
            'number'                           => 'donation:invoice_number',
            'comment'                          => 'donation:comment',
            'comments for payer'               => 'donation:comment',

            'transaction amount'               => 'transaction:amount',
            'payment state'                    => 'transaction:payment_state',
            'payment type'                     => 'transaction:payment_method',
            'online/offline'                   => 'transaction:payment_channel',
            'payment method id'                => 'transaction:external_id',

            'internal notes'                   => '__note_contact__',
        ];
    }
}
