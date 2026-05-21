<x-filament-panels::page>
    @php $counts = $this->getSnapshotCounts(); @endphp

    <div class="max-w-3xl space-y-8">
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900" data-testid="site-export-section">
            <div class="flex items-start gap-4">
                <x-heroicon-o-archive-box-arrow-down class="h-10 w-10 text-primary-500 flex-shrink-0" />
                <div class="space-y-2">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Export Site</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Build a self-contained snapshot of this install — all pages and templates, the current theme, and referenced media — as a single downloadable bundle. Use it to hand a copy of the site to another install or to keep a known-good restore point.
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        This snapshot will contain: <strong>{{ $counts['pages'] }}</strong> page{{ $counts['pages'] === 1 ? '' : 's' }},
                        <strong>{{ $counts['templates'] }}</strong> template{{ $counts['templates'] === 1 ? '' : 's' }},
                        the site theme,
                        and <strong>{{ $counts['media_count'] }}</strong> media file{{ $counts['media_count'] === 1 ? '' : 's' }}.
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        For per-entity exports (a single page, the theme alone, the media library on its own), use the existing per-page export actions or the Theme and Media Library pages.
                    </p>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900" data-testid="site-import-section">
            <div class="flex items-start gap-4">
                <x-heroicon-o-arrow-up-tray class="h-10 w-10 text-primary-500 flex-shrink-0" />
                <div class="space-y-2">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Import Site</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Upload a site bundle to merge into this install. The bundle is inspected on upload — a summary of what it contains (pages, templates, theme keys, media files) appears in the modal, along with toggles for the parts that have meaningful operator choices: replacing duplicate pages, replacing the current theme, including the bundled media.
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Imports run in the background; you will be notified when an import completes.
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        For a single-page or theme-only import, use the existing Page list or Theme page actions.
                    </p>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
