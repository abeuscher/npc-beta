<?php

use App\WidgetPrimitive\Slot;
use App\WidgetPrimitive\SlotRegistry;

function makeFakeSlot(string $handle, string $label = 'Fake'): Slot
{
    return new class($handle, $label) extends Slot
    {
        public function __construct(
            private readonly string $h,
            private readonly string $l,
        ) {}

        public function handle(): string
        {
            return $this->h;
        }

        public function label(): string
        {
            return $this->l;
        }

        public function layoutConstraints(): array
        {
            return [];
        }

        public function configSurface(): ?string
        {
            return null;
        }
    };
}

it('registers and retrieves slots by handle', function () {
    $registry = new SlotRegistry();
    $slot = makeFakeSlot('alpha');

    $registry->register($slot);

    expect($registry->find('alpha'))->toBe($slot)
        ->and($registry->find('missing'))->toBeNull();
});

it('returns all registered slots in insertion order', function () {
    $registry = new SlotRegistry();
    $registry->register(makeFakeSlot('first'));
    $registry->register(makeFakeSlot('second'));
    $registry->register(makeFakeSlot('third'));

    expect(array_keys($registry->all()))->toBe(['first', 'second', 'third']);
});

it('lets the last registration win when two slots share a handle', function () {
    $registry = new SlotRegistry();
    $original = makeFakeSlot('shared', 'First');
    $replacement = makeFakeSlot('shared', 'Second');

    $registry->register($original);
    $registry->register($replacement);

    expect($registry->find('shared'))->toBe($replacement)
        ->and($registry->find('shared')->label())->toBe('Second')
        ->and($registry->all())->toHaveCount(1);
});
