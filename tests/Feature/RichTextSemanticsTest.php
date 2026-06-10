<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use App\Support\RichTextSemantics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Bullet <ol> → <ul> (item 4) ──────────────────────────────────────────────

it('rewrites a Quill bullet <ol> into a real <ul> and drops data-list', function () {
    $out = RichTextSemantics::normalize(
        '<ol><li data-list="bullet">One</li><li data-list="bullet">Two</li></ol>'
    );

    expect($out)
        ->toContain('<ul>')
        ->toContain('<li>One</li>')
        ->toContain('<li>Two</li>')
        ->not->toContain('<ol')
        ->not->toContain('data-list');
});

it('leaves a genuine ordered list as <ol>', function () {
    $out = RichTextSemantics::normalize(
        '<ol><li data-list="ordered">One</li><li data-list="ordered">Two</li></ol>'
    );

    expect($out)->toContain('<ol>')->not->toContain('<ul');
});

it('leaves a plain <ol> with no data-list untouched', function () {
    expect(RichTextSemantics::normalize('<ol><li>One</li></ol>'))
        ->toContain('<ol>')->not->toContain('<ul');
});

it('leaves a mixed bullet/ordered list as <ol> (ambiguous, not guessed)', function () {
    $out = RichTextSemantics::normalize(
        '<ol><li data-list="bullet">A</li><li data-list="ordered">B</li></ol>'
    );

    expect($out)->toContain('<ol')->not->toContain('<ul');
});

it('converts nested bullet lists at every level', function () {
    $out = RichTextSemantics::normalize(
        '<ol><li data-list="bullet">A<ol><li data-list="bullet">B</li></ol></li></ol>'
    );

    expect($out)
        ->not->toContain('<ol')
        ->not->toContain('data-list')
        ->and(substr_count($out, '<ul>'))->toBe(2);
});

// ── Heading-wrapping bold (item 5) ───────────────────────────────────────────

it('unwraps a <strong> that wraps an entire heading', function () {
    expect(RichTextSemantics::normalize('<h2><strong>Title</strong></h2>'))
        ->toBe('<h2>Title</h2>');
});

it('unwraps a <b> that wraps an entire heading', function () {
    expect(RichTextSemantics::normalize('<h3><b>Title</b></h3>'))
        ->toBe('<h3>Title</h3>');
});

it('keeps partial (mid-heading) bold untouched', function () {
    $in = '<h2>Intro <strong>bold</strong> tail</h2>';
    expect(RichTextSemantics::normalize($in))->toBe($in);
});

it('keeps bold inside a paragraph untouched (headings only)', function () {
    $in = '<p><strong>Lead</strong></p>';
    expect(RichTextSemantics::normalize($in))->toBe($in);
});

it('returns list/heading-free content unchanged', function () {
    $in = '<p>Just a paragraph with <em>emphasis</em>.</p>';
    expect(RichTextSemantics::normalize($in))->toBe($in);
});

// ── Render path: WidgetRenderer applies the transform ────────────────────────

it('normalises richtext widget output on the render path', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $page      = Page::factory()->create();
    $textBlock = WidgetType::where('handle', 'text_block')->firstOrFail();

    $widget = PageWidget::create([
        'owner_type'        => $page->getMorphClass(),
        'owner_id'          => $page->getKey(),
        'widget_type_id'    => $textBlock->id,
        'label'             => 'Block',
        'config'            => [
            'content'        => '<h2><strong>Heading</strong></h2><ol><li data-list="bullet">Item</li></ol>',
            'vertical_align' => 'middle',
        ],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $html = WidgetRenderer::render($widget->fresh())['html'] ?? '';

    expect($html)
        ->toContain('<ul>')
        ->toContain('<h2>Heading</h2>')
        ->not->toContain('<ol')
        ->not->toContain('data-list="bullet"');
});
