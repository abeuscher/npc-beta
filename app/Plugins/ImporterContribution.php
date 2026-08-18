<?php

namespace App\Plugins;

/**
 * One plugin's declared importer (session 381, arc P5 —
 * docs/plugin-contract.md surface 8). A domain plugin ships its importer —
 * field registries, Filament wizard + progress pages, CSV-template shape —
 * and declares it here from its provider's register(); the core import
 * surfaces (the importer hub, the CSV-template streamer, the fixture runner,
 * the demo-source seeder) resolve through the ImporterRegistry instead of
 * naming plugin classes. Remove the plugin's config/plugins.php line and the
 * importer vanishes from every one of those surfaces.
 *
 * The Filament pages themselves are discovered via the plugin's
 * AdminContribution pagesPath (surface 3) — this object only tells the core
 * import surfaces which classes pair with which importer slug.
 */
class ImporterContribution
{
    /**
     * @param string   $slug               Importer key — the CSV-template type and fixture-runner name (e.g. 'events').
     * @param string   $label              Hub-card title (e.g. 'Import Events and Event Registrations').
     * @param string   $icon               Heroicon component name for the hub card (e.g. 'heroicon-o-calendar-days').
     * @param string   $modelType          ImportModelType enum value this importer writes — the hub's blocked-while-reviewing check.
     * @param string   $pageClass          Filament wizard page class (hub-card link).
     * @param string   $progressPageClass  Filament progress page class (fixture-runner pairing).
     * @param \Closure $templateHeaders    fn (): array — header row for the downloadable CSV template.
     * @param \Closure $fakeSourceFieldMap fn (): array — saved field map for the seeded demo import source.
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $label,
        public readonly string $icon,
        public readonly string $modelType,
        public readonly string $pageClass,
        public readonly string $progressPageClass,
        public readonly \Closure $templateHeaders,
        public readonly \Closure $fakeSourceFieldMap,
    ) {
    }
}
