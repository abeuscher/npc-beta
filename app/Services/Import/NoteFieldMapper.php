<?php

namespace App\Services\Import;

class NoteFieldMapper
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
            'type'                       => 'note:type',
            'note type'                  => 'note:type',
            'note_type'                  => 'note:type',
            'notetype'                   => 'note:type',
            'activity type'              => 'note:type',
            'activity_type'              => 'note:type',
            'activitytype'               => 'note:type',
            'action type'                => 'note:type',
            'action_type'                => 'note:type',
            'actiontype'                 => 'note:type',
            'interaction type'           => 'note:type',
            'interaction_type'           => 'note:type',
            'interactiontype'            => 'note:type',
            'contact type'               => 'note:type',
            'contact_type'               => 'note:type',
            'contacttype'                => 'note:type',
            'channel'                    => 'note:type',
            'kind'                       => 'note:type',
            'category'                   => 'note:type',

            'subject'                    => 'note:subject',
            'note subject'               => 'note:subject',
            'note_subject'               => 'note:subject',
            'notesubject'                => 'note:subject',
            'title'                      => 'note:subject',
            'activity subject'           => 'note:subject',
            'activity_subject'           => 'note:subject',
            'activitysubject'            => 'note:subject',
            'action subject'             => 'note:subject',
            'action_subject'             => 'note:subject',
            'actionsubject'              => 'note:subject',
            'summary'                    => 'note:subject',
            'topic'                      => 'note:subject',

            'status'                     => 'note:status',
            'note status'                => 'note:status',
            'note_status'                => 'note:status',
            'notestatus'                 => 'note:status',
            'activity status'            => 'note:status',
            'activity_status'            => 'note:status',
            'activitystatus'             => 'note:status',
            'action status'              => 'note:status',
            'action_status'              => 'note:status',
            'actionstatus'               => 'note:status',
            'state'                      => 'note:status',

            'body'                       => 'note:body',
            'note body'                  => 'note:body',
            'note_body'                  => 'note:body',
            'notebody'                   => 'note:body',
            'notes'                      => 'note:body',
            'description'                => 'note:body',
            'details'                    => 'note:body',
            'comments'                   => 'note:body',
            'comment'                    => 'note:body',
            'action notes'               => 'note:body',
            'action_notes'               => 'note:body',
            'actionnotes'                => 'note:body',
            'contact notes'              => 'note:body',
            'contact_notes'              => 'note:body',
            'contactnotes'               => 'note:body',
            'message'                    => 'note:body',
            'content'                    => 'note:body',

            'date'                       => 'note:occurred_at',
            'occurred at'                => 'note:occurred_at',
            'occurred_at'                => 'note:occurred_at',
            'occurredat'                 => 'note:occurred_at',
            'note occurred at'           => 'note:occurred_at',
            'note_occurred_at'           => 'note:occurred_at',
            'activity date'              => 'note:occurred_at',
            'activity_date'              => 'note:occurred_at',
            'activitydate'               => 'note:occurred_at',
            'action date'                => 'note:occurred_at',
            'action_date'                => 'note:occurred_at',
            'actiondate'                 => 'note:occurred_at',
            'contact date'               => 'note:occurred_at',
            'contact_date'               => 'note:occurred_at',
            'contactdate'                => 'note:occurred_at',
            'interaction date'           => 'note:occurred_at',
            'interaction_date'           => 'note:occurred_at',
            'interactiondate'            => 'note:occurred_at',
            'note date'                  => 'note:occurred_at',
            'note_date'                  => 'note:occurred_at',
            'notedate'                   => 'note:occurred_at',

            'follow up'                  => 'note:follow_up_at',
            'follow-up'                  => 'note:follow_up_at',
            'follow_up'                  => 'note:follow_up_at',
            'followup'                   => 'note:follow_up_at',
            'follow up at'               => 'note:follow_up_at',
            'follow_up_at'               => 'note:follow_up_at',
            'followupat'                 => 'note:follow_up_at',
            'note follow-up at'          => 'note:follow_up_at',
            'note_follow_up_at'          => 'note:follow_up_at',
            'next action date'           => 'note:follow_up_at',
            'next_action_date'           => 'note:follow_up_at',
            'nextactiondate'             => 'note:follow_up_at',
            'next contact date'          => 'note:follow_up_at',
            'next_contact_date'          => 'note:follow_up_at',
            'nextcontactdate'            => 'note:follow_up_at',
            'follow up date'             => 'note:follow_up_at',
            'followup date'              => 'note:follow_up_at',

            'outcome'                    => 'note:outcome',
            'note outcome'               => 'note:outcome',
            'note_outcome'               => 'note:outcome',
            'noteoutcome'                => 'note:outcome',
            'result'                     => 'note:outcome',
            'notes outcome'              => 'note:outcome',
            'notes_outcome'              => 'note:outcome',
            'notesoutcome'               => 'note:outcome',
            'disposition'                => 'note:outcome',

            'duration'                   => 'note:duration_minutes',
            'duration minutes'           => 'note:duration_minutes',
            'duration_minutes'           => 'note:duration_minutes',
            'durationminutes'            => 'note:duration_minutes',
            'note duration (minutes)'    => 'note:duration_minutes',
            'duration (minutes)'         => 'note:duration_minutes',
            'call duration'              => 'note:duration_minutes',
            'call_duration'              => 'note:duration_minutes',
            'callduration'               => 'note:duration_minutes',
            'minutes'                    => 'note:duration_minutes',

            'external id'                => 'note:external_id',
            'external_id'                => 'note:external_id',
            'externalid'                 => 'note:external_id',
            'note external id'           => 'note:external_id',
            'note_external_id'           => 'note:external_id',
            'activity id'                => 'note:external_id',
            'activity_id'                => 'note:external_id',
            'activityid'                 => 'note:external_id',
            'action id'                  => 'note:external_id',
            'action_id'                  => 'note:external_id',
            'actionid'                   => 'note:external_id',
            'interaction id'             => 'note:external_id',
            'interaction_id'             => 'note:external_id',
            'interactionid'              => 'note:external_id',
            'note id'                    => 'note:external_id',
            'note_id'                    => 'note:external_id',
            'noteid'                     => 'note:external_id',

            'contact email'              => 'contact:email',
            'contact_email'              => 'contact:email',
            'contactemail'               => 'contact:email',
            'email'                      => 'contact:email',
            'email address'              => 'contact:email',
            'email_address'              => 'contact:email',
            'emailaddress'               => 'contact:email',
            'e-mail'                     => 'contact:email',

            'contact external id'        => 'contact:external_id',
            'contact_external_id'        => 'contact:external_id',
            'user id'                    => 'contact:external_id',
            'user_id'                    => 'contact:external_id',
            'userid'                     => 'contact:external_id',
            'constituent id'             => 'contact:external_id',
            'constituent_id'             => 'contact:external_id',
            'constituentid'              => 'contact:external_id',
            'contact id'                 => 'contact:external_id',
            'contact_id'                 => 'contact:external_id',
            'contactid'                  => 'contact:external_id',

            'contact phone'              => 'contact:phone',
            'contact_phone'              => 'contact:phone',
            'contactphone'               => 'contact:phone',
            'phone'                      => 'contact:phone',
            'phone number'               => 'contact:phone',
            'phone_number'               => 'contact:phone',
            'phonenumber'                => 'contact:phone',
        ];
    }

    private static function wildApricotMap(): array
    {
        return [
            'type'                       => 'note:type',
            'note type'                  => 'note:type',
            'activity type'              => 'note:type',
            'action type'                => 'note:type',
            'interaction type'           => 'note:type',
            'contact type'               => 'note:type',
            'channel'                    => 'note:type',

            'subject'                    => 'note:subject',
            'note subject'               => 'note:subject',
            'title'                      => 'note:subject',
            'activity subject'           => 'note:subject',
            'action subject'             => 'note:subject',

            'status'                     => 'note:status',
            'note status'                => 'note:status',
            'activity status'            => 'note:status',
            'action status'              => 'note:status',

            'body'                       => 'note:body',
            'note body'                  => 'note:body',
            'notes'                      => 'note:body',
            'description'                => 'note:body',
            'details'                    => 'note:body',
            'comments'                   => 'note:body',
            'action notes'               => 'note:body',
            'contact notes'              => 'note:body',

            'date'                       => 'note:occurred_at',
            'occurred at'                => 'note:occurred_at',
            'note occurred at'           => 'note:occurred_at',
            'activity date'              => 'note:occurred_at',
            'action date'                => 'note:occurred_at',
            'contact date'               => 'note:occurred_at',
            'interaction date'           => 'note:occurred_at',

            'follow up'                  => 'note:follow_up_at',
            'follow-up'                  => 'note:follow_up_at',
            'follow up at'               => 'note:follow_up_at',
            'note follow-up at'          => 'note:follow_up_at',
            'next action date'           => 'note:follow_up_at',
            'next contact date'          => 'note:follow_up_at',

            'outcome'                    => 'note:outcome',
            'note outcome'               => 'note:outcome',
            'result'                     => 'note:outcome',
            'notes outcome'              => 'note:outcome',

            'duration'                   => 'note:duration_minutes',
            'duration minutes'           => 'note:duration_minutes',
            'note duration (minutes)'    => 'note:duration_minutes',
            'duration (minutes)'         => 'note:duration_minutes',
            'call duration'              => 'note:duration_minutes',

            'external id'                => 'note:external_id',
            'note external id'           => 'note:external_id',
            'activity id'                => 'note:external_id',
            'action id'                  => 'note:external_id',
            'interaction id'             => 'note:external_id',

            'email'                      => 'contact:email',
            'email address'              => 'contact:email',
            'user id'                    => 'contact:external_id',
            'constituent id'             => 'contact:external_id',
            'contact id'                 => 'contact:external_id',
            'phone'                      => 'contact:phone',
            'phone number'               => 'contact:phone',
        ];
    }

    private static function bloomerangMap(): array
    {
        return [
            'type'                       => 'note:type',
            'note type'                  => 'note:type',
            'activity type'              => 'note:type',
            'action type'                => 'note:type',
            'interaction type'           => 'note:type',
            'contact type'               => 'note:type',
            'channel'                    => 'note:type',

            'subject'                    => 'note:subject',
            'note subject'               => 'note:subject',
            'title'                      => 'note:subject',
            'activity subject'           => 'note:subject',
            'action subject'             => 'note:subject',

            'status'                     => 'note:status',
            'note status'                => 'note:status',
            'activity status'            => 'note:status',
            'action status'              => 'note:status',

            'body'                       => 'note:body',
            'note body'                  => 'note:body',
            'notes'                      => 'note:body',
            'description'                => 'note:body',
            'details'                    => 'note:body',
            'comments'                   => 'note:body',
            'action notes'               => 'note:body',
            'contact notes'              => 'note:body',

            'date'                       => 'note:occurred_at',
            'occurred at'                => 'note:occurred_at',
            'note occurred at'           => 'note:occurred_at',
            'activity date'              => 'note:occurred_at',
            'action date'                => 'note:occurred_at',
            'contact date'               => 'note:occurred_at',
            'interaction date'           => 'note:occurred_at',

            'follow up'                  => 'note:follow_up_at',
            'follow-up'                  => 'note:follow_up_at',
            'follow up at'               => 'note:follow_up_at',
            'note follow-up at'          => 'note:follow_up_at',
            'next action date'           => 'note:follow_up_at',
            'next contact date'          => 'note:follow_up_at',

            'outcome'                    => 'note:outcome',
            'note outcome'               => 'note:outcome',
            'result'                     => 'note:outcome',
            'notes outcome'              => 'note:outcome',

            'duration'                   => 'note:duration_minutes',
            'duration minutes'           => 'note:duration_minutes',
            'note duration (minutes)'    => 'note:duration_minutes',
            'duration (minutes)'         => 'note:duration_minutes',
            'call duration'              => 'note:duration_minutes',

            'external id'                => 'note:external_id',
            'note external id'           => 'note:external_id',
            'activity id'                => 'note:external_id',
            'action id'                  => 'note:external_id',
            'interaction id'             => 'note:external_id',

            'email'                      => 'contact:email',
            'email address'              => 'contact:email',
            'user id'                    => 'contact:external_id',
            'constituent id'             => 'contact:external_id',
            'contact id'                 => 'contact:external_id',
            'phone'                      => 'contact:phone',
            'phone number'               => 'contact:phone',
        ];
    }
}
