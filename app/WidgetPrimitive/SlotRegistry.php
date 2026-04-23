<?php

namespace App\WidgetPrimitive;

class SlotRegistry
{
    /** @var array<string, Slot> */
    protected array $slots = [];

    public function register(Slot $slot): void
    {
        $this->slots[$slot->handle()] = $slot;
    }

    public function find(string $handle): ?Slot
    {
        return $this->slots[$handle] ?? null;
    }

    /** @return array<string, Slot> */
    public function all(): array
    {
        return $this->slots;
    }
}
