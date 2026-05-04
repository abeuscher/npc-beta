<?php

namespace App\Services\Import;

class MembershipFieldMapper
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
            'contact external id'                  => 'contact:external_id',
            'contact_external_id'                  => 'contact:external_id',
            'user id'                              => 'contact:external_id',
            'user_id'                              => 'contact:external_id',
            'userid'                               => 'contact:external_id',
            'contact id'                           => 'contact:external_id',
            'contact_id'                           => 'contact:external_id',
            'contactid'                            => 'contact:external_id',
            'member id'                            => 'contact:external_id',
            'member_id'                            => 'contact:external_id',
            'memberid'                             => 'contact:external_id',

            // contact:email
            'contact email'                        => 'contact:email',
            'contact_email'                        => 'contact:email',
            'contactemail'                         => 'contact:email',
            'email'                                => 'contact:email',
            'email address'                        => 'contact:email',
            'email_address'                        => 'contact:email',
            'emailaddress'                         => 'contact:email',
            'e-mail'                               => 'contact:email',
            'member email'                         => 'contact:email',
            'member_email'                         => 'contact:email',

            // contact:phone
            'contact phone'                        => 'contact:phone',
            'contact_phone'                        => 'contact:phone',
            'contactphone'                         => 'contact:phone',
            'phone'                                => 'contact:phone',
            'phone number'                         => 'contact:phone',
            'phone_number'                         => 'contact:phone',
            'phonenumber'                          => 'contact:phone',
            'mobile'                               => 'contact:phone',
            'mobile phone'                         => 'contact:phone',
            'mobilephone'                          => 'contact:phone',

            // membership:tier
            'membership tier'                      => 'membership:tier',
            'membership_tier'                      => 'membership:tier',
            'membershiptier'                       => 'membership:tier',
            'membership level'                     => 'membership:tier',
            'membership_level'                     => 'membership:tier',
            'membershiplevel'                      => 'membership:tier',
            'membership level / tier'              => 'membership:tier',
            'membership type'                      => 'membership:tier',
            'membership_type'                      => 'membership:tier',
            'membershiptype'                       => 'membership:tier',
            'tier'                                 => 'membership:tier',
            'level'                                => 'membership:tier',
            'plan'                                 => 'membership:tier',

            // membership:status
            'membership status'                    => 'membership:status',
            'membership_status'                    => 'membership:status',
            'membershipstatus'                     => 'membership:status',
            'status'                               => 'membership:status',
            'state'                                => 'membership:status',

            // membership:starts_on
            'membership starts on'                 => 'membership:starts_on',
            'membership_starts_on'                 => 'membership:starts_on',
            'membership member since'              => 'membership:starts_on',
            'member since'                         => 'membership:starts_on',
            'member_since'                         => 'membership:starts_on',
            'membersince'                          => 'membership:starts_on',
            'start date'                           => 'membership:starts_on',
            'start_date'                           => 'membership:starts_on',
            'startdate'                            => 'membership:starts_on',
            'starts on'                            => 'membership:starts_on',
            'starts_on'                            => 'membership:starts_on',
            'startson'                             => 'membership:starts_on',
            'joined'                               => 'membership:starts_on',
            'join date'                            => 'membership:starts_on',
            'join_date'                            => 'membership:starts_on',
            'joindate'                             => 'membership:starts_on',

            // membership:expires_on
            'membership expires on'                => 'membership:expires_on',
            'membership_expires_on'                => 'membership:expires_on',
            'membership renewal due'               => 'membership:expires_on',
            'renewal due'                          => 'membership:expires_on',
            'renewal_due'                          => 'membership:expires_on',
            'renewaldue'                           => 'membership:expires_on',
            'renewal due / expires on'             => 'membership:expires_on',
            'expires on'                           => 'membership:expires_on',
            'expires_on'                           => 'membership:expires_on',
            'expireson'                            => 'membership:expires_on',
            'expiration date'                      => 'membership:expires_on',
            'expiration_date'                      => 'membership:expires_on',
            'expirationdate'                       => 'membership:expires_on',
            'expiry date'                          => 'membership:expires_on',
            'expiry_date'                          => 'membership:expires_on',
            'expirydate'                           => 'membership:expires_on',
            'end date'                             => 'membership:expires_on',
            'end_date'                             => 'membership:expires_on',
            'enddate'                              => 'membership:expires_on',

            // membership:amount_paid
            'membership amount paid'               => 'membership:amount_paid',
            'membership_amount_paid'               => 'membership:amount_paid',
            'balance'                              => 'membership:amount_paid',
            'amount paid'                          => 'membership:amount_paid',
            'amount_paid'                          => 'membership:amount_paid',
            'amountpaid'                           => 'membership:amount_paid',
            'paid'                                 => 'membership:amount_paid',
            'dues paid'                            => 'membership:amount_paid',
            'dues_paid'                            => 'membership:amount_paid',
            'duespaid'                             => 'membership:amount_paid',

            // membership:notes
            'membership notes'                     => 'membership:notes',
            'membership_notes'                     => 'membership:notes',
            'membershipnotes'                      => 'membership:notes',
            'notes'                                => 'membership:notes',
            'note'                                 => 'membership:notes',

            // membership:external_id
            'membership external id'               => 'membership:external_id',
            'membership_external_id'               => 'membership:external_id',
            'member bundle id or email'            => 'membership:external_id',
            'membership id'                        => 'membership:external_id',
            'membership_id'                        => 'membership:external_id',
            'membershipid'                         => 'membership:external_id',
            'external id'                          => 'membership:external_id',
            'external_id'                          => 'membership:external_id',
            'externalid'                           => 'membership:external_id',
        ];
    }

    private static function wildApricotMap(): array
    {
        return [
            'user id'                              => 'contact:external_id',
            'email'                                => 'contact:email',
            'email address'                        => 'contact:email',
            'phone'                                => 'contact:phone',
            'phone number'                         => 'contact:phone',

            'membership level'                     => 'membership:tier',
            'membership status'                    => 'membership:status',
            'member since'                         => 'membership:starts_on',
            'renewal due'                          => 'membership:expires_on',
            'balance'                              => 'membership:amount_paid',
            'notes'                                => 'membership:notes',
            'member bundle id or email'            => 'membership:external_id',
        ];
    }

    private static function bloomerangMap(): array
    {
        return [
            'user id'                              => 'contact:external_id',
            'email'                                => 'contact:email',
            'email address'                        => 'contact:email',
            'phone'                                => 'contact:phone',
            'phone number'                         => 'contact:phone',

            'membership level'                     => 'membership:tier',
            'membership status'                    => 'membership:status',
            'member since'                         => 'membership:starts_on',
            'renewal due'                          => 'membership:expires_on',
            'balance'                              => 'membership:amount_paid',
            'notes'                                => 'membership:notes',
            'member bundle id or email'            => 'membership:external_id',
        ];
    }
}
