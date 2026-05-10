<?php

use App\Support\HtmlSanitizer;

// ─── Round-trip-clean: every Quill default-toolbar shape ──────────────────────

it('paragraph round-trips', function () {
    expect(HtmlSanitizer::sanitize('<p>x</p>'))->toBe('<p>x</p>');
});

it('br round-trips', function () {
    expect(HtmlSanitizer::sanitize('<br>'))->toBe('<br>');
});

it('bold round-trips', function () {
    expect(HtmlSanitizer::sanitize('<strong>x</strong>'))->toBe('<strong>x</strong>');
});

it('italic round-trips', function () {
    expect(HtmlSanitizer::sanitize('<em>x</em>'))->toBe('<em>x</em>');
});

it('underline round-trips', function () {
    expect(HtmlSanitizer::sanitize('<u>x</u>'))->toBe('<u>x</u>');
});

it('strike round-trips', function () {
    expect(HtmlSanitizer::sanitize('<s>x</s>'))->toBe('<s>x</s>');
});

it('headings round-trip', function () {
    foreach (range(1, 6) as $level) {
        $tag = "h{$level}";
        expect(HtmlSanitizer::sanitize("<{$tag}>x</{$tag}>"))->toBe("<{$tag}>x</{$tag}>");
    }
});

it('ordered list round-trips', function () {
    expect(HtmlSanitizer::sanitize('<ol><li>a</li><li>b</li></ol>'))
        ->toBe('<ol><li>a</li><li>b</li></ol>');
});

it('unordered list round-trips', function () {
    expect(HtmlSanitizer::sanitize('<ul><li>a</li></ul>'))
        ->toBe('<ul><li>a</li></ul>');
});

it('blockquote round-trips', function () {
    expect(HtmlSanitizer::sanitize('<blockquote>q</blockquote>'))
        ->toBe('<blockquote>q</blockquote>');
});

it('inline code round-trips', function () {
    expect(HtmlSanitizer::sanitize('<code>x</code>'))->toBe('<code>x</code>');
});

it('code block round-trips', function () {
    expect(HtmlSanitizer::sanitize('<pre><code>x</code></pre>'))
        ->toBe('<pre><code>x</code></pre>');
});

it('http link round-trips', function () {
    expect(HtmlSanitizer::sanitize('<a href="https://example.com">x</a>'))
        ->toBe('<a href="https://example.com">x</a>');
});

it('mailto link round-trips', function () {
    expect(HtmlSanitizer::sanitize('<a href="mailto:a@b.com">x</a>'))
        ->toBe('<a href="mailto:a@b.com">x</a>');
});

it('relative path link round-trips', function () {
    expect(HtmlSanitizer::sanitize('<a href="/about">x</a>'))
        ->toBe('<a href="/about">x</a>');
});

it('relative anchor link round-trips', function () {
    expect(HtmlSanitizer::sanitize('<a href="#section">x</a>'))
        ->toBe('<a href="#section">x</a>');
});

it('link title attribute round-trips', function () {
    expect(HtmlSanitizer::sanitize('<a href="/x" title="t">x</a>'))
        ->toBe('<a href="/x" title="t">x</a>');
});

it('ql-align-center round-trips', function () {
    expect(HtmlSanitizer::sanitize('<p class="ql-align-center">x</p>'))
        ->toBe('<p class="ql-align-center">x</p>');
});

it('ql-align variants round-trip', function () {
    expect(HtmlSanitizer::sanitize('<p class="ql-align-right">x</p>'))
        ->toBe('<p class="ql-align-right">x</p>');
    expect(HtmlSanitizer::sanitize('<p class="ql-align-justify">x</p>'))
        ->toBe('<p class="ql-align-justify">x</p>');
});

it('ql-indent variants round-trip', function () {
    foreach (range(1, 9) as $n) {
        expect(HtmlSanitizer::sanitize("<p class=\"ql-indent-{$n}\">x</p>"))
            ->toBe("<p class=\"ql-indent-{$n}\">x</p>");
    }
});

