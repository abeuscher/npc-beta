<?php

use App\Services\Import\NoteFieldMapper;

it('generic preset maps every existing floor alias to its destination', function () {
    $mapper = new NoteFieldMapper();

    $expected = [
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

    foreach ($expected as $header => $destination) {
        expect($mapper->map($header, 'generic'))->toBe($destination, "{$header} should map to {$destination}");
    }
});

it('unknown column maps to null', function () {
    $mapper = new NoteFieldMapper();
    expect($mapper->map('unknown_column_xyz', 'generic'))->toBeNull();
});

it('mapper normalises column names by trimming whitespace and lowercasing', function () {
    $mapper = new NoteFieldMapper();
    expect($mapper->map('  NOTE BODY  ', 'generic'))->toBe('note:body');
    expect($mapper->map('  Email Address  ', 'generic'))->toBe('contact:email');
});

it('null preset falls back to generic', function () {
    $mapper = new NoteFieldMapper();
    expect($mapper->map('note body'))->toBe('note:body');
    expect($mapper->map('note body', null))->toBe('note:body');
});

it('presets() includes generic, wild_apricot, and bloomerang', function () {
    expect(NoteFieldMapper::presets())
        ->toContain('generic')
        ->toContain('wild_apricot')
        ->toContain('bloomerang');
});

it('presetMap returns an array with string keys and string values', function () {
    $map = NoteFieldMapper::presetMap('generic');
    expect($map)->toBeArray()->not->toBeEmpty();

    foreach ($map as $source => $dest) {
        expect($source)->toBeString();
        expect($dest)->toBeString();
    }
});

it('wild_apricot and bloomerang presets preserve the floor', function () {
    $mapper = new NoteFieldMapper();

    $floorSamples = [
        'type'                       => 'note:type',
        'note type'                  => 'note:type',
        'channel'                    => 'note:type',
        'subject'                    => 'note:subject',
        'title'                      => 'note:subject',
        'status'                     => 'note:status',
        'body'                       => 'note:body',
        'notes'                      => 'note:body',
        'description'                => 'note:body',
        'date'                       => 'note:occurred_at',
        'occurred at'                => 'note:occurred_at',
        'follow up'                  => 'note:follow_up_at',
        'next action date'           => 'note:follow_up_at',
        'outcome'                    => 'note:outcome',
        'duration'                   => 'note:duration_minutes',
        'duration (minutes)'         => 'note:duration_minutes',
        'external id'                => 'note:external_id',
        'activity id'                => 'note:external_id',
        'email'                      => 'contact:email',
        'user id'                    => 'contact:external_id',
        'phone'                      => 'contact:phone',
    ];

    foreach ($floorSamples as $header => $destination) {
        expect($mapper->map($header, 'wild_apricot'))->toBe($destination);
        expect($mapper->map($header, 'bloomerang'))->toBe($destination);
    }
});

it('generic preset recognises new note-type aliases', function () {
    $mapper = new NoteFieldMapper();

    foreach (['Type', 'Note Type', 'Note_Type', 'NoteType', 'Activity Type', 'ActivityType', 'Channel', 'Kind', 'Category'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('note:type');
    }
});

it('generic preset recognises new note-subject aliases', function () {
    $mapper = new NoteFieldMapper();

    foreach (['Subject', 'Note Subject', 'NoteSubject', 'Title', 'Activity Subject', 'Summary', 'Topic'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('note:subject');
    }
});

it('generic preset recognises new note-body aliases', function () {
    $mapper = new NoteFieldMapper();

    foreach (['Body', 'Note Body', 'NoteBody', 'Notes', 'Description', 'Details', 'Comments', 'Comment', 'Message', 'Content'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('note:body');
    }
});

it('generic preset recognises new note-occurred-at aliases', function () {
    $mapper = new NoteFieldMapper();

    foreach (['Date', 'Occurred At', 'Occurred_At', 'OccurredAt', 'Activity Date', 'ActivityDate', 'Note Date', 'NoteDate'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('note:occurred_at');
    }
});

it('generic preset recognises new follow-up aliases', function () {
    $mapper = new NoteFieldMapper();

    foreach (['Follow Up', 'Follow-Up', 'Follow_Up', 'FollowUp', 'Follow Up At', 'FollowUpAt', 'Next Action Date', 'Next Contact Date', 'Follow Up Date'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('note:follow_up_at');
    }
});

it('generic preset recognises new outcome aliases', function () {
    $mapper = new NoteFieldMapper();

    foreach (['Outcome', 'Note Outcome', 'NoteOutcome', 'Result', 'Disposition'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('note:outcome');
    }
});

it('generic preset recognises new duration aliases', function () {
    $mapper = new NoteFieldMapper();

    foreach (['Duration', 'Duration Minutes', 'Duration_Minutes', 'DurationMinutes', 'Call Duration', 'CallDuration', 'Minutes'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('note:duration_minutes');
    }
});

it('generic preset recognises new note-external-id aliases', function () {
    $mapper = new NoteFieldMapper();

    foreach (['External ID', 'External_ID', 'ExternalID', 'Activity ID', 'ActivityID', 'Action ID', 'Interaction ID', 'Note ID', 'NoteID'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('note:external_id');
    }
});

it('generic preset recognises canonical entity-prefixed note headers', function () {
    $mapper = new NoteFieldMapper();

    $expected = [
        'Note Type'              => 'note:type',
        'Note Subject'           => 'note:subject',
        'Note Status'            => 'note:status',
        'Note Body'              => 'note:body',
        'Note Occurred At'       => 'note:occurred_at',
        'Note Follow-up At'      => 'note:follow_up_at',
        'Note Outcome'           => 'note:outcome',
        'Note Duration (minutes)' => 'note:duration_minutes',
        'Note External ID'       => 'note:external_id',
        'Contact Email'          => 'contact:email',
        'Contact Phone'          => 'contact:phone',
        'Contact External ID'    => 'contact:external_id',
    ];

    foreach ($expected as $header => $destination) {
        expect($mapper->map($header, 'generic'))->toBe($destination, "{$header} should map to {$destination}");
    }
});
