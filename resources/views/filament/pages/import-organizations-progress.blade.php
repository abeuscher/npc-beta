<x-filament-panels::page>
    <div class="mx-auto max-w-3xl space-y-6">

        @if ($phase === 'rejected')
            <div data-testid="import-progress-phase-rejected" class="rounded-xl border border-red-200 bg-red-50 p-6 dark:border-red-800 dark:bg-red-950">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-shield-exclamation class="h-7 w-7 text-red-600 dark:text-red-400" />
                    <h2 class="text-lg font-semibold text-red-800 dark:text-red-300">Import rejected</h2>
                </div>
                <p class="mt-2 text-sm text-red-700 dark:text-red-400">
                    This organizations CSV was rejected due to sensitive data. Remove the flagged data and try again.
                </p>
            </div>

            <div class="flex gap-3">
                <a href="{{ \App\Filament\Pages\ImporterPage::getUrl() }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">
                    <x-heroicon-o-arrow-up-tray class="h-4 w-4" />
                    Start a new import
                </a>
            </div>

        @elseif ($phase === 'awaitingDecision')

            <div data-testid="import-progress-phase-awaiting" class="rounded-xl border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-950">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-beaker class="h-7 w-7 text-blue-600 dark:text-blue-400" />
                    <h2 class="text-lg font-semibold text-blue-800 dark:text-blue-300">Dry-run complete</h2>
                </div>
                <p class="mt-2 text-sm text-blue-700 dark:text-blue-400">
                    No changes have been written yet. Review the summary below and decide whether to commit.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div data-testid="import-stat-imported" class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-green-600">{{ number_format($dryRunReport['imported']) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Would import rows</p>
                </div>
                <div data-testid="import-stat-updated" class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-blue-600">{{ number_format($dryRunReport['updated']) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Would update</p>
                </div>
                <div data-testid="import-stat-skipped" class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-amber-500">{{ number_format($dryRunReport['skipped']) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Would skip</p>
                </div>
                <div data-testid="import-stat-errors" class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold {{ $dryRunReport['errorCount'] > 0 ? 'text-red-600' : 'text-gray-400' }}">
                        {{ number_format($dryRunReport['errorCount']) }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500">Errors</p>
                </div>
            </div>

            @php
                $entities    = $dryRunReport['entities'] ?? [];
                $orgsPreview = $entities['organizations'] ?? ['would_create' => 0, 'would_update' => 0];
                $tagsPreview = $entities['tags'] ?? ['would_create' => 0, 'would_match' => 0];
                $notesPreview = $entities['notes'] ?? ['would_create' => 0];
                $hasEntityPreview = ($orgsPreview['would_create'] + $orgsPreview['would_update']
                    + $tagsPreview['would_create'] + $tagsPreview['would_match']
                    + $notesPreview['would_create']) > 0;
            @endphp

            @if ($hasEntityPreview)
                <div class="rounded-xl border border-gray-200 bg-white p-5 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Entity preview</h3>
                    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 text-xs">
                        <div>
                            <dt class="text-gray-500 uppercase">Organizations</dt>
                            <dd class="mt-1 text-gray-700 dark:text-gray-300">
                                <strong>{{ number_format($orgsPreview['would_create']) }}</strong> new,
                                <strong>{{ number_format($orgsPreview['would_update']) }}</strong> staged
                            </dd>
                        </div>
                        @if (($tagsPreview['would_create'] + $tagsPreview['would_match']) > 0)
                            <div>
                                <dt class="text-gray-500 uppercase">Tags</dt>
                                <dd class="mt-1 text-gray-700 dark:text-gray-300">
                                    <strong>{{ number_format($tagsPreview['would_create']) }}</strong> new,
                                    <strong>{{ number_format($tagsPreview['would_match']) }}</strong> reused
                                </dd>
                            </div>
                        @endif
                        @if ($notesPreview['would_create'] > 0)
                            <div>
                                <dt class="text-gray-500 uppercase">Notes</dt>
                                <dd class="mt-1 text-gray-700 dark:text-gray-300">
                                    <strong>{{ number_format($notesPreview['would_create']) }}</strong> new
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

            @if ($dryRunReport['skipped'] > 0)
                @php $skipReasons = $dryRunReport['skipReasons'] ?? []; @endphp
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-800 dark:bg-amber-950">
                    <h3 class="font-semibold text-amber-800 dark:text-amber-300 mb-2">Why rows would skip</h3>
                    <ul class="space-y-1 text-xs text-amber-700 dark:text-amber-400">
                        @if (($skipReasons['blank_match_value'] ?? 0) > 0)
                            <li class="flex items-start gap-2">
                                <span class="font-mono font-semibold tabular-nums">{{ number_format($skipReasons['blank_match_value']) }}</span>
                                <span>— row has a blank value in the <strong>match key</strong> column.</span>
                            </li>
                        @endif
                        @if (($skipReasons['duplicate_skipped'] ?? 0) > 0)
                            <li class="flex items-start gap-2">
                                <span class="font-mono font-semibold tabular-nums">{{ number_format($skipReasons['duplicate_skipped']) }}</span>
                                <span>— row matched an <strong>existing organization</strong> and the duplicate strategy is "Skip".</span>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif

            @if ($dryRunReport['errorCount'] > 0)
                <div class="rounded-xl border border-red-200 bg-white shadow-sm dark:border-red-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-red-200 px-5 py-3 dark:border-red-800">
                        <h3 class="text-sm font-semibold text-red-800 dark:text-red-300">
                            {{ $dryRunReport['errorCount'] }} row{{ $dryRunReport['errorCount'] === 1 ? '' : 's' }} errored
                        </h3>
                        <button type="button"
                                wire:click="downloadErrors"
                                wire:loading.attr="disabled"
                                wire:target="downloadErrors"
                                class="inline-flex items-center gap-2 rounded border border-red-300 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:opacity-60 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900">
                            <span wire:loading.remove wire:target="downloadErrors" class="inline-flex items-center gap-2">
                                <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                                Download errored rows
                            </span>
                            <span wire:loading wire:target="downloadErrors" class="inline-flex items-center gap-2">
                                <x-loading-spinner />
                                Preparing…
                            </span>
                        </button>
                    </div>
                </div>
            @endif

            <div class="flex flex-wrap gap-3">
                <button type="button"
                        data-testid="import-progress-commit-button"
                        wire:click="runCommit"
                        wire:loading.attr="disabled"
                        wire:target="runCommit"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-60">
                    <span wire:loading.remove wire:target="runCommit" class="inline-flex items-center gap-2">
                        <x-heroicon-o-check class="h-4 w-4" />
                        @if ($dryRunReport['errorCount'] > 0)
                            Commit (omit the {{ $dryRunReport['errorCount'] }} errored row{{ $dryRunReport['errorCount'] === 1 ? '' : 's' }})
                        @else
                            Commit
                        @endif
                    </span>
                    <span wire:loading wire:target="runCommit" class="inline-flex items-center gap-2">
                        <x-loading-spinner />
                        Starting commit…
                    </span>
                </button>
                <button type="button"
                        data-testid="import-progress-cancel-button"
                        wire:click="cancel"
                        wire:loading.attr="disabled"
                        wire:target="cancel"
                        class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-gray-50 disabled:opacity-60 dark:hover:bg-gray-800">
                    <span wire:loading.remove wire:target="cancel" class="inline-flex items-center gap-2">
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                        Cancel
                    </span>
                    <span wire:loading wire:target="cancel" class="inline-flex items-center gap-2">
                        <x-loading-spinner />
                        Cancelling…
                    </span>
                </button>
            </div>

        @elseif ($phase === 'committing')
            <div data-testid="import-progress-phase-committing" wire:poll.500ms="tick" class="space-y-5">
                <div class="flex items-center justify-between text-sm text-gray-500">
                    <span>Committing row {{ number_format($processed) }} of {{ number_format($total) }}</span>
                    <span class="font-medium">{{ $this->percent() }}%</span>
                </div>
                <div data-testid="import-progress-bar" class="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="h-3 rounded-full bg-primary-500 transition-all duration-500"
                         style="width: {{ $this->percent() }}%"></div>
                </div>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div data-testid="import-stat-imported" class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold text-green-600">{{ number_format($imported) }}</p>
                        <p class="mt-1 text-sm text-gray-500">Imported</p>
                    </div>
                    <div data-testid="import-stat-updated" class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold text-blue-600">{{ number_format($updated) }}</p>
                        <p class="mt-1 text-sm text-gray-500">Staged</p>
                    </div>
                    <div data-testid="import-stat-skipped" class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold text-amber-500">{{ number_format($skipped) }}</p>
                        <p class="mt-1 text-sm text-gray-500">Skipped</p>
                    </div>
                    <div data-testid="import-stat-errors" class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold {{ $errorCount > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ number_format($errorCount) }}</p>
                        <p class="mt-1 text-sm text-gray-500">Errors</p>
                    </div>
                </div>
            </div>

        @elseif ($phase === 'done')
            <div data-testid="import-progress-phase-done" class="space-y-6">
            @if ($importSessionId)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-950">
                    <div class="flex items-center gap-3">
                        <x-heroicon-o-clock class="h-7 w-7 text-amber-600 dark:text-amber-400" />
                        <h2 class="text-lg font-semibold text-amber-800 dark:text-amber-300">Import pending review</h2>
                    </div>
                    <p class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                        Imported organizations are awaiting reviewer approval.
                    </p>
                </div>
            @else
                <div class="rounded-xl border border-green-200 bg-green-50 p-6 dark:border-green-800 dark:bg-green-950">
                    <div class="flex items-center gap-3">
                        <x-heroicon-o-check-circle class="h-7 w-7 text-green-600 dark:text-green-400" />
                        <h2 class="text-lg font-semibold text-green-800 dark:text-green-300">Import complete</h2>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div data-testid="import-stat-imported" class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-green-600">{{ number_format($imported) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Imported</p>
                </div>
                <div data-testid="import-stat-updated" class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-blue-600">{{ number_format($updated) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Staged</p>
                </div>
                <div data-testid="import-stat-skipped" class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-amber-500">{{ number_format($skipped) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Skipped</p>
                </div>
                <div data-testid="import-stat-errors" class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold {{ $errorCount > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ number_format($errorCount) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Errors</p>
                </div>
            </div>

            @if ($importSourceId && $sourceName && ! $mappingSaved)
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-semibold">Save this mapping?</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Store the organizations mapping on <strong>{{ $sourceName }}</strong>.
                            </p>
                        </div>
                        <button type="button"
                                data-testid="import-save-mapping"
                                wire:click="saveMapping"
                                wire:loading.attr="disabled"
                                wire:target="saveMapping"
                                class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-60">
                            <span wire:loading.remove wire:target="saveMapping" class="inline-flex items-center gap-2">
                                <x-heroicon-o-bookmark class="h-4 w-4" />
                                Save mapping
                            </span>
                            <span wire:loading wire:target="saveMapping" class="inline-flex items-center gap-2">
                                <x-loading-spinner />
                                Saving…
                            </span>
                        </button>
                    </div>
                </div>
            @endif

            @if ($mappingSaved)
                <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
                    Organizations mapping saved to <strong>{{ $sourceName }}</strong>.
                </div>
            @endif

            <div class="flex gap-3">
                @if ($importSessionId && auth()->user()?->can('review_imports'))
                    <x-loading-link :href="\App\Filament\Pages\ImporterPage::getUrl()"
                                    data-testid="import-go-to-review"
                                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">
                        <x-slot:icon><x-heroicon-o-clipboard-document-check class="h-4 w-4" /></x-slot:icon>
                        Go to review queue
                    </x-loading-link>
                @elseif ($importSessionId)
                    <p class="text-sm text-gray-500 self-center">A reviewer will approve this import.</p>
                @endif
            </div>
            </div>

        @endif
    </div>
</x-filament-panels::page>