it('ql-direction-rtl round-trips', function () {
    expect(HtmlSanitizer::sanitize('<p class="ql-direction-rtl">x</p>'))
        ->toBe('<p class="ql-direction-rtl">x</p>');
});

it('ql-size variants round-trip', function () {
    expect(HtmlSanitizer::sanitize('<span class="ql-size-large">x</span>'))
        ->toBe('<span class="ql-size-large">x</span>');
    expect(HtmlSanitizer::sanitize('<span class="ql-size-small">x</span>'))
        ->toBe('<span class="ql-size-small">x</span>');
});

it('ql-font variants round-trip', function () {
    expect(HtmlSanitizer::sanitize('<span class="ql-font-serif">x</span>'))
        ->toBe('<span class="ql-font-serif">x</span>');
    expect(HtmlSanitizer::sanitize('<span class="ql-font-monospace">x</span>'))
        ->toBe('<span class="ql-font-monospace">x</span>');
});

it('heroicon round-trips with data attr and inline svg', function () {
    $input = '<span class="ql-heroicon" data-heroicon="check">'
        . '<span class="ql-heroicon__svg" aria-hidden="true">'
        . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>'
        . '</svg>'
        . '</span>'
        . '</span>';

    // DOMDocument lower-cases attribute names (viewBox → viewbox) and expands
    // self-closing void elements (<path/> → <path></path>).
    $expected = '<span class="ql-heroicon" data-heroicon="check">'
        . '<span class="ql-heroicon__svg" aria-hidden="true">'
        . '<svg xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>'
        . '</svg>'
        . '</span>'
        . '</span>';

    expect(HtmlSanitizer::sanitize($input))->toBe($expected);
});

it('image with relative storage src round-trips', function () {
    expect(HtmlSanitizer::sanitize('<img src="/storage/1/img.jpg" alt="hi">'))
        ->toBe('<img src="/storage/1/img.jpg" alt="hi">');
});

it('image with http src round-trips', function () {
    expect(HtmlSanitizer::sanitize('<img src="https://cdn.example.com/x.png" alt="">'))
        ->toBe('<img src="https://cdn.example.com/x.png" alt="">');
});

it('image with width height style round-trips', function () {
    $input = '<img src="/storage/x.jpg" alt="" width="200" height="100" style="width: 50%;">';
    expect(HtmlSanitizer::sanitize($input))
        ->toBe('<img src="/storage/x.jpg" alt="" width="200" height="100" style="width: 50%;">');
});

it('nested inline marks round-trip', function () {
    expect(HtmlSanitizer::sanitize('<p><strong>bold</strong> and <em>italic</em></p>'))
        ->toBe('<p><strong>bold</strong> and <em>italic</em></p>');
});

it('utf-8 text round-trips', function () {
    expect(HtmlSanitizer::sanitize('<p class="ql-align-center">áéíóú 中文 emoji 🎉</p>'))
        ->toBe('<p class="ql-align-center">áéíóú 中文 emoji 🎉</p>');
});

it('sanitiser is idempotent on already-clean output', function () {
    $clean = '<p class="ql-align-center"><strong>x</strong></p>';
    $once  = HtmlSanitizer::sanitize($clean);
    $twice = HtmlSanitizer::sanitize($once);

    expect($once)->toBe($clean);
    expect($twice)->toBe($once);
});

it('empty string round-trips', function () {
    expect(HtmlSanitizer::sanitize(''))->toBe('');
});

// ─── Strip-disallowed: tags / attributes / class tokens ───────────────────────

it('script tag is dropped with its contents', function () {
    expect(HtmlSanitizer::sanitize('<script>alert(1)</script>'))->toBe('');
});

it('style tag is dropped with its contents', function () {
    expect(HtmlSanitizer::sanitize('<style>body{color:red}</style>'))->toBe('');
});

it('iframe is dropped', function () {
    expect(HtmlSanitizer::sanitize('<iframe src="https://evil.com"></iframe>'))->toBe('');
});

it('object is dropped', function () {
    expect(HtmlSanitizer::sanitize('<object data="evil.swf"></object>'))->toBe('');
});

