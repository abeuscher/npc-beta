<?php

namespace App\WidgetPrimitive;

/**
 * A Slot is a rendering target for widgets — a declared location in the UI
 * that accepts widgets compatible with its contract.
 *
 * Every slot also exposes an ambientContext(...) method, but its signature
 * varies per slot (page-builder canvas takes PageContext + ?Page; dashboard
 * will take an admin user + request; record-detail will take the current
 * record + user). Callers know which slot they are invoking by handle and
 * therefore know the argument shape. The method is intentionally not
 * declared on this abstract — PHP 8 requires LSP-compatible signatures
 * across overrides, and the per-slot shapes are incompatible.
 */
abstract class Slot
{
    abstract public function handle(): string;

    abstract public function label(): string;

    /**
     * Declarative metadata describing the slot's layout shape. Consumed by
     * admin surfaces (config UI, widget-compatibility reasoning). No runtime
     * enforcement in this phase.
     *
     * Shape convention:
     *   - allowed_appearance_fields: '*' | array<int, string>
     *   - dimensions: null | array{width: string, height: string}
     *   - column_stackable: bool
     *   - full_width_allowed: bool
     *
     * @return array<string, mixed>
     */
    abstract public function layoutConstraints(): array;

    /**
     * Identifier or class name for the admin surface that configures widgets
     * in this slot. Null when the surface isn't built yet.
     */
    abstract public function configSurface(): ?string;
}
