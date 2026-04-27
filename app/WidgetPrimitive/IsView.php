<?php

namespace App\WidgetPrimitive;

use App\Models\PageWidget;

/**
 * The shared widget-composition primitive — a configured collection of widget
 * instances arranged in a layout, scoped to a context type. Concrete kinds at
 * v1 include `RecordDetailView` (per-record-type, ambient-bound to a specific
 * record at render time), and eventually `DashboardView` (per-role) and CMS
 * `Page` (slug-routed) — see widget-primitive-migration.md "Design decisions
 * locked in" for the "a+" View framing.
 *
 * The interface is the contract; implementers are free to be plain PHP classes
 * (RecordDetailView this session) or Eloquent models (the table-backed
 * implementations land later).
 */
interface IsView
{
    public function handle(): string;

    public function slotHandle(): string;

    /**
     * @return array<int, PageWidget>
     */
    public function widgets(): array;

    /**
     * @return array<string, mixed>
     */
    public function layoutConfig(): array;
}
