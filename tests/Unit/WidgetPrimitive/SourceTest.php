<?php

use App\WidgetPrimitive\Source;

it('exposes the six well-known source constants', function () {
    expect(Source::HUMAN)->toBe('human')
        ->and(Source::DEMO)->toBe('demo')
        ->and(Source::IMPORT)->toBe('import')
        ->and(Source::GOOGLE_DOCS)->toBe('google_docs')
        ->and(Source::LLM_SYNTHESIS)->toBe('llm_synthesis')
        ->and(Source::STRIPE_WEBHOOK)->toBe('stripe_webhook');
});

it('enumerates every declared constant in KNOWN', function () {
    expect(Source::KNOWN)->toBe([
        Source::HUMAN,
        Source::DEMO,
        Source::IMPORT,
        Source::GOOGLE_DOCS,
        Source::LLM_SYNTHESIS,
        Source::STRIPE_WEBHOOK,
    ]);
});

it('accepts every declared source as known', function () {
    foreach (Source::KNOWN as $source) {
        expect(Source::isKnown($source))->toBeTrue();
    }
});

it('rejects an unknown source string', function () {
    expect(Source::isKnown('nonsense'))->toBeFalse()
        ->and(Source::isKnown(''))->toBeFalse()
        ->and(Source::isKnown('Human'))->toBeFalse();
});
