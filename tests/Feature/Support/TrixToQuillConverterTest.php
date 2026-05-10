<?php

use App\Support\TrixToQuillConverter;

it('converts top-level div blocks to p blocks', function () {
    expect(TrixToQuillConverter::convert('<div>Hello world</div>'))
        ->toBe('<p>Hello world</p>');
});

it('converts multiple consecutive div blocks', function () {
    expect(TrixToQuillConverter::convert('<div>Para 1</div><div>Para 2</div>'))
        ->toBe('<p>Para 1</p><p>Para 2</p>');
});

it('preserves inline marks across div conversion', function () {
    expect(TrixToQuillConverter::convert('<div><strong>bold</strong> normal <em>italic</em></div>'))
        ->toBe('<p><strong>bold</strong> normal <em>italic</em></p>');
});

it('unwraps nested div inside blockquote', function () {
    expect(TrixToQuillConverter::convert('<blockquote><div>quoted</div></blockquote>'))
        ->toBe('<blockquote>quoted</blockquote>');
});

it('unwraps div inside list items', function () {
    expect(TrixToQuillConverter::convert('<ul><li><div>item 1</div></li><li><div>item 2</div></li></ul>'))
        ->toBe('<ul><li>item 1</li><li>item 2</li></ul>');
});

it('drops trix figure attachments', function () {
    $input = '<div>before</div><figure data-trix-attachment="{}"><img src="x.jpg"></figure><div>after</div>';
    expect(TrixToQuillConverter::convert($input))->toBe('<p>before</p><p>after</p>');
});

it('preserves div class attribute when converting to p', function () {
    expect(TrixToQuillConverter::convert('<div class="custom">x</div>'))
        ->toBe('<p class="custom">x</p>');
});

it('is idempotent on already-quill markup (fast path)', function () {
    $quill = '<p>already</p><p>converted</p>';
    expect(TrixToQuillConverter::convert($quill))->toBe($quill);
});

it('is idempotent on second pass over converted markup', function () {
    $trix       = '<div>Hello</div>';
    $firstPass  = TrixToQuillConverter::convert($trix);
    $secondPass = TrixToQuillConverter::convert($firstPass);

    expect($firstPass)->toBe('<p>Hello</p>');
    expect($secondPass)->toBe($firstPass);
});

it('passes empty string through unchanged', function () {
    expect(TrixToQuillConverter::convert(''))->toBe('');
});

it('passes plain text through unchanged', function () {
    expect(TrixToQuillConverter::convert('Plain text without HTML'))
        ->toBe('Plain text without HTML');
});

it('handles links and lists in trix shape', function () {
    $trix = '<div>See <a href="https://example.com">link</a></div><ul><li><div>one</div></li></ul>';
    expect(TrixToQuillConverter::convert($trix))
        ->toBe('<p>See <a href="https://example.com">link</a></p><ul><li>one</li></ul>');
});
