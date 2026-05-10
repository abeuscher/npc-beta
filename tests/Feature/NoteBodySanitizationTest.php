<?php

use App\Models\Contact;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sanitises Note.body on save', function () {
    $contact = Contact::factory()->create();
    $author  = User::factory()->create();

    $note = Note::create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'author_id'    => $author->id,
        'type'         => 'note',
        'status'       => 'completed',
        'body'         => '<p>hello</p><script>alert(1)</script><a href="javascript:alert(2)">x</a>',
        'occurred_at'  => now(),
    ]);

    expect($note->fresh()->body)->toBe('<p>hello</p><a>x</a>');
});

it('sanitises Note.body on update', function () {
    $contact = Contact::factory()->create();
    $note    = Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
    ]);

    $note->update(['body' => '<p onclick="alert(1)" id="x">edit</p>']);

    expect($note->fresh()->body)->toBe('<p>edit</p>');
});
