<x-filament-panels::page>
    @php
        $fmtSize = function (?int $bytes): string {
            $bytes = (int) $bytes;
            return $bytes >= 1048576
                ? round($bytes / 1048576, 1) . ' MB'
                : round($bytes / 1024, 1) . ' KB';
        };
        $classLabels = [
            'dead_collection' => 'Dead collection',
            'orphan_owner'    => 'Orphan owner',
        ];
    @endphp

    <div class="max-w-5xl space-y-8">
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">What these scans cover</h2>
            <div class="mt-3 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                <p>
                    <strong>Unused scan</strong> lists media the site no longer points at. Media is considered <em>referenced</em> when its owning record (a widget, page, event, product, email template, collection item) still reads the collection it lives in, or when its <code>/storage/…</code> URL is embedded in rich-text content (widget rich text, collection-item content, event descriptions, email bodies). Everything else is a candidate, tagged:
                </p>
                <ul class="ml-5 list-disc space-y-1">
                    <li><strong>Dead collection</strong> — the owner record still exists but no longer reads this file (e.g. a widget image field was removed or replaced). Nothing else cleans these up.</li>
                    <li><strong>Orphan owner</strong> — the owning record is gone. These are also handled by the nightly <code>media-library:clean</code> sweeper; shown here for visibility.</li>
                </ul>
                <p>
                    <strong>Duplicate scan</strong> clusters records that look like copies: identical file contents (byte hash), or the same filename and size. Each member shows whether it is still referenced so you keep the one in use.
                </p>
                <p>
                    <strong>Missing-file scan</strong> lists media records whose file is gone from disk (a 404 if something tries to load it). A missing file that is still <em>referenced</em> is a visible breakage on a live surface; an unreferenced one is just a dead record.
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <strong>Out of scope:</strong> seeder / sample-library images are never flagged; orphan-owner deletion is left to the nightly sweeper; reference history (what used a file before it changed) is not tracked. Deletes here are permanent and removed one at a time after confirmation. See the <em>Media Finder</em> runbook for the full safety model.
                </p>
            </div>
        </section>

        {{-- Unused results --}}
        @if (is_array($unusedResults))
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900" data-testid="unused-results">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Unused candidates ({{ count($unusedResults) }})
                </h2>
                @if (count($unusedResults) === 0)
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No unused media found.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="py-2 pr-4">Preview</th>
                                    <th class="py-2 pr-4">File</th>
                                    <th class="py-2 pr-4">Collection</th>
                                    <th class="py-2 pr-4">Owner</th>
                                    <th class="py-2 pr-4">Reason</th>
                                    <th class="py-2 pr-4">Size</th>
                                    <th class="py-2 pr-4">Uploaded</th>
                                    <th class="py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($unusedResults as $row)
                                    <tr wire:key="unused-{{ $row['id'] }}">
                                        <td class="py-2 pr-4">
                                            @if ($row['url'])
                                                <img src="{{ $row['url'] }}" alt="" class="h-10 w-10 rounded object-cover" />
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">{{ \Illuminate\Support\Str::limit($row['file_name'], 40) }}</td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $row['collection_name'] }}</td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $row['owner'] }}</td>
                                        <td class="py-2 pr-4">
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                                'bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-300' => $row['classification'] === 'dead_collection',
                                                'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' => $row['classification'] === 'orphan_owner',
                                            ])>
                                                {{ $classLabels[$row['classification']] ?? $row['classification'] }}
                                            </span>
                                        </td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $fmtSize($row['size']) }}</td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $row['created_at'] }}</td>
                                        <td class="py-2 text-right">
                                            <x-filament::button
                                                size="xs"
                                                color="danger"
                                                icon="heroicon-o-trash"
                                                wire:click="mountAction('deleteMedia', @js(['media' => $row['id']]))"
                                            >
                                                Delete
                                            </x-filament::button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif

        {{-- Duplicate results --}}
        @if (is_array($duplicateResults))
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900" data-testid="duplicate-results">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Duplicate clusters ({{ count($duplicateResults) }})
                </h2>
                @if (count($duplicateResults) === 0)
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No duplicate media found.</p>
                @else
                    <div class="mt-4 space-y-5">
                        @foreach ($duplicateResults as $i => $cluster)
                            <div class="rounded-lg border border-gray-100 p-4 dark:border-gray-800" wire:key="cluster-{{ $i }}">
                                <p class="text-xs uppercase tracking-wide text-gray-400">
                                    {{ $cluster['reason'] === 'identical_content' ? 'Identical contents' : 'Same name & size' }}
                                    · {{ $cluster['count'] }} files
                                </p>
                                <div class="mt-3 overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                            @foreach ($cluster['members'] as $m)
                                                <tr wire:key="dup-{{ $i }}-{{ $m['id'] }}">
                                                    <td class="py-2 pr-4 w-12">
                                                        @if ($m['url'])
                                                            <img src="{{ $m['url'] }}" alt="" class="h-10 w-10 rounded object-cover" />
                                                        @else
                                                            <span class="text-xs text-gray-400">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">{{ \Illuminate\Support\Str::limit($m['file_name'], 40) }}</td>
                                                    <td class="py-2 pr-4 text-gray-500">{{ $m['owner'] }}</td>
                                                    <td class="py-2 pr-4">
                                                        @if ($m['referenced'])
                                                            <span class="inline-flex rounded-full bg-success-100 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-900 dark:text-success-300">Referenced</span>
                                                        @else
                                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">Unreferenced</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-2 pr-4 text-gray-500">{{ $fmtSize($m['size']) }}</td>
                                                    <td class="py-2 text-right">
                                                        <x-filament::button
                                                            size="xs"
                                                            color="danger"
                                                            icon="heroicon-o-trash"
                                                            wire:click="mountAction('deleteMedia', @js(['media' => $m['id']]))"
                                                        >
                                                            Delete
                                                        </x-filament::button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        {{-- Missing-file results --}}
        @if (is_array($missingResults))
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900" data-testid="missing-results">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Missing files ({{ count($missingResults) }})
                </h2>
                @if (count($missingResults) === 0)
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No media records with missing files.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="py-2 pr-4">File</th>
                                    <th class="py-2 pr-4">Collection</th>
                                    <th class="py-2 pr-4">Owner</th>
                                    <th class="py-2 pr-4">Status</th>
                                    <th class="py-2 pr-4">Uploaded</th>
                                    <th class="py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($missingResults as $row)
                                    <tr wire:key="missing-{{ $row['id'] }}">
                                        <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">{{ \Illuminate\Support\Str::limit($row['file_name'], 40) }}</td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $row['collection_name'] }}</td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $row['owner'] }}</td>
                                        <td class="py-2 pr-4">
                                            @if ($row['referenced'])
                                                <span class="inline-flex rounded-full bg-danger-100 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-900 dark:text-danger-300">Broken (referenced)</span>
                                            @else
                                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">Dead record</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $row['created_at'] }}</td>
                                        <td class="py-2 text-right">
                                            <x-filament::button
                                                size="xs"
                                                color="danger"
                                                icon="heroicon-o-trash"
                                                wire:click="mountAction('deleteMedia', @js(['media' => $row['id']]))"
                                            >
                                                Delete record
                                            </x-filament::button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