it('embed is dropped', function () {
    expect(HtmlSanitizer::sanitize('<embed src="evil.swf">'))->toBe('');
});

it('form elements are stripped', function () {
    expect(HtmlSanitizer::sanitize('<form action="x"><input></form>'))->toBe('');
});

it('comments are dropped', function () {
    expect(HtmlSanitizer::sanitize('<!-- comment -->'))->toBe('');
});

it('javascript: href is stripped, anchor tag retained', function () {
    expect(HtmlSanitizer::sanitize('<a href="javascript:alert(1)">x</a>'))
        ->toBe('<a>x</a>');
});

it('vbscript: href is stripped', function () {
    expect(HtmlSanitizer::sanitize('<a href="vbscript:alert(1)">x</a>'))
        ->toBe('<a>x</a>');
});

it('data: href is stripped', function () {
    expect(HtmlSanitizer::sanitize('<a href="data:text/html;base64,abc">x</a>'))
        ->toBe('<a>x</a>');
});

it('tel: href is stripped (not in allow-list)', function () {
    expect(HtmlSanitizer::sanitize('<a href="tel:555-1234">x</a>'))
        ->toBe('<a>x</a>');
});

it('on-event handlers are stripped from allowed elements', function () {
    expect(HtmlSanitizer::sanitize('<p onclick="alert(1)">x</p>'))->toBe('<p>x</p>');
    expect(HtmlSanitizer::sanitize('<a href="/x" onmouseover="alert(1)">x</a>'))
        ->toBe('<a href="/x">x</a>');
});

it('style attribute is stripped from non-img elements', function () {
    expect(HtmlSanitizer::sanitize('<p style="background:url(javascript:alert(1))">x</p>'))
        ->toBe('<p>x</p>');
    expect(HtmlSanitizer::sanitize('<span style="color:red">x</span>'))
        ->toBe('<span>x</span>');
});

it('id and name attributes are stripped', function () {
    expect(HtmlSanitizer::sanitize('<p id="hijack" name="x">y</p>'))->toBe('<p>y</p>');
});

it('class attribute preserves arbitrary tokens', function () {
    expect(HtmlSanitizer::sanitize('<p class="card meta ql-align-center">x</p>'))
        ->toBe('<p class="card meta ql-align-center">x</p>');
});

it('empty class attribute is dropped', function () {
    expect(HtmlSanitizer::sanitize('<p class="   ">x</p>'))->toBe('<p>x</p>');
});

it('class whitespace is collapsed to single spaces', function () {
    expect(HtmlSanitizer::sanitize('<p class="  card    meta  ">x</p>'))
        ->toBe('<p class="card meta">x</p>');
});

it('img with data: src strips src, retains tag', function () {
    expect(HtmlSanitizer::sanitize('<img src="data:image/png;base64,abc" alt="hi">'))
        ->toBe('<img alt="hi">');
});

it('img with javascript: src strips src', function () {
    expect(HtmlSanitizer::sanitize('<img src="javascript:alert(1)" alt="hi">'))
        ->toBe('<img alt="hi">');
});

it('img onerror handler is stripped', function () {
    expect(HtmlSanitizer::sanitize('<img src="/x.jpg" onerror="alert(1)">'))
        ->toBe('<img src="/x.jpg">');
});

it('bare svg outside heroicon span is stripped', function () {
    expect(HtmlSanitizer::sanitize('<svg onload="alert(1)"><path d="M0 0"/></svg>'))
        ->toBe('');
});

it('div tag round-trips (structural HTML allowed for widget templates)', function () {
    expect(HtmlSanitizer::sanitize('<div>hello</div>'))->toBe('<div>hello</div>');
});

it('article and section round-trip', function () {
    expect(HtmlSanitizer::sanitize('<article class="card"><section>x</section></article>'))
        ->toBe('<article class="card"><section>x</section></article>');
});

it('font tag drops keeping inner text', function () {
    expect(HtmlSanitizer::sanitize('<font color="red">x</font>'))->toBe('x');
});

