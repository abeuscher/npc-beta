<?php

use App\Widgets\Contracts\WidgetDefinition;
use App\Widgets\TextBlock\TextBlockDefinition;

it('defaults WidgetDefinition::allowedSlots() to page_builder_canvas', function () {
    $def = new class extends WidgetDefinition
    {
        public function handle(): string
        {
            return 'anon';
        }

        public function label(): string
        {
            return 'Anon';
        }

        public function description(): string
        {
            return '';
        }

        public function schema(): array
        {
            return [];
        }

        public function defaults(): array
        {
            return [];
        }
    };

    expect($def->allowedSlots())->toBe(['page_builder_canvas']);
});

it('preserves the page-builder-canvas default on a migrated widget (TextBlock)', function () {
    expect((new TextBlockDefinition())->allowedSlots())->toBe(['page_builder_canvas']);
});
