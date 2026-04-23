<?php

use App\WidgetPrimitive\Slot;

it('is abstract and cannot be instantiated directly', function () {
    $ref = new ReflectionClass(Slot::class);

    expect($ref->isAbstract())->toBeTrue();
});

it('requires handle, label, layoutConstraints and configSurface on every subclass', function () {
    $ref = new ReflectionClass(Slot::class);
    $abstractMethods = array_map(
        fn (ReflectionMethod $m) => $m->getName(),
        array_filter(
            $ref->getMethods(),
            fn (ReflectionMethod $m) => $m->isAbstract(),
        ),
    );

    sort($abstractMethods);
    expect($abstractMethods)->toBe(['configSurface', 'handle', 'label', 'layoutConstraints']);
});

it('exposes the declared primitives through a minimal subclass', function () {
    $slot = new class extends Slot
    {
        public function handle(): string
        {
            return 'fake_slot';
        }

        public function label(): string
        {
            return 'Fake Slot';
        }

        public function layoutConstraints(): array
        {
            return ['column_stackable' => true];
        }

        public function configSurface(): ?string
        {
            return null;
        }
    };

    expect($slot->handle())->toBe('fake_slot')
        ->and($slot->label())->toBe('Fake Slot')
        ->and($slot->layoutConstraints())->toBe(['column_stackable' => true])
        ->and($slot->configSurface())->toBeNull();
});