it('marquee drops keeping inner text', function () {
    expect(HtmlSanitizer::sanitize('<marquee>scroll</marquee>'))->toBe('scroll');
});

it('unknown attribute on allowed tag is stripped', function () {
    expect(HtmlSanitizer::sanitize('<a href="/x" target="_blank">x</a>'))
        ->toBe('<a href="/x">x</a>');
});

// ─── XSS payload neutralisation (representative OWASP cheat-sheet samples) ───

it('xss img onerror payload is neutralised', function () {
    $out = HtmlSanitizer::sanitize('<img src=x onerror=alert(1)>');
    expect($out)->not->toContain('onerror')
        ->and($out)->not->toContain('alert');
});

it('xss svg onload payload is neutralised', function () {
    $out = HtmlSanitizer::sanitize('<svg/onload=alert(1)>');
    expect($out)->toBe('');
});

it('xss tab-encoded javascript href is neutralised', function () {
    $out = HtmlSanitizer::sanitize('<a href="java&#x09;script:alert(1)">x</a>');
    expect($out)->toBe('<a>x</a>');
});

it('xss newline-encoded javascript href is neutralised', function () {
    $out = HtmlSanitizer::sanitize('<a href="java&#x0A;script:alert(1)">x</a>');
    expect($out)->toBe('<a>x</a>');
});

it('xss input autofocus onfocus payload is neutralised', function () {
    $out = HtmlSanitizer::sanitize('<input autofocus onfocus="alert(1)">');
    expect($out)->toBe('');
});

it('xss details ontoggle payload is neutralised (handler stripped, tag retained)', function () {
    $out = HtmlSanitizer::sanitize('<details open ontoggle="alert(1)">x</details>');
    expect($out)->not->toContain('ontoggle')
        ->and($out)->not->toContain('alert');
});

it('xss vbscript href is neutralised', function () {
    expect(HtmlSanitizer::sanitize('<a href="vbscript:alert(1)">x</a>'))
        ->toBe('<a>x</a>');
});

it('xss uppercase javascript scheme is neutralised', function () {
    expect(HtmlSanitizer::sanitize('<a href="JavaScript:alert(1)">x</a>'))
        ->toBe('<a>x</a>');
});

it('xss noscript fallback is neutralised', function () {
    expect(HtmlSanitizer::sanitize('<noscript><img src=x onerror=alert(1)></noscript>'))
        ->toBe('');
});

it('xss srcset attribute on img is stripped (not in attr allow-list)', function () {
    $out = HtmlSanitizer::sanitize('<img src="/x.jpg" srcset="javascript:alert(1) 1x">');
    expect($out)->toBe('<img src="/x.jpg">');
});

it('xss formaction on button is neutralised (button not in allow-list)', function () {
    expect(HtmlSanitizer::sanitize('<button formaction="javascript:alert(1)">x</button>'))
        ->toBe('x');
});

it('entity-encoded script text passes through as text', function () {
    $input    = 'Hello &lt;script&gt;alert(1)&lt;/script&gt; world';
    $expected = 'Hello &lt;script&gt;alert(1)&lt;/script&gt; world';
    expect(HtmlSanitizer::sanitize($input))->toBe($expected);
});

it('script tag inside allowed wrapper is dropped, sibling text preserved', function () {
    expect(HtmlSanitizer::sanitize('<div><script>alert(1)</script>after</div>'))
        ->toBe('<div>after</div>');
});

it('multiple xss vectors in one document are all neutralised', function () {
    $input = '<p onclick="alert(1)">'
        . '<a href="javascript:alert(2)">link</a> '
        . '<img src="x" onerror="alert(3)">'
        . '<script>alert(4)</script>'
        . '</p>';

    $out = HtmlSanitizer::sanitize($input);
    expect($out)->not->toContain('alert')
        ->and($out)->not->toContain('onclick')
        ->and($out)->not->toContain('onerror')
        ->and($out)->not->toContain('javascript')
        ->and($out)->not->toContain('script');
});
