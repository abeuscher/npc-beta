<?php

namespace App\WidgetPrimitive;

/**
 * Typed ambient payload attached to a SlotContext.
 *
 * Sealed by convention — the closed subtype set is:
 *   - PageAmbientContext        (page-builder canvas: ?Page currentPage)
 *   - DashboardAmbientContext   (admin dashboard grid: no payload yet)
 *   - RecordDetailAmbientContext (record-detail sidebar: payload lands in Phase 5b)
 *
 * Subtypes live in App\WidgetPrimitive\AmbientContexts. The abstract is empty;
 * payload lives on the subtypes. Resolver-layer code that needs a specific
 * payload checks via `instanceof`.
 */
abstract class AmbientContext
{
}
