<x-filament-panels::page>
    @php $timeline = $this->getTimeline(); @endphp

    @if ($timeline->isEmpty())
        <div class="text-center py-12 text-sm text-gray-500 dark:text-gray-400">
            No timeline entries yet.
        </div>
    @else
        <div class="space-y-2">
            @foreach ($timeline as $item)
                @if ($item->_type === 'note')
                    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900 dark:text-white whitespace-pre-wrap">{{ $item->body }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    by {{ $item->author_name }} · {{ $item->occurred_at->format('M j, Y g:i a') }}
                                </p>
                                @if ($item->import_source_name)
                                    <a href="{{ $item->import_source_url }}"
                                       class="mt-2 inline-flex items-center gap-1 rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700 hover:bg-primary-100 dark:bg-primary-900/30 dark:text-primary-300 dark:hover:bg-primary-900/60">
                                        <x-heroicon-o-arrow-up-tray class="h-3 w-3" />
                                        {{ $item->import_source_name }}
                                    </a>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <button
                                    type="button"
                                    wire:click="mountAction('editNote', @js(['note' => $item->id]))"
                                    class="text-xs text-primary-600 hover:text-primary-500 dark:text-primary-400"
                                >Edit</button>
                                <button
                                    type="button"
                                    wire:click="mountAction('deleteNote', @js(['note' => $item->id]))"
                                    class="text-xs text-danger-600 hover:text-danger-500 dark:text-danger-400"
                                >Delete</button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10 p-4">
                        <div class="flex items-start gap-4">
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    <span class="font-medium capitalize">{{ $item->event }}</span>
                                    {{ $item->actor_label }}@if ($item->description) — {{ $item->description }}@endif
                                </p>
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    {{ $item->created_at->format('M j, Y g:i a') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
