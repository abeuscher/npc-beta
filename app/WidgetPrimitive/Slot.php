<?php

namespace App\WidgetPrimitive;

/**
 * A Slot is a rendering target for widgets — a declared location in the UI
 * that accepts widgets compatible with its contract.
 *
 * Every slot also exposes an ambientContext(...) method. The per-slot
 * argument shapes still differ (page-builder canvas takes PageContext + ?Page;
 * dashboard takes nothing; record-detail will take the current record + user)
 * and PHP 8 LSP would reject a shared abstract declaration across them. What
 * is uniform is the **return** type: every implementation now returns a
 * SlotContext carrying a typed AmbientContext payload (PageAmbientContext,
 * DashboardAmbientContext, RecordDetailAmbientContext). Callers know which
 * slot they are invoking by handle and therefore know the argument shape.
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
