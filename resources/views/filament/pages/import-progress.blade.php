<x-filament-panels::page>
    <div class="mx-auto max-w-3xl space-y-6">

        @if ($phase === 'rejected')
            {{-- ── PII rejection card ── --}}
            <div class="rounded-xl border border-red-200 bg-red-50 p-6 dark:border-red-800 dark:bg-red-950">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-shield-exclamation class="h-7 w-7 text-red-600 dark:text-red-400" />
                    <h2 class="text-lg font-semibold text-red-800 dark:text-red-300">Import rejected</h2>
                </div>
                <p class="mt-2 text-sm text-red-700 dark:text-red-400">
                    This import was rejected because it contains data that may include sensitive financial or personal identifiers. Remove the flagged data and try again.
                </p>
                @if ($piiHeaderBlocked)
                    <p class="mt-3 rounded bg-red-100 px-3 py-2 font-mono text-xs text-red-800 dark:bg-red-900 dark:text-red-300">
                        {{ $rejectionReason }}
                    </p>
                @endif
            </div>

            @if (! $piiHeaderBlocked && count($piiViolations) > 0)
                <div class="rounded-xl border border-red-200 bg-white shadow-sm dark:border-red-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-red-200 px-5 py-3 dark:border-red-800">
                        <h3 class="text-sm font-semibold text-red-800 dark:text-red-300">
                            {{ count($piiViolations) }} flagged row{{ count($piiViolations) === 1 ? '' : 's' }}@if ($piiTruncated) (scanner stopped at {{ \App\Services\PiiScanner::DEFAULT_LIMIT }})@endif
                        </h3>
                        <button type="button"
                                wire:click="downloadPiiErrors"
                                wire:loading.attr="disabled"
                                wire:target="downloadPiiErrors"
                                class="inline-flex items-center gap-2 rounded border border-red-300 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:opacity-60 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900">
                            <span wire:loading.remove wire:target="downloadPiiErrors" class="inline-flex items-center gap-2">
                                <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                                Download report
                            </span>
                            <span wire:loading wire:target="downloadPiiErrors" class="inline-flex items-center gap-2">
                                <x-loading-spinner />
                                Preparing…
                            </span>
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-2">Row</th>
                                    <th class="px-4 py-2">Column</th>
                                    <th class="px-4 py-2">Issue</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($piiViolations as $v)
                                    <tr class="border-t border-red-100 bg-red-50/50 dark:border-red-900/50 dark:bg-red-950/30">
                                        <td class="px-4 py-2 font-mono text-xs align-top">{{ $v['row'] }}</td>
                                        <td class="px-4 py-2 text-xs align-top">{{ $v['column'] }}</td>
                                        <td class="px-4 py-2 text-xs text-red-700 dark:text-red-300 align-top">{{ $v['reason'] }}</td>
                                    </tr>
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td colspan="3" class="px-4 py-2 font-mono text-[11px] text-gray-500 break-all whitespace-pre-wrap">{{ implode(' │ ', $v['row_data']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="flex gap-3">
                <a href="{{ \App\Filament\Pages\ImporterPage::getUrl() }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">
                    <x-heroicon-o-arrow-up-tray class="h-4 w-4" />
                    Start a new import
                </a>
            </div>

        @elseif ($phase === 'awaitingDecision')
            {{-- ── Dry-run summary + commit/cancel ── --}}

            <div class="rounded-xl border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-950">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-beaker class="h-7 w-7 text-blue-600 dark:text-blue-400" />
                    <h2 class="text-lg font-semibold text-blue-800 dark:text-blue-300">Dry-run complete</h2>
                </div>
                <p class="mt-2 text-sm text-blue-700 dark:text-blue-400">
                    No changes have been written yet. Review the summary below and decide whether to commit.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-green-600">{{ number_format($dryRunReport['imported']) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Would create</p>
                </div>
                <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-blue-600">{{ number_format($dryRunReport['updated']) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Would update</p>
                </div>
                <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-amber-500">{{ number_format($dryRunReport['skipped']) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Would skip</p>
                </div>
                <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold {{ $dryRunReport['errorCount'] > 0 ? 'text-red-600' : 'text-gray-400' }}">
                        {{ number_format($dryRunReport['errorCount']) }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500">Errors</p>
                </div>
            </div>

            @php
                $orgPreview  = $dryRunReport['relationalPreview']['organizations'] ?? ['would_create' => [], 'would_match' => [], 'unmatched' => []];
                $tagPreview  = $dryRunReport['relationalPreview']['tags'] ?? ['would_create' => [], 'would_match' => []];
                $notePreview = $dryRunReport['relationalPreview']['notes'] ?? ['total_would_create' => 0];
                $hasOrgPreview  = count($orgPreview['would_create']) + count($orgPreview['would_match']) + count($orgPreview['unmatched']) > 0;
                $hasTagPreview  = count($tagPreview['would_create']) + count($tagPreview['would_match']) > 0;
                $hasNotePreview = ($notePreview['total_would_create'] ?? 0) > 0;
            @endphp

            @if ($hasOrgPreview || $hasTagPreview || $hasNotePreview)
                <div class="rounded-xl border border-gray-200 bg-white p-5 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Relational operations</h3>

                    @if ($hasOrgPreview)
                        <div class="mb-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Organizations</p>
                            @if (count($orgPreview['would_create']) > 0)
                                <p class="text-xs text-green-700 dark:text-green-400 mb-1">
                                    <strong>{{ count($orgPreview['would_create']) }} new</strong> would be created ({{ array_sum($orgPreview['would_create']) }} row{{ array_sum($orgPreview['would_create']) === 1 ? '' : 's' }}):
                                </p>
                                <ul class="text-xs text-gray-600 dark:text-gray-400 ml-4 list-disc">
                                    @foreach (array_slice($orgPreview['would_create'], 0, 25, true) as $name => $count)
                                        <li>{{ $name }} <span class="text-gray-400">× {{ $count }}</span></li>
                                    @endforeach
                                    @if (count($orgPreview['would_create']) > 25)
                                        <li class="text-gray-400">… {{ count($orgPreview['would_create']) - 25 }} more</li>
                                    @endif
                                </ul>
                            @endif

                            @if (count($orgPreview['would_match']) > 0)
                                <p class="text-xs text-blue-700 dark:text-blue-400 mt-2">
                                    <strong>{{ count($orgPreview['would_match']) }}</strong> would match existing ({{ array_sum($orgPreview['would_match']) }} row{{ array_sum($orgPreview['would_match']) === 1 ? '' : 's' }}).
                                </p>
                            @endif

                            @if (count($orgPreview['unmatched']) > 0)
                                <p class="text-xs text-amber-700 dark:text-amber-400 mt-2">
                                    <strong>{{ count($orgPreview['unmatched']) }}</strong> unmatched name{{ count($orgPreview['unmatched']) === 1 ? '' : 's' }} in match-only mode — rows get no organization link ({{ array_sum($orgPreview['unmatched']) }} row{{ array_sum($orgPreview['unmatched']) === 1 ? '' : 's' }}).
                                </p>
                            @endif
                        </div>
                    @endif

                    @if ($hasTagPreview)
                        <div class="mb-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Tags</p>
                            @if (count($tagPreview['would_create']) > 0)
                                <p class="text-xs text-green-700 dark:text-green-400">
                                    <strong>{{ count($tagPreview['would_create']) }} new</strong> would be created: {{ implode(', ', array_slice(array_keys($tagPreview['would_create']), 0, 15)) }}@if (count($tagPreview['would_create']) > 15), …@endif
                                </p>
                            @endif
                            @if (count($tagPreview['would_match']) > 0)
                                <p class="text-xs text-blue-700 dark:text-blue-400 mt-1">
                                    <strong>{{ count($tagPreview['would_match']) }}</strong> would reuse existing.
                                </p>
                            @endif
                        </div>
                    @endif

                    @if ($hasNotePreview)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Notes</p>
                            <p class="text-xs text-gray-700 dark:text-gray-300">
                                <strong>{{ number_format($notePreview['total_would_create']) }}</strong> note{{ $notePreview['total_would_create'] === 1 ? '' : 's' }} would be created on imported contacts.
                            </p>
                        </div>
                    @endif
                </div>
            @endif

            @if ($dryRunReport['updated'] > 0)
                <div class="rounded-xl border border-blue-200 bg-white shadow-sm dark:border-blue-800 dark:bg-gray-900">
                    <div class="border-b border-blue-200 px-5 py-3 dark:border-blue-800">
                        <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-300">
                            {{ number_format($dryRunReport['updated']) }} existing contact{{ $dryRunReport['updated'] === 1 ? '' : 's' }} would be updated
                        </h3>
                        <p class="mt-1 text-xs text-blue-700 dark:text-blue-400">
                            Non-blank values from the CSV are staged as updates on matched contacts. Blank cells are ignored. Review queue approval is still required before changes take effect.
                        </p>
                    </div>
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2">Row</th>
                                <th class="px-4 py-2">Matched contact</th>
                                <th class="px-4 py-2">Matched on</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dryRunReport['updatePreview'] ?? [] as $entry)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-2 font-mono text-xs">{{ $entry['row'] }}</td>
                                    <td class="px-4 py-2 text-xs">{{ $entry['display'] }}</td>
                                    <td class="px-4 py-2 text-xs">
                                        <span class="font-mono">{{ $entry['field'] }}</span>
                                        <span class="text-gray-400">=</span>
                                        <span>{{ $entry['value'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                            @if ($dryRunReport['updated'] > count($dryRunReport['updatePreview'] ?? []))
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-center text-xs text-gray-500">
                                        … {{ number_format($dryRunReport['updated'] - count($dryRunReport['updatePreview'] ?? [])) }} more match{{ ($dryRunReport['updated'] - count($dryRunReport['updatePreview'] ?? [])) === 1 ? '' : 'es' }} (first 50 shown)
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($dryRunReport['skipped'] > 0)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-800 dark:bg-amber-950">
                    <h3 class="font-semibold text-amber-800 dark:text-amber-300 mb-2">Why rows would skip</h3>
                    <ul class="space-y-1 text-xs text-amber-700 dark:text-amber-400">
                        @if (($dryRunReport['skipReasons']['missing_identifier'] ?? 0) > 0)
                            <li class="flex items-start gap-2">
                                <span class="font-mono font-semibold tabular-nums">{{ number_format($dryRunReport['skipReasons']['missing_identifier']) }}</span>
                                <span>— row had no value in the mapped <strong>Email</strong> or <strong>First name</strong> columns. Common cause: more than one column maps to the same field and one of them is blank in these rows.</span>
                            </li>
                        @endif
                        @if (($dryRunReport['skipReasons']['duplicate_skipped'] ?? 0) > 0)
                            <li class="flex items-start gap-2">
                                <span class="font-mono font-semibold tabular-nums">{{ number_format($dryRunReport['skipReasons']['duplicate_skipped']) }}</span>
                                <span>— row matched an existing contact via the match key, and the collision strategy is <strong>Skip</strong>. Change it to <strong>Update</strong> to stage these rows instead.</span>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif

            @if (!empty($customFieldLog))
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-800 dark:bg-blue-950">
                    <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-300 mb-3">Custom Field Definitions</h3>
                    <ul class="space-y-1 text-sm">
                        @foreach ($customFieldLog as $entry)
                            <li class="flex items-center gap-2">
                                @if ($entry['action'] === 'created')
                                    <x-heroicon-o-plus-circle class="h-4 w-4 text-green-600 shrink-0" />
                                    <span><strong>{{ $entry['label'] }}</strong> <span class="font-mono text-xs text-gray-500">({{ $entry['handle'] }})</span> — will create new field on commit</span>
                                @else
                                    <x-heroicon-o-arrow-path class="h-4 w-4 text-blue-500 shrink-0" />
                                    <span><strong>{{ $entry['label'] }}</strong> <span class="font-mono text-xs text-gray-500">({{ $entry['handle'] }})</span> — will reuse existing field</span>
                                @endif
                            </li>
                        @endforeach
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
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2">Row</th>
                                <th class="px-4 py-2">Identifying fields</th>
                                <th class="px-4 py-2">Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_slice($dryRunReport['errors'], 0, 50) as $err)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-2 font-mono text-xs">{{ $err['row'] }}</td>
                                    <td class="px-4 py-2 text-xs">
                                        @php
                                            $bits = array_filter([
                                                $err['identity']['first_name'] ?? null,
                                                $err['identity']['last_name'] ?? null,
                                                $err['identity']['email'] ?? null,
                                            ]);
                                        @endphp
                                        {{ $bits ? implode(' / ', $bits) : '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-xs text-red-700 dark:text-red-300">{{ $err['message'] ?? '' }}</td>
                                </tr>
                            @endforeach
                            @if (count($dryRunReport['errors']) > 50)
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-center text-xs text-gray-500">
                                        … {{ count($dryRunReport['errors']) - 50 }} more (download the CSV to see all)
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="flex flex-wrap gap-3">
                <button type="button"
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
                        wire:click="cancel"
                        wire:loading.attr="disabled"
                        wire:target="cancel"
                        class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-gray-50 disabled:opacity-60 dark:hover:bg-gray-800">
                    <span wire:loading.remove wire:target="cancel" class="inline-flex items-center gap-2">
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                        @if ($dryRunReport['errorCount'] > 0)
                            Cancel — I'll fix the CSV
                        @else
                            Cancel
                        @endif
                    </span>
                    <span wire:loading wire:target="cancel" class="inline-flex items-center gap-2">
                        <x-loading-spinner />
                        Cancelling…
                    </span>
                </button>
            </div>

        @elseif ($phase === 'committing')
            {{-- ── In-progress commit, polls every 500ms ── --}}
            <div wire:poll.500ms="tick" class="space-y-5">
                <div class="flex items-center justify-between text-sm text-gray-500">
                    <span>Committing row {{ number_format($processed) }} of {{ number_format($total) }}</span>
                    <span class="font-medium">{{ $this->percent() }}%</span>
                </div>

                <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="h-3 rounded-full bg-primary-500 transition-all duration-500"
                         style="width: {{ $this->percent() }}%">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold text-green-600">{{ number_format($imported) }}</p>
                        <p class="mt-1 text-sm text-gray-500">Imported</p>
                    </div>
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold text-blue-600">{{ number_format($updated) }}</p>
                        <p class="mt-1 text-sm text-gray-500">Staged</p>
                    </div>
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold text-amber-500">{{ number_format($skipped) }}</p>
                        <p class="mt-1 text-sm text-gray-500">Skipped</p>
                    </div>
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-2xl font-bold {{ $errorCount > 0 ? 'text-red-600' : 'text-gray-400' }}">
                            {{ number_format($errorCount) }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500">Errors</p>
                    </div>
                </div>

                <p class="text-center text-xs text-gray-400">
                    Please stay on this page until the commit finishes.
                </p>
            </div>

        @elseif ($phase === 'done')
            {{-- ── Completion card ── --}}
            @if ($importSessionId)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-950">
                    <div class="flex items-center gap-3">
                        <x-heroicon-o-clock class="h-7 w-7 text-amber-600 dark:text-amber-400" />
                        <h2 class="text-lg font-semibold text-amber-800 dark:text-amber-300">Import pending review</h2>
                    </div>
                    <p class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                        The import has been processed and is waiting for a reviewer to approve it before contacts become visible.
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
                <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-green-600">{{ number_format($imported) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Imported</p>
                </div>
                <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-blue-600">{{ number_format($updated) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Staged</p>
                </div>
                <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-amber-500">{{ number_format($skipped) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Skipped</p>
                </div>
                <div class="rounded-lg border bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-2xl font-bold {{ $errorCount > 0 ? 'text-red-600' : 'text-gray-400' }}">
                        {{ number_format($errorCount) }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500">Errors</p>
                </div>
            </div>

            @if ($importSourceId && $sourceName && ! $mappingSaved)
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-semibold">Save this mapping?</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Store the column map and match key on <strong>{{ $sourceName }}</strong> so the next import from this source starts pre-configured.
                            </p>
                        </div>
                        <button type="button"
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
                    <x-heroicon-o-check-circle class="mr-1 inline h-4 w-4 align-text-bottom" />
                    Mapping saved to <strong>{{ $sourceName }}</strong>.
                </div>
            @endif

            <div class="flex gap-3">
                @if ($importSessionId && auth()->user()?->can('review_imports'))
                    <x-loading-link :href="\App\Filament\Pages\ImporterPage::getUrl()"
                                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">
                        <x-slot:icon><x-heroicon-o-clipboard-document-check class="h-4 w-4" /></x-slot:icon>
                        Go to review queue
                    </x-loading-link>
                @elseif ($importSessionId)
                    <p class="text-sm text-gray-500 self-center">A reviewer will approve this import before contacts appear in the system.</p>
                @else
                    <x-loading-link :href="\App\Filament\Pages\ImportHistoryPage::getUrl()"
                                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">
                        <x-slot:icon><x-heroicon-o-clock class="h-4 w-4" /></x-slot:icon>
                        View import history
                    </x-loading-link>
                    <x-loading-link :href="\App\Filament\Resources\ContactResource::getUrl('index')"
                                    class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800">
                        <x-slot:icon><x-heroicon-o-users class="h-4 w-4" /></x-slot:icon>
                        Go to contacts
                    </x-loading-link>
                @endif
            </div>

        @endif
    </div>
</x-filament-panels::page>
